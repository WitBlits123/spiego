<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedSite extends Model
{
    use HasFactory;

    protected $table = 'blocked_sites';

    protected $fillable = [
        'hostname',
        'domain',
    ];
}
