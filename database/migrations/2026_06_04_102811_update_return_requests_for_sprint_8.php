<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->enum('inspection_result', ['passed', 'failed'])->nullable()->after('status');
            // Drop is_partial as it's no longer used, we rely on return_request_items
            $table->dropColumn('is_partial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn('inspection_result');
            $table->boolean('is_partial')->default(false);
        });
    }
};
