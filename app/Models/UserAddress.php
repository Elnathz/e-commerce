<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone', 'province', 'city', 
        'city_id', 'district', 'postal_code', 'address_detail', 'is_default'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
