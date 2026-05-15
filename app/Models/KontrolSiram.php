<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KontrolSiram extends Model
{
    protected $table = 'kontrol_sirams';
    protected $primaryKey = 'id_kontrol_siram';

    protected $fillable = ['id_sensor', 'mode_auto', 'status_pompa'];

    protected $casts = [
        'mode_auto' => 'boolean',
        'status_pompa' => 'boolean',
    ];

    public function sensor()
    {
        return $this->belongsTo(Sensor::class, 'id_sensor', 'id_sensor');
    }
}
