<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{
    protected $table      = 'sensors';
    protected $primaryKey = 'id_sensor';

    protected $fillable   = ['id_user', 'nama_sensor', 'mac_address', 'lokasi', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function riwayat_sensors()
    {
        return $this->hasMany(RiwayatSensor::class, 'id_sensor', 'id_sensor');
    }

    public function parameterPenyiraman()
    {
        return $this->hasOne(ParameterPenyiraman::class, 'id_sensor', 'id_sensor');
    }

    public function riwayatPenyiraman()
    {
        return $this->hasMany(RiwayatPenyiraman::class, 'id_sensor', 'id_sensor');
    }

    public function kontrolSiram()
    {
        return $this->hasOne(KontrolSiram::class, 'id_sensor', 'id_sensor');
    }

    public function historyKelembapans()
    {
        return $this->hasMany(HistoryKelembapan::class, 'id_sensor', 'id_sensor');
    }
}
