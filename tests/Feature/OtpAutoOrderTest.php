<?php

namespace Tests\Feature;

use App\Contracts\OtpProviderInterface;
use App\Models\BotMember;
use App\Models\OtpAutoOrder;
use App\Models\OtpOrder;
use App\Models\OtpService;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TelegramBot;
use App\Models\User;
use App\Services\OtpAutoOrderService;
use App\Services\OtpOrderService;
use App\Services\OtpProviderManager;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class OtpAutoOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setupBotAndMember(int $balance = 50000): array
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Bot OTP Reguler',
            'slug' => 'bot-otp-reguler',
            'price_activation' => 50000,
            'price_renewal' => 30000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $bot = TelegramBot::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => 'AutoOrderBot',
            'username' => 'autoorder_bot',
            'token' => '123456789:TEST_AUTO_ORDER_TOKEN',
            'status' => 'active',
            'otp_api_key' => 'kopken_test_api_key',
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'telegram_bot_id' => $bot->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $service = OtpService::create([
            'provider' => 'kopken',
            'provider_service_id' => 10,
            'name' => 'WhatsApp Test',
            'slug' => 'whatsapp-test',
            'provider_price' => 2000,
            'sell_price' => 2000,
            'stock' => 10,
            'is_active' => true,
            'is_enabled' => true,
        ]);

        $member = BotMember::create([
            'telegram_bot_id' => $bot->id,
            'telegram_chat_id' => '99887766',
            'telegram_name' => 'Tester',
            'balance' => $balance,
            'held_balance' => 0,
            'is_active' => true,
            'can_order' => true,
        ]);

        return [$bot, $member, $service, $subscription];
    }

    protected function mockProviderForOrders(int $orderCount = 1): void
    {
        $mockDriver = Mockery::mock(OtpProviderInterface::class);
        $mockDriver->shouldReceive('withApiKey')->andReturnSelf();
        $mockDriver->shouldReceive('forBot')->andReturnSelf();
        $mockDriver->shouldReceive('getServices')->andReturn([
            [
                'id' => 10,
                'name' => 'WhatsApp Test',
                'price' => 2000,
                'stock' => 50,
                'duration_seconds' => 1200,
            ],
        ]);

        $counter = 0;
        $mockDriver->shouldReceive('createOrder')->andReturnUsing(function () use (&$counter) {
            $counter++;
            return [
                'id' => 'prov_order_' . $counter,
                'phone_number' => '62812999' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'expire_at' => time() + 1200,
            ];
        });

        $manager = Mockery::mock(OtpProviderManager::class);
        $manager->shouldReceive('driver')->andReturn($mockDriver);
        $manager->shouldReceive('forBot')->andReturn($mockDriver);
        $manager->shouldReceive('forOrder')->andReturn($mockDriver);

        $this->app->instance(OtpProviderManager::class, $manager);
    }

    public function test_member_can_place_multiple_concurrent_orders_to_hold_for_20_minutes(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 101]], 200),
        ]);

        [$bot, $member, $service] = $this->setupBotAndMember(balance: 50000);
        $this->mockProviderForOrders();

        $otpOrderService = app(OtpOrderService::class);

        // 1. Order batch 1 (3 numbers)
        $batch1 = $otpOrderService->requestBulkOtp($bot, $member, $service, 3);
        $this->assertCount(3, $batch1['orders']);
        $this->assertEquals(3, OtpOrder::where('bot_member_id', $member->id)->where('status', 'pending')->count());

        // 2. Order batch 2 (another 2 numbers) - previously blocked by "Masih Ada Order Aktif"
        // Now it MUST succeed and allow member to hold both batches concurrently for 20 minutes!
        $batch2 = $otpOrderService->requestBulkOtp($bot, $member, $service, 2);
        $this->assertCount(2, $batch2['orders']);
        $this->assertEquals(5, OtpOrder::where('bot_member_id', $member->id)->where('status', 'pending')->count());

        // Check wallet held balance (5 orders * 3000 = 15000 held)
        $member->refresh();
        $this->assertEquals(15000, $member->held_balance);
        $this->assertEquals(35000, $member->availableBalance());
    }

    public function test_member_can_set_and_cancel_auto_order(): void
    {
        [$bot, $member, $service] = $this->setupBotAndMember();

        $autoService = app(OtpAutoOrderService::class);

        // 1. Set auto order for 10 numbers
        $autoOrder = $autoService->setAutoOrder($bot, $member, $service, 10);

        $this->assertInstanceOf(OtpAutoOrder::class, $autoOrder);
        $this->assertEquals(10, $autoOrder->target_quantity);
        $this->assertEquals(0, $autoOrder->ordered_quantity);
        $this->assertEquals('active', $autoOrder->status);
        $this->assertEquals(10, $autoOrder->remainingQuantity());

        // 2. Verify active auto order retrieval
        $retrieved = $autoService->getActiveForMember($bot, $member, $service);
        $this->assertNotNull($retrieved);
        $this->assertEquals($autoOrder->id, $retrieved->id);

        // 3. Cancel auto order
        $cancelled = $autoService->cancelAutoOrder($bot, $member, $service);
        $this->assertTrue($cancelled);
        $this->assertEquals('cancelled', $autoOrder->fresh()->status);
        $this->assertNull($autoService->getActiveForMember($bot, $member, $service));
    }

    public function test_auto_order_automatically_executes_when_stock_becomes_available(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 201]], 200),
        ]);

        [$bot, $member, $service] = $this->setupBotAndMember(balance: 50000);
        $this->mockProviderForOrders();

        $autoService = app(OtpAutoOrderService::class);

        // Set target 4 numbers
        $autoOrder = $autoService->setAutoOrder($bot, $member, $service, 4);

        // Simulate stock sync with stock = 10 available
        $executed = $autoService->processAutoOrdersForService($service, 10);

        $this->assertEquals(4, $executed);
        $autoOrder->refresh();
        $this->assertEquals(4, $autoOrder->ordered_quantity);
        $this->assertEquals('completed', $autoOrder->status);
        $this->assertEquals(0, $autoOrder->remainingQuantity());

        // 4 OtpOrder records created for the member (4 * 3000 = 12000 held)
        $this->assertEquals(4, OtpOrder::where('bot_member_id', $member->id)->where('status', 'pending')->count());
        $this->assertEquals(12000, $member->fresh()->held_balance);
    }

    public function test_auto_order_executes_partially_when_stock_is_less_than_target(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 202]], 200),
        ]);

        [$bot, $member, $service] = $this->setupBotAndMember(balance: 50000);
        $this->mockProviderForOrders();

        $autoService = app(OtpAutoOrderService::class);

        // Set target 7 numbers
        $autoOrder = $autoService->setAutoOrder($bot, $member, $service, 7);

        // Stock arrives: only 3 numbers
        $executed = $autoService->processAutoOrdersForService($service, 3);

        $this->assertEquals(3, $executed);
        $autoOrder->refresh();
        $this->assertEquals(3, $autoOrder->ordered_quantity);
        $this->assertEquals(4, $autoOrder->remainingQuantity());
        $this->assertEquals('active', $autoOrder->status);

        // Later, another 4 stock arrives
        $executed2 = $autoService->processAutoOrdersForService($service, 4);
        $this->assertEquals(4, $executed2);

        $autoOrder->refresh();
        $this->assertEquals(7, $autoOrder->ordered_quantity);
        $this->assertEquals('completed', $autoOrder->status);
    }

    public function test_auto_order_pauses_when_member_balance_is_insufficient(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 203]], 200),
        ]);

        [$bot, $member, $service] = $this->setupBotAndMember(balance: 50000);
        $this->mockProviderForOrders();

        $autoService = app(OtpAutoOrderService::class);
        $autoOrder = $autoService->setAutoOrder($bot, $member, $service, 5);

        // Later member drains balance before stock arrives
        $member->update(['balance' => 0]);

        // Stock arrives: 10
        $executed = $autoService->processAutoOrdersForService($service, 10);

        // Should not execute orders and should pause
        $this->assertEquals(0, $executed);
        $autoOrder->refresh();
        $this->assertEquals(0, $autoOrder->ordered_quantity);
        $this->assertEquals('paused', $autoOrder->status);
        $this->assertStringContainsString('Saldo tidak mencukupi', (string) $autoOrder->last_error);
    }

    public function test_telegram_webhook_auto_order_command_and_callbacks(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 301]], 200),
        ]);

        [$bot, $member, $service] = $this->setupBotAndMember();

        $tgService = app(TelegramBotService::class);

        // 1. Text command /auto
        $tgService->handleUpdate($bot, [
            'update_id' => 1001,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 99887766],
                'from' => ['id' => 99887766, 'first_name' => 'Tester'],
                'text' => '/auto',
            ],
        ]);

        // 2. Callback to set auto order 5x
        $tgService->handleUpdate($bot, [
            'update_id' => 1002,
            'callback_query' => [
                'id' => 'cb_1',
                'chat_instance' => 'instance_1',
                'from' => ['id' => 99887766, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 301,
                    'chat' => ['id' => 99887766],
                ],
                'data' => "auto_order_set:{$service->id}:5",
            ],
        ]);

        $active = app(OtpAutoOrderService::class)->getActiveForMember($bot, $member, $service);
        $this->assertNotNull($active);
        $this->assertEquals(5, $active->target_quantity);

        // 3. Prompt custom target quantity
        $tgService->handleUpdate($bot, [
            'update_id' => 1003,
            'callback_query' => [
                'id' => 'cb_2',
                'chat_instance' => 'instance_2',
                'from' => ['id' => 99887766, 'first_name' => 'Tester'],
                'message' => [
                    'message_id' => 301,
                    'chat' => ['id' => 99887766],
                ],
                'data' => "auto_order_prompt:{$service->id}",
            ],
        ]);

        // 4. Send custom target: 15
        $tgService->handleUpdate($bot, [
            'update_id' => 1004,
            'message' => [
                'message_id' => 2,
                'chat' => ['id' => 99887766],
                'from' => ['id' => 99887766, 'first_name' => 'Tester'],
                'text' => '15',
            ],
        ]);

        $updated = app(OtpAutoOrderService::class)->getActiveForMember($bot, $member, $service);
        $this->assertEquals(15, $updated->target_quantity);
    }
}
