<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    protected $fillable = [
        'placement', 'image_path', 'title', 'subtitle', 'badge_label',
        'cta_label', 'cta_url', 'link_type', 'link_id', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'link_id' => 'integer',
    ];

    /**
     * URL tujuan aktual, dihitung live (bukan disimpan statis) supaya tetap valid
     * walau slug produk berubah, dan form edit bisa pre-fill pilihan sebelumnya.
     */
    public function getDestinationUrlAttribute(): ?string
    {
        if ($this->link_type === 'category' && $this->link_id) {
            return route('search', ['categories' => [$this->link_id]]);
        }
        if ($this->link_type === 'product' && $this->link_id) {
            $slug = Product::where('id', $this->link_id)->value('slug');
            return $slug ? route('products.show', $slug) : null;
        }
        return $this->cta_url;
    }
}
