<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->foreignId('otp_service_id')
                ->nullable()
                ->after('otp_provider')
                ->constrained('otp_services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('otp_service_id');
        });
    }
};
