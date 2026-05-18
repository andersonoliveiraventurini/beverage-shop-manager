<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Store;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DepotConfig extends Page
{
    protected string $view = 'filament.pages.depot-config';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Depósito';

    protected static ?string $title = 'Configurações do depósito';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 98;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(Store::current()->only([
            'name', 'street', 'number', 'complement', 'district', 'city', 'state',
            'zip', 'lat', 'lng', 'phone_landline', 'phone_mobile', 'whatsapp', 'hours',
        ]));
    }

    protected function getForms(): array
    {
        return ['form'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(3)
                    ->components([
                        TextInput::make('name')
                            ->label('Nome do depósito')
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('hours')
                            ->label('Horário de funcionamento')
                            ->placeholder('Seg–Sáb 08–18h · Dom/feriado 08–12h'),
                    ]),

                Section::make('Endereço')
                    ->description('Origem usada para o cálculo de distância dos clientes.')
                    ->columns(4)
                    ->components([
                        TextInput::make('street')->label('Rua / Avenida')->required()->columnSpan(2),
                        TextInput::make('number')->label('Número'),
                        TextInput::make('complement')->label('Complemento'),
                        TextInput::make('district')->label('Bairro')->required(),
                        TextInput::make('city')->label('Cidade')->required(),
                        TextInput::make('state')->label('UF')->maxLength(2)->required(),
                        TextInput::make('zip')->label('CEP'),
                        TextInput::make('lat')->label('Latitude')->numeric()->step('0.0000001'),
                        TextInput::make('lng')->label('Longitude')->numeric()->step('0.0000001'),
                    ]),

                Section::make('Contato')
                    ->columns(3)
                    ->components([
                        TextInput::make('phone_landline')->label('Telefone fixo'),
                        TextInput::make('phone_mobile')->label('Celular'),
                        TextInput::make('whatsapp')->label('WhatsApp'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar')
                ->icon(Heroicon::OutlinedCheck)
                ->action('save'),
        ];
    }

    public function save(): void
    {
        Store::current()->update($this->form->getState());

        Notification::make()
            ->title('Dados do depósito salvos')
            ->success()
            ->send();
    }
}
