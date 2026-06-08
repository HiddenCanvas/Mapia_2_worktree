<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RiwayatSensor;
use App\Models\Sensor;
use App\Models\ParameterPenyiraman;
use App\Models\KontrolSiram;
use App\Models\HistoryKelembapan;
use App\Services\MqttService;
use Illuminate\Support\Facades\Log;

class SensorController extends Controller
{
    protected $mqttService;

    public function __construct()
    {
        $this->mqttService = new MqttService();
    }

    /**
     * ✅ Endpoint untuk ESP32 mengirim data sensor
     * POST /api/v1/send-data
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'id_sensor'  => 'required|integer|exists:sensors,id_sensor',
                'kelembapan' => 'required|numeric',
                'ph_tanah'   => 'required|numeric',
            ]);

            $data = RiwayatSensor::create([
                'id_sensor'  => $validated['id_sensor'],
                'kelembapan' => $validated['kelembapan'],
                'ph_tanah'   => $validated['ph_tanah'],
                'created_at' => now(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Data sensor berhasil disimpan',
                'data'    => $data
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * ✅ Get semua sensor data
     * GET /api/v1/sensors
     */
    public function getSensors()
    {
        try {
            $sensors = Sensor::with(['parameterPenyiraman', 'kontrolSiram'])
                ->where('status', true)
                ->get();

            return response()->json([
                'status' => 'success',
                'data'   => $sensors
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Get detail sensor + latest data
     * GET /api/v1/sensors/{id}
     */
    public function getSensorDetail($id)
    {
        try {
            $sensor = Sensor::with([
                'parameterPenyiraman',
                'kontrolSiram',
                'historyKelembapans' => function ($q) {
                    $q->latest()->limit(10);
                }
            ])->find($id);

            if (!$sensor) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sensor not found'
                ], 404);
            }

            // Get latest reading
            $latestData = HistoryKelembapan::where('id_sensor', $id)
                ->latest()
                ->first();

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'sensor'      => $sensor,
                    'latest_data' => $latestData,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Update parameter penyiraman (min/max kelembapan)
     * PATCH /api/v1/sensors/{id}/parameter
     * Body: {"min_kelembapan": 40, "max_kelembapan": 70}
     */
    public function updateParameter(Request $request, $sensorId)
    {
        try {
            $validated = $request->validate([
                'min_kelembapan' => 'required|numeric|min:0|max:99',
                'max_kelembapan' => 'required|numeric|min:1|max:100',
            ]);

            // Validasi: min < max
            if ($validated['min_kelembapan'] >= $validated['max_kelembapan']) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'min_kelembapan must be less than max_kelembapan'
                ], 422);
            }

            // Update di database
            $param = ParameterPenyiraman::updateOrCreate(
                ['id_sensor' => $sensorId],
                $validated
            );

            // Publish ke MQTT (device akan menerima update ini)
            $this->publishParameterUpdate($sensorId, $validated);

            Log::info('[API] Parameter updated for sensor ' . $sensorId . ': ' . json_encode($validated));

            return response()->json([
                'status'  => 'success',
                'message' => 'Parameter updated successfully',
                'data'    => $param
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * ✅ Update mode (Otomatis / Manual)
     * PATCH /api/v1/sensors/{id}/mode
     * Body: {"mode": "otomatis"} or {"mode": "manual"}
     */
    public function updateMode(Request $request, $sensorId)
    {
        try {
            $validated = $request->validate([
                'mode' => 'required|in:otomatis,manual',
            ]);

            $sensor = Sensor::find($sensorId);
            if (!$sensor) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sensor not found'
                ], 404);
            }

            // Update mode
            $kontrolSiram = KontrolSiram::updateOrCreate(
                ['id_sensor' => $sensorId],
                ['mode_auto' => $validated['mode'] === 'otomatis']
            );

            // Publish ke MQTT
            $topic = 'mapia/sensor/' . $sensor->mac_address . '/mode';
            $message = ($validated['mode'] === 'otomatis') ? 'Otomatis' : 'Manual';
            $this->mqttService->publish($topic, $message);

            Log::info('[API] Mode updated for sensor ' . $sensorId . ': ' . $message);

            return response()->json([
                'status'  => 'success',
                'message' => 'Mode updated successfully',
                'data'    => $kontrolSiram
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * ✅ Control pump (ON / OFF) - hanya di mode manual
     * POST /api/v1/sensors/{id}/pump
     * Body: {"action": "ON"} or {"action": "OFF"}
     */
    public function controlPump(Request $request, $sensorId)
    {
        try {
            $validated = $request->validate([
                'action' => 'required|in:ON,OFF',
            ]);

            $sensor = Sensor::find($sensorId);
            if (!$sensor) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Sensor not found'
                ], 404);
            }

            // Check if mode is manual
            $kontrolSiram = KontrolSiram::where('id_sensor', $sensorId)->first();
            if ($kontrolSiram && $kontrolSiram->mode_auto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Cannot control pump in automatic mode'
                ], 422);
            }

            // Update pump status
            KontrolSiram::updateOrCreate(
                ['id_sensor' => $sensorId],
                ['status_pompa' => $validated['action'] === 'ON']
            );

            // Publish ke MQTT
            $topic = 'mapia/actuator/' . $sensor->mac_address . '/pump';
            $this->mqttService->publish($topic, $validated['action']);

            Log::info('[API] Pump ' . $validated['action'] . ' for sensor ' . $sensorId);

            return response()->json([
                'status'  => 'success',
                'message' => 'Pump control sent successfully',
                'action'  => $validated['action']
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Helper: Publish parameter update ke MQTT
     */
    private function publishParameterUpdate($sensorId, $params)
    {
        try {
            $sensor = Sensor::find($sensorId);
            if (!$sensor) return;

            $topic = 'mapia/sensor/' . $sensor->mac_address . '/parameter';
            $message = json_encode([
                'min_kel' => $params['min_kelembapan'],
                'max_kel' => $params['max_kelembapan'],
            ]);

            $this->mqttService->publish($topic, $message);
        } catch (\Exception $e) {
            Log::error('[API] Failed to publish parameter: ' . $e->getMessage());
        }
    }
}
