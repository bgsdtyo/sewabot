<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_bot_id')->constrained('telegram_bots')->cascadeOnDelete();
            $table->foreignId('bot_member_id')->constrained('bot_members')->cascadeOnDelete();
            $table->foreignId('otp_service_id')->constrained('otp_services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['telegram_bot_id', 'bot_member_id', 'otp_service_id'], 'bot_member_service_alert_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_stock_alerts');
    }
};
