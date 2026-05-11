<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhonesRelationManager extends RelationManager
{
    protected static string $relationship = 'phones';

    protected static ?string $title = 'Telefones';

    protected static ?string $modelLabel = 'Telefone';

    protected static ?string $pluralModelLabel = 'Telefones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->label('Número')
                    ->required()
                    ->maxLength(32)
                    ->placeholder('(19) 9 8177-8284'),

                TextInput::make('label')
                    ->label('Rótulo')
                    ->maxLength(40)
                    ->placeholder('Celular, Casa, Trabalho…'),

                Toggle::make('is_primary')
                    ->label('Telefone principal')
                    ->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('is_primary', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Número')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('label')
                    ->label('Rótulo')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean()
                    ->sortable(),
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
