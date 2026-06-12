<?php

namespace App\Services;

use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\FailedToSubscribeTopicException;
use PhpMqtt\Client\Exceptions\PingFailedException;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    private $mqtt;
    private string $broker;
    private int    $port;
    private string $username;
    private string $password;

    public function __construct()
    {
        $this->broker   = env('MQTT_BROKER', 'x1517f89.ala.asia-southeast1.emqxsl.com');
        $this->port     = (int) env('MQTT_PORT', 8883);
        $this->username = env('MQTT_USERNAME', 'Mapia');
        $this->password = env('MQTT_PASSWORD', 'Mapia123');
    }

    // ─── Buat ConnectionSettings (dipakai baik connect() maupun publish()) ───
    private function buildSettings(): ConnectionSettings
    {
        return (new ConnectionSettings())
            ->setUsername($this->username)
            ->setPassword($this->password)
            ->setUseTls(true)
            ->setTlsCertificateAuthorityFile(base_path('emqxsl-ca.crt'))
            ->setTlsVerifyPeer(true)
            ->setTlsSelfSignedAllowed(false)
            ->setKeepAliveInterval((int) env('MQTT_KEEP_ALIVE', 60));
    }

    /**
     * Connect ke EMQX Broker (untuk listener command — koneksi long-lived)
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

            $this->mqtt->connect($this->buildSettings(), true);
            Log::info('[MQTT] ✓ Connected to broker: ' . $this->broker . ':' . $this->port);

            return true;
        } catch (ConnectingToBrokerFailedException $e) {
            Log::error('[MQTT] ✗ Failed to connect: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish ke MQTT Topic.
     * PENTING: membuat koneksi baru yang pendek (short-lived),
     * publish, lalu langsung disconnect — tidak ada connection leak.
     */
    public function publish(string $topic, string $message, int $qos = 1, bool $retain = false): bool
    {
        $clientId = env('MQTT_CLIENT_ID', 'mapia-publish-') . uniqid();
        $client   = null;

        try {
            $client = new MqttClient(
                $this->broker,
                $this->port,
                $clientId,
                MqttClient::MQTT_3_1_1
            );

            $client->connect($this->buildSettings(), false);
            $client->publish($topic, $message, $qos, $retain);
            Log::info('[MQTT] ✓ Published to ' . $topic . ': ' . $message);

            return true;
        } catch (\Exception $e) {
            Log::error('[MQTT] ✗ Publish failed: ' . $e->getMessage());
            return false;
        } finally {
            // Selalu disconnect meskipun ada error — mencegah connection leak
            try {
                $client?->disconnect();
            } catch (\Exception $e) {
                // Abaikan error saat disconnect
            }
        }
    }

    /**
     * Subscribe ke MQTT Topic dengan callback (untuk listener command)
     */
    public function subscribe(string $topic, callable $callback, int $qos = 1): void
    {
        try {
            if (!$this->mqtt || !$this->mqtt->isConnected()) {
                $this->connect();
            }

            $this->mqtt->subscribe($topic, $callback, $qos);
            Log::info('[MQTT] ✓ Subscribed to: ' . $topic);
        } catch (FailedToSubscribeTopicException $e) {
            Log::error('[MQTT] ✗ Subscribe failed: ' . $e->getMessage());
        }
    }

    /**
     * Loop untuk listen MQTT messages (untuk listener command)
     */
    public function loop(): void
    {
        try {
            if ($this->mqtt && $this->mqtt->isConnected()) {
                $this->mqtt->loop(true);
            }
        } catch (PingFailedException $e) {
            Log::error('[MQTT] ✗ Ping failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect dari MQTT (untuk listener command)
     */
    public function disconnect(): void
    {
        try {
            if ($this->mqtt && $this->mqtt->isConnected()) {
                $this->mqtt->disconnect();
                Log::info('[MQTT] ✓ Disconnected');
            }
        } catch (\Exception $e) {
            Log::error('[MQTT] ✗ Disconnect failed: ' . $e->getMessage());
        }
    }

    /**
     * Get MQTT client instance (untuk listener command)
     */
    public function getClient(): ?MqttClient
    {
        return $this->mqtt;
    }
}