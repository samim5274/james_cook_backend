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
        Schema::create('purchase_carts', function (Blueprint $table) {
            $table->id();

            $table->string('reg', 100)->index();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->decimal('price', 14, 2)->default(0.00)->comment('Purchase price per unit');

            $table->decimal('total_amount', 14, 2)->default(0.00)->comment('Purchase total amount');

            $table->decimal('sale_price', 14, 2)->default(0.00)->comment('Expected sale price per unit');

            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'reg', 'product_id'],
                'purchase_cart_user_reg_product_unique'
            );

            $table->index(['user_id', 'reg']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_carts');
    }
};
