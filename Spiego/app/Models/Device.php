<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'hostname',
        'platform',
        'processor',
        'cpu_count',
        'memory_total',
        'mac_addresses',
        'python_version',
        'last_seen',
    ];

    protected $casts = [
        'mac_addresses' => 'array',
        'last_seen' => 'datetime',
        'memory_total' => 'float',
    ];

    public function events()
    {
        return $this->hasMany(Event::class, 'hostname', 'hostname');
    }
}
