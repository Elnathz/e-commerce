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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('processing_at')->nullable()->after('paid_at');
        });

        // Safe Backfill paid_at (jika ada data lama yang belum terisi)
        \App\Helpers\MigrationHelpers::backfillOrderPaidAt();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('processing_at');
        });
    }
};
