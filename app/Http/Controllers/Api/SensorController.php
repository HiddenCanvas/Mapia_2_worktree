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
use Illuminate\Support\Facades\Auth;

class SensorController extends Controller
{
    protected $mqttService;

    public function __construct()
    {
        $this->mqttService = new MqttService();
    }

    private function normalizeMac(string $mac): string
    {
        return strtoupper(str_replace(':', '', $mac));
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
     * ✅ GET LIVE DATA — dipanggil frontend via polling setiap 5 detik
     * GET /api/v1/sensors/{id}/live
     * Mengembalikan: kelembapan, ph_tanah, kondisi, pump, mode, updated_at
     */
    public function getLiveData($id)
    {
        try {
            $sensor = Sensor::with(['parameterPenyiraman', 'kontrolSiram'])->find($id);

            if (!$sensor) {
                return response()->json(['status' => 'error', 'message' => 'Sensor not found'], 404);
            }

            // Data terbaru dari history kelembapan (dikirim ESP32 via MQTT)
            $latest = HistoryKelembapan::where('id_sensor', $id)->latest()->first();

            $kelembapan = $latest->kelembapan ?? 0;
            $phTanah    = $latest->ph_tanah ?? 7.0;
            $kondisi    = $latest->kondisi ?? 'UNKNOWN';
            $updatedAt  = $latest ? $latest->created_at->diffForHumans() : 'Belum ada data';

            $kontrolSiram = $sensor->kontrolSiram;

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'id_sensor'    => $sensor->id_sensor,
                    'nama_sensor'  => $sensor->nama_sensor,
                    'online'       => $sensor->status,
                    'kelembapan'   => round($kelembapan, 1),
                    'ph_tanah'     => round($phTanah, 2),
                    'kondisi'      => $kondisi,
                    'pump'         => $kontrolSiram ? ($kontrolSiram->status_pompa ? 'ON' : 'OFF') : 'OFF',
                    'mode'         => $kontrolSiram ? ($kontrolSiram->mode_auto ? 'otomatis' : 'manual') : 'manual',
                    'mode_auto'    => $kontrolSiram ? (bool)$kontrolSiram->mode_auto : false,
                    'pump_on'      => $kontrolSiram ? (bool)$kontrolSiram->status_pompa : false,
                    'updated_at'   => $updatedAt,
                    'updated_raw'  => $latest ? $latest->created_at->toIso8601String() : null,
                    'min_kel'      => $sensor->parameterPenyiraman->min_kelembapan ?? 40,
                    'max_kel'      => $sensor->parameterPenyiraman->max_kelembapan ?? 70,
                    'min_ph'       => $sensor->parameterPenyiraman->min_ph ?? 6,
                    'max_ph'       => $sensor->parameterPenyiraman->max_ph ?? 7,
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
     * ✅ GET LIVE DATA semua sensor milik user yang login
     * GET /api/v1/sensors/live/all
     */
    public function getAllLiveData(Request $request)
    {
        try {
            // Ambil id_user dari query param (karena ini mungkin dipanggil tanpa session auth)
            $userId = $request->query('user_id');

            $query = Sensor::with(['parameterPenyiraman', 'kontrolSiram']);
            if ($userId) {
                $query->where('id_user', $userId);
            }

            $sensors = $query->get();

            $result = $sensors->map(function ($sensor) {
                $latest = HistoryKelembapan::where('id_sensor', $sensor->id_sensor)->latest()->first();
                $kontrolSiram = $sensor->kontrolSiram;

                return [
                    'id_sensor'   => $sensor->id_sensor,
                    'nama_sensor' => $sensor->nama_sensor,
                    'lokasi'      => $sensor->lokasi,
                    'online'      => $sensor->status,
                    'kelembapan'  => $latest ? round($latest->kelembapan, 1) : 0,
                    'ph_tanah'    => $latest ? round($latest->ph_tanah ?? 7.0, 2) : 7.0,
                    'kondisi'     => $latest->kondisi ?? 'UNKNOWN',
                    'pump'        => $kontrolSiram ? ($kontrolSiram->status_pompa ? 'ON' : 'OFF') : 'OFF',
                    'mode'        => $kontrolSiram ? ($kontrolSiram->mode_auto ? 'otomatis' : 'manual') : 'manual',
                    'mode_auto'   => $kontrolSiram ? (bool)$kontrolSiram->mode_auto : false,
                    'pump_on'     => $kontrolSiram ? (bool)$kontrolSiram->status_pompa : false,
                    'updated_at'  => $latest ? $latest->created_at->diffForHumans() : 'Belum ada data',
                ];
            });

            return response()->json([
                'status' => 'success',
                'data'   => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ GET HISTORY KELEMBAPAN untuk chart
     * GET /api/v1/sensors/{id}/history?limit=50
     */
    public function getHistory(Request $request, $id)
    {
        try {
            $limit = min((int)$request->query('limit', 30), 200);

            $history = HistoryKelembapan::where('id_sensor', $id)
                ->latest()
                ->limit($limit)
                ->get()
                ->reverse()
                ->values()
                ->map(fn($h) => [
                    'waktu'      => $h->created_at->format('H:i'),
                    'tanggal'    => $h->created_at->format('d/m H:i'),
                    'kelembapan' => round($h->kelembapan, 1),
                    'ph_tanah'   => round($h->ph_tanah ?? 7.0, 2),
                    'kondisi'    => $h->kondisi ?? 'UNKNOWN',
                ]);

            return response()->json([
                'status' => 'success',
                'data'   => $history
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
     */
    public function updateParameter(Request $request, $sensorId)
    {
        try {
            $validated = $request->validate([
                'min_kelembapan' => 'required|numeric|min:0|max:99',
                'max_kelembapan' => 'required|numeric|min:1|max:100',
            ]);

            if ($validated['min_kelembapan'] >= $validated['max_kelembapan']) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'min_kelembapan must be less than max_kelembapan'
                ], 422);
            }

            $param = ParameterPenyiraman::updateOrCreate(
                ['id_sensor' => $sensorId],
                $validated
            );

            $this->publishParameterUpdate($sensorId, $validated);

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
     */
    public function updateMode(Request $request, $sensorId)
    {
        try {
            $validated = $request->validate([
                'mode' => 'required|in:otomatis,manual',
            ]);

            $sensor = Sensor::find($sensorId);
            if (!$sensor) {
                return response()->json(['status' => 'error', 'message' => 'Sensor not found'], 404);
            }

            $kontrolSiram = KontrolSiram::updateOrCreate(
                ['id_sensor' => $sensorId],
                ['mode_auto' => $validated['mode'] === 'otomatis']
            );

            $topic = 'mapia/sensor/' . $this->normalizeMac($sensor->mac_address) . '/mode';
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
     */
    public function controlPump(Request $request, $sensorId)
    {
        try {
            $validated = $request->validate([
                'action' => 'required|in:ON,OFF',
            ]);

            $sensor = Sensor::find($sensorId);
            if (!$sensor) {
                return response()->json(['status' => 'error', 'message' => 'Sensor not found'], 404);
            }

            $kontrolSiram = KontrolSiram::where('id_sensor', $sensorId)->first();
            if ($kontrolSiram && $kontrolSiram->mode_auto) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Tidak bisa kontrol pompa saat mode otomatis aktif'
                ], 422);
            }

            KontrolSiram::updateOrCreate(
                ['id_sensor' => $sensorId],
                ['status_pompa' => $validated['action'] === 'ON']
            );

            // Publish ke MQTT → ESP32 akan langsung terima dan nyalakan/matikan relay
            $topic = 'mapia/actuator/' . $this->normalizeMac($sensor->mac_address) . '/pump';
            $this->mqttService->publish($topic, $validated['action']);

            Log::info('[API] Pump ' . $validated['action'] . ' for sensor ' . $sensorId . ' via MQTT topic: ' . $topic);

            return response()->json([
                'status'  => 'success',
                'message' => 'Perintah pompa terkirim ke perangkat',
                'action'  => $validated['action'],
                'pump_on' => $validated['action'] === 'ON',
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

            $topic = 'mapia/sensor/' . $this->normalizeMac($sensor->mac_address) . '/parameter';
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
