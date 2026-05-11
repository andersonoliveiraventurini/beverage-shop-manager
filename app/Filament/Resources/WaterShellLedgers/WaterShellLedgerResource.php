<?php

declare(strict_types=1);

namespace App\Filament\Resources\WaterShellLedgers;

use App\Filament\Resources\WaterShellLedgers\Pages\ListWaterShellLedgers;
use App\Filament\Resources\WaterShellLedgers\Tables\WaterShellLedgersTable;
use App\Models\DeliverySetting;
use App\Models\WaterShellLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WaterShellLedgerResource extends Resource
{
    protected static ?string $model = WaterShellLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Vencimentos';

    protected static ?string $modelLabel = 'Vencimento de casco';

    protected static ?string $pluralModelLabel = 'Vencimentos de cascos';

    protected static string|\UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return DeliverySetting::trackingShells();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return WaterShellLedgersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaterShellLedgers::route('/'),
        ];
    }
}
