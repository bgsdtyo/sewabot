<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->string('deposit_whatsapp')->nullable()->after('otp_markup_percent');
            $table->string('deposit_telegram')->nullable()->after('deposit_whatsapp');
            $table->text('deposit_note')->nullable()->after('deposit_telegram');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn(['deposit_whatsapp', 'deposit_telegram', 'deposit_note']);
        });
    }
};
