<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Models\RiwayatPenyiraman;
use App\Models\ParameterPenyiraman;
use App\Models\KontrolSiram;
use App\Models\JenisNotif;
use App\Models\Notifikasi;
use App\Services\MqttService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    protected $mqttService;

    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }

    // ─── Helper: normalisasi MAC → B0CBD803ED40 (tanpa titik dua, uppercase) ───
    private function normalizeMac(string $mac): string
    {
        return strtoupper(str_replace(':', '', $mac));
    }

    // ════════════════════════════════════════════════════════════
    // INDEX — tampilkan halaman kontrol
    // ════════════════════════════════════════════════════════════

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

        $aktifIds = RiwayatPenyiraman::whereIn('id_sensor', $sensors->pluck('id_sensor'))
            ->whereNull('waktu_selesai')
            ->pluck('id_sensor')
            ->toArray();

        $penyiramanAktif = array_fill_keys($aktifIds, true);

        return view('monitoring.kontrol', compact('sensors', 'penyiramanAktif'));
    }

    // ════════════════════════════════════════════════════════════
    // TOGGLE MODE — Otomatis ↔ Manual
    // ════════════════════════════════════════════════════════════

    public function toggleMode(Request $request, $id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);

        $kontrolSiram = KontrolSiram::firstOrCreate(
            ['id_sensor' => $id],
            ['mode_auto' => true, 'status_pompa' => false]
        );

        // Checkbox tidak terkirim = unchecked = manual, terkirim = otomatis
        $modeAuto = $request->has('mode_auto');

        $kontrolSiram->update(['mode_auto' => $modeAuto]);

        $modeText = $modeAuto ? 'Otomatis' : 'Manual';

        // Tutup sesi penyiraman aktif bila ada
        RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->update([
                'waktu_selesai' => now(),
                'keterangan'    => 'Sesi dihentikan karena perubahan mode ke ' . $modeText,
            ]);

        // Catat riwayat perubahan mode
        RiwayatPenyiraman::create([
            'id_sensor'      => $id,
            'mode'           => $modeAuto ? 'otomatis' : 'manual',
            'status'         => 'berhasil',
            'waktu_mulai'    => now(),
            'waktu_selesai'  => now(),
            'keterangan'     => 'Perubahan mode penyiraman menjadi ' . $modeText,
        ]);

        // Notifikasi
        $jenisNotif = JenisNotif::firstOrCreate(
            ['kategori' => 7],
            ['keterangan' => 'Mode Penyiraman Diubah']
        );
        Notifikasi::create([
            'id_jenis_notif' => $jenisNotif->id_jenis_notif,
            'id_user'        => Auth::id(),
            'tanggal'        => now()->toDateString(),
            'waktu'          => now()->toTimeString(),
            'isi_data'       => "Mode penyiraman untuk sensor {$sensor->nama_sensor} diubah menjadi {$modeText}.",
        ]);

        // ✅ FIX: gunakan MAC yang sudah dinormalisasi (tanpa titik dua, uppercase)
        $mac   = $this->normalizeMac($sensor->mac_address);
        $topic = 'mapia/sensor/' . $mac . '/mode';

        $published = $this->mqttService->publish($topic, $modeText);

        Log::info("[Monitoring] toggleMode sensor={$id} mode={$modeText} mac={$mac} mqtt=" . ($published ? 'ok' : 'gagal'));

        return back()->with('success', "Mode berhasil diubah ke {$modeText} dan disinkronkan ke alat.");
    }

    // ════════════════════════════════════════════════════════════
    // NYALAKAN — pompa ON (mode manual saja)
    // ════════════════════════════════════════════════════════════

    public function nyalakan($id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);

        // Cek apakah mode otomatis aktif — jangan izinkan kontrol manual
        $kontrolSiram = KontrolSiram::where('id_sensor', $id)->first();
        if ($kontrolSiram && $kontrolSiram->mode_auto) {
            return back()->with('error', 'Tidak bisa menyalakan pompa saat mode otomatis aktif.');
        }

        // Cek apakah mode otomatis aktif — jangan izinkan kontrol manual
        // Hindari duplikat sesi
        $sudahAktif = RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->exists();

        if (!$sudahAktif) {
            RiwayatPenyiraman::create([
                'id_sensor'     => $id,
                'mode'          => 'manual',
                'status'        => 'berhasil',
                'waktu_mulai'   => now(),
                'waktu_selesai' => null,
                'keterangan'    => 'Dinyalakan manual oleh pengguna',
            ]);
        }

        // Tetap sinkronkan status dan kirim MQTT meskipun sesi aktif sudah ada.
        KontrolSiram::updateOrCreate(
            ['id_sensor' => $id],
            ['status_pompa' => true, 'mode_auto' => false]
        );

        if (!$sudahAktif) {
            // Notifikasi
            $jenisNotif = JenisNotif::firstOrCreate(
                ['kategori' => 9],
                ['keterangan' => 'Pompa Dinyalakan Manual']
            );
            Notifikasi::create([
                'id_jenis_notif' => $jenisNotif->id_jenis_notif,
                'id_user'        => Auth::id(),
                'tanggal'        => now()->toDateString(),
                'waktu'          => now()->toTimeString(),
                'isi_data'       => "Pompa air untuk sensor {$sensor->nama_sensor} dinyalakan secara manual.",
            ]);
        }

        // ✅ FIX: normalisasi MAC sebelum publish
        $mac   = $this->normalizeMac($sensor->mac_address);
        $topic = 'mapia/actuator/' . $mac . '/pump';

        $published = $this->mqttService->publish($topic, 'ON');

        Log::info("[Monitoring] nyalakan sensor={$id} mac={$mac} topic={$topic} mqtt=" . ($published ? 'ok' : 'gagal'));

        return back()->with('success', 'Pompa berhasil dinyalakan.');
    }

    // ════════════════════════════════════════════════════════════
    // MATIKAN — pompa OFF
    // ════════════════════════════════════════════════════════════

    public function matikan($id)
    {
        $sensor = Sensor::where('id_user', Auth::id())->findOrFail($id);

        // Tutup sesi penyiraman aktif
        RiwayatPenyiraman::where('id_sensor', $id)
            ->whereNull('waktu_selesai')
            ->update(['waktu_selesai' => now()]);

        // Update status pompa di DB
        KontrolSiram::updateOrCreate(
            ['id_sensor' => $id],
            ['status_pompa' => false]
        );

        // Notifikasi
        $jenisNotif = JenisNotif::firstOrCreate(
            ['kategori' => 10],
            ['keterangan' => 'Pompa Dimatikan Manual']
        );
        Notifikasi::create([
            'id_jenis_notif' => $jenisNotif->id_jenis_notif,
            'id_user'        => Auth::id(),
            'tanggal'        => now()->toDateString(),
            'waktu'          => now()->toTimeString(),
            'isi_data'       => "Pompa air untuk sensor {$sensor->nama_sensor} dimatikan secara manual.",
        ]);

        // ✅ FIX: normalisasi MAC sebelum publish
        $mac   = $this->normalizeMac($sensor->mac_address);
        $topic = 'mapia/actuator/' . $mac . '/pump';

        $published = $this->mqttService->publish($topic, 'OFF');

        Log::info("[Monitoring] matikan sensor={$id} mac={$mac} topic={$topic} mqtt=" . ($published ? 'ok' : 'gagal'));

        return back()->with('success', 'Pompa berhasil dimatikan.');
    }
}
