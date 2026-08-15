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
        $cfg = Setting::otpProvider();
        $this->form->fill([
            'otp_api_base_url' => $cfg['api_base_url'],
            'otp_api_key' => $cfg['api_key'],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Provider Global')
                    ->description('Base URL dipakai semua bot. API Key & markup diisi owner di Kelola Bot.')
                    ->schema([
                        Forms\Components\TextInput::make('otp_api_base_url')
                            ->label('API Base URL')
                            ->placeholder('https://api.example.com/v1')
                            ->helperText('Tanpa slash di akhir. Endpoint: /services, /orders')
                            ->required()
                            ->url(),
                        Forms\Components\TextInput::make('otp_api_key')
                            ->label('API Key sync (opsional)')
                            ->password()
                            ->revealable()
                            ->helperText('Opsional — hanya fallback sync layanan. Order OTP & markup = per bot.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        Setting::set('otp_api_base_url', rtrim($state['otp_api_base_url'], '/'));
        Setting::set('otp_api_key', $state['otp_api_key'] ?? '');

        Notification::make()->title('API settings disimpan')->success()->send();
    }

    public function syncKopken(): void
    {
        try {
            $bot = TelegramBot::query()->whereNotNull('otp_api_key')->where('otp_api_key', '!=', '')->latest('id')->first();
            $count = app(OtpOrderService::class)->syncServices(['KOPKEN'], $bot);
            Notification::make()->title("Sync OK: {$count} layanan (KOPKEN)")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Sync gagal')->body($e->getMessage())->danger()->send();
        }
    }
}
