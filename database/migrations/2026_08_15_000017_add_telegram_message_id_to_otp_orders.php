<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_message_id')->nullable()->after('raw_payload');
        });
    }

    public function down(): void
    {
        Schema::table('otp_orders', function (Blueprint $table) {
            $table->dropColumn('telegram_message_id');
        });
    }
};
