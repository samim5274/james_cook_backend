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
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // FIX: was constrained() with no delete rule while column is nullable
            // -> would block customer deletion. Now safely nulls out.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reg')->index();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('refund_amount', 12, 2)->default(0);

            $table->string('refund_method');

            // FIX: refund payout tracking — refund_amount alone doesn't tell you
            // whether the money/credit has actually been given to the customer yet.
            $table->enum('refund_status', [
                'pending',
                'processed',
                'failed',
            ])->default('pending')->index();

            // FIX: return approval workflow — previously any return request was
            // treated as final/completed with no review step.
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'completed',
            ])->default('pending')->index();

            $table->enum('reason', [
                'defective',
                'wrong_item',
                'damaged_in_transit',
                'customer_changed_mind',
                'other',
            ])->default('other');

            // FIX: who approved/rejected the return, and when.
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->text('remarks')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_returns');
    }
};
