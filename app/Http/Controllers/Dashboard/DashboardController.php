<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Models\RiwayatPenyiraman;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
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

    $sensorData = $sensors->map(function ($sensor) {
        $latestHistory = $sensor->historyKelembapans->first();
        $latestRiwayat = $sensor->riwayat_sensors->first();

        return (object) [
            'id_sensor'    => $sensor->id_sensor,
            'nama_sensor'  => $sensor->nama_sensor,
            'lokasi'       => $sensor->lokasi,
            'status'       => $sensor->status,
            'kelembapan'   => $latestHistory?->kelembapan ?? $latestRiwayat?->kelembapan ?? 0,
            'ph_tanah'     => $latestRiwayat?->ph_tanah ?? 0,
            'kondisi'      => $latestHistory?->kondisi ?? 'UNKNOWN',
            'created_at'   => $latestHistory?->created_at ?? $latestRiwayat?->created_at,
            'mode_auto'    => $sensor->kontrolSiram?->mode_auto ?? true,
            'status_pompa' => $sensor->kontrolSiram?->status_pompa ?? false,
        ];
    });

    $penyiramanAktifIds = \App\Models\RiwayatPenyiraman::whereIn('id_sensor', $sensors->pluck('id_sensor'))
        ->whereNull('waktu_selesai')
        ->pluck('id_sensor')
        ->toArray();

    $stats = [
        'total_sensor'     => $sensors->count(),
        'sensor_online'    => $sensors->where('status', true)->count(),
        'tanah_kering'     => $sensorData->filter(fn($s) => $s->kelembapan < 30)->count(),
        'penyiraman_aktif' => count($penyiramanAktifIds),
    ];

    $unreadCount = 0;

    return view('dashboard.index', compact('sensorData', 'stats', 'unreadCount'));
}
}
