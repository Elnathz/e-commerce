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
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Enum for status, based on TDD FR019
            $table->enum('status', [
                'submitted', 'approved', 'rejected', 'returned',
                'received', 'refund_processed', 'completed', 'cancelled'
            ])->default('submitted');
            
            $table->text('reason');
            $table->boolean('is_partial')->default(false);
            
            // Evidence images, max 3, first is mandatory
            $table->string('evidence_image_1');
            $table->string('evidence_image_2')->nullable();
            $table->string('evidence_image_3')->nullable();
            
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->string('refund_method')->nullable();
            $table->text('admin_notes')->nullable();
            
            $table->string('return_tracking_number')->nullable();
            $table->string('return_courier')->nullable();
            
            $table->timestamp('return_received_at')->nullable();
            $table->timestamp('refund_processed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Used if customer doesn't return the item in time
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
