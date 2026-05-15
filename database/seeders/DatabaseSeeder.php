<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Sensor;
use App\Models\ParameterPenyiraman;
use App\Models\RiwayatSensor;
use App\Models\JenisNotif;
use App\Models\Notifikasi;
use App\Models\RiwayatPenyiraman;
use App\Models\HistoryKelembapan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. USER ──────────────────────────────────────────────
        $user = User::create([
            'nama'       => 'Pak Budi Santoso',
            'email'      => 'budi@mapia.id',
            'password'   => Hash::make('password'),
        ]);

        // ── 2. SENSOR ─────────────────────────────────────────────
        $sensorData = [
            ['nama_sensor' => 'Sensor Blok A', 'mac_address' => 'AA:BB:CC:DD:EE:01', 'lokasi' => 'Blok A — Barat Kebun',  'status' => true],
            ['nama_sensor' => 'Sensor Blok B', 'mac_address' => 'AA:BB:CC:DD:EE:02', 'lokasi' => 'Blok B — Tengah Kebun', 'status' => true],
            ['nama_sensor' => 'Sensor Blok C', 'mac_address' => 'AA:BB:CC:DD:EE:03', 'lokasi' => 'Blok C — Timur Kebun',  'status' => false],
        ];

        $sensors = [];
        foreach ($sensorData as $s) {
            $sensors[] = Sensor::create(array_merge($s, [
                'id_user' => $user->id_user,
            ]));
        }

        // ── 3. PARAMETER PENYIRAMAN ───────────────────────────────
        $paramConfig = [
            ['min_kelembapan' => 35.0, 'max_kelembapan' => 75.0, 'min_ph' => 6, 'max_ph' => 7, 'mode_auto' => true],
            ['min_kelembapan' => 40.0, 'max_kelembapan' => 80.0, 'min_ph' => 5, 'max_ph' => 7, 'mode_auto' => false],
            ['min_kelembapan' => 30.0, 'max_kelembapan' => 70.0, 'min_ph' => 6, 'max_ph' => 8, 'mode_auto' => true],
        ];

        foreach ($sensors as $i => $sensor) {
            ParameterPenyiraman::create(array_merge(
                $paramConfig[$i],
                ['id_sensor' => $sensor->id_sensor]
            ));
        }

        // ── 4. RIWAYAT SENSOR (bacaan IoT) ───────────────────────
        $readings = [
            [55.2, 58.1, 62.4, 60.0, 57.3, 53.8, 48.2, 45.0, 42.1, 38.5, 36.0, 33.2, 31.0, 28.5, 26.0, 38.0, 50.0, 58.0, 63.0, 65.0, 67.0, 64.0, 60.0, 56.0, 52.0, 49.0, 47.0, 44.0, 42.0, 40.0],
            [30.5, 28.0, 25.5, 23.0, 20.0, 18.5, 17.0, 15.5, 14.0, 28.0, 40.0, 50.0, 55.0, 52.0, 48.0, 44.0, 40.0, 36.0, 32.0, 28.0, 25.0, 22.0, 19.0, 17.0, 15.0, 30.0, 45.0, 55.0, 60.0, 58.0],
            [72.0, 75.5, 78.0, 80.0, 78.5, 76.0, 74.0, 72.0, 70.0, 68.0, 66.0, 64.0, 62.0, 60.0, 58.0, 56.0, 54.0, 52.0, 50.0, 48.0, 46.0, 44.0, 42.0, 40.0, 38.0, 36.0, 34.0, 32.0, 30.0, 28.0],
        ];

        $phReadings = [
            [6.5, 6.6, 6.7, 6.5, 6.4, 6.5, 6.6, 6.7, 6.5, 6.4, 6.5, 6.6, 6.4, 6.5, 6.6, 6.5, 6.4, 6.5, 6.6, 6.7, 6.5, 6.4, 6.5, 6.6, 6.5, 6.4, 6.5, 6.6, 6.5, 6.4],
            [5.8, 5.9, 6.0, 6.1, 6.0, 5.9, 5.8, 5.7, 5.8, 5.9, 6.0, 6.1, 6.0, 5.9, 5.8, 5.7, 5.8, 5.9, 6.0, 6.1, 6.0, 5.9, 5.8, 5.7, 5.8, 5.9, 6.0, 6.1, 6.0, 5.9],
            [7.2, 7.3, 7.4, 7.5, 7.4, 7.3, 7.2, 7.1, 7.2, 7.3, 7.4, 7.5, 7.4, 7.3, 7.2, 7.1, 7.0, 7.1, 7.2, 7.3, 7.4, 7.5, 7.4, 7.3, 7.2, 7.1, 7.0, 7.1, 7.2, 7.3],
        ];

        $base = Carbon::now()->subDays(7);
        foreach ($sensors as $i => $sensor) {
            foreach ($readings[$i] as $j => $kel) {
                RiwayatSensor::create([
                    'id_sensor'  => $sensor->id_sensor,
                    'kelembapan' => $kel,
                    'ph_tanah'   => $phReadings[$i][$j % count($phReadings[$i])],
                    'created_at' => $base->copy()->addHours($j * 6),
                ]);

                // Tambahkan juga ke history_kelembapans (tabel baru)
                HistoryKelembapan::create([
                    'id_sensor'  => $sensor->id_sensor,
                    'kelembapan' => $kel,
                    'created_at' => $base->copy()->addHours($j * 6),
                ]);
            }
        }

        // ── 5. JENIS NOTIFIKASI ────────────────────────────────────
        $jenisData = [
            ['kategori' => 1, 'keterangan' => 'Tanah Terlalu Kering'],
            ['kategori' => 2, 'keterangan' => 'Tanah Terlalu Basah'],
            ['kategori' => 3, 'keterangan' => 'pH Terlalu Rendah'],
            ['kategori' => 4, 'keterangan' => 'pH Terlalu Tinggi'],
            ['kategori' => 5, 'keterangan' => 'Penyiraman Selesai'],
            ['kategori' => 6, 'keterangan' => 'Sensor Offline'],
            ['kategori' => 7, 'keterangan' => 'Mode Penyiraman Diubah'],
            ['kategori' => 8, 'keterangan' => 'Parameter Diubah'],
            ['kategori' => 9, 'keterangan' => 'Pompa Dinyalakan Manual'],
            ['kategori' => 10, 'keterangan' => 'Pompa Dimatikan Manual'],
        ];
        $jenisModels = [];
        foreach ($jenisData as $jd) {
            $jenisModels[] = JenisNotif::create($jd);
        }

        // ── 6. NOTIFIKASI ─────────────────────────────────────────
        $notifData = [
            ['id_jenis_notif' => $jenisModels[0]->id_jenis_notif, 'tanggal' => Carbon::now()->subDays(1)->toDateString(), 'waktu' => '08:30:00', 'isi_data' => 'Sensor Blok B mendeteksi kelembapan 15.5% — di bawah batas minimum 40%.'],
            ['id_jenis_notif' => $jenisModels[4]->id_jenis_notif, 'tanggal' => Carbon::now()->subDays(1)->toDateString(), 'waktu' => '09:15:00', 'isi_data' => 'Penyiraman Blok A telah selesai. Kelembapan naik ke 65%.'],
            ['id_jenis_notif' => $jenisModels[1]->id_jenis_notif, 'tanggal' => Carbon::now()->subDays(2)->toDateString(), 'waktu' => '14:00:00', 'isi_data' => 'Sensor Blok C mendeteksi kelembapan 80% — melebihi batas maksimum.'],
            ['id_jenis_notif' => $jenisModels[5]->id_jenis_notif, 'tanggal' => Carbon::now()->subDays(2)->toDateString(), 'waktu' => '16:45:00', 'isi_data' => 'Sensor Blok C tidak mengirim data selama lebih dari 2 jam.'],
        ];

        foreach ($notifData as $nd) {
            Notifikasi::create(array_merge($nd, ['id_user' => $user->id_user]));
        }

        // ── 7. RIWAYAT PENYIRAMAN ─────────────────────────────────
        $riwayatPenyiraman = [
            [
                'id_sensor'     => $sensors[0]->id_sensor,
                'mode'          => 'otomatis',
                'status'        => 'berhasil',
                'waktu_mulai'   => Carbon::now()->subDays(1)->setTime(9, 0),
                'waktu_selesai' => Carbon::now()->subDays(1)->setTime(9, 45),
                'keterangan'    => 'Penyiraman otomatis — kelembapan 28% → 65%',
            ],
            [
                'id_sensor'     => $sensors[1]->id_sensor,
                'mode'          => 'manual',
                'status'        => 'berhasil',
                'waktu_mulai'   => Carbon::now()->subDays(1)->setTime(14, 0),
                'waktu_selesai' => Carbon::now()->subDays(1)->setTime(14, 30),
                'keterangan'    => 'Penyiraman manual oleh pengguna',
            ],
            [
                'id_sensor'     => $sensors[0]->id_sensor,
                'mode'          => 'otomatis',
                'status'        => 'berhasil',
                'waktu_mulai'   => Carbon::now()->subMinutes(10),
                'waktu_selesai' => null,
                'keterangan'    => 'Penyiraman otomatis sedang berlangsung',
            ],
        ];

        foreach ($riwayatPenyiraman as $rp) {
            RiwayatPenyiraman::create($rp);
        }

        $this->command->info('✅ Seeder MAPIA selesai!');
        $this->command->info('   Email  : budi@mapia.id');
        $this->command->info('   Password: password');
    }
}
