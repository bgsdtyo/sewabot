<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_service_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('provider_price')->default(0);
            $table->unsignedInteger('sell_price')->default(0);
            $table->unsignedInteger('duration_seconds')->default(1200);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('bot_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_bot_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_chat_id');
            $table->string('telegram_username')->nullable();
            $table->string('telegram_name')->nullable();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('held_balance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['telegram_bot_id', 'telegram_chat_id']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('telegram_bot_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // topup, hold, charge, refund, adjust
            $table->bigInteger('amount');
            $table->bigInteger('balance_after')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('otp_service_id')->constrained()->cascadeOnDelete();
            $table->uuid('provider_order_id')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->string('phone_number')->nullable();
            $table->string('otp_code')->nullable();
            $table->text('full_text')->nullable();
            $table->unsignedInteger('provider_price')->default(0);
            $table->unsignedInteger('sell_price')->default(0);
            $table->enum('status', ['pending', 'completed', 'cancelled', 'expired'])->default('pending');
            $table->enum('wallet_status', ['held', 'charged', 'refunded', 'none'])->default('none');
            $table->timestamp('provider_expire_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_orders');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('bot_members');
        Schema::dropIfExists('otp_services');
    }
};
