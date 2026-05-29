<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResolvesDashboardPeriod;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class WaterVsRestChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ResolvesDashboardPeriod;

    protected ?string $heading = 'Água vs resto — período selecionado';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    /**
     * Resolves the chart period.
     *
     * When page filters are provided the filter date-range is used (delegated
     * to ResolvesDashboardPeriod::resolvePeriod()).
     * When no filters are set the widget falls back to the rolling last-30-days
     * window (the original pre-filter behavior) so that the daily bar chart
     * always renders exactly 30 data-points by default.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function resolveChartPeriod(): array
    {
        $filters = $this->pageFilters ?? [];

        $hasStart = isset($filters['startDate']) && $filters['startDate'] !== '';
        $hasEnd = isset($filters['endDate']) && $filters['endDate'] !== '';

        if ($hasStart || $hasEnd) {
            return $this->resolvePeriod();
        }

        // Default: rolling 30-day window (original behavior).
        $end = CarbonImmutable::now()->endOfDay();
        $start = CarbonImmutable::now()->startOfDay()->subDays(29);

        return [$start, $end];
    }

    protected function getData(): array
    {
        [$start, $end] = $this->resolveChartPeriod();

        $labels = [];
        $waterDaily = [];
        $restDaily = [];

        $day = $start->startOfDay();

        while ($day->lte($end->endOfDay())) {
            $labels[] = $day->format('d/m');

            $waterDaily[] = (float) Sale::query()
                ->where('status', Sale::STATUS_CONFIRMED)
                ->where('contains_water', true)
                ->whereDate('created_at', $day)
                ->sum('total');

            $restDaily[] = (float) Sale::query()
                ->where('status', Sale::STATUS_CONFIRMED)
                ->where('contains_water', false)
                ->whereDate('created_at', $day)
                ->sum('total');

            $day = $day->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Água',
                    'data' => $waterDaily,
                    'backgroundColor' => config('brand.chart_colors.azul_cristal'),
                    'borderColor' => config('brand.chart_colors.azul_cristal_border'),
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Outros',
                    'data' => $restDaily,
                    'backgroundColor' => config('brand.chart_colors.amarelo_solar'),
                    'borderColor' => config('brand.chart_colors.amarelo_solar_border'),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
