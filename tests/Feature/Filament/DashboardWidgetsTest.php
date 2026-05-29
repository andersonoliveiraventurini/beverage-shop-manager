<?php

declare(strict_types=1);

use App\Filament\Widgets\MonthlyEvolutionChart;
use App\Filament\Widgets\TopCustomersTable;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;

// ---------------------------------------------------------------------------
// canView() gating
// ---------------------------------------------------------------------------

it('TopCustomersTable is visible to managers', function () {
    $this->actingAs(User::factory()->manager()->create());

    expect(TopCustomersTable::canView())->toBeTrue();
});

it('TopCustomersTable is hidden from attendants', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(TopCustomersTable::canView())->toBeFalse();
});

it('MonthlyEvolutionChart is visible to managers', function () {
    $this->actingAs(User::factory()->manager()->create());

    expect(MonthlyEvolutionChart::canView())->toBeTrue();
});

it('MonthlyEvolutionChart is hidden from attendants', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(MonthlyEvolutionChart::canView())->toBeFalse();
});

// ---------------------------------------------------------------------------
// TopCustomersTable: data within period
// ---------------------------------------------------------------------------

it('TopCustomersTable renders for a manager', function () {
    $this->actingAs(User::factory()->manager()->create());

    livewireTest(TopCustomersTable::class)->assertSuccessful();
});

it('TopCustomersTable shows top customers by total spent within the period', function () {
    $this->actingAs(User::factory()->manager()->create());

    $year = CarbonImmutable::now()->year;
    $inDate = "{$year}-01-10";
    $outDate = "{$year}-06-20";

    // Avoid collision if we are in January
    if (CarbonImmutable::now()->month === 1) {
        $inDate = "{$year}-01-15";
        $outDate = ($year - 1).'-11-01';
    }

    $customerA = Customer::factory()->create(['name' => 'Customer Alpha']);
    $customerB = Customer::factory()->create(['name' => 'Customer Beta']);

    // Inside range: customerA spends 300, customerB spends 150
    Sale::factory()->create([
        'customer_id' => $customerA->id,
        'status' => Sale::STATUS_CONFIRMED,
        'subtotal' => 300,
        'total' => 300,
        'created_at' => $inDate.' 12:00:00',
        'updated_at' => $inDate.' 12:00:00',
    ]);
    Sale::factory()->create([
        'customer_id' => $customerB->id,
        'status' => Sale::STATUS_CONFIRMED,
        'subtotal' => 150,
        'total' => 150,
        'created_at' => $inDate.' 12:00:00',
        'updated_at' => $inDate.' 12:00:00',
    ]);
    // Outside range: customerC spends 999 (must NOT appear)
    $customerC = Customer::factory()->create(['name' => 'Customer Gamma']);
    Sale::factory()->create([
        'customer_id' => $customerC->id,
        'status' => Sale::STATUS_CONFIRMED,
        'subtotal' => 999,
        'total' => 999,
        'created_at' => $outDate.' 12:00:00',
        'updated_at' => $outDate.' 12:00:00',
    ]);

    $inMonth = (int) substr($inDate, 5, 2);
    $start = CarbonImmutable::create($year, $inMonth, 1)->startOfMonth()->toDateString();
    $end = CarbonImmutable::create($year, $inMonth, 1)->endOfMonth()->toDateString();

    $widget = new TopCustomersTable;
    $widget->pageFilters = ['startDate' => $start, 'endDate' => $end];

    $query = (new ReflectionMethod($widget, 'buildQuery'))->invoke($widget);
    $results = $query->get();

    // Should contain customerA and customerB but NOT customerC
    $names = $results->pluck('customer_name')->toArray();
    expect($names)->toContain('Customer Alpha')
        ->toContain('Customer Beta')
        ->not->toContain('Customer Gamma');

    // customerA (300) should come before customerB (150)
    $alphaIndex = array_search('Customer Alpha', $names);
    $betaIndex = array_search('Customer Beta', $names);
    expect($alphaIndex)->toBeLessThan($betaIndex);
});

it('TopCustomersTable excludes sales without a customer', function () {
    $this->actingAs(User::factory()->manager()->create());

    $today = CarbonImmutable::now()->toDateString();

    // Walk-in sale: no customer_id
    Sale::factory()->create([
        'customer_id' => null,
        'status' => Sale::STATUS_CONFIRMED,
        'subtotal' => 500,
        'total' => 500,
        'created_at' => $today.' 12:00:00',
        'updated_at' => $today.' 12:00:00',
    ]);

    $widget = new TopCustomersTable;
    // null pageFilters → current-month fallback
    $query = (new ReflectionMethod($widget, 'buildQuery'))->invoke($widget);
    $results = $query->get();

    // All rows should have a non-null customer_name
    expect($results->every(fn ($row) => $row->customer_name !== null))->toBeTrue();
});

it('TopCustomersTable excludes non-confirmed sales', function () {
    $this->actingAs(User::factory()->manager()->create());

    $today = CarbonImmutable::now()->toDateString();
    $customer = Customer::factory()->create(['name' => 'Cancelled Customer']);

    Sale::factory()->create([
        'customer_id' => $customer->id,
        'status' => Sale::STATUS_CANCELLED,
        'subtotal' => 800,
        'total' => 800,
        'created_at' => $today.' 12:00:00',
        'updated_at' => $today.' 12:00:00',
    ]);

    $widget = new TopCustomersTable;
    $query = (new ReflectionMethod($widget, 'buildQuery'))->invoke($widget);
    $results = $query->get();

    expect($results->pluck('customer_name'))->not->toContain('Cancelled Customer');
});

// ---------------------------------------------------------------------------
// MonthlyEvolutionChart: data within period
// ---------------------------------------------------------------------------

it('MonthlyEvolutionChart renders for a manager', function () {
    $this->actingAs(User::factory()->manager()->create());

    livewireTest(MonthlyEvolutionChart::class)->assertSuccessful();
});

it('MonthlyEvolutionChart produces one data point per month in the range', function () {
    $this->actingAs(User::factory()->manager()->create());

    $widget = new MonthlyEvolutionChart;
    $widget->pageFilters = ['startDate' => '2024-03-01', 'endDate' => '2024-05-31'];

    $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

    // 3 months: March, April, May 2024
    expect($data['labels'])->toHaveCount(3)
        ->and($data['datasets'][0]['data'])->toHaveCount(3);
});

it('MonthlyEvolutionChart sums revenue by month and excludes out-of-range sales', function () {
    $this->actingAs(User::factory()->manager()->create());

    // Create a sale in January 2024
    Sale::factory()->create([
        'status' => Sale::STATUS_CONFIRMED,
        'subtotal' => 250,
        'total' => 250,
        'created_at' => '2024-01-15 12:00:00',
        'updated_at' => '2024-01-15 12:00:00',
        'customer_id' => null,
    ]);
    // Out-of-range sale in March 2024
    Sale::factory()->create([
        'status' => Sale::STATUS_CONFIRMED,
        'subtotal' => 999,
        'total' => 999,
        'created_at' => '2024-03-10 12:00:00',
        'updated_at' => '2024-03-10 12:00:00',
        'customer_id' => null,
    ]);

    $widget = new MonthlyEvolutionChart;
    $widget->pageFilters = ['startDate' => '2024-01-01', 'endDate' => '2024-01-31'];

    $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

    // Only January — one label, one data point
    expect($data['labels'])->toHaveCount(1);
    // The data point should reflect the 250 sale
    expect((float) $data['datasets'][0]['data'][0])->toBe(250.0);
});

it('MonthlyEvolutionChart labels months as M/Y format', function () {
    $this->actingAs(User::factory()->manager()->create());

    $widget = new MonthlyEvolutionChart;
    $widget->pageFilters = ['startDate' => '2024-01-01', 'endDate' => '2024-02-28'];

    $data = (new ReflectionMethod($widget, 'getData'))->invoke($widget);

    expect($data['labels'])->toContain('01/2024')
        ->toContain('02/2024');
});
