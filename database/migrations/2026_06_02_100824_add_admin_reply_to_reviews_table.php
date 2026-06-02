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
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('admin_reply')->nullable()->after('comment');
            $table->timestamp('replied_at')->nullable()->after('admin_reply');

            // Optimizing query performance for large datasets
            $table->index('rating');
            $table->index('is_published');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['rating']);
            $table->dropIndex(['is_published']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['admin_reply', 'replied_at']);
        });
    }
};
