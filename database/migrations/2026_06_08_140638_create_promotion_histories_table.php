<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('action', ['created', 'updated', 'deactivated', 'reactivated']);
            // JSON snapshot: {"before": {...}, "after": {...}} — satu record per edit action
            $table->json('changes')->nullable()
                ->comment('JSON: {"before": {field: value}, "after": {field: value}}');
            $table->timestamp('created_at')->useCurrent();

            $table->index('promotion_id', 'idx_promotion');
            $table->index('admin_id', 'idx_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_histories');
    }
};
