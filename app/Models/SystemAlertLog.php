<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAlertLog extends Model
{
    protected $fillable = [
        'event',
        'severity',
        'payload',
        'status',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
