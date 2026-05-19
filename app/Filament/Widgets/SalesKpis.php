<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Headline KPIs for the manager dashboard: total revenue this month, average
 * ticket, water share of revenue, accumulated card fees. Manager-only.
 */
class SalesKpis extends StatsOverviewWidget
{
    protected ?string $heading = 'Resumo do mês';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    protected function getStats(): array
    {
        $confirmed = fn (): Builder => Sale::query()
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);

        $revenue = (float) $confirmed()->sum('total');
        $count = (int) $confirmed()->count();
        $avgTicket = $count > 0 ? $revenue / $count : 0.0;
        $waterRevenue = (float) $confirmed()->where('contains_water', true)->sum('total');
        $waterShare = $revenue > 0 ? round(($waterRevenue / $revenue) * 100, 1) : 0.0;
        $cardFees = (float) $confirmed()->sum('card_fee');

        return [
            Stat::make('Receita do mês', 'R$ ' . number_format($revenue, 2, ',', '.'))
                ->description("{$count} vendas confirmadas")
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('primary'),

            Stat::make('Ticket médio', 'R$ ' . number_format($avgTicket, 2, ',', '.'))
                ->description('Receita ÷ vendas')
                ->color('info'),

            Stat::make("Água: {$waterShare}%", 'R$ ' . number_format($waterRevenue, 2, ',', '.'))
                ->description('Share da receita')
                ->color('warning'),

            Stat::make('Taxas de cartão', 'R$ ' . number_format($cardFees, 2, ',', '.'))
                ->description('Acumulado no mês')
                ->color('gray'),
        ];
    }
}
