<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->string('otp_provider', 32)->default('kopken')->after('token');
            $table->text('otp_wahub_api_key')->nullable()->after('otp_api_key');
        });

        Schema::table('otp_services', function (Blueprint $table) {
            $table->string('provider', 32)->default('kopken')->after('id');
            // Drop old single column unique index if exists
            $table->dropUnique('otp_services_provider_service_id_unique');
            $table->unique(['provider', 'provider_service_id']);
        });

        Schema::table('otp_orders', function (Blueprint $table) {
            $table->string('provider', 32)->default('kopken')->after('otp_service_id');
            $table->string('provider_token')->nullable()->after('provider_order_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('otp_orders', function (Blueprint $table) {
            $table->dropColumn(['provider', 'provider_token']);
        });

        Schema::table('otp_services', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_service_id']);
            $table->dropColumn('provider');
            $table->unique('provider_service_id');
        });

        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn(['otp_provider', 'otp_wahub_api_key']);
        });
    }
};
