<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Models\Sensor;
use App\Models\HistoryKelembapan;
use App\Models\KontrolSiram;
use App\Models\ParameterPenyiraman;
use App\Models\JenisNotif;
use App\Models\Notifikasi;
use App\Models\RiwayatPenyiraman;
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
            $mac   = strtoupper(str_replace(':', '', $parts[2] ?? '?'));
            $data  = json_decode($message, true);
            $this->line("💓 Heartbeat dari {$mac} | RSSI: " . ($data['rssi'] ?? '?') . " dBm");
        });

        $this->info('📡 Listening... (Ctrl+C untuk berhenti)');

        try {
            $client = $mqtt->getClient();
            while ($client && $client->isConnected()) {
                $mqtt->loop();
                usleep(100000);
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
        $parts = explode('/', $topic);
        $mac   = strtoupper(str_replace(':', '', $parts[2] ?? ''));

        $this->line("\n📨 Data masuk dari MAC: {$mac}");
        $this->line("   Payload: {$message}");

        $data = json_decode($message, true);

        if (!$data || !is_array($data)) {
            $this->warn('⚠️  Payload bukan JSON valid, skip.');
            return;
        }

        $sensor = Sensor::where('mac_address', $mac)->first();

        if (!$sensor) {
            $this->warn("⚠️  Sensor dengan MAC [{$mac}] tidak ditemukan di database!");
            return;
        }

        // FIX #3: jangan fallback ke ph_tanah kalau kelembapan null
        $kelembapan = $data['kelembapan'] ?? null;

        if ($kelembapan === null) {
            $this->warn('⚠️  Field kelembapan tidak ada di payload, skip.');
            return;
        }

        $phTanah = $data['ph_tanah'] ?? null;
        $pumpStr = $data['pump']     ?? 'OFF';
        $modeStr = $data['mode']     ?? 'otomatis';
        $uptime  = $data['uptime']   ?? 0;

        $kondisi = $this->hitungKondisi($sensor, (float) $kelembapan, $phTanah ? (float) $phTanah : null);

        // Simpan ke history
        HistoryKelembapan::create([
            'id_sensor'  => $sensor->id_sensor,
            'kelembapan' => $kelembapan,
            'ph_tanah'   => $phTanah,
            'kondisi'    => $kondisi,
            'uptime'     => $uptime,
        ]);

        // Ambil status pompa sebelumnya (untuk deteksi perubahan)
        $kontrolSiram = KontrolSiram::where('id_sensor', $sensor->id_sensor)->first();
        $pumpWasOn    = $kontrolSiram?->status_pompa ?? false;
        $pumpNowOn    = strtoupper($pumpStr) === 'ON';

        // Update kontrol siram
        KontrolSiram::updateOrCreate(
            ['id_sensor' => $sensor->id_sensor],
            [
                'status_pompa' => $pumpNowOn,
                'mode_auto'    => strtolower($modeStr) === 'otomatis',
            ]
        );

        // Update status online sensor
        $sensor->update(['status' => true]);

        // FIX: Catat riwayat penyiraman otomatis
        $this->catatRiwayat($sensor, $pumpWasOn, $pumpNowOn, $modeStr);

        // FIX: Buat notifikasi berdasarkan kondisi
        $this->buatNotifikasi($sensor, $kondisi, (float) $kelembapan, $phTanah ? (float) $phTanah : null);

        // Broadcast ke browser via Reverb (WebSocket)
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
     * Catat riwayat penyiraman saat pompa berubah status (ON→OFF atau OFF→ON).
     * Ini yang bikin riwayat jalan di mode otomatis juga, bukan cuma manual.
     */
    private function catatRiwayat(Sensor $sensor, bool $pumpWasOn, bool $pumpNowOn, string $modeStr): void
    {
        $modeLabel = strtolower($modeStr) === 'otomatis' ? 'otomatis' : 'manual';

        // Pompa baru nyala (OFF → ON)
        if (!$pumpWasOn && $pumpNowOn) {
            RiwayatPenyiraman::create([
                'id_sensor'     => $sensor->id_sensor,
                'mode'          => $modeLabel,
                'status'        => 'berhasil',
                'waktu_mulai'   => now(),
                'waktu_selesai' => null,
                'keterangan'    => 'Penyiraman dimulai secara ' . $modeLabel,
            ]);

            $this->line("   📝 Riwayat: pompa ON dicatat");
        }

        // Pompa baru mati (ON → OFF)
        if ($pumpWasOn && !$pumpNowOn) {
            // Tutup sesi riwayat yang masih terbuka
            $openSession = RiwayatPenyiraman::where('id_sensor', $sensor->id_sensor)
                ->whereNull('waktu_selesai')
                ->latest('waktu_mulai')
                ->first();

            if ($openSession) {
                $openSession->update([
                    'waktu_selesai' => now(),
                    'keterangan'    => $openSession->keterangan . ' — selesai otomatis',
                ]);
                $this->line("   📝 Riwayat: sesi penyiraman ditutup");
            }
        }
    }

    /**
     * Buat notifikasi untuk kondisi abnormal.
     * Pakai throttle 30 menit agar tidak spam notifikasi tiap 30 detik.
     */
    private function buatNotifikasi(Sensor $sensor, string $kondisi, float $kelembapan, ?float $ph): void
    {
        // Throttle: cek apakah sudah ada notifikasi kondisi sama dalam 30 menit terakhir
        $throttleMinutes = 30;

        $kategoriMap = [
            'KERING'      => 1,   // Tanah Terlalu Kering
            'BASAH'       => 2,   // Tanah Terlalu Basah
            'PH_ABNORMAL' => 3,   // pH Terlalu Rendah/Tinggi
            'NORMAL'      => null, // Tidak perlu notifikasi saat normal
        ];

        $kategori = $kategoriMap[$kondisi] ?? null;

        if ($kategori === null) {
            return; // kondisi NORMAL atau UNKNOWN → tidak buat notif
        }

        // Cek throttle: sudah ada notif sama dalam 30 menit?
        $jenisNotif = JenisNotif::where('kategori', $kategori)->first();
        if (!$jenisNotif) return;

        $sudahAda = Notifikasi::where('id_jenis_notif', $jenisNotif->id_jenis_notif)
            ->where('id_user', $sensor->id_user)
            ->where('tanggal', now()->toDateString())
            ->where('waktu', '>=', now()->subMinutes($throttleMinutes)->format('H:i:s'))
            ->exists();

        if ($sudahAda) {
            return; // sudah ada notif serupa, skip
        }

        // Buat pesan notifikasi yang informatif
        $isiMap = [
            'KERING' => "Kelembapan tanah sensor [{$sensor->nama_sensor}] sangat rendah ({$kelembapan}%). " .
                        "Sistem sedang memproses penyiraman otomatis.",
            'BASAH'  => "Kelembapan tanah sensor [{$sensor->nama_sensor}] terlalu tinggi ({$kelembapan}%). " .
                        "Penyiraman dihentikan untuk mencegah genangan.",
            'PH_ABNORMAL' => "Nilai pH tanah sensor [{$sensor->nama_sensor}] di luar rentang aman " .
                             "(pH: " . ($ph !== null ? number_format($ph, 2) : 'N/A') . "). " .
                             "Periksa kondisi tanah Anda.",
        ];

        $isi = $isiMap[$kondisi] ?? "Kondisi abnormal terdeteksi pada sensor [{$sensor->nama_sensor}].";

        Notifikasi::create([
            'id_jenis_notif' => $jenisNotif->id_jenis_notif,
            'id_user'        => $sensor->id_user,
            'tanggal'        => now()->toDateString(),
            'waktu'          => now()->toTimeString(),
            'isi_data'       => $isi,
        ]);

        $this->line("   🔔 Notifikasi dibuat: {$kondisi} untuk sensor [{$sensor->nama_sensor}]");
    }

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

        // Notifikasi sensor offline
        if (!$isOnline) {
            $jenisNotif = JenisNotif::where('kategori', 6)->first(); // Sensor Offline
            if ($jenisNotif) {
                $sudahAda = Notifikasi::where('id_jenis_notif', $jenisNotif->id_jenis_notif)
                    ->where('id_user', $sensor->id_user)
                    ->where('tanggal', now()->toDateString())
                    ->where('waktu', '>=', now()->subHour()->format('H:i:s'))
                    ->exists();

                if (!$sudahAda) {
                    Notifikasi::create([
                        'id_jenis_notif' => $jenisNotif->id_jenis_notif,
                        'id_user'        => $sensor->id_user,
                        'tanggal'        => now()->toDateString(),
                        'waktu'          => now()->toTimeString(),
                        'isi_data'       => "Sensor [{$sensor->nama_sensor}] terdeteksi offline. Periksa koneksi perangkat.",
                    ]);
                }
            }
        }

        $this->line("📡 Status dari {$mac}: " . ($isOnline ? 'Online ✓' : 'Offline ✗'));
    }
}