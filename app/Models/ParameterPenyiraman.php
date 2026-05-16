<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParameterPenyiraman extends Model
{
    protected $table      = 'parameter_penyiramans';
    protected $primaryKey = 'id_parameter';
    public    $timestamps = false;

    protected $fillable   = ['id_sensor', 'min_kelembapan', 'max_kelembapan', 'min_ph', 'max_ph'];

    protected $casts = [
        'min_kelembapan' => 'float',
        'max_kelembapan' => 'float',
        'min_ph'         => 'float',
        'max_ph'         => 'float',
    ];

    public function sensor()
    {
        return $this->belongsTo(Sensor::class, 'id_sensor', 'id_sensor');
    }
}
