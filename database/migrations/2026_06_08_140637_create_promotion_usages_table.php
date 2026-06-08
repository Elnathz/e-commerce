<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['reserved', 'confirmed', 'released'])->default('reserved')
                ->comment('reserved=checkout belum bayar, confirmed=sudah bayar, released=order expired/cancelled');
            $table->decimal('discount_applied', 15, 2)->comment('Nominal diskon yang diterapkan');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'user_id'], 'idx_promo_user');
            $table->index(['promotion_id', 'status'], 'idx_promo_status');
            $table->index('order_id', 'idx_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
    }
};
