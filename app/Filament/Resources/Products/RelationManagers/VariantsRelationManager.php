<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variações';

    protected static ?string $modelLabel = 'Variação';

    protected static ?string $pluralModelLabel = 'Variações';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(2)
                    ->components([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state) => is_string($state) ? strtoupper($state) : $state)
                            ->helperText('Convertido para maiúsculas automaticamente.'),

                        TextInput::make('size')
                            ->label('Tamanho / variação')
                            ->required()
                            ->maxLength(80)
                            ->placeholder('Ex.: 20L, 350ml lata, 60g (vermelha)'),
                    ]),

                Section::make('Preços')
                    ->columns(3)
                    ->components([
                        TextInput::make('sale_price')
                            ->label('Preço de venda')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),

                        TextInput::make('cost_price')
                            ->label('Preço de custo')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->placeholder('≈ 65% do preço de venda'),

                        TextInput::make('min_stock')
                            ->label('Estoque mínimo')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(5)
                            ->required(),
                    ]),

                Section::make('Vasilhame (retornável)')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_returnable')
                            ->label('Vasilhame retornável')
                            ->live()
                            ->inline(false)
                            ->helperText('Marque para galões e garrafas que voltam para o depósito.'),

                        TextInput::make('shell_cost')
                            ->label('Custo do vasilhame')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->visible(fn (callable $get) => (bool) $get('is_returnable')),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('size')
            ->defaultSort('size')
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->weight('semibold'),

                TextColumn::make('size')
                    ->label('Tamanho')
                    ->searchable(),

                IconColumn::make('is_returnable')
                    ->label('Retornável')
                    ->boolean(),

                TextColumn::make('current_stock')
                    ->label('Estoque')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn ($state, $record) => $record->isLowStock() ? 'danger' : 'success')
                    ->weight(fn ($state, $record) => $record->isLowStock() ? 'bold' : null)
                    ->tooltip(fn ($record) => 'Mínimo: ' . $record->min_stock),

                TextColumn::make('sale_price')
                    ->label('Venda')
                    ->money('BRL')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('cost_price')
                    ->label('Custo')
                    ->money('BRL')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('shell_cost')
                    ->label('Vasilhame')
                    ->money('BRL')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('min_stock')
                    ->label('Estoque mín.')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_returnable')
                    ->label('Retornável')
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
