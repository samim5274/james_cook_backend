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
        Schema::create('upazilas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')
                ->constrained('divisions')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('district_id')
                ->constrained('districts')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('upazila_id')
                ->nullable()
                ->constrained('upazilas')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('name'); // thana / police station name
            $table->string('bn_name')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->unique(['district_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upazilas');
    }
};
