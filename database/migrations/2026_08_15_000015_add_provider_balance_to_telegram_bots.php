<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_balance')->nullable()->after('otp_api_key');
            $table->string('provider_balance_currency', 8)->nullable()->after('provider_balance');
            $table->timestamp('provider_balance_checked_at')->nullable()->after('provider_balance_currency');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn(['provider_balance', 'provider_balance_currency', 'provider_balance_checked_at']);
        });
    }
};
