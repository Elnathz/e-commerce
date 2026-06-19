<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $t) {
            $t->decimal('discount_percent', 5, 2)->nullable()->after('base_price');
        });
        Schema::table('product_variants', function (Blueprint $t) {
            $t->decimal('discount_price', 15, 2)->nullable()->after('price');
        });
    }
    public function down(): void {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('discount_percent'));
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropColumn('discount_price'));
    }
};
