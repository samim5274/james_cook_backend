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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('reg')->unique();
            $table->string('order_number')->unique();
            $table->string('slug')->unique();
            $table->date('order_date')->index();

            $table->foreignId('user_id')->constrained()->restrictOnDelete()->index();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_phone', 20)->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('due_amount', 14, 2)->default(0);
            $table->decimal('payable_amount', 14, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);

            $table->enum('payment_method', [
                'cash',
                'card',
                'bank_transfer',
                'bkash',
                'nagad',
                'rocket',
                'wallet',
            ])->default('cash')->index();

            $table->char('currency',3)->default('BDT');
            $table->integer('point')->default(0);

            $table->enum('status', [
                'pending',
                'unpaid',
                'partially_paid',
                'paid',
                'completed',
                'returned',
            ])->default('pending')->index();


            $table->timestamp('completed_at')->nullable();
            $table->timestamp('returned_at')->nullable();

            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('paid_at')->nullable()->index();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id','status',]);
            $table->index(['order_date','status',]);
            $table->index(['customer_id','order_date',]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
