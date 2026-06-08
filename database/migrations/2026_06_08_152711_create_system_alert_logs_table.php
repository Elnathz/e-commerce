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
        Schema::create('system_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('severity', ['info', 'warning', 'critical', 'emergency'])->default('warning');
            $table->enum('status', ['open', 'acknowledged', 'resolved'])->default('open');
            $table->string('event', 255)->index();
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alert_logs');
    }
};
