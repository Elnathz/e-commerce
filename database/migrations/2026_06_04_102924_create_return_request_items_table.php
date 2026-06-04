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
        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->integer('quantity');
            $table->enum('reason_code', ['defective', 'wrong_item', 'missing_part', 'damaged_shipping', 'not_as_described', 'other']);
            $table->text('reason_notes')->nullable();
            $table->enum('condition', ['opened', 'damaged', 'wrong_item', 'defective', 'other']);
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
    }
};
