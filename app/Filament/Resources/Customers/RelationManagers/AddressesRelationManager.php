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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Endereços';

    protected static ?string $modelLabel = 'Endereço';

    protected static ?string $pluralModelLabel = 'Endereços';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Endereço')
                    ->columns(4)
                    ->components([
                        TextInput::make('label')
                            ->label('Rótulo')
                            ->maxLength(40)
                            ->placeholder('Casa, Trabalho, Loja…'),

                        TextInput::make('zip')
                            ->label('CEP')
                            ->maxLength(16),

                        TextInput::make('street')
                            ->label('Rua / Avenida')
                            ->required()
                            ->maxLength(180)
                            ->columnSpan(2),

                        TextInput::make('number')
                            ->label('Número')
                            ->maxLength(20),

                        TextInput::make('complement')
                            ->label('Complemento')
                            ->maxLength(120)
                            ->columnSpan(3),

                        TextInput::make('district')
                            ->label('Bairro')
                            ->maxLength(80)
                            ->columnSpan(2),

                        TextInput::make('city')
                            ->label('Cidade')
                            ->default('Campinas')
                            ->maxLength(80)
                            ->columnSpan(2),

                        TextInput::make('reference')
                            ->label('Ponto de referência')
                            ->maxLength(180)
                            ->columnSpanFull(),
                    ]),

                Section::make('Geolocalização e flags')
                    ->columns(4)
                    ->components([
                        TextInput::make('lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step('0.0000001'),

                        TextInput::make('lng')
                            ->label('Longitude')
                            ->numeric()
                            ->step('0.0000001'),

                        Toggle::make('is_building')
                            ->label('Em prédio')
                            ->inline(false),

                        Toggle::make('is_primary')
                            ->label('Endereço principal')
                            ->inline(false),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('street')
            ->defaultSort('is_primary', 'desc')
            ->columns([
                TextColumn::make('full_address')
                    ->label('Endereço')
                    ->wrap()
                    ->searchable(['street', 'number', 'district', 'city']),

                TextColumn::make('district')
                    ->label('Bairro')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                IconColumn::make('is_building')
                    ->label('Prédio')
                    ->boolean()
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
