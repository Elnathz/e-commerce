<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    protected $fillable = [
        'user_id', 'type', 'status', 'filters',
        'estimated_rows', 'file_path', 'file_size_bytes',
        'row_count', 'error_message', 'expires_at',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'filters'      => 'array',
        'expires_at'   => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed'
            && $this->file_path
            && !$this->isExpired();
    }
}
