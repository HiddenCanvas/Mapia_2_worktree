<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Models\RiwayatPenyiraman;
use App\Models\ParameterPenyiraman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $sensors = Sensor::where('id_user', $userId)
            ->with([
                'riwayat_sensors' => fn($q) => $q->latest('created_at')->limit(1),
                'parameterPenyiraman',
                'kontrolSiram',
            ])
            ->get();

        // Sensor mana yang pompanya sedang aktif (waktu_selesai null)
        $aktifIds = RiwayatPenyiraman::whereIn('id_sensor', $sensors->pluck('id_sensor'))
            ->whereNull('waktu_selesai')
            ->pluck('id_sensor')
            ->toArray();

        $penyiramanAktif = array_fill_keys($aktifIds, true);

        return view('monitoring.kontrol', compact('sensors', 'penyiramanAktif'));
    }

    public function toggleMode(Request $request, $id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);
        $kontrolSiram = \App\Models\KontrolSiram::firstOrCreate(
            ['id_sensor' => $id],
            ['mode_auto' => true, 'status_pompa' => false]
        );

        $kontrolSiram->update([
            'mode_auto' => $request->has('mode_auto') ? true : false,
        ]);

        $modeText = $kontrolSiram->mode_auto ? 'Otomatis' : 'Manual';

        // Tutup sesi penyiraman aktif jika ada saat ganti mode
        RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->update([
                'waktu_selesai' => now(),
                'keterangan'    => 'Sesi dihentikan karena perubahan mode ke ' . $modeText
            ]);

        // Catat perubahan mode ke tabel riwayat (Opsional, agar 'Up to Date')
        RiwayatPenyiraman::create([
            'id_sensor'     => $id,
            'mode'          => $kontrolSiram->mode_auto ? 'otomatis' : 'manual',
            'status'        => 'berhasil',
            'waktu_mulai'   => now(),
            'waktu_selesai' => now(),
            'keterangan'    => 'Perubahan mode penyiraman menjadi ' . $modeText,
        ]);

        // Buat notifikasi perubahan mode
        $jenisNotif = \App\Models\JenisNotif::firstOrCreate(
            ['kategori' => 7],
            ['keterangan' => 'Mode Penyiraman Diubah']
        );
        \App\Models\Notifikasi::create([
            'id_jenis_notif' => $jenisNotif->id_jenis_notif,
            'id_user'        => Auth::id(),
            'tanggal'        => now()->toDateString(),
            'waktu'          => now()->toTimeString(),
            'isi_data'       => "Mode penyiraman untuk sensor {$sensor->nama_sensor} diubah menjadi {$modeText}.",
        ]);

        // KEDEPANNYA UNTUK IOT:
        // Saat mode berubah, publish pesan MQTT ke NodeMCU/ESP32 agar alat tau harus jalan otomatis atau tunggu perintah manual.
        // Contoh: MQTT::publish('mapia/sensor/'.$sensor->mac_address.'/mode', $modeText);

        return back()->with('success', 'Mode penyiraman berhasil diubah dan riwayat diperbarui.');
    }

    public function nyalakan($id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);

        // Cek apakah sudah ada yang aktif
        $sudahAktif = RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->exists();

        if (!$sudahAktif) {
            RiwayatPenyiraman::create([
                'id_sensor'    => $id,
                'mode'         => 'manual',
                'status'       => 'berhasil',
                'waktu_mulai'  => now(),
                'waktu_selesai'=> null,
                'keterangan'   => 'Dinyalakan manual oleh pengguna',
            ]);

            // Buat notifikasi pompa menyala
            $jenisNotif = \App\Models\JenisNotif::firstOrCreate(
                ['kategori' => 9],
                ['keterangan' => 'Pompa Dinyalakan Manual']
            );
            \App\Models\Notifikasi::create([
                'id_jenis_notif' => $jenisNotif->id_jenis_notif,
                'id_user'        => Auth::id(),
                'tanggal'        => now()->toDateString(),
                'waktu'          => now()->toTimeString(),
                'isi_data'       => "Pompa air untuk sensor {$sensor->nama_sensor} dinyalakan secara manual.",
            ]);

            // KEDEPANNYA UNTUK IOT:
            // Kirim perintah MQTT untuk menyalakan relay pompa.
            // Contoh: MQTT::publish('mapia/actuator/'.$sensor->mac_address.'/pump', 'ON');
        }

        return back()->with('success', 'Pompa berhasil dinyalakan.');
    }

    public function matikan($id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);

        // Tutup semua sesi penyiraman aktif untuk sensor ini
        RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->update(['waktu_selesai' => now()]);

        // Buat notifikasi pompa dimatikan
        $jenisNotif = \App\Models\JenisNotif::firstOrCreate(
            ['kategori' => 10],
            ['keterangan' => 'Pompa Dimatikan Manual']
        );
        \App\Models\Notifikasi::create([
            'id_jenis_notif' => $jenisNotif->id_jenis_notif,
            'id_user'        => Auth::id(),
            'tanggal'        => now()->toDateString(),
            'waktu'          => now()->toTimeString(),
            'isi_data'       => "Pompa air untuk sensor {$sensor->nama_sensor} dimatikan secara manual.",
        ]);

        // KEDEPANNYA UNTUK IOT:
        // Kirim perintah MQTT untuk mematikan relay pompa.
        // Contoh: MQTT::publish('mapia/actuator/'.$sensor->mac_address.'/pump', 'OFF');

        return back()->with('success', 'Pompa berhasil dimatikan.');
    }
}
