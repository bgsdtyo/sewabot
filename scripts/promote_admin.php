<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'bgsdtyo@gmail.com')->first();
if ($user) {
    $user->is_admin = true;
    $user->save();
    echo "promoted {$user->email}\n";
} else {
    echo "user not found\n";
}

$admins = App\Models\User::where('is_admin', true)->pluck('email');
echo 'admins: '.$admins->implode(', ')."\n";
