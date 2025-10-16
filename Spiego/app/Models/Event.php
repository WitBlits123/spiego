<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'timestamp',
        'event_type',
        'hostname',
        'data',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'data' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'hostname', 'hostname');
    }
}
