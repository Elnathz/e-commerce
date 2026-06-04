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
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            Schema::table('return_requests', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('return_requests', function (Blueprint $table) {
                $table->string('status')->default('submitted');
            });
        } else {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE return_requests MODIFY status ENUM('submitted', 'approved', 'rejected', 'waiting_customer_shipment', 'customer_shipped', 'received', 'inspected', 'refund_processed', 'completed', 'cancelled') DEFAULT 'submitted'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            Schema::table('return_requests', function (Blueprint $table) {
                $table->dropColumn('status');
            });
            Schema::table('return_requests', function (Blueprint $table) {
                $table->string('status')->default('submitted');
            });
        } else {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE return_requests MODIFY status ENUM('submitted', 'approved', 'rejected', 'returned', 'received', 'refund_processed', 'completed', 'cancelled') DEFAULT 'submitted'");
        }
    }
};
