<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDispatch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_key', 'dispatched_at',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
    ];
}
