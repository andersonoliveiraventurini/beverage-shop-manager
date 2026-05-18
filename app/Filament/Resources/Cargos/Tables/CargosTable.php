<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cargos\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CargosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('items')->with('user'))
            ->columns([
                TextColumn::make('received_at')
                    ->label('Recebido em')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('supplier')
                    ->label('Fornecedor')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('items_count')
                    ->label('Itens')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->alignEnd()
                    ->weight('semibold'),

                TextColumn::make('user.name')
                    ->label('Recebido por')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
