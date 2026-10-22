<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\TelegramBot;
use App\Services\OtpProviders\WahubProvider;
use Illuminate\Console\Command;

class SetupWahubWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wahub:setup-webhook
                            {--bot= : Telegram Bot ID to configure}
                            {--url= : Custom webhook URL (defaults to APP_URL/api/webhook/wahub)}
                            {--status : Check current webhook status}
                            {--delete : Delete / disable webhook}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup, check, or delete WAHub/Dehuy OTP webhook via Bearer API (Opsi 1)';

    public function handle(WahubProvider $wahubProvider): int
    {
        $botId = $this->option('bot');
        $customUrl = $this->option('url');
        $checkStatus = $this->option('status');
        $doDelete = $this->option('delete');

        $query = TelegramBot::query()
            ->where(function ($q) {
                $q->where('otp_provider', 'wahub')
                    ->orWhereNotNull('otp_wahub_api_key');
            });

        if ($botId) {
            $query->where('id', $botId);
        }

        $bots = $query->get();

        if ($bots->isEmpty()) {
            $this->info('Tidak ada bot dengan provider WAHub/Dehuy yang ditemukan. Memeriksa konfigurasi global...');
            $globalKey = (string) (Setting::wahubProvider()['api_key'] ?? '');
            if ($globalKey === '') {
                $this->error('API Key WAHub/Dehuy belum dikonfigurasi di bot manapun atau di Pengaturan Global.');

                return self::FAILURE;
            }

            return $this->handleGlobal($wahubProvider->withApiKey($globalKey), $customUrl, $checkStatus, $doDelete);
        }

        $successCount = 0;
        foreach ($bots as $bot) {
            $this->line("--------------------------------------------------");
            $this->info("Memproses Bot: {$bot->name} (#{$bot->id}) - @{$bot->username}");

            try {
                $provider = $wahubProvider->forBot($bot);

                if ($checkStatus) {
                    $res = $provider->getWebhookStatus();
                    $this->table(
                        ['Key', 'Value'],
                        collect($res)->map(fn ($val, $key) => [$key, is_scalar($val) ? (string) $val : json_encode($val)])->toArray()
                    );
                    $successCount++;
                    continue;
                }

                if ($doDelete) {
                    $res = $provider->deleteWebhook();
                    $bot->update(['wahub_webhook_secret' => null]);
                    $this->info("✅ Webhook berhasil dihapus dari server WAHub/Dehuy.");
                    $successCount++;
                    continue;
                }

                // Setup webhook via Opsi 1 (PUT /api/webhook with Bearer token)
                $appUrl = rtrim((string) ($customUrl ?: (config('app.url') ?: env('APP_URL', 'https://sewabot.com'))), '/');
                $targetUrl = $customUrl ?: ($appUrl.'/api/webhook/wahub/'.$bot->id);

                $this->line("Mendaftarkan webhook ke URL: <comment>{$targetUrl}</comment>");
                $result = $provider->registerWebhook($targetUrl);

                $secret = (string) ($result['secret'] ?? '');
                if ($secret !== '') {
                    $bot->update(['wahub_webhook_secret' => $secret]);
                    Setting::set('wahub_webhook_secret', $secret);

                    $this->info("✅ Webhook berhasil dipasang via Bearer API (Opsi 1)!");
                    $this->line("Signing Secret: <comment>{$secret}</comment> (Tersimpan di DB bot #{$bot->id})");
                    $successCount++;
                } else {
                    $this->warn("Webhook terdaftar tetapi tidak ada secret yang dikembalikan: ".json_encode($result));
                }
            } catch (\Throwable $e) {
                $this->error("Gagal memproses bot #{$bot->id}: ".$e->getMessage());
            }
        }

        $this->line("--------------------------------------------------");
        $this->info("Selesai. Total sukses: {$successCount} dari {$bots->count()} bot.");

        return self::SUCCESS;
    }

    protected function handleGlobal(WahubProvider $provider, ?string $customUrl, bool $checkStatus, bool $doDelete): int
    {
        try {
            if ($checkStatus) {
                $res = $provider->getWebhookStatus();
                $this->table(
                    ['Key', 'Value'],
                    collect($res)->map(fn ($val, $key) => [$key, is_scalar($val) ? (string) $val : json_encode($val)])->toArray()
                );

                return self::SUCCESS;
            }

            if ($doDelete) {
                $provider->deleteWebhook();
                Setting::set('wahub_webhook_secret', null);
                $this->info("✅ Webhook global berhasil dihapus.");

                return self::SUCCESS;
            }

            $appUrl = rtrim((string) ($customUrl ?: (config('app.url') ?: env('APP_URL', 'https://sewabot.com'))), '/');
            $targetUrl = $customUrl ?: ($appUrl.'/api/webhook/wahub');

            $this->line("Mendaftarkan webhook global ke URL: <comment>{$targetUrl}</comment>");
            $result = $provider->registerWebhook($targetUrl);

            $secret = (string) ($result['secret'] ?? '');
            if ($secret !== '') {
                Setting::set('wahub_webhook_secret', $secret);
                $this->info("✅ Webhook global berhasil dipasang via Bearer API (Opsi 1)!");
                $this->line("Signing Secret: <comment>{$secret}</comment>");

                return self::SUCCESS;
            }

            $this->warn("Respon: ".json_encode($result));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Gagal memproses webhook global: ".$e->getMessage());

            return self::FAILURE;
        }
    }
}
