<?php

use App\Http\Controllers\BotDetailController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OtpWatchController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::get('/cron/check-subscriptions', [CronController::class, 'checkSubscriptions'])->name('cron.check-subscriptions');
Route::get('/cron/check-expired', [CronController::class, 'checkSubscriptions'])->name('cron.check-expired');
Route::get('/cron/check-provider-balance', [CronController::class, 'checkProviderBalance'])->name('cron.check-provider-balance');
Route::get('/cron/reminder-saldo', [CronController::class, 'checkProviderBalance'])->name('cron.reminder-saldo');
Route::get('/cron/sync-stock', [CronController::class, 'syncStock'])->name('cron.sync-stock');
Route::get('/cron/update-stok', [CronController::class, 'syncStock'])->name('cron.update-stok');

Route::post('/telegram/webhook/{telegramBot}', TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::get('/internal/otp-watch/{order}', OtpWatchController::class)
    ->middleware('signed')
    ->name('otp.watch');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/payments', PaymentHistoryController::class)->name('payments.index');

    Route::get('/bots/{telegramBot}', [BotDetailController::class, 'show'])->name('bots.show');
    Route::put('/bots/{telegramBot}/settings', [BotDetailController::class, 'updateSettings'])->name('bots.settings');
    Route::post('/bots/{telegramBot}/sync-services', [BotDetailController::class, 'syncServices'])->name('bots.sync-services');
    Route::post('/bots/{telegramBot}/provider-balance', [BotDetailController::class, 'checkProviderBalance'])->name('bots.provider-balance');
    Route::post('/bots/{telegramBot}/members/{botMember}/topup', [BotDetailController::class, 'topup'])
        ->scopeBindings()
        ->name('bots.members.topup');

    Route::get('/checkout/{product}/select-bot', [CheckoutController::class, 'selectBot'])->name('checkout.select-bot');
    Route::post('/checkout/{product}/duration', [CheckoutController::class, 'saveDuration'])->name('checkout.duration');
    Route::post('/checkout/{product}/start', [CheckoutController::class, 'start'])->name('checkout.start');
    Route::get('/checkout/order/{order}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::get('/checkout/order/{order}/qris.png', [CheckoutController::class, 'qrisImage'])->name('checkout.qris');
    Route::get('/checkout/order/{order}/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::post('/checkout/order/{order}/proof', [CheckoutController::class, 'uploadProof'])->name('checkout.upload-proof');

    Route::get('/subscriptions/{subscription}/renew', [CheckoutController::class, 'renewForm'])->name('subscriptions.renew');
    Route::post('/subscriptions/{subscription}/renew', [CheckoutController::class, 'renew'])->name('subscriptions.renew.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
