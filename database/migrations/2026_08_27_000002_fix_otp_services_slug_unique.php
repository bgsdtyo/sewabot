<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_services', function (Blueprint $table) {
            // Drop global unique on slug so different providers can have 'kopken' or 'whatsapp' slug
            $table->dropUnique('otp_services_slug_unique');
            $table->unique(['provider', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('otp_services', function (Blueprint $table) {
            $table->dropUnique(['provider', 'slug']);
            $table->unique('slug');
        });
    }
};
