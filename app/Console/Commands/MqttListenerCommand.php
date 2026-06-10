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

        $mqtt->subscribe('mapia/sensor/+/data', function (string $topic, string $message) {
            $this->prosesData($topic, $message);
        });

        $mqtt->subscribe('mapia/sensor/+/status', function (string $topic, string $message) {
            $this->prosesStatus($topic, $message);
        });

        $mqtt->subscribe('mapia/sensor/+/heartbeat', function (string $topic, string $message) {
            $parts = explode('/', $topic);
            $mac   = $parts[2] ?? '?';
            $data  = json_decode($message, true);
            $this->line("💓 Heartbeat dari {$mac} | RSSI: " . ($data['rssi'] ?? '?') . " dBm | Heap: " . ($data['heap'] ?? '?'));
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

    /**
     * Normalisasi MAC address:
     * "B0:CB:D8:03:ED:40" → "B0CBD803ED40"
     * "b0:cb:d8:03:ed:40" → "B0CBD803ED40"
     * "B0CBD803ED40"      → "B0CBD803ED40" (sudah bersih)
     */
    private function normalizeMac(string $mac): string
    {
        return strtoupper(str_replace([':', '-', '.', ' '], '', $mac));
    }

    private function prosesData(string $topic, string $message): void
    {
        // Ekstrak MAC dari topic: mapia/sensor/B0CBD803ED40/data
        $parts     = explode('/', $topic);
        $macRaw    = $parts[2] ?? null;
        $mac       = $this->normalizeMac($macRaw ?? '');

        $this->line("\n📨 Data masuk dari MAC: {$macRaw} (normalized: {$mac})");
        $this->line("   Payload: {$message}");

        $data = json_decode($message, true);

        if (!$data || !is_array($data)) {
            $this->warn('⚠️  Payload bukan JSON valid, skip.');
            return;
        }

        // Cari sensor berdasarkan MAC yang sudah dinormalisasi
        $sensor = Sensor::where('mac_address', $mac)->first();

        if (!$sensor) {
            $this->warn("⚠️  Sensor dengan MAC [{$mac}] tidak ditemukan di database!");
            $this->warn("    MAC di database yang tersedia:");
            Sensor::select('id_sensor', 'nama_sensor', 'mac_address')->get()->each(function ($s) {
                $this->warn("    → ID {$s->id_sensor} | {$s->nama_sensor} | {$s->mac_address}");
            });
            return;
        }

        $kelembapan = $data['kelembapan'] ?? null;
        $phTanah    = $data['ph_tanah']   ?? 7.0;
        $kondisi    = $data['kondisi']    ?? 'UNKNOWN';
        $uptime     = $data['uptime']     ?? 0;
        $pumpStr    = $data['pump']       ?? 'OFF';
        $modeStr    = $data['mode']       ?? 'otomatis';

        if ($kelembapan === null) {
            $this->warn('⚠️  Field kelembapan tidak ada di payload, skip.');
            return;
        }

        // Simpan ke history_kelembapans (termasuk ph_tanah)
        HistoryKelembapan::create([
            'id_sensor'  => $sensor->id_sensor,
            'kelembapan' => $kelembapan,
            'ph_tanah'   => $phTanah,
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

        $this->info("✅ Tersimpan: [{$sensor->nama_sensor}] Kelembapan: {$kelembapan}% | pH: {$phTanah} | Kondisi: {$kondisi} | Pompa: {$pumpStr} | Mode: {$modeStr}");
    }

    private function prosesStatus(string $topic, string $message): void
    {
        $parts  = explode('/', $topic);
        $mac    = $this->normalizeMac($parts[2] ?? '');

        $data = json_decode($message, true);
        if (!$data) return;

        $sensor = Sensor::where('mac_address', $mac)->first();
        if (!$sensor) return;

        $isOnline = (bool) ($data['online'] ?? true);
        $sensor->update(['status' => $isOnline]);

        $this->line("📡 Status dari {$mac}: " . ($isOnline ? '🟢 Online' : '🔴 Offline'));
    }
}