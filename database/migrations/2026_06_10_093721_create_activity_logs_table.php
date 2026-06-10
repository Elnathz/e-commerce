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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('actor_type'); // 'system', 'admin', 'user'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            
            $table->string('subject_type'); // 'order', 'return_request', dll
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable(); // misal: 'ORD-123'
            
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['type', 'created_at'], 'idx_type_created');
            $table->index(['subject_type', 'subject_id'], 'idx_subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
