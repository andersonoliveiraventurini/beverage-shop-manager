<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Custom dashboard page for FA Distribuidora.
 *
 * Extends the Filament base Dashboard page and adds a date-range filter form
 * (HasFiltersForm).  The two DatePicker fields — startDate / endDate — default
 * to the first and last day of the current month, preserving the existing
 * widget behaviour on first load.
 *
 * Widgets that need to honour the range should use:
 *   use Filament\Widgets\Concerns\InteractsWithPageFilters;
 * and read $this->pageFilters['startDate'] / ['endDate'] via the shared
 * App\Filament\Widgets\Concerns\ResolvesDashboardPeriod trait.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    /**
     * "Exportar PDF" button — opens the dashboard KPI report in a new tab,
     * forwarding the currently selected date-range filters as query params.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('dashboard.report.pdf', [
                    'startDate' => $this->filters['startDate'] ?? CarbonImmutable::now()->startOfMonth()->toDateString(),
                    'endDate' => $this->filters['endDate'] ?? CarbonImmutable::now()->endOfMonth()->toDateString(),
                ]))
                ->openUrlInNewTab(),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('startDate')
                ->label('De')
                ->default(CarbonImmutable::now()->startOfMonth()->toDateString()),

            DatePicker::make('endDate')
                ->label('Até')
                ->default(CarbonImmutable::now()->endOfMonth()->toDateString()),
        ]);
    }
}
