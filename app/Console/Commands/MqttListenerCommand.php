<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MqttService;
use App\Models\Sensor;
use App\Models\HistoryKelembapan;
use App\Models\KontrolSiram;
use Illuminate\Support\Facades\Log;

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

        // Connect to EMQX Broker
        if (!$this->mqttService->connect()) {
            $this->error('✗ Failed to connect to MQTT broker');
            return 1;
        }

        $this->info('✓ Connected to MQTT Broker');

        // Subscribe to wildcard topic untuk data sensor
        // Pattern: mapia/sensor/MAC_ADDRESS/data
        $this->mqttService->subscribe('mapia/sensor/+/data', function (string $topic, string $message) {
            $this->processSensorData($topic, $message);
        });

        // Subscribe to status topic (Antisipasi jika digunakan di masa depan)
        // Pattern: mapia/sensor/MAC_ADDRESS/status
        $this->mqttService->subscribe('mapia/sensor/+/status', function (string $topic, string $message) {
            $this->processStatus($topic, $message);
        });

        // Subscribe to heartbeat
        // Pattern: mapia/sensor/MAC_ADDRESS/heartbeat
        $this->mqttService->subscribe('mapia/sensor/+/heartbeat', function (string $topic, string $message) {
            $this->processHeartbeat($topic, $message);
        });

        $this->info('✓ Subscribed to topics');
        $this->line('Listening for messages... Press Ctrl+C to stop');

        // Loop tiada henti untuk mendengarkan pesan masuk
        try {
            while ($this->mqttService->getClient() && $this->mqttService->getClient()->isConnected()) {
                $this->mqttService->loop();
                
                // Jeda 100ms agar menghemat penggunaan CPU Server
                usleep(100000); 
            }
        } catch (\Exception $e) {
            Log::error('[MQTT LISTENER] Error: ' . $e->getMessage());
            $this->error('✗ Error: ' . $e->getMessage());
        } finally {
            $this->mqttService->disconnect();
            $this->info('🔴 MQTT Listener stopped.');
        }

        return 0;
    }

    /**
     * Process sensor data dari ESP32 (Pilihan A - Sinkronisasi Semua Data)
     * Topic: mapia/sensor/{MAC}/data
     * Payload: {"kelembapan": 0, "ph_tanah": 3.37, "id_sensor": 1, "pump": "OFF", "mode": "otomatis"}
     */
    private function processSensorData(string $topic, string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            // 1. Validasi format JSON
            if (!$data || !is_array($data)) {
                Log::warning('[MQTT] Sensor data payload is not a valid JSON: ' . $message);
                return;
            }
            
            // Periksa parameter wajib
            if (!isset($data['id_sensor'], $data['kelembapan'])) {
                Log::warning('[MQTT] Incomplete data payload received: ' . $message);
                return;
            }

            $sensorId = $data['id_sensor'];
            $kelembapan = $data['kelembapan'];
            $phTanah = $data['ph_tanah'] ?? 7.0; 

            // 2. Verifikasi keberadaan Sensor di Database
            $sensor = Sensor::find($sensorId);
            if (!$sensor) {
                Log::warning('[MQTT] Sensor ID not found in database: ' . $sensorId);
                return; 
            }

            // 3. Simpan Riwayat Berkala ke HistoryKelembapan
            HistoryKelembapan::create([
                'id_sensor'  => $sensorId,
                'kelembapan' => $kelembapan,
                'ph_tanah'   => $phTanah,
                'kondisi'    => $data['kondisi'] ?? 'UNKNOWN',
                'uptime'     => $data['uptime'] ?? 0,
            ]);

            // 4. Update Status Pompa & Mode secara Real-time ke DB KontrolSiram
            if (isset($data['pump'])) {
                $sensor->kontrolSiram()->updateOrCreate(
                    ['id_sensor' => $sensor->id_sensor],
                    [
                        'status_pompa' => $data['pump'] === 'ON', 
                        'mode_auto'    => isset($data['mode']) && $data['mode'] === 'otomatis', 
                    ]
                );
                Log::info('[MQTT] KontrolSiram updated directly from data payload for Sensor: ' . $sensorId);
            }

            Log::info('[MQTT] Data saved successfully - Sensor: ' . $sensorId . ' | Kelembapan: ' . $kelembapan . '% | pH: ' . $phTanah);

            // 5. Broadcast Real-time ke Frontend Web
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
     * Process status dari ESP32 (Versi Aman)
     * Topic: mapia/sensor/{MAC}/status
     */
    private function processStatus(string $topic, string $message): void
    {
        try {
            $data = json_decode($message, true);
            
            if (!$data || !is_array($data)) {
                return;
            }
            
            $parts = explode('/', $topic);
            $mac = $parts[2] ?? null;

            if (!$mac) return;

            $sensor = Sensor::where('mac_address', $mac)->first();
            if (!$sensor) return;

            // Update status online alat
            $isOnline = isset($data['online']) ? (bool)$data['online'] : true;
            $sensor->update(['status' => $isOnline]);

            Log::info('[MQTT] Status updated via status topic - MAC: ' . $mac . ' | Online: ' . ($isOnline ? 'true' : 'false'));

        } catch (\Exception $e) {
            Log::error('[MQTT] Error processing status: ' . $e->getMessage());
        }
    }

    /**
     * Process heartbeat dari ESP32
     * Topic: mapia/sensor/{MAC}/heartbeat
     */
    private function processHeartbeat(string $topic, string $message): void
    {
        try {
            $data = json_decode($message, true);
            
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