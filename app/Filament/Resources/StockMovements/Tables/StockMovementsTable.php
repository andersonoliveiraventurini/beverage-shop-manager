<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['variant.product', 'user']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('variant.product.name')
                    ->label('Produto')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('direction')
                    ->label('Direção')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'in' ? 'Entrada' : 'Saída')
                    ->color(fn (string $state) => $state === 'in' ? 'success' : 'danger'),

                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->numeric()
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, $record) => ($record->direction === 'in' ? '+' : '−') . $state),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'sale' => 'Venda',
                        'sale_reversal' => 'Estorno',
                        'manual_adjust' => 'Ajuste manual',
                        'cargo' => 'Carga',
                        default => $state,
                    }),

                TextColumn::make('source_id')
                    ->label('Origem')
                    ->formatStateUsing(fn ($state, $record) => $record->source_type
                        ? sprintf('%s #%s', class_basename($record->source_type), $state)
                        : '—')
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Direção')
                    ->options(['in' => 'Entrada', 'out' => 'Saída']),
                SelectFilter::make('reason')
                    ->options([
                        'sale' => 'Venda',
                        'sale_reversal' => 'Estorno',
                        'manual_adjust' => 'Ajuste manual',
                        'cargo' => 'Carga',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
