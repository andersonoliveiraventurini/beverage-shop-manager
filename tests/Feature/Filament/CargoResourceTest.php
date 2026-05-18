<?php

declare(strict_types=1);

use App\Filament\Resources\Cargos\CargoResource;
use App\Filament\Resources\Cargos\Pages\CreateCargo;
use App\Filament\Resources\Cargos\Pages\ListCargos;
use App\Filament\Widgets\ExpiringProducts;
use App\Models\Cargo;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;

beforeEach(function () {
    $this->manager = User::factory()->manager()->create();
    $this->actingAs($this->manager);

    $category = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L', 'active' => true]);
    $this->variant = $product->variants()->create([
        'sku' => 'CARGO-FIL-20L',
        'size' => '20L',
        'is_returnable' => true,
        'sale_price' => 15,
        'min_stock' => 10,
    ]);
});

it('renders the Cargos list page for a manager', function () {
    livewireTest(ListCargos::class)->assertSuccessful();
});

it('creates a cargo with items through the Filament form, generating stock movements', function () {
    livewireTest(CreateCargo::class)
        ->fillForm([
            'supplier' => 'Acqua Fina',
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 80,
                    'purchase_price' => 7.50,
                    'expires_at' => now()->addDays(45)->toDateString(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cargo = Cargo::query()->latest('id')->first();

    expect($cargo)->not->toBeNull()
        ->and($cargo->items)->toHaveCount(1)
        ->and($cargo->user_id)->toBe($this->manager->id)
        ->and($this->variant->refresh()->current_stock)->toBe(80)
        ->and(StockMovement::where('reason', 'cargo')->count())->toBe(1);
});

it('blocks attendants from accessing the Cargos resource', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(CargoResource::canViewAny())->toBeFalse()
        ->and(CargoResource::canCreate())->toBeFalse();
});

it('exposes the ExpiringProducts widget to non-deliverer users', function () {
    expect(ExpiringProducts::canView())->toBeTrue();

    $this->actingAs(User::factory()->deliverer()->create());
    expect(ExpiringProducts::canView())->toBeFalse();
});

it('counts cargo items by expiry horizon', function () {
    $cargo = Cargo::create(['received_at' => now()->toDateString()]);
    $cargo->items()->create(['variant_id' => $this->variant->id, 'quantity' => 4,  'purchase_price' => 5, 'expires_at' => now()->subDays(5)->toDateString()]);
    $cargo->items()->create(['variant_id' => $this->variant->id, 'quantity' => 3,  'purchase_price' => 5, 'expires_at' => now()->addDays(10)->toDateString()]);
    $cargo->items()->create(['variant_id' => $this->variant->id, 'quantity' => 7,  'purchase_price' => 5, 'expires_at' => now()->addDays(45)->toDateString()]);
    $cargo->items()->create(['variant_id' => $this->variant->id, 'quantity' => 5,  'purchase_price' => 5, 'expires_at' => now()->addDays(80)->toDateString()]);
    $cargo->items()->create(['variant_id' => $this->variant->id, 'quantity' => 9,  'purchase_price' => 5, 'expires_at' => now()->addDays(200)->toDateString()]);

    $widget = new ExpiringProducts();
    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    expect(array_map(fn ($s) => (int) $s->getValue(), $stats))->toBe([4, 3, 7, 5]);
});

it('exposes write_off as a valid manual stock-movement reason', function () {
    expect(StockMovement::REASON_WRITE_OFF)->toBe('write_off');

    StockMovement::create([
        'variant_id' => $this->variant->id,
        'direction' => 'out',
        'reason' => 'write_off',
        'quantity' => 2,
    ]);

    expect(StockMovement::where('reason', 'write_off')->count())->toBe(1);
});
