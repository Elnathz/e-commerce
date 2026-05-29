<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $fillable = [
        'name',
        'rajaongkir_province_id',
        'komerce_province_id',
        'binderbyte_province_id'
    ];

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
