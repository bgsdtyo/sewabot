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
        // Deactivate all Shopee, Gopay, and non-Kopken services
        DB::table('otp_services')
            ->where(function ($q) {
                $q->whereRaw('UPPER(name) LIKE ?', ['%SHOPEE%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%GOPAY%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%GRAB%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%TIKTOK%']);
            })
            ->update([
                'is_active' => false,
                'is_enabled' => false,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
