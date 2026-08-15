<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronController extends Controller
{
    /**
     * Check expired subscriptions and suspend associated telegram bots.
     */
    public function checkSubscriptions(Request $request, TelegramBotService $telegramBotService): JsonResponse
    {
        $expiredSubscriptions = Subscription::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('telegramBot')
            ->get();

        $expiredSubCount = 0;
        $suspendedBotCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
            $expiredSubCount++;

            if ($subscription->telegramBot) {
                // Check if this bot has any other active, valid subscription
                $hasOtherActive = $subscription->telegramBot->subscriptions()
                    ->where('id', '!=', $subscription->id)
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->exists();

                if (! $hasOtherActive) {
                    $telegramBotService->deactivate($subscription->telegramBot);
                    $suspendedBotCount++;
                }
            }
        }

        // Also ensure any bot currently marked 'active' without ANY active subscription is suspended
        $botsWithoutActiveSub = TelegramBot::query()
            ->where('status', 'active')
            ->whereDoesntHave('subscriptions', function ($q) {
                $q->where('status', 'active')
                    ->where('expires_at', '>', now());
            })
            ->get();

        foreach ($botsWithoutActiveSub as $bot) {
            $telegramBotService->deactivate($bot);
            $suspendedBotCount++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengecekan masa aktif subscription berhasil dijalankan.',
            'expired_subscriptions' => $expiredSubCount,
            'suspended_bots' => $suspendedBotCount,
            'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
        ]);
    }

    /**
     * Check provider balance for active bots and send low-balance alert to Telegram admins.
     */
    public function checkProviderBalance(
        Request $request,
        TelegramBotService $telegramBotService,
        \App\Services\OtpProviderClient $client
    ): JsonResponse {
        $bots = TelegramBot::query()
            ->where('status', 'active')
            ->whereNotNull('otp_api_key')
            ->whereNotNull('token')
            ->whereNotNull('min_provider_balance_alert')
            ->where('min_provider_balance_alert', '>', 0)
            ->get();

        $checked = 0;
        $alertsSent = 0;
        $results = [];

        foreach ($bots as $bot) {
            $res = $telegramBotService->checkAndAlertProviderBalance($bot, $client);
            $checked++;
            if (! empty($res['alert_sent'])) {
                $alertsSent++;
            }
            $results[] = $res;
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengecekan saldo pusat & reminder admin selesai dijalankan.',
            'bots_checked' => $checked,
            'alerts_sent' => $alertsSent,
            'results' => $results,
            'timestamp' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString(),
        ]);
    }
}
