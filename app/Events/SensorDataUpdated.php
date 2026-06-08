<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sensorId;
    public $kelembapan;
    public $kondisi;
    public $pump;
    public $mode;
    public $timestamp;

    public function __construct($sensorId, $kelembapan, $kondisi, $pump, $mode)
    {
        $this->sensorId = $sensorId;
        $this->kelembapan = $kelembapan;
        $this->kondisi = $kondisi;
        $this->pump = $pump;
        $this->mode = $mode;
        $this->timestamp = now();
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
}
