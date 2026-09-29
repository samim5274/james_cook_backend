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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Address Label
            $table->string('label')->default('Home');

            // Receiver Information
            $table->string('customer_name');
            $table->string('phone', 20);

            // Location
            $table->foreignId('division_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('district_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('upazila_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('police_station_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Address
            $table->text('address')->nullable();

            $table->string('postal_code', 20)->nullable();

            // Default Address
            $table->boolean('is_default')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
