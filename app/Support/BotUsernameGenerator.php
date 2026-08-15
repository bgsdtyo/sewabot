<?php

namespace App\Support;

use App\Models\TelegramBot;
use Illuminate\Support\Str;

class BotUsernameGenerator
{
    public static function fromName(string $name): string
    {
        $base = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if ($base === '') {
            $base = 'bot';
        }

        if (strlen($base) < 3) {
            $base = 'bot_'.$base;
        }

        $base = substr($base, 0, 20);
        $base = rtrim($base, '_');

        do {
            $username = $base.'_'.Str::lower(Str::random(4));
            // Telegram usernames: 5-32 chars
            $username = substr($username, 0, 32);
        } while (TelegramBot::where('username', $username)->exists());

        return $username;
    }

    public static function preview(string $name): string
    {
        $base = Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if ($base === '') {
            $base = 'bot';
        }

        if (strlen($base) < 3) {
            $base = 'bot_'.$base;
        }

        $base = substr(rtrim($base, '_'), 0, 20);

        return $base.'_xxxx';
    }
}
