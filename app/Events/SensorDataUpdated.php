<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int    $sensorId;
    public float  $kelembapan;
    public string $kondisi;
    public string $pump;
    public string $mode;
    public int    $uptime;
    public string $timestamp;

    public function __construct(
        int    $sensorId,
        float  $kelembapan,
        string $kondisi,
        string $pump,
        string $mode,
        int    $uptime = 0
    ) {
        $this->sensorId   = $sensorId;
        $this->kelembapan = $kelembapan;
        $this->kondisi    = $kondisi;
        $this->pump       = $pump;
        $this->mode       = $mode;
        $this->uptime     = $uptime;
        $this->timestamp  = now()->toISOString();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('sensor.' . $this->sensorId),
            new Channel('sensors'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sensor-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'sensorId'   => $this->sensorId,
            'kelembapan' => $this->kelembapan,
            'kondisi'    => $this->kondisi,
            'pump'       => $this->pump,
            'mode'       => $this->mode,
            'uptime'     => $this->uptime,
            'timestamp'  => $this->timestamp,
        ];
    }
}