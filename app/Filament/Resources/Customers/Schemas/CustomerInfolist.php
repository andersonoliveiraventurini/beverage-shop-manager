<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(2)
                    ->components([
                        TextEntry::make('name')
                            ->label('Nome')
                            ->weight('bold')
                            ->size(TextSize::Large)
                            ->columnSpan(2),

                        TextEntry::make('document')
                            ->label('Documento (CPF/CNPJ)')
                            ->placeholder('—'),

                        TextEntry::make('notes')
                            ->label('Observações')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Entrega e taxas')
                    ->columns(3)
                    ->components([
                        TextEntry::make('distance_km')
                            ->label('Distância')
                            ->suffix(' km')
                            ->placeholder('—'),

                        TextEntry::make('delivery_fee')
                            ->label('Taxa de entrega')
                            ->money('BRL'),

                        TextEntry::make('building_fee')
                            ->label('Adicional prédio')
                            ->money('BRL'),

                        TextEntry::make('fee_source')
                            ->label('Origem das taxas')
                            ->state(fn (Customer $record): string => $record->has_manual_fee_override
                                ? 'Manual'
                                : 'Automática'),

                        TextEntry::make('fees_calculated_at')
                            ->label('Recalculadas em')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
