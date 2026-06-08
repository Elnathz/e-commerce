<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'promotion_id', 'admin_id', 'action', 'changes',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
