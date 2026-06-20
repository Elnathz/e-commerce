<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sales');
    }
};
