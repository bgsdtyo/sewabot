<?php

namespace Tests\Feature;

use App\Models\BotMember;
use App\Models\OtpOrder;
use App\Models\OtpService;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Models\User;
use App\Services\OtpOrderService;
use App\Services\OtpProviders\WahubProvider;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WahubIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setupBotAndMember(): array
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Bot OTP Wahub',
            'slug' => 'bot-otp-wahub',
            'price_activation' => 50000,
            'price_renewal' => 30000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $bot = TelegramBot::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => 'WahubBot',
            'username' => 'wahub_bot',
            'token' => '123456789:TEST_WAHUB_TOKEN',
            'status' => 'active',
            'otp_provider' => 'wahub',
            'otp_wahub_api_key' => 'wh_live_test_api_key_123',
            'wahub_webhook_secret' => 'wh_secret_abcdef1234567890',
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'telegram_bot_id' => $bot->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $service = OtpService::create([
            'provider' => 'wahub',
            'provider_service_id' => 1,
            'name' => 'WhatsApp Wahub',
            'slug' => 'whatsapp-wahub',
            'provider_price' => 2500,
            'sell_price' => 4500,
            'stock' => 15,
            'is_active' => true,
            'is_enabled' => true,
        ]);

        $member = BotMember::create([
            'telegram_bot_id' => $bot->id,
            'telegram_chat_id' => '99887766',
            'telegram_name' => 'Tester Wahub',
            'balance' => 50000,
            'held_balance' => 0,
            'is_active' => true,
            'can_order' => true,
        ]);

        return [$bot, $member, $service];
    }

    public function test_wahub_cancel_order_uses_post_action_cancel(): void
    {
        Http::fake([
            'https://dehuyzotp.shop/api/order/105' => Http::response([
                'ok' => true,
                'action' => 'cancel',
                'order_id' => 105,
                'refunded' => 2500,
            ], 200),
        ]);

        [$bot] = $this->setupBotAndMember();
        $provider = app(WahubProvider::class)->forBot($bot);

        $res = $provider->cancelOrder('105', 'wh_rnt_token_105');

        $this->assertTrue($res['ok']);
        $this->assertEquals(2500, $res['refunded']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://dehuyzotp.shop/api/order/105'
                && $request->method() === 'POST'
                && $request['action'] === 'cancel';
        });
    }

    public function test_wahub_rebuy_order_sends_post_action_rebuy(): void
    {
        Http::fake([
            'https://dehuyzotp.shop/api/order/105' => Http::response([
                'order_id' => 1024,
                'token' => 'wh_rnt_rebuy_token',
                'phone' => '85196495750',
                'service_id' => 1,
                'service' => 'WhatsApp Wahub',
                'price' => 2500,
                'rebuy_of' => 105,
                'expires_at' => 1735902400,
                'allow_retry' => true,
            ], 200),
        ]);

        [$bot] = $this->setupBotAndMember();
        $provider = app(WahubProvider::class)->forBot($bot);

        $res = $provider->rebuyOrder('105', 'wh_rnt_token_105');

        $this->assertEquals('1024', $res['id']);
        $this->assertEquals('wh_rnt_rebuy_token', $res['token']);
        $this->assertEquals('6285196495750', $res['phone_number']);
        $this->assertEquals('105', $res['rebuy_of']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://dehuyzotp.shop/api/order/105'
                && $request->method() === 'POST'
                && $request['action'] === 'rebuy';
        });
    }

    public function test_wahub_register_webhook_via_bearer_api_option_1(): void
    {
        Http::fake([
            'https://dehuyzotp.shop/api/webhook' => Http::response([
                'secret' => 'a5f2c848hexcharssecretkey1234567890abcdef',
            ], 200),
        ]);

        [$bot] = $this->setupBotAndMember();
        $provider = app(WahubProvider::class)->forBot($bot);

        $res = $provider->registerWebhook('https://example.com/api/webhook/wahub');

        $this->assertTrue($res['ok']);
        $this->assertEquals('a5f2c848hexcharssecretkey1234567890abcdef', $res['secret']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://dehuyzotp.shop/api/webhook'
                && $request->method() === 'PUT'
                && $request['url'] === 'https://example.com/api/webhook/wahub';
        });
    }

    public function test_wahub_webhook_receives_otp_and_completes_order(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 888]], 200),
        ]);

        [$bot, $member, $service] = $this->setupBotAndMember();

        $order = OtpOrder::create([
            'idempotency_key' => 'idemp_wahub_webhook_test',
            'telegram_bot_id' => $bot->id,
            'bot_member_id' => $member->id,
            'otp_service_id' => $service->id,
            'provider' => 'wahub',
            'provider_order_id' => '12345',
            'provider_token' => 'wh_rnt_webhook_test',
            'phone_number' => '6281234567890',
            'status' => 'pending',
            'wallet_status' => 'held',
            'provider_price' => 2500,
            'sell_price' => 4500,
            'provider_expire_at' => now()->addMinutes(15),
        ]);
        $member->update(['held_balance' => 4500]);

        $payload = [
            'order_id' => 12345,
            'service_id' => 1,
            'service_name' => 'WhatsApp Wahub',
            'phone' => '6281234567890',
            'otp' => '998811',
            'full_sms' => 'Kode verifikasi WhatsApp kamu: 998811',
            'price' => 2500,
            'timestamp' => time(),
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, $bot->wahub_webhook_secret);

        $response = $this->call(
            'POST',
            '/api/webhook/wahub/' . $bot->id,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WAHUB_SIGNATURE' => $signature,
            ],
            $rawBody
        );

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('998811', $order->otp_code);
        $this->assertEquals('Kode verifikasi WhatsApp kamu: 998811', $order->full_text);
        $this->assertEquals('charged', $order->wallet_status);
    }

    public function test_wahub_webhook_rejects_invalid_signature(): void
    {
        [$bot, $member, $service] = $this->setupBotAndMember();

        $payload = [
            'order_id' => 12345,
            'otp' => '998811',
        ];

        $rawBody = json_encode($payload);
        $invalidSig = 'sha256=invalid_signature_hex_value';

        $response = $this->call(
            'POST',
            '/api/webhook/wahub/' . $bot->id,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_WAHUB_SIGNATURE' => $invalidSig,
            ],
            $rawBody
        );

        $response->assertStatus(401);
    }

    public function test_member_can_rebuy_completed_order(): void
    {
        Http::fake([
            'https://dehuyzotp.shop/api/order/501' => Http::response([
                'order_id' => 502,
                'token' => 'wh_rnt_rebuy_502',
                'phone' => '85196495750',
                'service_id' => 1,
                'service' => 'WhatsApp Wahub',
                'price' => 2500,
                'rebuy_of' => 501,
                'expires_at' => time() + 1200,
                'allow_retry' => true,
            ], 200),
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        [$bot, $member, $service] = $this->setupBotAndMember();

        // 1. Create a successful old order
        $oldOrder = OtpOrder::create([
            'idempotency_key' => 'idemp_old_order',
            'telegram_bot_id' => $bot->id,
            'bot_member_id' => $member->id,
            'otp_service_id' => $service->id,
            'provider' => 'wahub',
            'provider_order_id' => '501',
            'provider_token' => 'wh_rnt_token_501',
            'phone_number' => '6285196495750',
            'otp_code' => '123456',
            'status' => 'completed',
            'wallet_status' => 'charged',
            'provider_price' => 2500,
            'sell_price' => 4500,
            'completed_at' => now()->subMinutes(10),
        ]);

        // 2. Member triggers rebuy via callback
        $tgService = app(TelegramBotService::class);
        $tgService->handleUpdate($bot, [
            'update_id' => 9001,
            'callback_query' => [
                'id' => 'cb_rebuy_1',
                'chat_instance' => 'inst_1',
                'from' => ['id' => 99887766, 'first_name' => 'Tester Wahub'],
                'message' => [
                    'message_id' => 401,
                    'chat' => ['id' => 99887766],
                ],
                'data' => "otp_rebuy:{$oldOrder->id}",
            ],
        ]);

        // 3. Verify new order created
        $newOrder = OtpOrder::where('provider_order_id', '502')->first();
        $this->assertNotNull($newOrder);
        $this->assertEquals('pending', $newOrder->status);
        $this->assertEquals('wh_rnt_rebuy_502', $newOrder->provider_token);
        $this->assertEquals('6285196495750', $newOrder->phone_number);
        $this->assertEquals('held', $newOrder->wallet_status);
    }
}
