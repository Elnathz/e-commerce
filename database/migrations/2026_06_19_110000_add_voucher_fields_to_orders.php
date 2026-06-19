<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('voucher_code')->nullable()->after('discount_amount');
            $t->boolean('discount_on_shipping')->default(false)->after('voucher_code');
        });
    }
    public function down(): void {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['voucher_code', 'discount_on_shipping']));
    }
};
