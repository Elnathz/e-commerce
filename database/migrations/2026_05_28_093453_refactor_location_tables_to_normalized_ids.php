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
        // 1. Drop constraints and old tables
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');

        // 2. Create normalized provinces table
        Schema::create('provinces', function (Blueprint $table) {
            $table->id(); // Internal Auto-Increment ID
            $table->string('name');
            $table->unsignedBigInteger('rajaongkir_province_id')->nullable();
            $table->unsignedBigInteger('komerce_province_id')->nullable();
            $table->unsignedBigInteger('binderbyte_province_id')->nullable();
            $table->timestamps();
        });

        // 3. Create normalized cities table
        Schema::create('cities', function (Blueprint $table) {
            $table->id(); // Internal Auto-Increment ID
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // Kota / Kabupaten
            $table->string('postal_code')->nullable();
            $table->unsignedBigInteger('rajaongkir_city_id')->nullable();
            $table->unsignedBigInteger('komerce_city_id')->nullable();
            $table->unsignedBigInteger('binderbyte_city_id')->nullable();
            $table->timestamps();
        });

        // 4. Create normalized districts table
        Schema::create('districts', function (Blueprint $table) {
            $table->id(); // Internal Auto-Increment ID
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('rajaongkir_district_id')->nullable();
            $table->unsignedBigInteger('komerce_district_id')->nullable();
            $table->unsignedBigInteger('binderbyte_district_id')->nullable();
            $table->timestamps();
        });

        // 5. Refactor user_addresses table to match normalized IDs
        Schema::table('user_addresses', function (Blueprint $table) {
            // Change city_id column from string(20) to unsignedBigInteger to act as foreign key
            // (We drop and recreate the column to avoid database driver conversion issues)
            $table->dropColumn('city_id');
        });

        Schema::table('user_addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('city_id')->nullable()->after('city');
            $table->unsignedBigInteger('district_id')->nullable()->after('district');

            // Set up clean foreign key constraints
            $table->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['district_id']);
            $table->dropColumn(['city_id', 'district_id']);
        });

        Schema::table('user_addresses', function (Blueprint $table) {
            $table->string('city_id', 20)->nullable()->after('city');
        });

        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');

        // Restore old structure
        Schema::create('provinces', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('postal_code')->nullable();
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
};
