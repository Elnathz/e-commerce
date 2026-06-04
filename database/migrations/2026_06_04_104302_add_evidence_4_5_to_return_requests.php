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
            $table->string('evidence_image_4')->nullable()->after('evidence_image_3');
            $table->string('evidence_image_5')->nullable()->after('evidence_image_4');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn(['evidence_image_4', 'evidence_image_5']);
        });
    }
};
