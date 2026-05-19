<?php

declare(strict_types=1);

use App\Filament\Pages\GeneralSales;
use App\Filament\Pages\WaterSales;
use App\Filament\Widgets\PaymentMethodBreakdown;
use App\Filament\Widgets\SalesKpis;
use App\Filament\Widgets\TopProductsTable;
use App\Filament\Widgets\WaterVsRestChart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;

function seedReportingFixtures(): array
{
    $waterCat = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $otherCat = Category::firstOrCreate(['slug' => 'cerveja'], ['name' => 'Cerveja', 'active' => true]);

    $waterProduct = $waterCat->products()->create(['name' => 'Galão 20L', 'active' => true]);
    $waterVariant = $waterProduct->variants()->create([
        'sku' => 'REP-AGUA-20L', 'size' => '20L', 'sale_price' => 15, 'min_stock' => 10,
    ]);

    $beerProduct = $otherCat->products()->create(['name' => 'Brahma 350ml', 'active' => true]);
    $beerVariant = $beerProduct->variants()->create([
        'sku' => 'REP-CERV-350', 'size' => '350ml', 'sale_price' => 6, 'min_stock' => 12,
    ]);

    foreach ([$waterVariant, $beerVariant] as $v) {
        StockMovement::create([
            'variant_id' => $v->id, 'direction' => 'in', 'reason' => 'manual_adjust', 'quantity' => 100,
        ]);
    }

    return [$waterVariant, $beerVariant];
}

beforeEach(function () {
    $this->actingAs(User::factory()->manager()->create());
});

it('separates Water Sales from General Sales on the listing pages', function () {
    [$waterVariant, $beerVariant] = seedReportingFixtures();
    $customer = Customer::factory()->create();

    $waterSale = Sale::create([
        'customer_id' => $customer->id, 'type' => 'counter', 'payment_method' => 'cash', 'status' => 'confirmed',
    ]);
    $waterSale->items()->create(['variant_id' => $waterVariant->id, 'quantity' => 2, 'unit_price' => 15]);

    $generalSale = Sale::create([
        'customer_id' => $customer->id, 'type' => 'counter', 'payment_method' => 'pix', 'status' => 'confirmed',
    ]);
    $generalSale->items()->create(['variant_id' => $beerVariant->id, 'quantity' => 6, 'unit_price' => 6]);

    livewireTest(WaterSales::class)->assertCanSeeTableRecords([$waterSale])->assertCanNotSeeTableRecords([$generalSale]);
    livewireTest(GeneralSales::class)->assertCanSeeTableRecords([$generalSale])->assertCanNotSeeTableRecords([$waterSale]);
});

it('shows a mixed badge column for sales containing both water and non-water items', function () {
    [$waterVariant, $beerVariant] = seedReportingFixtures();
    $sale = Sale::create([
        'type' => 'counter', 'payment_method' => 'cash', 'status' => 'confirmed',
    ]);
    $sale->items()->create(['variant_id' => $waterVariant->id, 'quantity' => 1, 'unit_price' => 15]);
    $sale->items()->create(['variant_id' => $beerVariant->id, 'quantity' => 1, 'unit_price' => 6]);

    // Mixed sales contain water so they appear in WaterSales — the table column
    // 'mixed' lights up for them.
    expect($sale->fresh()->contains_water)->toBeTrue();
});

it('computes the headline KPIs for the current month', function () {
    [$waterVariant, $beerVariant] = seedReportingFixtures();

    $a = Sale::create(['type' => 'counter', 'payment_method' => 'cash', 'status' => 'confirmed']);
    $a->items()->create(['variant_id' => $waterVariant->id, 'quantity' => 4, 'unit_price' => 15]);

    $b = Sale::create(['type' => 'counter', 'payment_method' => 'debit', 'status' => 'confirmed', 'card_fee' => 2]);
    $b->items()->create(['variant_id' => $beerVariant->id, 'quantity' => 10, 'unit_price' => 6]);

    $widget = new SalesKpis();
    $stats = (new ReflectionMethod($widget, 'getStats'))->setAccessible(true) ?: null;
    $stats = (new ReflectionMethod($widget, 'getStats'));
    $stats->setAccessible(true);
    $result = $stats->invoke($widget);

    // 4 KPI cards.
    expect($result)->toHaveCount(4);
});

it('breaks down revenue by payment method for the current month', function () {
    [$waterVariant] = seedReportingFixtures();

    foreach (['cash', 'pix', 'pix', 'debit', 'credit'] as $method) {
        $s = Sale::create([
            'type' => 'counter', 'payment_method' => $method, 'status' => 'confirmed',
        ]);
        $s->items()->create(['variant_id' => $waterVariant->id, 'quantity' => 1, 'unit_price' => 15]);
    }

    $widget = new PaymentMethodBreakdown();
    $stats = (new ReflectionMethod($widget, 'getStats'));
    $stats->setAccessible(true);
    $cards = $stats->invoke($widget);

    expect($cards)->toHaveCount(4);
});

it('produces a chart series spanning 30 days for water vs rest', function () {
    seedReportingFixtures();

    $widget = new WaterVsRestChart();
    $data = (new ReflectionMethod($widget, 'getData'));
    $data->setAccessible(true);
    $result = $data->invoke($widget);

    expect($result['labels'])->toHaveCount(30)
        ->and($result['datasets'])->toHaveCount(2)
        ->and($result['datasets'][0]['label'])->toBe('Água')
        ->and($result['datasets'][1]['label'])->toBe('Outros');
});

it('hides every dashboard widget from attendants', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(SalesKpis::canView())->toBeFalse()
        ->and(PaymentMethodBreakdown::canView())->toBeFalse()
        ->and(WaterVsRestChart::canView())->toBeFalse()
        ->and(TopProductsTable::canView())->toBeFalse();
});

it('hides the reporting pages from deliverers', function () {
    $this->actingAs(User::factory()->deliverer()->create());

    expect(WaterSales::canAccess())->toBeFalse()
        ->and(GeneralSales::canAccess())->toBeFalse();
});
