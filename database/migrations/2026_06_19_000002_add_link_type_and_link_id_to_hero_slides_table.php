<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            // string (bukan enum/morph) demi konsistensi dgn kolom `placement`.
            // Nilai: 'category' | 'product' | 'custom' | null.
            $table->string('link_type', 20)->nullable()->after('cta_url');
            $table->unsignedBigInteger('link_id')->nullable()->after('link_type');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn(['link_type', 'link_id']);
        });
    }
};
