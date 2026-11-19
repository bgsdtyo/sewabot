<?php

use App\Models\OtpAutoOrder;
use App\Models\OtpService;
use App\Models\OtpStockAlert;
use App\Models\TelegramBot;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $filterServices = OtpService::where(function ($q) {
            $q->where(function ($sub) {
                $sub->whereRaw('UPPER(name) LIKE ?', ['%FILTER%'])
                    ->where(function ($sub2) {
                        $sub2->whereRaw('UPPER(name) LIKE ?', ['%KOPKEN%'])
                            ->orWhereRaw('UPPER(name) LIKE ?', ['%KOPI%'])
                            ->orWhereRaw('UPPER(name) LIKE ?', ['%KENANGAN%']);
                    });
            })->orWhereIn('slug', ['kopken-filter', 'kopi-kenangan-filter', 'filter-kopken']);
        })->get();

        foreach ($filterServices as $svc) {
            // Hapus antrean auto order yang menargetkan service ini
            OtpAutoOrder::where('otp_service_id', $svc->id)->delete();

            // Hapus notifikasi stock alert terkait
            OtpStockAlert::where('otp_service_id', $svc->id)->delete();

            // Reset bot yang diset default ke service ini
            TelegramBot::where('otp_service_id', $svc->id)->update(['otp_service_id' => null]);

            // Hapus service jika tidak ada riwayat order lama, atau nonaktifkan permanen
            if ($svc->otpOrders()->count() === 0) {
                $svc->delete();
            } else {
                $svc->update([
                    'is_active' => false,
                    'is_enabled' => false,
                    'stock' => 0,
                    'name' => $svc->name.' (Nonaktif/Dihapus)',
                ]);
            }
        }

        // Hapus juga auto order yatim tanpa relasi service
        OtpAutoOrder::whereDoesntHave('otpService')->delete();
    }

    public function down(): void
    {
        // No rollback needed for obsolete services cleanup
    }
};
