<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Kode voucher unik');
            $table->string('name')->comment('Nama promo untuk display');
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping'])
                ->comment('Tipe diskon');
            $table->decimal('value', 15, 2)->default(0)
                ->comment('Nilai diskon. 0 untuk free_shipping.');
            $table->decimal('min_purchase', 15, 2)->default(0)
                ->comment('Minimum pembelian untuk bisa pakai voucher');
            $table->unsignedInteger('max_usage')->nullable()
                ->comment('Maksimum penggunaan global. NULL = tidak terbatas.');
            $table->unsignedInteger('max_usage_per_user')->nullable()
                ->comment('Maksimum per user. NULL = tidak terbatas.');
            $table->decimal('max_shipping_discount', 15, 2)->nullable()
                ->comment('Khusus free_shipping: batas maksimal diskon ongkir. NULL = gratis penuh.');
            $table->enum('applicable_shipping_type', ['all', 'internal', 'external'])
                ->default('all')
                ->comment('Kurir yang berlaku: all, internal (Semarang), external (RajaOngkir)');
            $table->unsignedInteger('used_count')->default(0)
                ->comment('Cache counter — fast-path validation. Source of truth: promotion_usages.');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['code', 'is_active'], 'idx_code_active');
            $table->index('valid_until', 'idx_valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
