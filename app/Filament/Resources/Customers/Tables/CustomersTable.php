<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount('sales')
                ->with(['phones' => fn ($q) => $q->orderByDesc('is_primary'), 'primaryAddress']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('document')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('primary_phone')
                    ->label('Telefone')
                    ->getStateUsing(fn ($record) => optional($record->phones->first())->number ?? '—')
                    ->searchable(
                        query: fn ($query, string $search) => $query->orWhereHas(
                            'phones',
                            fn ($q) => $q->where('number', 'like', '%' . $search . '%'),
                        ),
                    ),

                TextColumn::make('primaryAddress.district')
                    ->label('Bairro')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                IconColumn::make('in_delivery_area')
                    ->label('Na área')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('delivery_fee')
                    ->label('Entrega')
                    ->money('BRL')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('sales_count')
                    ->label('Compras')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('in_delivery_area')
                    ->label('Área de entrega')
                    ->trueLabel('Dentro')
                    ->falseLabel('Fora')
                    ->native(false),
                TernaryFilter::make('has_manual_fee_override')
                    ->label('Taxa manual')
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
