<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardPeriod;
use App\Models\Sale;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Headline KPIs for the manager dashboard: total revenue this month, average
 * ticket, water share of revenue, accumulated card fees. Manager-only.
 */
class SalesKpis extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ResolvesDashboardPeriod;

    protected ?string $heading = 'Resumo do período';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    protected function getStats(): array
    {
        [$start, $end] = $this->resolvePeriod();

        $confirmed = fn (): Builder => Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end);

        $revenue = (float) $confirmed()->sum('total');
        $count = (int) $confirmed()->count();
        $avgTicket = $count > 0 ? $revenue / $count : 0.0;
        $waterRevenue = (float) $confirmed()->where('contains_water', true)->sum('total');
        $waterShare = $revenue > 0 ? round(($waterRevenue / $revenue) * 100, 1) : 0.0;
        $cardFees = (float) $confirmed()->sum('card_fee');

        return [
            Stat::make('Receita do período', 'R$ '.number_format($revenue, 2, ',', '.'))
                ->description("{$count} vendas confirmadas")
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('primary'),

            Stat::make('Ticket médio', 'R$ '.number_format($avgTicket, 2, ',', '.'))
                ->description('Receita ÷ vendas')
                ->color('info'),

            Stat::make("Água: {$waterShare}%", 'R$ '.number_format($waterRevenue, 2, ',', '.'))
                ->description('Share da receita')
                ->color('warning'),

            Stat::make('Taxas de cartão', 'R$ '.number_format($cardFees, 2, ',', '.'))
                ->description('Acumulado no período')
                ->color('gray'),
        ];
    }
}
