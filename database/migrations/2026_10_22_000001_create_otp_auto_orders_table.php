<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_auto_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $table->foreignId('bot_member_id')->constrained('bot_members')->cascadeOnDelete();
            $table->foreignId('otp_service_id')->constrained('otp_services')->cascadeOnDelete();
            $table->unsignedInteger('target_quantity')->default(1);
            $table->unsignedInteger('ordered_quantity')->default(0);
            $table->string('status', 30)->default('active'); // active, paused, completed, cancelled
            $table->timestamp('last_order_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['telegram_bot_id', 'bot_member_id', 'otp_service_id'], 'bot_member_service_auto_unique');
            $table->index(['otp_service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_auto_orders');
    }
};
