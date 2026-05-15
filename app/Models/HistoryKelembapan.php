<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryKelembapan extends Model
{
    protected $table = 'history_kelembapans';
    protected $primaryKey = 'id_history';

    protected $fillable = ['id_sensor', 'kelembapan'];

    public function sensor()
    {
        return $this->belongsTo(Sensor::class, 'id_sensor', 'id_sensor');
    }
}
