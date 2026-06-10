<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryKelembapan extends Model
{
    protected $table = 'history_kelembapans';
    protected $primaryKey = 'id_history';

    // PERBAIKAN: Tambahkan 'ph_tanah' di dalam array ini
    protected $fillable = ['id_sensor', 'kelembapan', 'ph_tanah', 'kondisi', 'uptime'];

    public function sensor()
    {
        return $this->belongsTo(Sensor::class, 'id_sensor', 'id_sensor');
    }
}