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
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->enum('type', [
                'earn',
                'redeem',
                'bonus',
                'refund',
                'adjustment',
                'expire'
            ]);
            $table->integer('points');
            $table->enum('status', ['credit', 'debit']);

            $table->string('source')->nullable();
            // order, refund, admin, promotion, expiry

            $table->foreignId('order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'created_at']);
            $table->unique(
                ['order_id', 'type'],
                'point_transactions_order_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
