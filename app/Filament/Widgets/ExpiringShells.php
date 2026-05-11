<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\DeliverySetting;
use App\Models\WaterShellLedger;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpiringShells extends StatsOverviewWidget
{
    protected ?string $heading = 'Cascos próximos do vencimento';

    public static function canView(): bool
    {
        return DeliverySetting::trackingShells();
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $shellsExpired = $this->countShells(null, $today);
        $shells30 = $this->countShells($today, $today->copy()->addDays(30));
        $shells60 = $this->countShells($today->copy()->addDays(30), $today->copy()->addDays(60));
        $shells90 = $this->countShells($today->copy()->addDays(60), $today->copy()->addDays(90));

        return [
            Stat::make('Vencidos', $shellsExpired)
                ->description('Cascos cujo prazo já passou')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),

            Stat::make('Até 30 dias', $shells30)
                ->description('Vencem nos próximos 30 dias')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('danger'),

            Stat::make('30–60 dias', $shells60)
                ->description('Vencem entre 31 e 60 dias')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),

            Stat::make('60–90 dias', $shells90)
                ->description('Vencem entre 61 e 90 dias')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('success'),
        ];
    }

    private function countShells($from, $to): int
    {
        $query = WaterShellLedger::query()->where('shell_count', '>', 0);

        if ($from === null) {
            $query->whereDate('expires_at', '<', $to);
        } else {
            $query->whereDate('expires_at', '>=', $from)
                ->whereDate('expires_at', '<', $to);
        }

        return (int) $query->sum('shell_count');
    }
}
