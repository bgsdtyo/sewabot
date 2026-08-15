<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Setting;
use App\Models\TelegramBot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@sewabot.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@sewabot.test'],
            [
                'name' => 'Bagus',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
                'phone' => '081234567890',
            ]
        );

        $product = Product::updateOrCreate(
            ['slug' => 'telegram-otp-bot'],
            [
                'name' => 'Telegram OTP Bot',
                'description' => 'Sewa Telegram Bot untuk menerima OTP WhatsApp. Aktivasi 30 hari.',
                'price_activation' => 150000,
                'price_renewal' => 30000,
                'duration_days' => 30,
                'is_active' => true,
            ]
        );

        $bots = [
            ['name' => 'OTP Bot 01', 'username' => 'sewabot_otp_01'],
            ['name' => 'OTP Bot 02', 'username' => 'sewabot_otp_02'],
            ['name' => 'OTP Bot 03', 'username' => 'sewabot_otp_03'],
        ];

        foreach ($bots as $bot) {
            TelegramBot::updateOrCreate(
                ['username' => $bot['username']],
                [
                    'product_id' => $product->id,
                    'name' => $bot['name'],
                    'status' => 'available',
                    'token' => null,
                    'notes' => 'Isi token bot dari BotFather di admin panel.',
                ]
            );
        }

        Setting::set('merchant_name', 'SewaBot Indonesia');
        Setting::set('payment_instruction', "1. Scan QRIS di atas (nominal sudah terisi otomatis)\n2. Bayar sesuai nominal invoice\n3. Upload bukti pembayaran\n4. Tunggu konfirmasi admin");
        Setting::set('qris_image', null);
        Setting::set('qris_static_payload', null);

        $this->command?->info('Seeded admin: '.$admin->email.' / password');
    }
}
