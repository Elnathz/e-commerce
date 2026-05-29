<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'province',
        'city', 
        'city_id', // Now stores internal cities.id
        'district', 
        'district_id', // Now stores internal districts.id
        'postal_code', 
        'address_detail', 
        'is_default'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cityRelation()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function districtRelation()
    {
        return $this->belongsTo(District::class, 'district_id');
    }
}
