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
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('payment_number', 50)->unique();
            $table->string('receipt_no', 100)->nullable()->unique();

            $table->enum('payment_type', [
                'payment',
                'refund',
                'adjustment',
            ])->default('payment')->index();

            $table->enum('payment_method', [
                'cash',
                'card',
                'bank_transfer',
                'bkash',
                'nagad',
                'rocket',
                'wallet',
            ])->default('cash')->index();

            $table->decimal('amount',12,2)->default(0);
            $table->decimal('discount',12,2)->default(0);
            $table->char('currency',3)->default('BDT');

            $table->timestamp('paid_at')->nullable();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('remarks')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Security
            |--------------------------------------------------------------------------
            */

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexing
            |--------------------------------------------------------------------------
            */

            $table->index(['order_id',]);
            $table->index(['order_id','payment_type',]);
            $table->index(['user_id','created_at',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
