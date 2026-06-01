<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\ExpiringProducts;
use App\Filament\Widgets\ExpiringShells;
use App\Filament\Widgets\MonthlyEvolutionChart;
use App\Filament\Widgets\PaymentMethodBreakdown;
use App\Filament\Widgets\SalesKpis;
use App\Filament\Widgets\TopCustomersTable;
use App\Filament\Widgets\TopProductsTable;
use App\Filament\Widgets\WaterVsRestChart;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

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
     * Dashboard widgets in "morning briefing" order: the headline revenue first,
     * then anything that needs action (expiring stock/shells), then trends and
     * breakdowns. The order here drives the on-screen order; per-widget canView()
     * still controls visibility per role.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            SalesKpis::class,
            ExpiringProducts::class,
            ExpiringShells::class,
            MonthlyEvolutionChart::class,
            WaterVsRestChart::class,
            PaymentMethodBreakdown::class,
            TopProductsTable::class,
            TopCustomersTable::class,
            FilamentInfoWidget::class,
        ];
    }

    /** Show the active date range under the title so the period is never ambiguous. */
    public function getSubheading(): ?string
    {
        $start = $this->filters['startDate'] ?? CarbonImmutable::now()->startOfMonth()->toDateString();
        $end = $this->filters['endDate'] ?? CarbonImmutable::now()->endOfMonth()->toDateString();

        return 'Período: '.CarbonImmutable::parse($start)->format('d/m/Y').' a '.CarbonImmutable::parse($end)->format('d/m/Y');
    }

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
