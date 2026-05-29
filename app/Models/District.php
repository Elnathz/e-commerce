<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = [
        'city_id',
        'name',
        'rajaongkir_district_id',
        'komerce_district_id',
        'binderbyte_district_id'
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
