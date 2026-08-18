<?php

namespace App\Http\Controllers;

use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __construct(protected TelegramBotService $telegramBotService) {}

    public function __invoke(Request $request, TelegramBot $telegramBot): Response
    {
        ignore_user_abort(true);
        @set_time_limit(180);

        $secret = (string) $request->query('secret');
        $expected = (string) config('services.telegram.webhook_secret');

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(403);
        }

        $this->telegramBotService->handleUpdate($telegramBot, $request->all());

        return response('OK', 200);
    }
}
