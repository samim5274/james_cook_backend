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
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 50)->nullable()->index();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('otp');

            // purpose
            $table->enum('type', [
                'withdraw',
                'login',
                'reset_password',
                'transfer'
            ]);

            $table->timestamp('expired_at')->nullable();

            $table->boolean('is_used')->default(false);

            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
