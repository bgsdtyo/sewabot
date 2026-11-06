<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\TelegramBot;
use App\Services\KopkenPhoneCheckerService;
use App\Services\OtpOrderService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OtpProviderSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.pages.otp-provider-settings';

    protected static ?string $navigationGroup = 'OTP Provider';

    protected static ?string $navigationLabel = 'API Settings';

    protected static ?string $title = 'OTP Provider API';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $cfgKopken = Setting::otpProvider();
        $cfgWahub = Setting::wahubProvider();
        $cfgChecker = Setting::phoneChecker();

        $this->form->fill([
            'active_otp_provider' => Setting::activeOtpProvider(),
            'otp_api_base_url' => $cfgKopken['api_base_url'],
            'otp_api_key' => $cfgKopken['api_key'],
            'wahub_api_base_url' => $cfgWahub['api_base_url'],
            'wahub_api_key' => $cfgWahub['api_key'],
            'phone_checker_api_key' => $cfgChecker['api_key'],
            'phone_checker_base_url' => $cfgChecker['base_url'],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider Aktif Default')
                    ->description('Provider default yang digunakan jika bot belum memilih provider khusus.')
                    ->schema([
                        Forms\Components\Radio::make('active_otp_provider')
                            ->label('Default Active Provider')
                            ->options([
                                'kopken' => 'Provider 1: Ninja OTP (app.ninjatop.cloud)',
                                'wahub' => 'Provider 2: WAHub (dehuyzotp.shop)',
                            ])
                            ->required(),
                    ]),

                Forms\Components\Section::make('Provider 1 (Ninja OTP / app.ninjatop.cloud)')
                    ->description('Konfigurasi REST API Provider 1 (Ninja OTP).')
                    ->schema([
                        Forms\Components\TextInput::make('otp_api_base_url')
                            ->label('API Base URL Ninja OTP')
                            ->placeholder('https://app.ninjatop.cloud/api/public/v1')
                            ->default('https://app.ninjatop.cloud/api/public/v1')
                            ->helperText('Default: https://app.ninjatop.cloud/api/public/v1')
                            ->required()
                            ->url(),
                        Forms\Components\TextInput::make('otp_api_key')
                            ->label('API Key Sync Ninja OTP (opsional)')
                            ->password()
                            ->revealable()
                            ->helperText('Fallback key Ninja OTP (nk_...) untuk sync layanan dari admin.'),
                    ]),

                Forms\Components\Section::make('Provider 2 (WAHub / dehuyzotp.shop)')
                    ->description('Konfigurasi REST API WAHub.')
                    ->schema([
                        Forms\Components\TextInput::make('wahub_api_base_url')
                            ->label('API Base URL WAHub')
                            ->placeholder('https://dehuyzotp.shop')
                            ->default('https://dehuyzotp.shop')
                            ->helperText('Default: https://dehuyzotp.shop')
                            ->required()
                            ->url(),
                        Forms\Components\TextInput::make('wahub_api_key')
                            ->label('API Key Sync WAHub (opsional)')
                            ->password()
                            ->revealable()
                            ->helperText('Fallback key WAHub (wh_live_...) untuk sync layanan dari admin.'),
                    ]),

                Forms\Components\Section::make('Phone Checker — Kopi Kenangan (Global)')
                    ->description('Validasi nomor fresh via Kopi Kenangan Phone Checker API. Nomor yang sudah terdaftar akan dicancel otomatis & saldo member dikembalikan. Aktifkan per-bot di halaman konfigurasi bot.')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('phone_checker_base_url')
                            ->label('Phone Checker Base URL')
                            ->placeholder(KopkenPhoneCheckerService::DEFAULT_BASE_URL)
                            ->default(KopkenPhoneCheckerService::DEFAULT_BASE_URL)
                            ->helperText('URL base server Phone Checker API.')
                            ->url(),
                        Forms\Components\TextInput::make('phone_checker_api_key')
                            ->label('Phone Checker API Key (Global)')
                            ->password()
                            ->revealable()
                            ->helperText('API key global (X-API-Key). Bisa di-override per-bot di halaman konfigurasi bot.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set('active_otp_provider', $state['active_otp_provider'] ?? 'kopken');
        Setting::set('otp_api_base_url', rtrim($state['otp_api_base_url'] ?? 'https://app.ninjatop.cloud/api/public/v1', '/'));
        Setting::set('otp_api_key', $state['otp_api_key'] ?? '');
        Setting::set('wahub_api_base_url', rtrim($state['wahub_api_base_url'] ?? 'https://dehuyzotp.shop', '/'));
        Setting::set('wahub_api_key', $state['wahub_api_key'] ?? '');
        Setting::set('phone_checker_api_key', $state['phone_checker_api_key'] ?? '');
        Setting::set('phone_checker_base_url', rtrim($state['phone_checker_base_url'] ?? KopkenPhoneCheckerService::DEFAULT_BASE_URL, '/'));

        Notification::make()->title('Pengaturan Provider OTP berhasil disimpan')->success()->send();
    }

    public function syncKopken(): void
    {
        try {
            $bot = TelegramBot::query()->where('otp_provider', 'kopken')->whereNotNull('otp_api_key')->where('otp_api_key', '!=', '')->latest('id')->first();
            $count = app(OtpOrderService::class)->syncServices(['KOPKEN', 'WHATSAPP', 'WA', 'KOPI KENANGAN', 'KOPIKENANGAN', 'KOPKEN FILTER', 'KOPI KENANGAN FILTER'], $bot, 'kopken');
            Notification::make()->title("Sync Provider 1 (Ninja OTP) OK: {$count} layanan")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync Ninja OTP gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function syncWahub(): void
    {
        try {
            $bot = TelegramBot::query()->where('otp_provider', 'wahub')->whereNotNull('otp_wahub_api_key')->where('otp_wahub_api_key', '!=', '')->latest('id')->first();
            $count = app(OtpOrderService::class)->syncServices(['KOPKEN', 'WHATSAPP', 'WA', 'KOPI KENANGAN', 'KOPIKENANGAN', 'KOPKEN FILTER', 'KOPI KENANGAN FILTER'], $bot, 'wahub');
            Notification::make()->title("Sync Provider 2 (WAHub) OK: {$count} layanan")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync WAHub gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function testPhoneChecker(): void
    {
        $apiKey = (string) Setting::get('phone_checker_api_key', '');

        if (! filled($apiKey)) {
            Notification::make()
                ->title('Phone Checker API Key belum diisi')
                ->body('Simpan konfigurasi dengan API Key terlebih dahulu.')
                ->warning()
                ->send();

            return;
        }

        try {
            $checker = app(KopkenPhoneCheckerService::class);
            $result  = $checker->getBalance($apiKey);
            $credits = $result['credits'];
            $ms      = $result['ms'];

            Notification::make()
                ->title('Phone Checker Terhubung ✅')
                ->body("Saldo kredit: {$credits} kredit | Latency: {$ms}ms")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Phone Checker Gagal ❌')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
