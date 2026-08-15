<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->unsignedBigInteger('min_provider_balance_alert')->nullable()->after('provider_balance_checked_at');
            $table->timestamp('provider_balance_last_alerted_at')->nullable()->after('min_provider_balance_alert');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn(['min_provider_balance_alert', 'provider_balance_last_alerted_at']);
        });
    }
};
