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
        Schema::create('return_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->onDelete('cascade');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type')->nullable(); // User or Admin
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_histories');
    }
};
