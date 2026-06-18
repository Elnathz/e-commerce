<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            // string (bukan enum) demi kompat SQLite test. Nilai: 'hero_main' | 'hero_side'.
            $table->string('placement', 20)->default('hero_main')->after('id');
            $table->index(['placement', 'is_active', 'sort_order'], 'idx_placement_active_sort');
        });
    }
    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropIndex('idx_placement_active_sort');
            $table->dropColumn('placement');
        });
    }
};
