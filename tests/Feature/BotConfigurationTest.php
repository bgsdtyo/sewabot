<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BotConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function createBotWithUser(): array
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Bot OTP Reguler',
            'slug' => 'bot-otp-reguler',
            'description' => 'Test bot',
            'price_activation' => 50000,
            'price_renewal' => 30000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $bot = TelegramBot::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => 'Bot Test',
            'username' => 'bottest_bot',
            'token' => '123456789:ABCDEF_TEST_TOKEN',
            'status' => 'active',
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'telegram_bot_id' => $bot->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $bot, $product, $subscription];
    }

    public function test_user_can_view_bot_configuration_page(): void
    {
        [$user, $bot] = $this->createBotWithUser();

        $response = $this->actingAs($user)->get(route('bots.show', $bot));

        $response->assertStatus(200);
        $response->assertSee('Konfigurasi Bot');
        $response->assertSee('Status Operasional Bot');
        $response->assertSee('Token Bot Telegram (BotFather)');
        $response->assertSee('RUNNING');
    }

    public function test_user_can_update_bot_status_to_inactive_and_active(): void
    {
        [$user, $bot] = $this->createBotWithUser();

        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        // 1. Deactivate bot
        $response = $this->actingAs($user)->put(route('bots.settings', $bot), [
            'status' => 'inactive',
            'otp_markup_type' => 'percent',
            'otp_markup_percent' => 50,
        ]);

        $response->assertRedirect(route('bots.show', $bot));
        $this->assertEquals('inactive', $bot->fresh()->status);
        $this->assertFalse($bot->fresh()->isRunning());

        // 2. Reactivate bot
        $response2 = $this->actingAs($user)->put(route('bots.settings', $bot), [
            'status' => 'active',
            'otp_markup_type' => 'percent',
            'otp_markup_percent' => 50,
        ]);

        $response2->assertRedirect(route('bots.show', $bot));
        $this->assertEquals('active', $bot->fresh()->status);
        $this->assertTrue($bot->fresh()->isRunning());
    }

    public function test_user_can_customize_botfather_token(): void
    {
        [$user, $bot] = $this->createBotWithUser();

        Http::fake([
            'https://api.telegram.org/bot987654321:NEW_VALID_TOKEN/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 987654321,
                    'is_bot' => true,
                    'first_name' => 'Custom OTP Bot',
                    'username' => 'custom_otp_bot',
                ],
            ], 200),
            'https://api.telegram.org/bot987654321:NEW_VALID_TOKEN/setWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ], 200),
        ]);

        $response = $this->actingAs($user)->put(route('bots.settings', $bot), [
            'token' => '987654321:NEW_VALID_TOKEN',
            'status' => 'active',
            'otp_markup_type' => 'percent',
            'otp_markup_percent' => 50,
        ]);

        $response->assertRedirect(route('bots.show', $bot));
        $freshBot = $bot->fresh();
        $this->assertEquals('987654321:NEW_VALID_TOKEN', $freshBot->token);
        $this->assertEquals('custom_otp_bot', $freshBot->username);
        $this->assertEquals('active', $freshBot->status);
    }

    public function test_invalid_botfather_token_is_rejected(): void
    {
        [$user, $bot] = $this->createBotWithUser();

        Http::fake([
            'https://api.telegram.org/botinvalid_token/getMe' => Http::response([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Not Found',
            ], 404),
        ]);

        $response = $this->actingAs($user)->put(route('bots.settings', $bot), [
            'token' => 'invalid_token',
            'status' => 'active',
            'otp_markup_type' => 'percent',
            'otp_markup_percent' => 50,
        ]);

        $response->assertSessionHasErrors('token');
        $this->assertNotEquals('invalid_token', $bot->fresh()->token);
    }

    public function test_user_can_clear_botfather_token(): void
    {
        [$user, $bot] = $this->createBotWithUser();

        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $response = $this->actingAs($user)->put(route('bots.settings', $bot), [
            'clear_token' => '1',
            'otp_markup_type' => 'percent',
            'otp_markup_percent' => 50,
        ]);

        $response->assertRedirect(route('bots.show', $bot));
        $freshBot = $bot->fresh();
        $this->assertNull($freshBot->token);
        $this->assertEquals('inactive', $freshBot->status);
    }

    public function test_cannot_activate_bot_if_subscription_expired(): void
    {
        [$user, $bot, $product, $subscription] = $this->createBotWithUser();

        // Expire the subscription
        $subscription->update([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
        $bot->update(['status' => 'inactive']);

        $response = $this->actingAs($user)->put(route('bots.settings', $bot), [
            'status' => 'active',
            'otp_markup_type' => 'percent',
            'otp_markup_percent' => 50,
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('inactive', $bot->fresh()->status);
    }

    public function test_expired_bot_is_not_running_and_webhook_update_is_blocked_and_auto_deactivates(): void
    {
        [$user, $bot, $product, $subscription] = $this->createBotWithUser();

        // Subscription expires in the past
        $subscription->update([
            'status' => 'active',
            'expires_at' => now()->subHours(2),
        ]);

        // Even if status in DB is active, isRunning() must return false
        $this->assertFalse($bot->isRunning());
        $this->assertFalse($bot->hasActiveSubscription());
        $this->assertTrue($bot->isExpired());

        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        // Simulate incoming webhook from Telegram
        $secret = config('services.telegram.webhook_secret', 'test-secret');
        config(['services.telegram.webhook_secret' => $secret]);

        $response = $this->post(route('telegram.webhook', [
            'telegramBot' => $bot,
            'secret' => $secret,
        ]), [
            'message' => [
                'chat' => ['id' => 123456],
                'from' => ['id' => 123456, 'first_name' => 'John'],
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(200);

        // Bot should have been auto-deactivated and subscription marked expired
        $freshBot = $bot->fresh();
        $this->assertEquals('suspended', $freshBot->status);
        $this->assertNull($freshBot->webhook_url);
        $this->assertEquals('expired', $subscription->fresh()->status);

        // Verify deleteWebhook was called to detach Telegram webhook
        Http::assertSent(function ($request) use ($bot) {
            return str_contains($request->url(), "https://api.telegram.org/bot{$bot->token}/deleteWebhook");
        });

        // Verify user was told the bot is inactive/expired
        Http::assertSent(function ($request) use ($bot) {
            return str_contains($request->url(), "https://api.telegram.org/bot{$bot->token}/sendMessage")
                && str_contains($request->data()['text'], 'Masa Sewa Bot Berakhir');
        });
    }

    public function test_expire_subscriptions_command_deactivates_expired_bots(): void
    {
        [$user, $bot, $product, $subscription] = $this->createBotWithUser();

        $subscription->update([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $this->artisan('subscriptions:expire')
            ->assertSuccessful();

        $this->assertEquals('expired', $subscription->fresh()->status);
        $this->assertEquals('suspended', $bot->fresh()->status);
    }

    public function test_cannot_place_otp_order_when_bot_is_expired(): void
    {
        [$user, $bot, $product, $subscription] = $this->createBotWithUser();

        // Expire subscription
        $subscription->update([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $service = \App\Models\OtpService::create([
            'provider' => 'kopken',
            'provider_service_id' => '1',
            'name' => 'WhatsApp',
            'provider_price' => 2000,
            'is_active' => true,
            'is_enabled' => true,
        ]);

        $member = \App\Models\BotMember::create([
            'telegram_bot_id' => $bot->id,
            'telegram_chat_id' => '12345678',
            'balance' => 50000,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\OtpOrderService::class)->requestBulkOtp($bot, $member, $service, 1);
    }

    public function test_dashboard_auto_deactivates_expired_active_bot(): void
    {
        [$user, $bot, $product, $subscription] = $this->createBotWithUser();

        $subscription->update([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        $this->assertEquals('suspended', $bot->fresh()->status);
    }
}
