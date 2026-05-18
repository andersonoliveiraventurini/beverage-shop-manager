<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cargos;

use App\Filament\Resources\Cargos\Pages\CreateCargo;
use App\Filament\Resources\Cargos\Pages\ListCargos;
use App\Filament\Resources\Cargos\Pages\ViewCargo;
use App\Filament\Resources\Cargos\Schemas\CargoForm;
use App\Filament\Resources\Cargos\Tables\CargosTable;
use App\Models\Cargo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CargoResource extends Resource
{
    protected static ?string $model = Cargo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Cargas (entradas)';

    protected static ?string $modelLabel = 'Carga';

    protected static ?string $pluralModelLabel = 'Cargas';

    protected static string|\UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return CargoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CargosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCargos::route('/'),
            'create' => CreateCargo::route('/create'),
            'view' => ViewCargo::route('/{record}'),
        ];
    }
}
