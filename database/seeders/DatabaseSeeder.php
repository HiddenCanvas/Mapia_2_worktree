<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Sensor;
use App\Models\ParameterPenyiraman;
use App\Models\KontrolSiram;
use App\Models\JenisNotif;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── USER ──────────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'admin@mapia.id'],
            [
                'nama'     => 'Admin MAPIA',
                'password' => Hash::make('password'),
            ]
        );

        // ── SENSOR (1 sensor, MAC sesuai ESP32) ───────────
        $sensor = Sensor::firstOrCreate(
            ['mac_address' => 'B0CBD803ED40'],
            [
                'id_user'     => $user->id_user,
                'nama_sensor' => 'Sensor Pepaya Blok A',
                'lokasi'      => 'Kebun Utama',
                'status'      => true,
            ]
        );

        // ── PARAMETER (nilai default, bisa diubah via UI) ─
        ParameterPenyiraman::firstOrCreate(
            ['id_sensor' => $sensor->id_sensor],
            [
                'min_kelembapan' => 40.0,
                'max_kelembapan' => 70.0,
                'min_ph'         => 5.5,
                'max_ph'         => 7.0,
            ]
        );

        // ── KONTROL SIRAM ─────────────────────────────────
        KontrolSiram::firstOrCreate(
            ['id_sensor' => $sensor->id_sensor],
            [
                'mode_auto'    => true,
                'status_pompa' => false,
            ]
        );

        // ── JENIS NOTIFIKASI (master data) ────────────────
        $jenisData = [
            ['kategori' => 1,  'keterangan' => 'Tanah Terlalu Kering'],
            ['kategori' => 2,  'keterangan' => 'Tanah Terlalu Basah'],
            ['kategori' => 3,  'keterangan' => 'pH Terlalu Rendah'],
            ['kategori' => 4,  'keterangan' => 'pH Terlalu Tinggi'],
            ['kategori' => 5,  'keterangan' => 'Penyiraman Selesai'],
            ['kategori' => 6,  'keterangan' => 'Sensor Offline'],
            ['kategori' => 7,  'keterangan' => 'Mode Penyiraman Diubah'],
            ['kategori' => 8,  'keterangan' => 'Parameter Diubah'],
            ['kategori' => 9,  'keterangan' => 'Pompa Dinyalakan Manual'],
            ['kategori' => 10, 'keterangan' => 'Pompa Dimatikan Manual'],
        ];

        foreach ($jenisData as $jd) {
            JenisNotif::firstOrCreate(
                ['kategori' => $jd['kategori']],
                ['keterangan' => $jd['keterangan']]
            );
        }

        $this->command->info('✅ Seeder selesai!');
        $this->command->info('   Email   : admin@mapia.id');
        $this->command->info('   Password: password');
        $this->command->info('   MAC ESP32: B0CBD803ED40');
    }
}