<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Carbon\CarbonImmutable;

/**
 * Shared helper used by every analytics widget that reads the dashboard
 * date-range filter.  Reads $this->pageFilters (set by
 * Filament\Widgets\Concerns\InteractsWithPageFilters) and falls back to
 * the current calendar month when the filters are absent or empty.
 *
 * Expected property provided by InteractsWithPageFilters:
 *   public ?array $pageFilters = null;
 */
trait ResolvesDashboardPeriod
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function resolvePeriod(): array
    {
        $filters = $this->pageFilters ?? [];

        $startRaw = $filters['startDate'] ?? null;
        $endRaw = $filters['endDate'] ?? null;

        $start = ($startRaw !== null && $startRaw !== '')
            ? CarbonImmutable::parse($startRaw)->startOfDay()
            : CarbonImmutable::now()->startOfMonth();

        $end = ($endRaw !== null && $endRaw !== '')
            ? CarbonImmutable::parse($endRaw)->endOfDay()
            : CarbonImmutable::now()->endOfMonth();

        return [$start, $end];
    }
}
