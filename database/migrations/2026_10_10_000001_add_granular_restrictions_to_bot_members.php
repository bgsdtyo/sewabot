<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_members', function (Blueprint $table) {
            $table->boolean('can_order')->default(true)->after('is_active');
            $table->boolean('can_receive_broadcast')->default(true)->after('can_order');
            $table->string('ban_reason')->nullable()->after('can_receive_broadcast');
        });
    }

    public function down(): void
    {
        Schema::table('bot_members', function (Blueprint $table) {
            $table->dropColumn(['can_order', 'can_receive_broadcast', 'ban_reason']);
        });
    }
};
