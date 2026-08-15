<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->string('deposit_bank_name')->nullable()->after('deposit_telegram');
            $table->string('deposit_account_number')->nullable()->after('deposit_bank_name');
            $table->string('deposit_account_name')->nullable()->after('deposit_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn(['deposit_bank_name', 'deposit_account_number', 'deposit_account_name']);
        });
    }
};
