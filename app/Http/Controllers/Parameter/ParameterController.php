<?php

namespace App\Http\Controllers\Parameter;

use App\Http\Controllers\Controller;
use App\Models\ParameterPenyiraman;
use App\Models\Sensor;
use App\Services\MqttService; // <-- Import MqttService
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParameterController extends Controller
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

        $parameter = ParameterPenyiraman::with('sensor')
            ->whereHas('sensor', fn($q) => $q->where('id_user', $userId))
            ->get();

        return view('parameter.index', compact('parameter'));
    }

    public function edit($id)
    {
        $parameter = ParameterPenyiraman::with('sensor')->findOrFail($id);
        abort_if($parameter->sensor->id_user !== Auth::id(), 403);

        $sensor = $parameter->sensor;
        return view('parameter.edit', compact('parameter', 'sensor'));
    }

    public function update(Request $request, $id)
    {
        $parameter = ParameterPenyiraman::with('sensor')->findOrFail($id);
        abort_if($parameter->sensor->id_user !== Auth::id(), 403);

        $validated = $request->validate([
            'min_kelembapan' => 'required|numeric|min:0|max:100',
            'max_kelembapan' => 'required|numeric|min:0|max:100|gte:min_kelembapan',
            'min_ph'         => 'required|numeric|min:0|max:14',
            'max_ph'         => 'required|numeric|min:0|max:14|gte:min_ph',
        ]);

        $parameter->update([
            'min_kelembapan' => $validated['min_kelembapan'],
            'max_kelembapan' => $validated['max_kelembapan'],
            'min_ph'         => $validated['min_ph'],
            'max_ph'         => $validated['max_ph'],
        ]);

        $jenisNotif = \App\Models\JenisNotif::firstOrCreate(
            ['kategori' => 8],
            ['keterangan' => 'Parameter Diubah']
        );
        \App\Models\Notifikasi::create([
            'id_jenis_notif' => $jenisNotif->id_jenis_notif,
            'id_user'        => Auth::id(),
            'tanggal'        => now()->toDateString(),
            'waktu'          => now()->toTimeString(),
            'isi_data'       => "Parameter ambang batas untuk sensor {$parameter->sensor->nama_sensor} berhasil diperbarui.",
        ]);

        // Realisasi MQTT: Kirim payload JSON ambang batas ke ESP32
        $topic = 'mapia/sensor/' . $parameter->sensor->mac_address . '/parameter';
        $payload = json_encode([
            'min_kel' => (float) $parameter->min_kelembapan,
            'max_kel' => (float) $parameter->max_kelembapan,
            'min_ph'  => (float) $parameter->min_ph,
            'max_ph'  => (float) $parameter->max_ph,
        ]);
        
        $this->mqttService->publish($topic, $payload);

        return redirect()->route('parameter.index')
            ->with('success', 'Parameter sensor berhasil disimpan dan disinkronkan ke alat!');
    }
}