<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_bots', 'phone_checker_base_url')) {
                $table->string('phone_checker_base_url')->nullable()->after('phone_checker_api_key')
                    ->comment('Base URL API Phone Checker (contoh: https://checker-api.domain.com)');
            }
            if (! Schema::hasColumn('telegram_bots', 'phone_checker_price')) {
                $table->unsignedInteger('phone_checker_price')->default(100)->after('phone_checker_enabled')
                    ->comment('Harga per 1x nomor fresh yang dibebankan ke saldo member (Rp)');
            }
        });

        Schema::table('otp_auto_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('otp_auto_orders', 'use_checker')) {
                $table->boolean('use_checker')->default(false)->after('target_quantity')
                    ->comment('Apakah auto order ini menggunakan validasi Phone Checker');
            }
            if (! Schema::hasColumn('otp_auto_orders', 'checker_price')) {
                $table->unsignedInteger('checker_price')->default(0)->after('use_checker')
                    ->comment('Biaya per 1x nomor fresh yang dibebankan saat order berhasil');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('telegram_bots', 'phone_checker_base_url')) {
                $columns[] = 'phone_checker_base_url';
            }
            if (Schema::hasColumn('telegram_bots', 'phone_checker_price')) {
                $columns[] = 'phone_checker_price';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('otp_auto_orders', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('otp_auto_orders', 'use_checker')) {
                $columns[] = 'use_checker';
            }
            if (Schema::hasColumn('otp_auto_orders', 'checker_price')) {
                $columns[] = 'checker_price';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
