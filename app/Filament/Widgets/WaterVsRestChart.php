<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;

class WaterVsRestChart extends ChartWidget
{
    protected ?string $heading = 'Água vs resto — últimos 30 dias';

    public static function canView(): bool
    {
        return optional(auth()->user())->isManager() ?? false;
    }

    protected function getData(): array
    {
        $start = now()->startOfDay()->subDays(29);
        $labels = [];
        $waterDaily = [];
        $restDaily = [];

        for ($i = 0; $i < 30; $i++) {
            $day = $start->copy()->addDays($i);
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
