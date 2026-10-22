<?php

namespace App\Console\Commands;

use App\Models\OtpAutoOrder;
use App\Models\OtpService;
use App\Models\TelegramBot;
use App\Services\OtpAutoOrderService;
use App\Services\OtpOrderService;
use App\Services\OtpProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckAutoOrderCommand extends Command
{
    protected $signature = 'otp:check-auto-order
                            {--run : Trigger stock sync and auto order execution immediately}';

    protected $description = 'Audit and diagnose active auto order queues, provider stocks, and bot statuses';

    public function handle(
        OtpOrderService $otpOrderService,
        OtpAutoOrderService $autoOrderService,
        OtpProviderManager $providerManager
    ): int {
        $this->info('==================================================');
        $this->info('   AUDIT & DIAGNOSA SISTEM AUTO ORDER (SNIPER)    ');
        $this->info('==================================================');

        // 1. Audit Schedule Mutex Lock
        $mutexKey = 'framework/schedule-'.sha1('otp:sync-stock');
        $isLocked = Cache::has($mutexKey);
        $this->line('');
        $this->info('1. Status Scheduler Cron:');
        if ($isLocked) {
            $this->warn('   ⚠️ Mutex Schedule [otp:sync-stock] terdeteksi TERKUNCI di Cache.');
            $this->warn('      Jalankan "php artisan optimize:clear" untuk membuka kunci jika macet.');
        } else {
            $this->info('   ✅ Mutex Schedule [otp:sync-stock] Bersih (Siap berjalan tiap menit).');
        }

        // 2. Audit Active Bots
        $this->line('');
        $this->info('2. Status Bot Telegram:');
        $bots = TelegramBot::all();
        if ($bots->isEmpty()) {
            $this->error('   ❌ Tidak ada bot yang terdaftar di database.');
        } else {
            $botRows = [];
            foreach ($bots as $b) {
                $isRunning = $b->isRunning();
                $hasSub = $b->hasActiveSubscription();
                $provider = $b->activeOtpProvider();
                $botRows[] = [
                    $b->id,
                    $b->name.' (@'.$b->username.')',
                    $provider,
                    $b->status,
                    $hasSub ? 'Aktif' : 'Expired ❌',
                    $isRunning ? '✅ RUNNING' : '⛔ STOPPED',
                ];
            }
            $this->table(['ID', 'Bot Name', 'Provider', 'Status', 'Langganan', 'Engine'], $botRows);
        }

        // 3. Audit Antrean Auto Order di Database
        $this->line('');
        $this->info('3. Antrean Auto Order Member:');
        $autoOrders = OtpAutoOrder::with(['telegramBot', 'botMember', 'otpService'])->latest('id')->get();

        if ($autoOrders->isEmpty()) {
            $this->line('   ⚪ Tidak ada antrean auto order di database saat ini.');
        } else {
            $rows = [];
            foreach ($autoOrders as $ao) {
                $member = $ao->botMember;
                $service = $ao->otpService;
                $bot = $ao->telegramBot;

                $statusLabel = match ($ao->status) {
                    'active' => '🟢 ACTIVE',
                    'paused' => '⏸️ PAUSED',
                    'completed' => '✅ DONE',
                    'cancelled' => '❌ CANCEL',
                    default => $ao->status,
                };

                $balanceStr = $member ? 'Rp '.number_format($member->availableBalance(), 0, ',', '.') : '-';
                $unitPrice = ($bot && $service) ? $bot->sellPriceFor($service->provider_price) : ($service?->sell_price ?? 0);
                $isAffordable = $member && ($member->availableBalance() >= $unitPrice);

                $rows[] = [
                    $ao->id,
                    $member?->telegram_name ?? ('#'.$ao->bot_member_id),
                    $service?->name ?? ('#'.$ao->otp_service_id),
                    $ao->target_quantity,
                    $ao->ordered_quantity,
                    $ao->remainingQuantity(),
                    $balanceStr.' '.($isAffordable ? '✅' : '⚠️ Kurang'),
                    $statusLabel,
                    $ao->last_error ? substr($ao->last_error, 0, 30).'...' : '-',
                ];
            }
            $this->table(['ID', 'Member', 'Layanan', 'Target', 'Didapat', 'Sisa', 'Saldo Member', 'Status', 'Last Error'], $rows);
        }

        // 4. Live Stock Provider
        $this->line('');
        $this->info('4. Cek Live Stok Provider Saat Ini:');
        $services = OtpService::where('is_active', true)->where('is_enabled', true)->get();
        if ($services->isEmpty()) {
            $this->warn('   ⚠️ Tidak ada layanan aktif di tabel otp_services.');
        } else {
            $servRows = [];
            foreach ($services as $s) {
                $servRows[] = [
                    $s->id,
                    $s->provider,
                    $s->name,
                    'Rp '.number_format($s->provider_price, 0, ',', '.'),
                    $s->stock > 0 ? "🟢 {$s->stock} nomor" : '🔴 Habis (0)',
                    $s->updated_at?->diffForHumans() ?? '-',
                ];
            }
            $this->table(['ID', 'Provider', 'Nama Layanan', 'Modal/No', 'Stok di DB', 'Terakhir Update'], $servRows);
        }

        // 5. Opsi Eksekusi Langsung (--run)
        if ($this->option('run')) {
            $this->line('');
            $this->info('5. Memicu Sinkronisasi Stok & Eksekusi Auto Order Sekarang...');
            $this->call('otp:sync-stock');
        } else {
            $this->line('');
            $this->comment('💡 Tambahkan flag --run untuk langsung mengecek stok ke provider dan mengeksekusi antrean sekarang:');
            $this->line('   php artisan otp:check-auto-order --run');
        }

        $this->info('==================================================');

        return self::SUCCESS;
    }
}
