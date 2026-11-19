<?php

namespace App\Console\Commands;

use App\Models\OtpAutoOrder;
use App\Models\OtpService;
use App\Models\OtpStockAlert;
use App\Models\TelegramBot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RemoveKopkenFilterCommand extends Command
{
    protected $signature = 'otp:remove-kopken-filter';

    protected $description = 'Audit and remove obsolete Kopken Filter services, associated auto orders, and stock alerts';

    public function handle(): int
    {
        $this->info('==================================================');
        $this->info('    PEMBERSIHAN LAYANAN OBSOLETE KOPKEN FILTER    ');
        $this->info('==================================================');

        // 1. Cari semua service Kopken Filter di database
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

        if ($filterServices->isEmpty()) {
            $this->info('✅ Tidak ditemukan layanan "Kopken Filter" di database.');
        } else {
            $this->warn("Ditemukan {$filterServices->count()} layanan Kopken Filter:");

            foreach ($filterServices as $svc) {
                $this->line('');
                $this->line("👉 Layanan #{$svc->id}: <fg=yellow>{$svc->name}</> | Provider: <fg=cyan>{$svc->provider}</> | Stok: {$svc->stock}");

                // a. Hapus antrean auto order yang menargetkan service ini
                $autoOrderCount = OtpAutoOrder::where('otp_service_id', $svc->id)->count();
                if ($autoOrderCount > 0) {
                    OtpAutoOrder::where('otp_service_id', $svc->id)->delete();
                    $this->warn("   🗑️ Menghapus {$autoOrderCount} antrean Auto Order yang terhubung ke layanan ini.");
                }

                // b. Hapus notifikasi stock alert terkait
                $alertCount = OtpStockAlert::where('otp_service_id', $svc->id)->count();
                if ($alertCount > 0) {
                    OtpStockAlert::where('otp_service_id', $svc->id)->delete();
                    $this->warn("   🔔 Menghapus {$alertCount} stock alert terkait.");
                }

                // c. Reset bot yang diset default ke service ini
                $affectedBots = TelegramBot::where('otp_service_id', $svc->id)->get();
                foreach ($affectedBots as $bot) {
                    $bot->update(['otp_service_id' => null]);
                    $this->info("   🔄 Mereset konfigurasi default service pada Bot #{$bot->id} (@{$bot->username}).");
                }

                // d. Hapus service atau nonaktifkan jika ada riwayat order lama
                $orderCount = $svc->otpOrders()->count();
                if ($orderCount === 0) {
                    $svc->delete();
                    $this->info("   ✅ Layanan #{$svc->id} ({$svc->name}) berhasil DIHAPUS PERMANEN dari database.");
                } else {
                    $svc->update([
                        'is_active' => false,
                        'is_enabled' => false,
                        'stock' => 0,
                        'name' => $svc->name.' (Nonaktif/Dihapus)',
                    ]);
                    $this->warn("   ⚠️ Layanan #{$svc->id} memiliki {$orderCount} riwayat order masa lalu.");
                    $this->warn('      Layanan dinonaktifkan permanen (is_active=0, is_enabled=0, stock=0).');
                }

                Cache::forget("auto_order_service_{$svc->id}");
            }
        }

        // 2. Bersihkan antrean auto order yatim (tanpa relasi service yang valid)
        $orphans = OtpAutoOrder::whereDoesntHave('otpService')->delete();
        if ($orphans > 0) {
            $this->warn("🗑️ Membersihkan {$orphans} antrean auto order yatim (tanpa relasi layanan yang valid).");
        }

        $this->line('');
        $this->info('==================================================');
        $this->info('✅ Pembersihan selesai! Layanan dan antrean Kopken Filter telah dibersihkan.');
        $this->info('==================================================');

        return self::SUCCESS;
    }
}
