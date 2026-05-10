<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public $incrementing = false;
    protected $fillable = ['id', 'province_id', 'name', 'type', 'postal_code'];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
