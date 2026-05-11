<?php

declare(strict_types=1);

namespace App\Filament\Resources\WaterShellLedgers\Pages;

use App\Filament\Resources\WaterShellLedgers\WaterShellLedgerResource;
use App\Filament\Widgets\ExpiringShells;
use Filament\Resources\Pages\ListRecords;

class ListWaterShellLedgers extends ListRecords
{
    protected static string $resource = WaterShellLedgerResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ExpiringShells::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
