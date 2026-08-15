<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = 0;
foreach (App\Models\TelegramBot::all() as $bot) {
    $bot->syncWebhookUrl();
    $count++;
    echo "#{$bot->id} {$bot->name} => {$bot->fresh()->webhook_url}\n";
}
echo "synced: {$count}\n";
