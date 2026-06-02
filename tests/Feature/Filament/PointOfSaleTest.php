<?php

declare(strict_types=1);

use App\Filament\Pages\PointOfSale;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;

/** Seed a minimal two-category catalog: one returnable water gallon + one soda. */
function seedCatalog(): array
{
    $agua = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $refri = Category::create(['slug' => 'refrigerante', 'name' => 'Refrigerante', 'active' => true]);

    $galao = $agua->products()->create(['name' => 'Galão Acqua Fina', 'brand' => 'Serra Negra', 'active' => true]);
    $water = $galao->variants()->create([
        'sku' => 'AGUA-ACQUAFINA-20L', 'size' => '20L', 'is_returnable' => true,
        'shell_cost' => 30, 'sale_price' => 15.00, 'min_stock' => 30,
    ]);

    $coca = $refri->products()->create(['name' => 'Coca-Cola', 'brand' => 'Coca-Cola', 'active' => true]);
    $soda = $coca->variants()->create([
        'sku' => 'REFRI-COCA-2L', 'size' => '2L', 'is_returnable' => false,
        'sale_price' => 13.00, 'min_stock' => 5,
    ]);

    return ['water' => $water, 'soda' => $soda];
}

/* ---- Task 1: page render + nav + access ---- */

it('renders the PDV page for an attendant', function () {
    $this->actingAs(User::factory()->attendant()->create());
    livewireTest(PointOfSale::class)->assertSuccessful();
});

it('exposes PDV navigation metadata', function () {
    expect(PointOfSale::getNavigationLabel())->toBe('PDV')
        ->and(PointOfSale::getNavigationGroup())->toBe('Operação')
        ->and(PointOfSale::getSlug())->toBe('pos');
});

it('denies PDV access to a deliverer', function () {
    $this->actingAs(User::factory()->deliverer()->create());
    expect(PointOfSale::canAccess())->toBeFalse();
});

/* ---- Task 2: catalog listing, search, category filter ---- */

it('lists all active variants and filters by search and category', function () {
    $this->actingAs(User::factory()->attendant()->create());
    seedCatalog();

    livewireTest(PointOfSale::class)
        ->assertSee('Galão Acqua Fina')
        ->assertSee('Coca-Cola')
        ->set('search', 'coca')
        ->assertSee('Coca-Cola')
        ->assertDontSee('Galão Acqua Fina')
        ->set('search', '')
        ->set('activeCategory', 'agua')
        ->assertSee('Galão Acqua Fina')
        ->assertDontSee('Coca-Cola');
});

/* ---- Task 3: cart mutations ---- */

it('adds a variant, increments on re-add, and defaults returnable water to exchange', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->call('addVariant', $v['soda']->id)
        ->call('addVariant', $v['water']->id);

    $cart = $page->get('cart');
    expect($cart[$v['soda']->id]['quantity'])->toBe(2)
        ->and((float) $cart[$v['soda']->id]['unit_price'])->toBe(13.00)
        ->and($cart[$v['soda']->id]['modality'])->toBeNull()
        ->and($cart[$v['water']->id]['quantity'])->toBe(1)
        ->and($cart[$v['water']->id]['modality'])->toBe(SaleItem::MODALITY_EXCHANGE);
});

it('increments, decrements, and removes cart lines', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->call('increment', $v['soda']->id)
        ->call('increment', $v['soda']->id);
    expect($page->get('cart')[$v['soda']->id]['quantity'])->toBe(3);

    $page->call('decrement', $v['soda']->id);
    expect($page->get('cart')[$v['soda']->id]['quantity'])->toBe(2);

    // decrement to zero removes the line
    $page->call('decrement', $v['soda']->id)->call('decrement', $v['soda']->id);
    expect($page->get('cart'))->not->toHaveKey($v['soda']->id);

    // explicit remove
    $page->call('addVariant', $v['water']->id)->call('removeVariant', $v['water']->id);
    expect($page->get('cart'))->toBeEmpty();
});

/* ---- Task 4: modality + shell-validity setters ---- */

it('changes modality and clears the returned-shell date when leaving exchange', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['water']->id)
        ->call('setItemDate', $v['water']->id, 'returned_shell_expires_at', '2027-03-01')
        ->call('setModality', $v['water']->id, SaleItem::MODALITY_FULL);

    $cart = $page->get('cart');
    expect($cart[$v['water']->id]['modality'])->toBe(SaleItem::MODALITY_FULL)
        ->and($cart[$v['water']->id]['returned_shell_expires_at'])->toBeNull();
});

/* ---- Task 5: live subtotal / total ---- */

it('computes live subtotal and total with fees and discount', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['water']->id)   // 15.00
        ->call('increment', $v['water']->id)     // qty 2 -> 30.00
        ->call('addVariant', $v['soda']->id);    // 13.00 -> subtotal 43.00
    expect((float) $page->instance()->subtotal)->toBe(43.00)
        ->and((float) $page->instance()->total)->toBe(43.00);

    $page->set('type', 'delivery')->set('delivery_fee', 2.00)->set('discount', 5.00);
    expect((float) $page->instance()->total)->toBe(40.00); // 43 + 2 - 5
});

/* ---- Task 6: finalize ---- */

it('finalizes a counter sale, persists items, and settles stock', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();
    StockMovement::create([
        'variant_id' => $v['soda']->id,
        'direction' => StockMovement::DIRECTION_IN,
        'reason' => StockMovement::REASON_MANUAL_ADJUST,
        'quantity' => 10,
    ]);

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->call('increment', $v['soda']->id)   // qty 2
        ->set('payment_method', 'cash')
        ->call('finalize')
        ->assertHasNoErrors();

    $sale = Sale::query()->latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and($sale->type)->toBe('counter')
        ->and($sale->status)->toBe('confirmed')
        ->and((float) $sale->subtotal)->toBe(26.00)
        ->and((float) $sale->total)->toBe(26.00)
        ->and($sale->items)->toHaveCount(1)
        ->and($sale->user_id)->not->toBeNull()
        ->and($sale->stock_settled)->toBeTrue()
        ->and($v['soda']->refresh()->current_stock)->toBe(8);
});

it('blocks finalize with an empty cart', function () {
    $this->actingAs(User::factory()->attendant()->create());
    livewireTest(PointOfSale::class)
        ->call('finalize')
        ->assertHasErrors('cart');
    expect(Sale::count())->toBe(0);
});

it('requires the returned-shell validity for an exchange water item', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['water']->id)   // defaults to exchange, no date set
        ->call('finalize')
        ->assertHasErrors('cart');
    expect(Sale::count())->toBe(0);
});

it('finalizes an exchange water item when both validities are present', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['water']->id)
        ->call('setItemDate', $v['water']->id, 'returned_shell_expires_at', '2027-03-01')
        ->call('setItemDate', $v['water']->id, 'delivered_shell_expires_at', '2030-03-01')
        ->call('finalize')
        ->assertHasNoErrors();

    $item = Sale::query()->latest('id')->first()->items->first();
    expect($item->modality)->toBe('exchange')
        ->and($item->returned_shell_expires_at?->format('Y-m'))->toBe('2027-03')
        ->and($item->delivered_shell_expires_at?->format('Y-m'))->toBe('2030-03');
});

/* ---- Task 7: manager-only unit-price override ---- */

it('lets a manager override unit price but ignores it for an attendant', function () {
    $v = seedCatalog();

    // Attendant: override ignored, keeps catalog price.
    $this->actingAs(User::factory()->attendant()->create());
    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->call('setUnitPrice', $v['soda']->id, 99.00);
    expect((float) $page->get('cart')[$v['soda']->id]['unit_price'])->toBe(13.00);

    // Manager: override applied.
    $this->actingAs(User::factory()->manager()->create());
    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->call('setUnitPrice', $v['soda']->id, 11.00);
    expect((float) $page->get('cart')[$v['soda']->id]['unit_price'])->toBe(11.00);
});

it('ignores a client-tampered unit price for an attendant on finalize', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)->call('addVariant', $v['soda']->id);
    // The cart is a public, client-writable property — simulate a tampered payload.
    $cart = $page->get('cart');
    $cart[$v['soda']->id]['unit_price'] = 0.01;
    $page->set('cart', $cart)->call('finalize')->assertHasNoErrors();

    $item = Sale::query()->latest('id')->first()->items->first();
    expect((float) $item->unit_price)->toBe(13.00); // catalog price, not the tampered 0.01
});

it('persists a manager unit-price override on finalize', function () {
    $this->actingAs(User::factory()->manager()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->call('setUnitPrice', $v['soda']->id, 11.00)
        ->call('finalize')
        ->assertHasNoErrors();

    $item = Sale::query()->latest('id')->first()->items->first();
    expect((float) $item->unit_price)->toBe(11.00);
});

/* ---- Follow-up: customer search (locks the phones query) + clearCustomer ---- */

it('finds delivery customers by name and phone number', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $joao = Customer::factory()->create(['name' => 'João Silva']);
    $joao->phones()->create(['number' => '19998887766']);
    Customer::factory()->create(['name' => 'Maria Souza']);

    $page = livewireTest(PointOfSale::class)->call('setType', 'delivery');

    $page->set('customerSearch', 'João');
    expect($page->instance()->customerResults->pluck('name'))
        ->toContain('João Silva')->not->toContain('Maria Souza');

    $page->set('customerSearch', '99888');
    expect($page->instance()->customerResults->pluck('name'))->toContain('João Silva');
});

it('clears the selected customer, address and fees with clearCustomer (stays in delivery)', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $customer = Customer::factory()->create(['has_manual_fee_override' => true, 'delivery_fee' => 4.00]);
    $customer->addresses()->create(['street' => 'Rua C', 'district' => 'JG', 'city' => 'Campinas', 'is_primary' => true]);

    $page = livewireTest(PointOfSale::class)
        ->call('setType', 'delivery')
        ->call('selectCustomer', $customer->id)
        ->call('clearCustomer');

    expect($page->get('customer_id'))->toBeNull()
        ->and($page->get('address_id'))->toBeNull()
        ->and($page->get('type'))->toBe('delivery')
        ->and((float) $page->get('delivery_fee'))->toBe(0.0);
});

/* ---- Task 8: view regions ---- */

it('renders the cart region with a live total', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->assertSee('Comanda')          // cart heading
        ->assertSee('Finalizar venda')  // finalize button
        ->assertSee('13,00');           // line/total formatted in BRL
});

/* ---- Follow-up: delivery customer / address picker ---- */

it('selects a customer for delivery, pre-filling fees and primary address', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $customer = Customer::factory()->create([
        'delivery_fee' => 4.00, 'building_fee' => 1.50, 'has_manual_fee_override' => true,
    ]);
    $address = $customer->addresses()->create([
        'street' => 'Rua A', 'district' => 'JG', 'city' => 'Campinas', 'is_primary' => true,
    ]);

    $page = livewireTest(PointOfSale::class)
        ->call('setType', 'delivery')
        ->call('selectCustomer', $customer->id);

    expect($page->get('customer_id'))->toBe($customer->id)
        ->and((float) $page->get('delivery_fee'))->toBe(4.00)
        ->and((float) $page->get('building_fee'))->toBe(1.50)
        ->and($page->get('address_id'))->toBe($address->id);
});

it('clears the customer, fees and address when switching back to counter', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $customer = Customer::factory()->create(['has_manual_fee_override' => true, 'delivery_fee' => 5.00]);
    $customer->addresses()->create(['street' => 'Rua B', 'district' => 'JG', 'city' => 'Campinas', 'is_primary' => true]);

    $page = livewireTest(PointOfSale::class)
        ->call('setType', 'delivery')
        ->call('selectCustomer', $customer->id)
        ->call('setType', 'counter');

    expect($page->get('customer_id'))->toBeNull()
        ->and($page->get('address_id'))->toBeNull()
        ->and((float) $page->get('delivery_fee'))->toBe(0.0);
});

/* ---- Follow-up: cash-change calculation ---- */

it('computes cash change due only when paying cash', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)   // 13.00
        ->set('payment_method', 'cash')
        ->set('cashTendered', 20.00);
    expect((float) $page->instance()->changeDue)->toBe(7.00);

    $page->set('payment_method', 'pix');
    expect((float) $page->instance()->changeDue)->toBe(0.0);
});

it('computes the cash shortfall only when paying cash and tendered is below the total', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)   // 13.00
        ->set('payment_method', 'cash')
        ->set('cashTendered', 10.00);
    expect((float) $page->instance()->cashShortfall)->toBe(3.00);

    // Enough cash tendered → no shortfall.
    $page->set('cashTendered', 20.00);
    expect((float) $page->instance()->cashShortfall)->toBe(0.0);

    // Non-cash methods never report a shortfall.
    $page->set('payment_method', 'pix')->set('cashTendered', 0.0);
    expect((float) $page->instance()->cashShortfall)->toBe(0.0);
});

/* ---- Hotfix: finalize hardening against tampered public properties ---- */

it('clamps a tampered negative discount to zero on finalize', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)   // subtotal 13.00
        ->set('discount', -50)                 // crafted: a negative discount would overcharge
        ->call('finalize')
        ->assertHasNoErrors();

    $sale = Sale::query()->latest('id')->first();
    expect((float) $sale->discount)->toBe(0.0)
        ->and((float) $sale->total)->toBe(13.00);
});

it('clamps a discount larger than the subtotal down to the subtotal on finalize', function () {
    $this->actingAs(User::factory()->manager()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)   // subtotal 13.00
        ->set('discount', 99999)
        ->call('finalize')
        ->assertHasNoErrors();

    $sale = Sale::query()->latest('id')->first();
    expect((float) $sale->discount)->toBe(13.00)
        ->and((float) $sale->total)->toBe(0.0);
});

it('rejects a tampered cart quantity below 1 on finalize', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    $page = livewireTest(PointOfSale::class)->call('addVariant', $v['soda']->id);
    $cart = $page->get('cart');
    $cart[$v['soda']->id]['quantity'] = 0;     // crafted payload
    $page->set('cart', $cart)->call('finalize')->assertHasErrors('cart');

    expect(Sale::count())->toBe(0);
});

it('rejects a tampered payment_method outside the allow-list on finalize', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->set('payment_method', 'boleto')      // crafted: bypasses the setPaymentMethod() guard
        ->call('finalize')
        ->assertHasErrors('cart');

    expect(Sale::count())->toBe(0);
});

it('re-derives delivery and building fees from the customer for a non-manager on finalize', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();
    // has_manual_fee_override locks the stored fees (otherwise the model recomputes
    // them from DeliverySetting defaults, as the other delivery tests rely on).
    $customer = Customer::factory()->create([
        'delivery_fee' => 4.00, 'building_fee' => 1.50, 'has_manual_fee_override' => true,
    ]);
    $customer->addresses()->create([
        'street' => 'Rua A', 'district' => 'JG', 'city' => 'Campinas', 'is_primary' => true,
    ]);

    livewireTest(PointOfSale::class)
        ->call('setType', 'delivery')
        ->call('selectCustomer', $customer->id)
        ->call('addVariant', $v['soda']->id)
        ->set('delivery_fee', 999)             // crafted: inflated fees ignored for non-managers
        ->set('building_fee', 999)
        ->call('finalize')
        ->assertHasNoErrors();

    $sale = Sale::query()->latest('id')->first();
    expect((float) $sale->delivery_fee)->toBe(4.00)
        ->and((float) $sale->building_fee)->toBe(1.50);
});

/* ---- Follow-up: Enter shortcut (addFirstMatch) ---- */

it('adds the only catalog match via addFirstMatch and ignores ambiguous searches', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    // exactly one match -> added
    $single = livewireTest(PointOfSale::class)->set('search', 'coca')->call('addFirstMatch');
    expect($single->get('cart'))->toHaveKey($v['soda']->id);

    // multiple matches (no search) -> no-op
    $multi = livewireTest(PointOfSale::class)->set('search', '')->call('addFirstMatch');
    expect($multi->get('cart'))->toBeEmpty();
});

/* ---- Follow-up: finalize a delivery sale end-to-end ---- */

it('finalizes a delivery sale with customer and address and creates a delivery', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();
    $customer = Customer::factory()->create(['has_manual_fee_override' => true, 'delivery_fee' => 3.00]);
    $address = $customer->addresses()->create(['street' => 'Rua X', 'district' => 'JG', 'city' => 'Campinas', 'is_primary' => true]);

    livewireTest(PointOfSale::class)
        ->call('setType', 'delivery')
        ->call('selectCustomer', $customer->id)
        ->call('addVariant', $v['soda']->id)
        ->call('finalize')
        ->assertHasNoErrors();

    $sale = Sale::query()->latest('id')->first();
    expect($sale->type)->toBe('delivery')
        ->and($sale->customer_id)->toBe($customer->id)
        ->and($sale->address_id)->toBe($address->id)
        ->and((float) $sale->delivery_fee)->toBe(3.00)
        ->and($sale->delivery)->not->toBeNull();
});
