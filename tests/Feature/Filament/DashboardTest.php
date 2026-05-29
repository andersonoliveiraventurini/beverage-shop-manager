<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\SalesKpis;
use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->actingAs(User::factory()->manager()->create());
});

/**
 * Seed a confirmed sale at a specific date with the given unit price.
 *
 * @param  string  $date  Y-m-d
 */
function dashSale(string $date, float $price = 50.0): Sale
{
    $cat = Category::firstOrCreate(['slug' => 'dash-cat'], ['name' => 'Dash Cat', 'active' => true]);
    $product = $cat->products()->firstOrCreate(['name' => 'Dash Prod'], ['active' => true]);
    $variant = $product->variants()->firstOrCreate(['sku' => 'DASH-001'], [
        'size' => '1L',
        'sale_price' => $price,
        'min_stock' => 0,
    ]);

    if ($variant->current_stock < 1) {
        StockMovement::create([
            'variant_id' => $variant->id,
            'direction' => 'in',
            'reason' => 'manual_adjust',
            'quantity' => 100,
        ]);
    }

    $sale = Sale::create([
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => $price,
        'line_total' => $price,
        'modality' => SaleItem::MODALITY_FULL,
    ]);

    // Force the sale into the desired date so queries by month/year work correctly.
    $sale->timestamps = false;
    $sale->forceFill(['created_at' => $date.' 12:00:00', 'updated_at' => $date.' 12:00:00'])->save();

    return $sale->fresh();
}

// ---------------------------------------------------------------------------
// Dashboard page renders with filter form
// ---------------------------------------------------------------------------

it('renders the custom Dashboard page without errors', function () {
    livewireTest(Dashboard::class)->assertSuccessful();
});

it('has the date-range filter form with startDate and endDate fields', function () {
    livewireTest(Dashboard::class)
        ->assertFormExists('filtersForm')
        ->assertFormFieldExists('startDate', 'filtersForm')
        ->assertFormFieldExists('endDate', 'filtersForm');
});

// ---------------------------------------------------------------------------
// Default behavior: current-month fallback
// ---------------------------------------------------------------------------

it('defaults filters to the first and last day of the current month', function () {
    $component = livewireTest(Dashboard::class);

    $filters = $component->get('filters');

    $expectedStart = CarbonImmutable::now()->startOfMonth()->toDateString();
    $expectedEnd = CarbonImmutable::now()->endOfMonth()->toDateString();

    expect($filters['startDate'])->toBe($expectedStart)
        ->and($filters['endDate'])->toBe($expectedEnd);
});

// ---------------------------------------------------------------------------
// SalesKpis: filter fanning
// ---------------------------------------------------------------------------

it('SalesKpis counts only sales within the filtered date range', function () {
    $currentYear = CarbonImmutable::now()->year;

    // Pick a month far enough from now to not be the current month
    $inRangeDate = "{$currentYear}-01-05";
    $outOfRangeDate = "{$currentYear}-06-20";

    // If we ARE in January, swap so both are distinct from current month
    if (CarbonImmutable::now()->month === 1) {
        $inRangeDate = "{$currentYear}-01-10";
        $outOfRangeDate = ($currentYear - 1).'-11-15';
    }

    dashSale($inRangeDate, 100.0);    // INSIDE filter range
    dashSale($outOfRangeDate, 200.0); // OUTSIDE filter range

    $inYear = (int) substr($inRangeDate, 0, 4);
    $inMonth = (int) substr($inRangeDate, 5, 2);
    $start = CarbonImmutable::create($inYear, $inMonth, 1)->startOfMonth()->toDateString();
    $end = CarbonImmutable::create($inYear, $inMonth, 1)->endOfMonth()->toDateString();

    $widget = new SalesKpis;
    $widget->pageFilters = ['startDate' => $start, 'endDate' => $end];

    $stats = new ReflectionMethod($widget, 'getStats');
    $stats->setAccessible(true);
    $result = $stats->invoke($widget);

    // Revenue stat (index 0) should show R$ 100 (in-range), not R$ 200 (out-of-range)
    expect($result[0]->getValue())
        ->toContain('100')
        ->not->toContain('200');
});

it('SalesKpis falls back to current month when pageFilters is null', function () {
    $today = CarbonImmutable::now()->toDateString();
    dashSale($today, 75.0);

    $widget = new SalesKpis;
    // pageFilters intentionally NOT set — should fall back to current month

    $stats = new ReflectionMethod($widget, 'getStats');
    $stats->setAccessible(true);
    $result = $stats->invoke($widget);

    expect($result)->toHaveCount(4);
    // Revenue card should reflect the R$ 75 sale created today
    expect($result[0]->getValue())->toContain('75');
});

// ---------------------------------------------------------------------------
// Dashboard page: filters are stored in component state
// ---------------------------------------------------------------------------

it('stores filter values in the filters state when the form is filled', function () {
    $currentYear = CarbonImmutable::now()->year;
    $start = "{$currentYear}-01-01";
    $end = "{$currentYear}-01-31";

    livewireTest(Dashboard::class)
        ->fillForm(['startDate' => $start, 'endDate' => $end], 'filtersForm')
        ->assertSet('filters.startDate', $start)
        ->assertSet('filters.endDate', $end);
});
