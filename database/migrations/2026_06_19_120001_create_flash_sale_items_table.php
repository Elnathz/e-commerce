<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $t->decimal('sale_price', 15, 2);
            $t->unsignedInteger('quota')->nullable();
            $t->unsignedInteger('sold_count')->default(0);
            $t->timestamps();
            $t->unique(['flash_sale_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
    }
};
