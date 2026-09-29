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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // Order Information
            $table->string('reg')->index();
            $table->string('order_number')->unique();
            $table->string('slug')->unique();
            $table->date('order_date')->index();

            // User
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Supplier
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('supplyers')
                ->nullOnDelete();

            $table->string('supplier_name', 255)->nullable();
            $table->string('supplier_phone', 20)->nullable();

            // Amount
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('due_amount', 14, 2)->default(0);
            $table->decimal('payable_amount', 14, 2)->default(0);

            // Payment
            $table->enum('payment_method', [
                'cash',
                'card',
                'bank_transfer',
                'bkash',
                'nagad',
                'rocket',
                'wallet',
            ])->default('cash')->index();

            $table->char('currency', 3)->default('BDT');

            // Status
            $table->enum('status', [
                'pending',
                'unpaid',
                'partially_paid',
                'paid',
                'completed',
                'returned',
            ])->default('pending')->index();

            // Completion / Return
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('returned_at')->nullable();

            $table->foreignId('returned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Additional Information
            $table->text('remarks')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('paid_at')->nullable()->index();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['order_date', 'status']);
            $table->index(['supplier_id', 'order_date']);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
