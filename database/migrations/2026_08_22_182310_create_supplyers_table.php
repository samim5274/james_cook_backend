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
        Schema::create('supplyers', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name', 150);
            $table->string('company_name', 200)->nullable();
            $table->string('code', 50)->unique()->nullable();

            // Contact Information
            $table->string('phone', 20)->nullable()->unique();
            $table->string('phone_alt', 20)->nullable();
            $table->string('email', 150)->nullable();

            // Address Information
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('Bangladesh');

            // Business Information
            $table->string('trade_license', 100)->nullable();
            $table->string('tax_number', 100)->nullable();

            // Financial Information
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->unsignedInteger('credit_days')->default(0);

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('name');
            $table->index('phone');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplyers');
    }
};
