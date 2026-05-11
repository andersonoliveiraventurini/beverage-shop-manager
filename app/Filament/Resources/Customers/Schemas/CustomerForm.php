<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(180)
                            ->columnSpan(2),

                        TextInput::make('document')
                            ->label('Documento (CPF/CNPJ)')
                            ->maxLength(32),

                        Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Entrega e taxas')
                    ->description('Taxas armazenadas no cliente — usadas como padrão em todas as vendas. Para sobrescrever em uma venda, ajuste no formulário da venda.')
                    ->columns(3)
                    ->components([
                        Toggle::make('in_delivery_area')
                            ->label('Dentro da área de entrega')
                            ->default(true)
                            ->inline(false),

                        TextInput::make('distance_km')
                            ->label('Distância (km)')
                            ->numeric()
                            ->step('0.01')
                            ->suffix('km'),

                        Toggle::make('has_manual_fee_override')
                            ->label('Taxas manuais (sem recálculo)')
                            ->inline(false)
                            ->helperText('Quando ativo, o recálculo em massa não altera as taxas deste cliente.'),

                        TextInput::make('delivery_fee')
                            ->label('Taxa de entrega')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->required(),

                        TextInput::make('building_fee')
                            ->label('Adicional prédio')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->default(0)
                            ->required(),

                        DateTimePicker::make('fees_calculated_at')
                            ->label('Recalculadas em')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Google Contacts')
                    ->description('Sincronização de contato (F16 — opcional, sem efeito até a integração ser ativada).')
                    ->collapsed()
                    ->columns(2)
                    ->components([
                        TextInput::make('google_contact_id')
                            ->label('ID do contato no Google')
                            ->maxLength(128),
                        DateTimePicker::make('google_synced_at')
                            ->label('Sincronizado em')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
