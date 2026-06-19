<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('promotions', fn (Blueprint $t) =>
            $t->boolean('applies_to_flash_sale')->default(false)->after('is_active'));
    }
    public function down(): void {
        Schema::table('promotions', fn (Blueprint $t) => $t->dropColumn('applies_to_flash_sale'));
    }
};
