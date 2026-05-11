<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use App\Models\Product;
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

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('variants')->with('category'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('brand')
                    ->label('Marca')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('variants_count')
                    ->label('Variações')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('brand')
                    ->label('Marca')
                    ->options(fn () => Product::query()
                        ->whereNotNull('brand')
                        ->distinct()
                        ->orderBy('brand')
                        ->pluck('brand', 'brand')
                        ->all())
                    ->searchable()
                    ->multiple(),

                TernaryFilter::make('active')
                    ->label('Status')
                    ->trueLabel('Apenas ativos')
                    ->falseLabel('Apenas inativos')
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
