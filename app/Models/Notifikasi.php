<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table      = 'notifikasis';
    protected $primaryKey = 'id_notif';
    public    $timestamps = false;

    protected $fillable   = ['id_jenis_notif', 'id_user', 'tanggal', 'waktu', 'isi_data', 'dibaca'];

    protected $casts = [
        'dibaca' => 'boolean',
    ];

    public function jenisNotif()
    {
        return $this->belongsTo(JenisNotif::class, 'id_jenis_notif', 'id_jenis_notif');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
