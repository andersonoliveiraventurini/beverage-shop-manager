<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('customer')->withCount('items'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('id')
                    ->label('Venda')
                    ->prefix('#')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->default('Balcão')
                    ->color(fn ($state) => $state === 'Balcão' ? 'gray' : null)
                    ->weight(fn ($state) => $state === 'Balcão' ? null : 'semibold'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'delivery' ? 'Entrega' : 'Balcão')
                    ->color(fn (string $state) => $state === 'delivery' ? 'info' : 'gray'),

                TextColumn::make('payment_method')
                    ->label('Pagamento')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cash' => 'Dinheiro',
                        'pix' => 'PIX',
                        'debit' => 'Débito',
                        'credit' => 'Crédito',
                        default => $state,
                    }),

                TextColumn::make('items_count')
                    ->label('Itens')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('contains_water')
                    ->label('Água')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->alignEnd()
                    ->weight('semibold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'open' => 'Aberta',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Aberta',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                    ]),
                SelectFilter::make('payment_method')
                    ->label('Pagamento')
                    ->options([
                        'cash' => 'Dinheiro',
                        'pix' => 'PIX',
                        'debit' => 'Débito',
                        'credit' => 'Crédito',
                    ]),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(['counter' => 'Balcão', 'delivery' => 'Entrega']),
                TernaryFilter::make('contains_water')
                    ->label('Contém galão de água')
                    ->native(false),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
