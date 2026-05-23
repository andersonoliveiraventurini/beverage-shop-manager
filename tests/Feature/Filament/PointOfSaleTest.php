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
