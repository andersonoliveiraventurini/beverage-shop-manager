<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\DeliverySetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configurações';

    protected static ?string $title = 'Configurações';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(DeliverySetting::current()->only([
            'radius_km',
            'default_delivery_fee',
            'out_of_area_extra_fee',
            'default_building_fee',
            'track_water_shells',
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
                Section::make('Entrega')
                    ->description('Valores padrão usados como base para todos os clientes. Alterações aqui NÃO recalculam automaticamente clientes existentes (recálculo em massa virá em F02).')
                    ->columns(2)
                    ->components([
                        TextInput::make('radius_km')
                            ->label('Raio de entrega (km)')
                            ->numeric()
                            ->step('0.01')
                            ->suffix('km')
                            ->required(),

                        TextInput::make('default_delivery_fee')
                            ->label('Taxa padrão de entrega')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->required(),

                        TextInput::make('out_of_area_extra_fee')
                            ->label('Adicional fora-da-área')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->helperText('Somado à taxa padrão quando o cliente está fora do raio.')
                            ->required(),

                        TextInput::make('default_building_fee')
                            ->label('Adicional prédio')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->required(),
                    ]),

                Section::make('Cascos de água')
                    ->description('Quando ativo, toda venda confirmada com galão retornável atualiza o livro-razão de cascos por cliente (cascos em circulação + validade).')
                    ->components([
                        Toggle::make('track_water_shells')
                            ->label('Acompanhar cascos por cliente')
                            ->inline(false)
                            ->helperText('Default: desligado.'),
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
        $state = $this->form->getState();
        DeliverySetting::current()->update($state);

        Notification::make()
            ->title('Configurações salvas')
            ->success()
            ->send();
    }
}
