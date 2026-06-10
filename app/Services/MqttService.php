<?php

namespace App\Services;

use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\FailedToSubscribeTopicException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\Exceptions\PingFailedException;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    private $mqtt;
    private $broker;
    private $port;
    private $username;
    private $password;

    public function __construct()
    {
        $this->broker = env('MQTT_BROKER', 'x1517f89.ala.asia-southeast1.emqxsl.com');
        $this->port = env('MQTT_PORT', 8883);
        $this->username = env('MQTT_USERNAME', 'Mapia');
        $this->password = env('MQTT_PASSWORD', 'Mapia123');
    }

    /**
     * Connect ke EMQX Broker
     */
    public function connect(): bool
    {
        try {
            $clientId = env('MQTT_CLIENT_ID', 'mapia-laravel-') . uniqid();
            $this->mqtt = new MqttClient(
                $this->broker,
                $this->port,
                $clientId,
                MqttClient::MQTT_3_1_1
            );

            // PERBAIKAN DI SINI:
            // 1. Tambahkan username & password agar bisa login ke EMQX
            // 2. Ubah setVerifyPeer menjadi setTlsVerifyPeer
// app/Services/MqttService.php

$connectionSettings = (new ConnectionSettings())
    ->setUsername($this->username)
    ->setPassword($this->password)
    ->setUseTls(true)
    ->setTlsCertificateAuthorityFile(base_path('emqxsl-ca.crt')) // Mengarah ke file crt di root proyek
    ->setTlsVerifyPeer(true) // Set true karena kita menggunakan CA certificate yang valid
    ->setTlsSelfSignedAllowed(false) // Nonaktifkan self-signed karena ini dari CA resmi DigiCert
    ->setKeepAliveInterval(env('MQTT_KEEP_ALIVE', 60));
    
            $this->mqtt->connect($connectionSettings, true);
            Log::info('[MQTT] ✓ Connected to broker: ' . $this->broker . ':' . $this->port);
            
            return true;
        } catch (ConnectingToBrokerFailedException $e) {
            Log::error('[MQTT] ✗ Failed to connect: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish ke MQTT Topic
     */
/**
     * Publish ke MQTT Topic
     */
    public function publish(string $topic, string $message, int $qos = 1, bool $retain = false): bool
    {
        try {
            if (!$this->mqtt) {
                $this->connect();
            }

            // Tambahkan validasi ini agar aman jika broker sedang down
            if (!$this->mqtt) {
                Log::error('[MQTT] ✗ Publish failed: Client instance is null.');
                return false;
            }

            $this->mqtt->publish($topic, $message, $qos, $retain);
            Log::info('[MQTT] ✓ Published to ' . $topic . ': ' . $message);
            
            return true;
        } catch (\Exception $e) {
            Log::error('[MQTT] ✗ Publish failed: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Subscribe ke MQTT Topic dengan callback
     */
    public function subscribe(string $topic, callable $callback, int $qos = 1): void
    {
        try {
            if (!$this->mqtt) {
                $this->connect();
            }

            $this->mqtt->subscribe($topic, $callback, $qos);
            Log::info('[MQTT] ✓ Subscribed to: ' . $topic);
            
        } catch (FailedToSubscribeTopicException $e) {
            Log::error('[MQTT] ✗ Subscribe failed: ' . $e->getMessage());
        }
    }

    /**
     * Loop untuk listen MQTT messages
     */
    public function loop(): void
    {
        try {
            if ($this->mqtt) {
                $this->mqtt->loop(true);
            }
        } catch (PingFailedException $e) {
            Log::error('[MQTT] ✗ Ping failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect dari MQTT
     */
    public function disconnect(): void
    {
        try {
            if ($this->mqtt) {
                $this->mqtt->disconnect();
                Log::info('[MQTT] ✓ Disconnected');
            }
        } catch (\Exception $e) {
            Log::error('[MQTT] ✗ Disconnect failed: ' . $e->getMessage());
        }
    }

    /**
     * Get MQTT client instance
     */
    public function getClient(): ?MqttClient
    {
        return $this->mqtt;
    }
}