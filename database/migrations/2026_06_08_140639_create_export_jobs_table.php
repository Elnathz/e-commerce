<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->default('orders')
                ->comment('Type of export: orders, customers, etc.');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');
            $table->json('filters')->nullable()
                ->comment('Filter parameters used for this export');
            $table->unsignedInteger('estimated_rows')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable()
                ->comment('File and record deleted after this timestamp');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index('expires_at', 'idx_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
