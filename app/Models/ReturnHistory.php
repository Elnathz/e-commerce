<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_request_id',
        'from_status',
        'to_status',
        'actor_id',
        'actor_type',
        'notes',
    ];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function actor()
    {
        return $this->morphTo();
    }
}
