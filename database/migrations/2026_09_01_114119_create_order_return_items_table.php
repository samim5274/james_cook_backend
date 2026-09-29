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
        Schema::create('order_return_items', function (Blueprint $table) {
                        $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_discount', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2);
            $table->decimal('vat', 12, 2);
            $table->decimal('refund_amount', 12, 2);

            // FIX: item-level reason (order_returns.reason is the overall/primary
            // reason, but a multi-item return may have different reasons per line).
            $table->enum('reason', [
                'defective',
                'wrong_item',
                'damaged_in_transit',
                'customer_changed_mind',
                'other',
            ])->nullable();

            // FIX: QC outcome per item — decides whether it goes back to sellable
            // stock or into damaged/write-off stock. Without this, inventory
            // can't safely auto-restock returned items.
            $table->enum('condition', [
                'resellable',
                'damaged',
                'defective',
            ])->default('resellable');

            // FIX: whether this item has actually been added back to inventory yet.
            // Prevents double-restocking and lets QC be a separate step from
            // the return request itself.
            $table->boolean('restocked')->default(false);
            $table->timestamp('restocked_at')->nullable();

            $table->timestamps();

            // FIX: prevents the same cart line being returned twice within
            // the same return record (accidental duplicate submission).
            $table->unique(['order_return_id', 'cart_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
    }
};
