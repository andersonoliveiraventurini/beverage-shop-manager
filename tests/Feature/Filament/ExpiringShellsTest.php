<?php

declare(strict_types=1);

use App\Filament\Resources\WaterShellLedgers\Pages\ListWaterShellLedgers;
use App\Filament\Resources\WaterShellLedgers\WaterShellLedgerResource;
use App\Filament\Widgets\ExpiringShells;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliverySetting;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WaterShellLedger;

function makeWaterVariant(): ProductVariant
{
    $category = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão Vencimentos', 'active' => true]);
    return $product->variants()->create([
        'sku' => 'AGUA-VENC-20L-' . uniqid(),
        'size' => '20L',
        'is_returnable' => true,
        'sale_price' => 15,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    DeliverySetting::current()->update(['track_water_shells' => true]);

    $this->variant = makeWaterVariant();
    $this->customer = Customer::factory()->create();

    // Seed several ledger rows across horizons.
    $rows = [
        ['offset' => -15, 'count' => 4],   // expired
        ['offset' => 10,  'count' => 3],   // 0–30
        ['offset' => 45,  'count' => 7],   // 30–60
        ['offset' => 80,  'count' => 5],   // 60–90
        ['offset' => 200, 'count' => 9],   // beyond 90
    ];
    foreach ($rows as $r) {
        WaterShellLedger::create([
            'customer_id' => $this->customer->id,
            'variant_id' => $this->variant->id,
            'expires_at' => now()->addDays($r['offset'])->toDateString(),
            'shell_count' => $r['count'],
            'last_out_at' => now(),
        ]);
    }
});

it('reports the correct counts in each horizon bucket', function () {
    $widget = new ExpiringShells();
    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    $values = array_map(fn ($s) => (int) $s->getValue(), $stats);

    expect($values)->toBe([4, 3, 7, 5]);
});

it('hides the widget when shell tracking is off', function () {
    DeliverySetting::current()->update(['track_water_shells' => false]);

    expect(ExpiringShells::canView())->toBeFalse();
});

it('shows the widget when shell tracking is on', function () {
    expect(ExpiringShells::canView())->toBeTrue();
});

it('renders the Vencimentos list page', function () {
    livewireTest(ListWaterShellLedgers::class)->assertSuccessful();
});

it('hides the Vencimentos resource from navigation when tracking is off', function () {
    DeliverySetting::current()->update(['track_water_shells' => false]);

    expect(WaterShellLedgerResource::shouldRegisterNavigation())->toBeFalse();
});

it('shows the Vencimentos resource in navigation when tracking is on', function () {
    expect(WaterShellLedgerResource::shouldRegisterNavigation())->toBeTrue();
});

it('cannot create ledger rows from the UI (read-only)', function () {
    expect(WaterShellLedgerResource::canCreate())->toBeFalse();
});

it('filters the table by horizon = 30 days', function () {
    livewireTest(ListWaterShellLedgers::class)
        ->filterTable('horizon', '30')
        ->assertCanSeeTableRecords(
            WaterShellLedger::query()
                ->whereDate('expires_at', '>=', now())
                ->whereDate('expires_at', '<=', now()->addDays(30))
                ->where('shell_count', '>', 0)
                ->get()
        );
});
