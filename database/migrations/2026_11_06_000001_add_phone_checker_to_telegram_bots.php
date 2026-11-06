<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom konfigurasi Phone Checker API ke tabel telegram_bots,
 * dan setting global di tabel settings.
 *
 * phone_checker_api_key   : API key khusus Phone Checker per-bot (opsional, override global setting)
 * phone_checker_enabled   : Toggle aktif/nonaktif validasi phone checker untuk bot ini
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->string('phone_checker_api_key')->nullable()->after('otp_api_key')
                ->comment('API Key Kopi Kenangan Phone Checker (override global setting)');
            $table->boolean('phone_checker_enabled')->default(false)->after('phone_checker_api_key')
                ->comment('Aktifkan validasi nomor fresh via Phone Checker API saat auto order');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn(['phone_checker_api_key', 'phone_checker_enabled']);
        });
    }
};

