<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Models\Sensor;
use App\Models\HistoryKelembapan;
use App\Models\KontrolSiram;
use App\Models\ParameterPenyiraman;
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
            $parts = explode('/', $topic);
            $mac   = strtoupper(str_replace(':', '', $parts[2] ?? '?'));
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
        // Ekstrak MAC dari topic: mapia/sensor/B0:CB:D8:03:ED:40/data atau B0CBD803ED40
        $parts = explode('/', $topic);
        $mac   = strtoupper(str_replace(':', '', $parts[2] ?? ''));

        $this->line("\n📨 Data masuk dari MAC: {$mac}");
        $this->line("   Payload: {$message}");

        $data = json_decode($message, true);

        if (!$data || !is_array($data)) {
            $this->warn('⚠️  Payload bukan JSON valid, skip.');
            return;
        }

        // Cari sensor berdasarkan MAC (sudah tanpa titik dua, uppercase)
        $sensor = Sensor::where('mac_address', $mac)->first();

        if (!$sensor) {
            $this->warn("⚠️  Sensor dengan MAC [{$mac}] tidak ditemukan di database!");
            return;
        }

$kelembapan = $data['kelembapan'] ?? $data['ph_tanah'] ?? null;

        if ($kelembapan === null) {
            $this->warn('⚠️  Field kelembapan tidak ada di payload, skip.');
            return;
        }

        // ─── Ambil field dari payload ESP32 ───
        // ESP32 mengirim: kelembapan, ph_tanah, id_sensor, pump, mode
        // Field kondisi & uptime tidak ada di firmware — kita hitung sendiri
        $phTanah  = $data['ph_tanah'] ?? null;
        $pumpStr  = $data['pump']     ?? 'OFF';
        $modeStr  = $data['mode']     ?? 'otomatis';
        $uptime   = $data['uptime']   ?? 0;

        // ─── Hitung kondisi berdasarkan parameter sensor ───
        $kondisi = $this->hitungKondisi($sensor, (float) $kelembapan, $phTanah ? (float) $phTanah : null);

        // ─── Simpan ke history_kelembapans ───
        HistoryKelembapan::create([
            'id_sensor'  => $sensor->id_sensor,
            'kelembapan' => $kelembapan,
            'ph_tanah'   => $phTanah,
            'kondisi'    => $kondisi,
            'uptime'     => $uptime,
        ]);

        // ─── Update status pompa & mode di kontrol_sirams ───
        KontrolSiram::updateOrCreate(
            ['id_sensor' => $sensor->id_sensor],
            [
                'status_pompa' => strtoupper($pumpStr) === 'ON',
                'mode_auto'    => strtolower($modeStr) === 'otomatis',
            ]
        );

        // ─── Update status online sensor ───
        $sensor->update(['status' => true]);

        // ─── Broadcast ke browser via Reverb (WebSocket) ───
        broadcast(new SensorDataUpdated(
            $sensor->id_sensor,
            (float) $kelembapan,
            $kondisi,
            $pumpStr,
            $modeStr,
            (int) $uptime
        ));

        $this->info(
            "✅ Data disimpan: [{$sensor->nama_sensor}] " .
            "Kelembapan: {$kelembapan}% | " .
            "pH: " . ($phTanah ?? 'N/A') . " | " .
            "Kondisi: {$kondisi} | " .
            "Pompa: {$pumpStr}"
        );
    }

    /**
     * Hitung kondisi tanah berdasarkan parameter yang sudah diset user.
     * Jika parameter belum ada, gunakan nilai default.
     */
    private function hitungKondisi(Sensor $sensor, float $kelembapan, ?float $ph): string
    {
        $param = ParameterPenyiraman::where('id_sensor', $sensor->id_sensor)->first();

        $minKel = $param?->min_kelembapan ?? 40.0;
        $maxKel = $param?->max_kelembapan ?? 70.0;
        $minPh  = $param?->min_ph ?? 5.5;
        $maxPh  = $param?->max_ph ?? 7.0;

        if ($kelembapan < $minKel) {
            return 'KERING';
        }

        if ($kelembapan > $maxKel) {
            return 'BASAH';
        }

        if ($ph !== null && ($ph < $minPh || $ph > $maxPh)) {
            return 'PH_ABNORMAL';
        }

        return 'NORMAL';
    }

    private function prosesStatus(string $topic, string $message): void
    {
        $parts = explode('/', $topic);
        $mac   = strtoupper(str_replace(':', '', $parts[2] ?? ''));

        $data = json_decode($message, true);
        if (!$data) return;

        $sensor = Sensor::where('mac_address', $mac)->first();
        if (!$sensor) return;

        $isOnline = (bool) ($data['online'] ?? true);
        $sensor->update(['status' => $isOnline]);

        $this->line("📡 Status dari {$mac}: " . ($isOnline ? 'Online ✓' : 'Offline ✗'));
    }
}