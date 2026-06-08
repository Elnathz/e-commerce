<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            // Atomic gatekeeper — UNIQUE constraint prevents duplicate dispatches
            // Format: {event_type}_{entity_type}_{entity_id}_{user_id}
            // Example: order_paid_order_123_user_45
            $table->string('event_key', 255)->unique()
                ->comment('Unique key per event. Insert fails gracefully on duplicate.');
            $table->timestamp('dispatched_at')->useCurrent();

            $table->index('dispatched_at', 'idx_dispatched_at'); // for pruning
        }) ;
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
