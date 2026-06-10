<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Models\Sensor;
use App\Models\HistoryKelembapan;
use App\Models\KontrolSiram;
use App\Events\SensorDataUpdated;
use Illuminate\Support\Facades\Log;

class MqttListenerCommand extends Command
{
    protected $signature   = 'mqtt:listen';
    protected $description = 'Listen MQTT dan simpan data sensor ke database';

    public function handle(): int
    {
        $this->info('🟢 MQTT Listener dimulai...');

        $mqtt = new MqttService();

        if (!$mqtt->connect()) {
            $this->error('❌ Gagal konek ke MQTT broker!');
            return 1;
        }

        $this->info('✅ Terhubung ke broker MQTT');

        // Subscribe wildcard — tangkap semua MAC
        $mqtt->subscribe('mapia/sensor/+/data', function (string $topic, string $message) {
            $this->prosesData($topic, $message);
        });

        $mqtt->subscribe('mapia/sensor/+/status', function (string $topic, string $message) {
            $this->prosesStatus($topic, $message);
        });

        $mqtt->subscribe('mapia/sensor/+/heartbeat', function (string $topic, string $message) {
            // Cukup log saja
            $parts = explode('/', $topic);
            $mac   = $parts[2] ?? '?';
            $data  = json_decode($message, true);
            $this->line("💓 Heartbeat dari {$mac} | RSSI: " . ($data['rssi'] ?? '?') . " dBm");
        });

        $this->info('📡 Listening... (Ctrl+C untuk berhenti)');

        try {
            $client = $mqtt->getClient();
            while ($client && $client->isConnected()) {
                $mqtt->loop();
                usleep(100000); // 100ms
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('[MQTT] Error: ' . $e->getMessage());
        } finally {
            $mqtt->disconnect();
            $this->info('🔴 MQTT Listener berhenti.');
        }

        return 0;
    }

    private function prosesData(string $topic, string $message): void
    {
        // Ekstrak MAC dari topic: mapia/sensor/B0CBD803ED40/data
        $parts = explode('/', $topic);
        $mac   = $parts[2] ?? null;

        $this->line("\n📨 Data masuk dari MAC: {$mac}");
        $this->line("   Payload: {$message}");

        $data = json_decode($message, true);

        if (!$data || !is_array($data)) {
            $this->warn('⚠️  Payload bukan JSON valid, skip.');
            return;
        }

        // Cari sensor berdasarkan MAC (tanpa titik dua, uppercase)
        $sensor = Sensor::where('mac_address', strtoupper($mac))->first();

        if (!$sensor) {
            $this->warn("⚠️  Sensor dengan MAC [{$mac}] tidak ditemukan di database!");
            $this->warn("    Pastikan MAC di database sama dengan MAC ESP32.");
            return;
        }

        $kelembapan = $data['kelembapan'] ?? null;
        $kondisi    = $data['kondisi']    ?? 'UNKNOWN';
        $uptime     = $data['uptime']     ?? 0;
        $pumpStr    = $data['pump']       ?? 'OFF';
        $modeStr    = $data['mode']       ?? 'otomatis';

        if ($kelembapan === null) {
            $this->warn('⚠️  Field kelembapan tidak ada di payload, skip.');
            return;
        }

        // Simpan ke history_kelembapans
        HistoryKelembapan::create([
            'id_sensor'  => $sensor->id_sensor,
            'kelembapan' => $kelembapan,
            'kondisi'    => $kondisi,
            'uptime'     => $uptime,
        ]);

        // Update status pompa & mode di kontrol_sirams
        KontrolSiram::updateOrCreate(
            ['id_sensor' => $sensor->id_sensor],
            [
                'status_pompa' => strtoupper($pumpStr) === 'ON',
                'mode_auto'    => strtolower($modeStr) === 'otomatis',
            ]
        );

        // Update status online sensor
        $sensor->update(['status' => true]);

        // Broadcast ke browser via Reverb (WebSocket)
        broadcast(new SensorDataUpdated(
            $sensor->id_sensor,
            $kelembapan,
            $kondisi,
            $pumpStr,
            $modeStr,
            $uptime
        ));

        $this->info("✅ Data disimpan: Sensor [{$sensor->nama_sensor}] | Kelembapan: {$kelembapan}% | Kondisi: {$kondisi} | Pompa: {$pumpStr}");
    }

    private function prosesStatus(string $topic, string $message): void
    {
        $parts = explode('/', $topic);
        $mac   = strtoupper($parts[2] ?? '');

        $data = json_decode($message, true);
        if (!$data) return;

        $sensor = Sensor::where('mac_address', $mac)->first();
        if (!$sensor) return;

        $isOnline = (bool) ($data['online'] ?? true);
        $sensor->update(['status' => $isOnline]);

        $this->line("📡 Status dari {$mac}: " . ($isOnline ? 'Online' : 'Offline'));
    }
}