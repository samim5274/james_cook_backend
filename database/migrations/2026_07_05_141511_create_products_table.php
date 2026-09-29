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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->onDelete('restrict');
            $table->foreignId('subcategory_id')->nullable()->constrained('product_sub_categories')->onDelete('restrict');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();

            $table->text('summary')->nullable();
            $table->longText('description')->nullable();

            $table->decimal('purchase_price', 12, 2);
            $table->decimal('price', 12, 2);
            $table->decimal('discount', 12, 2)->nullable();

            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock')->default(5);

            $table->boolean('is_active')->default(true);

            // ১ = Pending, ২ = Approved, ৩ = Rejected
            $table->text('admin_remark')->nullable();

            $table->integer('point')->default(0);

            $table->timestamps();

            $table->index(['is_active']);
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
