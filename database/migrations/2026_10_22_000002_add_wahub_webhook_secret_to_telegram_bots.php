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
        Schema::table('telegram_bots', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_bots', 'wahub_webhook_secret')) {
                $table->string('wahub_webhook_secret', 255)->nullable()->after('otp_wahub_api_key');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_bots', 'wahub_webhook_secret')) {
                $table->dropColumn('wahub_webhook_secret');
            }
        });
    }
};
