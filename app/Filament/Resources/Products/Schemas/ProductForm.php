<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(2)
                    ->components([
                        Select::make('category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(180),

                        TextInput::make('brand')
                            ->label('Marca')
                            ->maxLength(120)
                            ->datalist(fn () => Product::query()
                                ->whereNotNull('brand')
                                ->distinct()
                                ->orderBy('brand')
                                ->pluck('brand')
                                ->all()),

                        Toggle::make('active')
                            ->label('Ativo')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Descrição')
                    ->components([
                        Textarea::make('description')
                            ->label('Descrição')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
