<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    const UPDATED_AT = null; // We only need created_at

    protected $fillable = [
        'type',
        'actor_type',
        'actor_id',
        'actor_name',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
