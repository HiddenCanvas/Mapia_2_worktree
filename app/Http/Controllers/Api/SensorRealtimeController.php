<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Models\KontrolSiram;
use App\Models\ParameterPenyiraman;
use App\Services\MqttService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SensorRealtimeController extends Controller
{
    protected MqttService $mqtt;

    public function __construct()
    {
        $this->mqtt = new MqttService();
    }

    // GET /api/v1/sensors/{id}/live — ambil data terbaru sensor
    public function getLiveData($id)
    {
        $sensor = Sensor::with([
            'parameterPenyiraman',
            'kontrolSiram',
            'historyKelembapans' => fn($q) => $q->latest()->limit(1),
        ])->find($id);

        if (!$sensor) {
            return response()->json(['status' => 'error', 'message' => 'Sensor not found'], 404);
        }

        $latest = $sensor->historyKelembapans->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_sensor'    => $sensor->id_sensor,
                'nama_sensor'  => $sensor->nama_sensor,
                'mac_address'  => $sensor->mac_address,
                'status'       => $sensor->status,
                'kelembapan'   => $latest?->kelembapan ?? 0,
                'kondisi'      => $latest?->kondisi ?? 'UNKNOWN',
                'uptime'       => $latest?->uptime ?? 0,
                'mode_auto'    => $sensor->kontrolSiram?->mode_auto ?? true,
                'status_pompa' => $sensor->kontrolSiram?->status_pompa ?? false,
                'min_kelembapan' => $sensor->parameterPenyiraman?->min_kelembapan ?? 40,
                'max_kelembapan' => $sensor->parameterPenyiraman?->max_kelembapan ?? 70,
                'updated_at'   => $latest?->created_at,
            ],
        ]);
    }

    // PATCH /api/v1/sensors/{id}/mode — ganti mode otomatis/manual
    public function setMode(Request $request, $id)
    {
        $validated = $request->validate([
            'mode' => 'required|in:otomatis,manual',
        ]);

        $sensor = Sensor::find($id);
        if (!$sensor) return response()->json(['status' => 'error', 'message' => 'Sensor not found'], 404);

        $kontrolSiram = KontrolSiram::updateOrCreate(
            ['id_sensor' => $id],
            ['mode_auto' => $validated['mode'] === 'otomatis']
        );

        // Kalau ganti ke manual, matikan pompa dulu
        if ($validated['mode'] === 'manual') {
            $kontrolSiram->update(['status_pompa' => false]);
            $this->mqtt->publish('mapia/actuator/' . $sensor->mac_address . '/pump', 'OFF');
        }

        $this->mqtt->publish(
            'mapia/sensor/' . $sensor->mac_address . '/mode',
            $validated['mode'] === 'otomatis' ? 'Otomatis' : 'Manual'
        );

        Log::info('[API] Mode changed for sensor ' . $id . ': ' . $validated['mode']);

        return response()->json([
            'status' => 'success',
            'message' => 'Mode updated',
            'mode_auto' => $kontrolSiram->mode_auto,
        ]);
    }

    // POST /api/v1/sensors/{id}/pump — nyalakan/matikan pompa (manual only)
    public function setPump(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:ON,OFF',
        ]);

        $sensor = Sensor::find($id);
        if (!$sensor) return response()->json(['status' => 'error', 'message' => 'Sensor not found'], 404);

        $kontrolSiram = KontrolSiram::where('id_sensor', $id)->first();

        if (!$kontrolSiram || $kontrolSiram->mode_auto) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot control pump in automatic mode',
            ], 422);
        }

        $kontrolSiram->update(['status_pompa' => $validated['action'] === 'ON']);

        $this->mqtt->publish(
            'mapia/actuator/' . $sensor->mac_address . '/pump',
            $validated['action']
        );

        Log::info('[API] Pump ' . $validated['action'] . ' for sensor ' . $id);

        return response()->json([
            'status' => 'success',
            'message' => 'Pump ' . $validated['action'],
            'status_pompa' => $validated['action'] === 'ON',
        ]);
    }

    // PATCH /api/v1/sensors/{id}/parameter — update parameter kelembapan
    public function setParameter(Request $request, $id)
    {
        $validated = $request->validate([
            'min_kelembapan' => 'required|numeric|min:0|max:99',
            'max_kelembapan' => 'required|numeric|min:1|max:100',
        ]);

        if ($validated['min_kelembapan'] >= $validated['max_kelembapan']) {
            return response()->json([
                'status' => 'error',
                'message' => 'min_kelembapan harus lebih kecil dari max_kelembapan',
            ], 422);
        }

        $sensor = Sensor::find($id);
        if (!$sensor) return response()->json(['status' => 'error', 'message' => 'Sensor not found'], 404);

        $param = ParameterPenyiraman::updateOrCreate(
            ['id_sensor' => $id],
            [
                'min_kelembapan' => $validated['min_kelembapan'],
                'max_kelembapan' => $validated['max_kelembapan'],
            ]
        );

        $this->mqtt->publish(
            'mapia/sensor/' . $sensor->mac_address . '/parameter',
            json_encode([
                'min_kel' => $validated['min_kelembapan'],
                'max_kel' => $validated['max_kelembapan'],
            ])
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Parameter updated',
            'data' => $param,
        ]);
    }
}