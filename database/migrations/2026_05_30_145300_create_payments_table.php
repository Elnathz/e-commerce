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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_reference', 100)->nullable()->index(); // transactionId from iPaymu
            $table->string('merchant_ref', 100)->index(); // our order_number
            $table->string('payment_method', 50); // va, qris, cstore
            $table->string('payment_channel', 50); // bca, bni, mandiri, qris, alfamart
            $table->string('payment_name', 100); // "BCA Virtual Account"
            $table->decimal('amount', 15, 2); // amount customer pays (= order total)
            $table->decimal('fee_amount', 15, 2)->default(0); // iPaymu fee (merchant-absorbed)
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending');
            $table->text('pay_code')->nullable(); // VA number, QRIS payload, or payment code
            $table->text('pay_url')->nullable(); // checkout URL (for redirect channels)
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->json('callback_payload')->nullable(); // callback data summary
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
