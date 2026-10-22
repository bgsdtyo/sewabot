<?php

namespace App\Http\Controllers;

use App\Models\OtpOrder;
use App\Models\Setting;
use App\Models\TelegramBot;
use App\Services\OtpOrderService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WahubWebhookController extends Controller
{
    public function handle(
        Request $request,
        OtpOrderService $otpOrderService,
        TelegramBotService $telegramBotService,
        ?TelegramBot $telegramBot = null
    ): JsonResponse {
        $rawBody = $request->getContent();
        $signatureHeader = (string) $request->header('X-WAHub-Signature', '');
        $data = $request->json()->all();

        if (empty($data) || ! isset($data['order_id'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $orderId = (string) $data['order_id'];
        $otpCode = (string) ($data['otp'] ?? '');
        $fullSms = (string) ($data['full_sms'] ?? $otpCode);

        // Find order by provider_order_id or provider_token
        $order = OtpOrder::query()
            ->where(function ($q) use ($orderId) {
                $q->where('provider_order_id', $orderId)
                    ->orWhere('provider_token', $orderId);
            })
            ->with(['telegramBot', 'botMember', 'otpService'])
            ->latest('id')
            ->first();

        // If not found by order_id, try phone_number
        if (! $order && filled($data['phone'] ?? null)) {
            $digits = preg_replace('/\D+/', '', (string) $data['phone']);
            $order = OtpOrder::query()
                ->where('status', 'pending')
                ->where(function ($q) use ($digits) {
                    $q->where('phone_number', $digits)
                        ->orWhere('phone_number', '62'.ltrim($digits, '0'))
                        ->orWhere('phone_number', ltrim($digits, '0'));
                })
                ->with(['telegramBot', 'botMember', 'otpService'])
                ->latest('id')
                ->first();
        }

        $bot = $telegramBot ?? $order?->telegramBot;

        // Signature Verification
        $secret = (string) ($bot?->wahub_webhook_secret ?: Setting::get('wahub_webhook_secret', ''));
        if ($secret !== '') {
            $expectedSignature = 'sha256='.hash_hmac('sha256', $rawBody, $secret);
            if (! hash_equals($expectedSignature, $signatureHeader)) {
                Log::warning('WAHub webhook signature mismatch', [
                    'received_sig' => $signatureHeader,
                    'order_id' => $orderId,
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        if (! $order) {
            Log::info("WAHub webhook order #{$orderId} not found in database.");

            return response()->json(['ok' => true, 'message' => 'Order not found, ignored'], 200);
        }

        // Process OTP arrival if order is pending or waiting for code
        if ($order->status === 'pending' || ! filled($order->otp_code)) {
            $otpOrderService->applyProviderStatus($order, [
                'status' => 'completed',
                'otp' => $otpCode,
                'full_text' => $fullSms,
                'raw' => $data,
            ], notify: true);

            Log::info("WAHub webhook successfully processed OTP for order #{$order->id} (prov: #{$orderId})");
        }

        return response()->json([
            'ok' => true,
            'order_id' => $order->id,
            'message' => 'OTP processed successfully',
        ], 200);
    }
}
