<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    protected $fillable = [
        'review_id', 'image_path'
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        return asset('storage/' . $this->image_path);
    }
}
