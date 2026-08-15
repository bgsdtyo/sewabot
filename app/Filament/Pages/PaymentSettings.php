<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\QrisDinamisService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.payment-settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payment';

    protected static ?string $title = 'Payment Settings';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public int|string|null $testAmount = 150000;

    public ?string $testQrDataUri = null;

    public ?string $testDynamicPayload = null;

    public ?string $testMerchant = null;

    public function mount(): void
    {
        $this->form->fill([
            'qris_static_payload' => Setting::get('qris_static_payload'),
            'qris_image' => Setting::get('qris_image'),
            'merchant_name' => Setting::get('merchant_name', 'NAMA BISNIS'),
            'payment_instruction' => Setting::get(
                'payment_instruction',
                "1. Scan QRIS di atas (nominal sudah terisi otomatis)\n2. Bayar sesuai nominal invoice\n3. Upload bukti pembayaran\n4. Tunggu konfirmasi admin"
            ),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('QRIS Dinamis')
                    ->description('Tempel string QRIS statis dari e-wallet/bank. Saat checkout, sistem convert ke QRIS dinamis (nominal otomatis) memakai logic verssache/qris-dinamis.')
                    ->schema([
                        Forms\Components\Textarea::make('qris_static_payload')
                            ->label('QRIS Static String')
                            ->helperText('Ambil dari QRIS statis merchant (biasanya diawali 000201...). Bisa copy dari tools https://github.com/verssache/qris-dinamis')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('qris_image')
                            ->label('QRIS Image (opsional / cadangan)')
                            ->helperText('Opsional. Kalau string dinamis gagal, checkout fallback ke gambar ini.')
                            ->image()
                            ->disk('public')
                            ->directory('qris')
                            ->imageEditor(),
                        Forms\Components\TextInput::make('merchant_name')
                            ->label('Merchant Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('payment_instruction')
                            ->label('Payment Instruction')
                            ->rows(4)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $payload = trim((string) ($state['qris_static_payload'] ?? ''));
        $qris = app(QrisDinamisService::class);

        if (! $qris->isValid($payload)) {
            throw ValidationException::withMessages([
                'data.qris_static_payload' => 'QRIS static tidak valid (CRC / format salah). Paste ulang string lengkap.',
            ]);
        }

        try {
            $qris->convert($payload, 1000);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'data.qris_static_payload' => 'Gagal convert ke dinamis: '.$e->getMessage(),
            ]);
        }

        $merchant = $state['merchant_name'];
        if ($auto = $qris->merchantNameFromPayload($payload)) {
            if (blank($merchant) || $merchant === 'NAMA BISNIS') {
                $merchant = $auto;
            }
        }

        Setting::set('qris_static_payload', $payload);
        Setting::set('qris_image', $state['qris_image'] ?? null);
        Setting::set('merchant_name', $merchant);
        Setting::set('payment_instruction', $state['payment_instruction']);

        Notification::make()
            ->title('QRIS dinamis siap')
            ->body('Checkout akan generate QR dengan nominal invoice otomatis.')
            ->success()
            ->send();
    }

    public function generateTestQris(): void
    {
        $this->validate([
            'testAmount' => ['required', 'integer', 'min:100'],
        ], [], [
            'testAmount' => 'nominal test',
        ]);

        $payload = trim((string) ($this->data['qris_static_payload'] ?? Setting::get('qris_static_payload', '')));
        $qris = app(QrisDinamisService::class);

        if ($payload === '') {
            Notification::make()
                ->title('QRIS static masih kosong')
                ->body('Paste QRIS Static String dulu, lalu generate test.')
                ->danger()
                ->send();

            return;
        }

        if (! $qris->isValid($payload)) {
            Notification::make()
                ->title('QRIS static tidak valid')
                ->body('CRC / format salah. Cek string yang di-paste.')
                ->danger()
                ->send();

            return;
        }

        try {
            $amount = (int) $this->testAmount;
            $dynamic = $qris->convert($payload, $amount);
            $this->testDynamicPayload = $dynamic;
            $this->testQrDataUri = $qris->dataUri($payload, $amount, 360);
            $this->testMerchant = $qris->merchantNameFromPayload($dynamic)
                ?: (string) ($this->data['merchant_name'] ?? Setting::get('merchant_name', '-'));

            Notification::make()
                ->title('QRIS test siap')
                ->body('Nominal Rp'.number_format($amount, 0, ',', '.').' — scan dengan e-wallet untuk cek.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->testQrDataUri = null;
            $this->testDynamicPayload = null;
            $this->testMerchant = null;

            Notification::make()
                ->title('Gagal generate')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
