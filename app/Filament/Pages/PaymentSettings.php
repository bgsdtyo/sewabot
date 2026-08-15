<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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

    public function mount(): void
    {
        $this->form->fill([
            'qris_image' => Setting::get('qris_image'),
            'merchant_name' => Setting::get('merchant_name', 'NAMA BISNIS'),
            'payment_instruction' => Setting::get('payment_instruction', 'Scan QRIS, transfer sesuai nominal, lalu upload bukti pembayaran.'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('QRIS Payment')
                    ->schema([
                        Forms\Components\FileUpload::make('qris_image')
                            ->label('QRIS Image')
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

        Setting::set('qris_image', $state['qris_image'] ?? null);
        Setting::set('merchant_name', $state['merchant_name']);
        Setting::set('payment_instruction', $state['payment_instruction']);

        Notification::make()->title('Settings disimpan')->success()->send();
    }
}
