<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->boolean('force_subscribe_enabled')->default(false)->after('admin_telegram_ids');
            $table->string('force_subscribe_channel', 100)->nullable()->after('force_subscribe_enabled');
            $table->string('force_subscribe_join_url', 255)->nullable()->after('force_subscribe_channel');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn([
                'force_subscribe_enabled',
                'force_subscribe_channel',
                'force_subscribe_join_url',
            ]);
        });
    }
};
