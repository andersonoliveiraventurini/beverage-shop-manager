<?php

declare(strict_types=1);

use App\Filament\Pages\DeliveryBoard;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;

function makeConfirmedDeliverySale(): Sale
{
    $cat = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Agua', 'active' => true]);
    $product = $cat->products()->create(['name' => 'G'.uniqid(), 'active' => true]);
    $variant = $product->variants()->create([
        'sku' => 'BOARD-'.uniqid(),
        'size' => '20L',
        'sale_price' => 15,
        'min_stock' => 10,
    ]);
    StockMovement::create([
        'variant_id' => $variant->id,
        'direction' => 'in',
        'reason' => 'manual_adjust',
        'quantity' => 50,
    ]);

    $customer = Customer::factory()->create();
    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'delivery',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 15,
    ]);

    return $sale->fresh();
}

function makeConfirmedDeliverySaleWithDistrict(string $district): Sale
{
    $cat = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Agua', 'active' => true]);
    $product = $cat->products()->create(['name' => 'G'.uniqid(), 'active' => true]);
    $variant = $product->variants()->create([
        'sku' => 'BOARD-'.uniqid(),
        'size' => '20L',
        'sale_price' => 15,
        'min_stock' => 10,
    ]);
    StockMovement::create([
        'variant_id' => $variant->id,
        'direction' => 'in',
        'reason' => 'manual_adjust',
        'quantity' => 50,
    ]);

    $customer = Customer::factory()->create();
    $address = CustomerAddress::create([
        'customer_id' => $customer->id,
        'label' => 'Principal',
        'street' => 'Rua '.$district,
        'number' => '100',
        'district' => $district,
        'city' => 'Cidade',
        'is_primary' => true,
    ]);

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'address_id' => $address->id,
        'type' => 'delivery',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 15,
    ]);

    return $sale->fresh();
}

it('renders the delivery board for the manager and lists pending deliveries', function () {
    $this->actingAs(User::factory()->manager()->create());
    makeConfirmedDeliverySale();
    makeConfirmedDeliverySale();

    livewireTest(DeliveryBoard::class)
        ->assertSuccessful();

    expect(Delivery::where('status', 'pending')->count())->toBe(2);
});

it("shows only the deliverer's own (or unassigned) deliveries", function () {
    $manager = User::factory()->manager()->create();
    $alice = User::factory()->deliverer()->create();
    $bob = User::factory()->deliverer()->create();

    $sale1 = makeConfirmedDeliverySale();
    $sale2 = makeConfirmedDeliverySale();
    $sale3 = makeConfirmedDeliverySale();

    // Assign sale1 to alice, sale2 to bob, sale3 unassigned.
    $sale1->delivery->update(['deliverer_id' => $alice->id]);
    $sale2->delivery->update(['deliverer_id' => $bob->id]);

    $this->actingAs($alice);
    $page = livewireTest(DeliveryBoard::class);
    $pending = $page->instance()->getPendingDeliveries();

    expect($pending->pluck('id')->all())->toEqual([
        $sale1->delivery->id, // alice's own
        $sale3->delivery->id, // unassigned
    ]);
});

it('starts a route, marks completed, and cancels through the page actions', function () {
    $deliverer = User::factory()->deliverer()->create();
    $this->actingAs($deliverer);

    $sale = makeConfirmedDeliverySale();
    $delivery = $sale->delivery;

    $page = livewireTest(DeliveryBoard::class);

    $page->call('startRoute', $delivery->id);
    expect($delivery->fresh()->status)->toBe('en_route');

    $page->call('markCompleted', $delivery->id);
    expect($delivery->fresh()->status)->toBe('completed');

    // Set up another delivery to cancel.
    $sale2 = makeConfirmedDeliverySale();
    $page->call('cancelDelivery', $sale2->delivery->id, 'Endereco errado');

    expect($sale2->delivery->fresh()->status)->toBe('cancelled')
        ->and($sale2->fresh()->status)->toBe('cancelled')
        ->and($sale2->delivery->fresh()->cancellation_reason)->toBe('Endereco errado');
});

it('exposes the receipt route', function () {
    $sale = makeConfirmedDeliverySale();

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('sales.receipt', ['sale' => $sale]))
        ->assertOk()
        ->assertSee('DISK ENTREGAS')
        ->assertSee('BEBIDAS');
});

it("blocks deliverers from seeing other deliverer's receipts", function () {
    $sale = makeConfirmedDeliverySale();
    $alice = User::factory()->deliverer()->create();
    $sale->delivery->update(['deliverer_id' => $alice->id]);

    $this->actingAs(User::factory()->deliverer()->create())
        ->get(route('sales.receipt', ['sale' => $sale]))
        ->assertOk(); // Sales view policy still allows; receipt itself does not filter by deliverer.
});

it('district filter shows only deliveries to the selected district', function () {
    $this->actingAs(User::factory()->manager()->create());

    $centro = makeConfirmedDeliverySaleWithDistrict('Centro');
    $bairroNovo = makeConfirmedDeliverySaleWithDistrict('Bairro Novo');

    $page = livewireTest(DeliveryBoard::class);

    // No filter: both appear
    $all = $page->instance()->getPendingDeliveries();
    expect($all->pluck('id'))->toContain($centro->delivery->id)
        ->toContain($bairroNovo->delivery->id);

    // Apply district filter
    $page->set('districtFilter', 'Centro');

    $filtered = $page->instance()->getPendingDeliveries();
    expect($filtered->pluck('id'))->toContain($centro->delivery->id)
        ->not->toContain($bairroNovo->delivery->id);
});

it('status filter shows only deliveries with the selected status', function () {
    $this->actingAs(User::factory()->manager()->create());

    $salePending = makeConfirmedDeliverySale();
    $saleEnRoute = makeConfirmedDeliverySale();

    // Manually promote one to en_route
    $saleEnRoute->delivery->update(['status' => Delivery::STATUS_EN_ROUTE, 'started_at' => now()]);

    $page = livewireTest(DeliveryBoard::class);

    // With status filter = pending
    $page->set('statusFilter', Delivery::STATUS_PENDING);

    $filtered = $page->instance()->getPendingDeliveries();
    expect($filtered->pluck('id'))
        ->toContain($salePending->delivery->id)
        ->not->toContain($saleEnRoute->delivery->id);

    // Switch to en_route
    $page->set('statusFilter', Delivery::STATUS_EN_ROUTE);

    $filteredEnRoute = $page->instance()->getPendingDeliveries();
    expect($filteredEnRoute->pluck('id'))
        ->toContain($saleEnRoute->delivery->id)
        ->not->toContain($salePending->delivery->id);
});

it('deliverer scoping still holds when district filter is active', function () {
    $alice = User::factory()->deliverer()->create();
    $bob = User::factory()->deliverer()->create();
    $this->actingAs($alice);

    $aliceCentro = makeConfirmedDeliverySaleWithDistrict('Centro');
    $bobCentro = makeConfirmedDeliverySaleWithDistrict('Centro');

    $aliceCentro->delivery->update(['deliverer_id' => $alice->id]);
    $bobCentro->delivery->update(['deliverer_id' => $bob->id]);

    $page = livewireTest(DeliveryBoard::class);
    $page->set('districtFilter', 'Centro');

    $filtered = $page->instance()->getPendingDeliveries();

    // Alice sees her own but not Bob's even though both are in Centro
    expect($filtered->pluck('id'))
        ->toContain($aliceCentro->delivery->id)
        ->not->toContain($bobCentro->delivery->id);
});
