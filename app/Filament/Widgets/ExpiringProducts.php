<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\CargoItem;
use App\Models\DeliverySetting;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Product-batch near-expiry overview, fed by cargo_items with an expires_at.
 * Threshold is read from delivery_settings.near_expiry_threshold_days
 * (default 30). Mirrors the shell widget but operates on cargo batches.
 */
class ExpiringProducts extends StatsOverviewWidget
{
    protected ?string $heading = 'Produtos próximos do vencimento';

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user !== null && ! $user->isDeliverer();
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $threshold = DeliverySetting::nearExpiryThresholdDays();

        return [
            Stat::make('Vencidos', $this->countItems(null, $today))
                ->description('Lotes com validade já vencida')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),

            Stat::make("Próximos {$threshold} dias", $this->countItems($today, $today->copy()->addDays($threshold)))
                ->description("Vencem em até {$threshold} dias")
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('danger'),

            Stat::make("{$threshold}–60 dias", $this->countItems($today->copy()->addDays($threshold), $today->copy()->addDays(60)))
                ->description('Janela de rotação preferencial')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('warning'),

            Stat::make('60–90 dias', $this->countItems($today->copy()->addDays(60), $today->copy()->addDays(90)))
                ->description('Saudável — observar')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('success'),
        ];
    }

    private function countItems($from, $to): int
    {
        $query = CargoItem::query()
            ->whereNotNull('expires_at')
            ->where('quantity', '>', 0);

        if ($from === null) {
            $query->whereDate('expires_at', '<', $to);
        } else {
            $query->whereDate('expires_at', '>=', $from)
                ->whereDate('expires_at', '<', $to);
        }

        return (int) $query->sum('quantity');
    }
}
