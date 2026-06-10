<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Models\RiwayatPenyiraman;
use App\Models\ParameterPenyiraman;
use App\Services\MqttService; // <-- Import MqttService
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    protected $mqttService;

    // Inject MqttService melalui Constructor
    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

public function index()
{
    $userId = Auth::id();

    $sensors = Sensor::where('id_user', $userId)
        ->with([
            'historyKelembapans' => fn($q) => $q->latest()->limit(1),
            'riwayat_sensors'    => fn($q) => $q->latest('created_at')->limit(1),
            'parameterPenyiraman',
            'kontrolSiram',
        ])
        ->get();

    $aktifIds = \App\Models\RiwayatPenyiraman::whereIn('id_sensor', $sensors->pluck('id_sensor'))
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

        RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->update([
                'waktu_selesai' => now(),
                'keterangan'    => 'Sesi dihentikan karena perubahan mode ke ' . $modeText
            ]);

        RiwayatPenyiraman::create([
            'id_sensor'      => $id,
            'mode'          => $kontrolSiram->mode_auto ? 'otomatis' : 'manual',
            'status'        => 'berhasil',
            'waktu_mulai'   => now(),
            'waktu_selesai' => now(),
            'keterangan'    => 'Perubahan mode penyiraman menjadi ' . $modeText,
        ]);

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

        // Realisasi MQTT: Kirim perubahan mode ke ESP32
        $topic = 'mapia/sensor/' . $sensor->mac_address . '/mode';
        $this->mqttService->publish($topic, $modeText);

        return back()->with('success', 'Mode penyiraman berhasil diubah dan disinkronkan ke alat.');
    }

    public function nyalakan($id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);

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

            // Realisasi MQTT: Kirim instruksi ON ke relay pompa ESP32
            $topic = 'mapia/actuator/' . $sensor->mac_address . '/pump';
            $this->mqttService->publish($topic, 'ON');
        }

        return back()->with('success', 'Pompa berhasil dinyalakan.');
    }

    public function matikan($id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);

        RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->update(['waktu_selesai' => now()]);

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

        // Realisasi MQTT: Kirim instruksi OFF ke relay pompa ESP32
        $topic = 'mapia/actuator/' . $sensor->mac_address . '/pump';
        $this->mqttService->publish($topic, 'OFF');

        return back()->with('success', 'Pompa berhasil dimatikan.');
    }
}