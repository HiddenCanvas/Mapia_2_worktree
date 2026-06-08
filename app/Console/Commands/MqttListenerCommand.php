<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Models\Sensor;
use App\Models\HistoryKelembapan;
use App\Models\KontrolSiram;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MqttListenerCommand extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen to MQTT topics and save sensor data';

    private $mqttService;

    public function __construct()
    {
        parent::__construct();
        $this->mqttService = new MqttService();
    }

    public function handle()
    {
        $this->info('🟢 MQTT Listener started...');
        Log::info('[MQTT LISTENER] Started');

        // Connect to EMQX
        if (!$this->mqttService->connect()) {
            $this->error('✗ Failed to connect to MQTT broker');
            return 1;
        }

        $this->info('✓ Connected to MQTT Broker');

        // Subscribe to wildcard topic untuk semua sensor
        // Pattern: mapia/sensor/MAC_ADDRESS/data
        $this->mqttService->subscribe('mapia/sensor/+/data', function (string $topic, string $message) {
            $this->processSensorData($topic, $message);
        });

        // Subscribe to status topic
        // Pattern: mapia/sensor/MAC_ADDRESS/status
        $this->mqttService->subscribe('mapia/sensor/+/status', function (string $topic, string $message) {
            $this->processStatus($topic, $message);
        });

        // Subscribe to heartbeat
        $this->mqttService->subscribe('mapia/sensor/+/heartbeat', function (string $topic, string $message) {
            $this->processHeartbeat($topic, $message);
        });

        $this->info('✓ Subscribed to topics');
        $this->line('Listening for messages... Press Ctrl+C to stop');

        // Loop to listen messages
        try {
            $this->mqttService->loop();
        } catch (\Exception $e) {
            Log::error('[MQTT LISTENER] Error: ' . $e->getMessage());
            $this->error('✗ Error: ' . $e->getMessage());
        } finally {
            $this->mqttService->disconnect();
        }

        return 0;
    }

    /**
     * Process sensor data dari ESP32
     * Topic: mapia/sensor/{MAC}/data
     * Payload: {"kelembapan": 65.5, "id_sensor": 1, "pump": "ON", "mode": "otomatis", "kondisi": "LEMBAP", "uptime": 120}
     */
    private function processSensorData(string $topic, string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            if (!isset($data['id_sensor'], $data['kelembapan'])) {
                Log::warning('[MQTT] Incomplete data: ' . $message);
                return;
            }

            $sensorId = $data['id_sensor'];
            $kelembapan = $data['kelembapan'];

            // Verifikasi sensor exists
            $sensor = Sensor::find($sensorId);
            if (!$sensor) {
                Log::warning('[MQTT] Sensor not found: ' . $sensorId);
                return;
            }

            // Simpan ke HistoryKelembapan
            HistoryKelembapan::create([
                'id_sensor' => $sensorId,
                'kelembapan' => $kelembapan,
                'kondisi' => $data['kondisi'] ?? 'UNKNOWN',
                'uptime' => $data['uptime'] ?? 0,
            ]);

            Log::info('[MQTT] Data saved - Sensor: ' . $sensorId . ' | Kelembapan: ' . $kelembapan . '%');

            // Broadcast to web (real-time)
            broadcast(new \App\Events\SensorDataUpdated(
                $sensorId,
                $kelembapan,
                $data['kondisi'] ?? 'UNKNOWN',
                $data['pump'] ?? 'OFF',
                $data['mode'] ?? 'manual'
            ));

        } catch (\Exception $e) {
            Log::error('[MQTT] Error processing sensor data: ' . $e->getMessage());
        }
    }

    /**
     * Process status dari ESP32
     * Topic: mapia/sensor/{MAC}/status
     * Payload: {"online": true, "pump": "ON", "mode": "otomatis", "kel": 65.5, "kondisi": "LEMBAP", "min_kel": 40, "max_kel": 70, "rssi": -55, "uptime": 120}
     */
    private function processStatus(string $topic, string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            // Extract MAC address from topic: mapia/sensor/{MAC}/status
            $parts = explode('/', $topic);
            $mac = $parts[2] ?? null;

            if (!$mac) {
                Log::warning('[MQTT] Invalid topic format: ' . $topic);
                return;
            }

            // Find sensor by MAC address
            $sensor = Sensor::where('mac_address', $mac)->first();
            if (!$sensor) {
                Log::warning('[MQTT] Sensor not found for MAC: ' . $mac);
                return;
            }

            // Update status ke database
            $sensor->update(['status' => $data['online'] ?? true]);

            // Update parameter if received
            if (isset($data['min_kel'], $data['max_kel'])) {
                $sensor->parameterPenyiraman()->updateOrCreate(
                    ['id_sensor' => $sensor->id_sensor],
                    [
                        'min_kelembapan' => $data['min_kel'],
                        'max_kelembapan' => $data['max_kel'],
                    ]
                );
            }

            // Update pump status
            if (isset($data['pump'])) {
                $sensor->kontrolSiram()->updateOrCreate(
                    ['id_sensor' => $sensor->id_sensor],
                    [
                        'status_pompa' => $data['pump'] === 'ON',
                        'mode_auto' => $data['mode'] === 'otomatis',
                    ]
                );
            }

            Log::info('[MQTT] Status updated - MAC: ' . $mac . ' | Online: ' . ($data['online'] ? 'true' : 'false'));

        } catch (\Exception $e) {
            Log::error('[MQTT] Error processing status: ' . $e->getMessage());
        }
    }

    /**
     * Process heartbeat dari ESP32
     */
    private function processHeartbeat(string $topic, string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            // Extract MAC address
            $parts = explode('/', $topic);
            $mac = $parts[2] ?? null;

            if ($mac) {
                Log::info('[MQTT] Heartbeat from ' . $mac . ' - RSSI: ' . ($data['rssi'] ?? 'N/A') . ' | Heap: ' . ($data['heap'] ?? 'N/A'));
            }
        } catch (\Exception $e) {
            Log::error('[MQTT] Error processing heartbeat: ' . $e->getMessage());
        }
    }
}
