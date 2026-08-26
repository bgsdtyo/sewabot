<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\TelegramBot;
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

        $this->form->fill([
            'active_otp_provider' => Setting::activeOtpProvider(),
            'otp_api_base_url' => $cfgKopken['api_base_url'],
            'otp_api_key' => $cfgKopken['api_key'],
            'wahub_api_base_url' => $cfgWahub['api_base_url'],
            'wahub_api_key' => $cfgWahub['api_key'],
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
                                'kopken' => 'Provider 1: EngineUnicorn (engineunicorn.cloud)',
                                'wahub' => 'Provider 2: WAHub (dehuyzotp.shop)',
                            ])
                            ->required(),
                    ]),

                Forms\Components\Section::make('Provider 1 (EngineUnicorn / engineunicorn.cloud)')
                    ->description('Konfigurasi REST API Provider 1 (EngineUnicorn).')
                    ->schema([
                        Forms\Components\TextInput::make('otp_api_base_url')
                            ->label('API Base URL EngineUnicorn')
                            ->placeholder('https://api.engineunicorn.cloud/v1')
                            ->helperText('Tanpa slash di akhir. Endpoint: /services, /orders')
                            ->required()
                            ->url(),
                        Forms\Components\TextInput::make('otp_api_key')
                            ->label('API Key Sync EngineUnicorn (opsional)')
                            ->password()
                            ->revealable()
                            ->helperText('Fallback key untuk sync layanan dari admin.'),
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
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set('active_otp_provider', $state['active_otp_provider'] ?? 'kopken');
        Setting::set('otp_api_base_url', rtrim($state['otp_api_base_url'], '/'));
        Setting::set('otp_api_key', $state['otp_api_key'] ?? '');
        Setting::set('wahub_api_base_url', rtrim($state['wahub_api_base_url'] ?? 'https://dehuyzotp.shop', '/'));
        Setting::set('wahub_api_key', $state['wahub_api_key'] ?? '');

        Notification::make()->title('Pengaturan Provider OTP berhasil disimpan')->success()->send();
    }

    public function syncKopken(): void
    {
        try {
            $bot = TelegramBot::query()->where('otp_provider', 'kopken')->whereNotNull('otp_api_key')->where('otp_api_key', '!=', '')->latest('id')->first();
            $count = app(OtpOrderService::class)->syncServices(['KOPKEN', 'WHATSAPP'], $bot, 'kopken');
            Notification::make()->title("Sync Provider 1 (EngineUnicorn) OK: {$count} layanan")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync EngineUnicorn gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public function syncWahub(): void
    {
        try {
            $bot = TelegramBot::query()->where('otp_provider', 'wahub')->whereNotNull('otp_wahub_api_key')->where('otp_wahub_api_key', '!=', '')->latest('id')->first();
            $count = app(OtpOrderService::class)->syncServices(['KOPKEN', 'WHATSAPP', 'WA'], $bot, 'wahub');
            Notification::make()->title("Sync Provider 2 (WAHub) OK: {$count} layanan")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync WAHub gagal')->body($e->getMessage())->danger()->send();
        }
    }
}
