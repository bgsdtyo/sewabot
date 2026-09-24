<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Deactivate non-Kopi Kenangan / non-WhatsApp services under 'kopken' provider (e.g. Gopay, Shopee)
        DB::table('otp_services')
            ->where('provider', 'kopken')
            ->where(function ($q) {
                $q->whereRaw('UPPER(name) NOT LIKE ?', ['%KOPI%'])
                    ->whereRaw('UPPER(name) NOT LIKE ?', ['%KENANGAN%'])
                    ->whereRaw('UPPER(name) NOT LIKE ?', ['%KOPKEN%'])
                    ->whereRaw('UPPER(name) NOT LIKE ?', ['%WHATSAPP%']);
            })
            ->update([
                'is_active' => false,
                'is_enabled' => false,
            ]);

        // 2. Ensure all Kopi Kenangan services are active and enabled
        DB::table('otp_services')
            ->where('provider', 'kopken')
            ->where(function ($q) {
                $q->whereRaw('UPPER(name) LIKE ?', ['%KOPI%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%KENANGAN%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPKEN%']);
            })
            ->update([
                'is_active' => true,
                'is_enabled' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed
    }
};
