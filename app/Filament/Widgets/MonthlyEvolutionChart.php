<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardPeriod;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyEvolutionChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ResolvesDashboardPeriod;

    protected ?string $heading = 'Evolução mensal de receita';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    protected function getData(): array
    {
        [$start, $end] = $this->resolvePeriod();

        $labels = [];
        $revenues = [];

        // Walk month-by-month from start to end
        $month = CarbonImmutable::create($start->year, $start->month, 1)->startOfMonth();

        while ($month->lte($end->startOfMonth())) {
            $monthStart = $month->startOfMonth()->toDateString();
            $monthEnd = $month->endOfMonth()->toDateString();

            $labels[] = $month->format('m/Y');

            $revenues[] = (float) Sale::query()
                ->where('status', Sale::STATUS_CONFIRMED)
                ->whereDate('created_at', '>=', $monthStart)
                ->whereDate('created_at', '<=', $monthEnd)
                ->sum('total');

            $month = $month->addMonth();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Receita',
                    'data' => $revenues,
                    'backgroundColor' => config('brand.chart_colors.azul_profundo'),
                    'borderColor' => config('brand.chart_colors.azul_profundo_border'),
                    'borderWidth' => 2,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
