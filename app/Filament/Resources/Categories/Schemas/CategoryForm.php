<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, callable $set) => $operation === 'create' && filled($state)
                        ? $set('slug', Str::slug($state))
                        : null),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->helperText('Identificador único usado em URLs. Gerado automaticamente a partir do nome.'),

                Toggle::make('active')
                    ->label('Ativa')
                    ->default(true)
                    ->inline(false),
            ]);
    }
}
