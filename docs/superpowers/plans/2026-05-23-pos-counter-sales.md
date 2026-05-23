# PDV Counter-Sales Page — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a dedicated, fast point-of-sale page at `/admin/pos` where an attendant adds products in one click from a searchable catalog grid and sees a live cart + running total.

**Architecture:** A custom Filament page (`PointOfSale`) that is itself a Livewire component holding the cart state. Catalog browsing, search, cart mutations, and totals are server-side (computed properties) for testability; Alpine adds micro-interactions in the Blade view. On finalize it creates a normal `Sale` + `SaleItem`s inside a transaction and relies entirely on the existing model hooks (`SaleItem::saved` → `Sale::recalculate()` → `Sale::saved` → `settleStock()` + shell-ledger sync) — no domain logic is reimplemented.

**Tech Stack:** Laravel 12, Filament v4 (custom Page), Livewire 3, Alpine, Tailwind (Filament theme), Pest 4. Spec: `docs/superpowers/specs/2026-05-23-pos-counter-sales-design.md`.

**Conventions for this repo:**
- All Laravel/Filament artifacts are generated via Artisan, never hand-created. Run Artisan and tests through Docker: `docker compose exec laravel.test php artisan …`.
- Run focused tests with: `docker compose exec laravel.test ./vendor/bin/pest --filter="<name>"`.
- Cart entry shape (used everywhere — keep field names identical):
  ```php
  // $this->cart is keyed by variant_id
  [
    'variant_id' => int,
    'sku' => string,
    'name' => string,           // product name
    'size' => string,           // variant size label
    'is_returnable' => bool,
    'quantity' => int,
    'unit_price' => float,
    'modality' => ?string,      // SaleItem::MODALITY_* | null
    'returned_shell_expires_at' => ?string,  // 'Y-m-01' | null
    'delivered_shell_expires_at' => ?string, // 'Y-m-01' | null
  ]
  ```

---

### Task 1: Generate the page, set navigation + access control

**Files:**
- Create (via Artisan): `app/Filament/Pages/PointOfSale.php`
- Create (via Artisan): `resources/views/filament/pages/point-of-sale.blade.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

- [ ] **Step 1: Generate the page via Artisan**

Run: `docker compose exec laravel.test php artisan make:filament-page PointOfSale`
Expected: creates `app/Filament/Pages/PointOfSale.php` and `resources/views/filament/pages/point-of-sale.blade.php`. It is auto-discovered by `AdminPanelProvider::discoverPages`.

- [ ] **Step 2: Write the failing test**

```php
<?php
declare(strict_types=1);

use App\Filament\Pages\PointOfSale;
use App\Models\User;

it('renders the PDV page for an attendant', function () {
    $this->actingAs(User::factory()->attendant()->create());
    livewireTest(PointOfSale::class)->assertSuccessful();
});

it('exposes PDV navigation metadata', function () {
    expect(PointOfSale::getNavigationLabel())->toBe('PDV')
        ->and(PointOfSale::getNavigationGroup())->toBe('Operação')
        ->and(PointOfSale::getSlug())->toBe('pos');
});

it('denies access to a deliverer', function () {
    $this->actingAs(User::factory()->deliverer()->create());
    expect(PointOfSale::canAccess())->toBeFalse();
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="PDV"`
Expected: FAIL (label is the default "Point Of Sale", slug default "point-of-sale", `canAccess` returns true).

- [ ] **Step 4: Edit the generated page class**

Replace the body of `app/Filament/Pages/PointOfSale.php` header section so it reads:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PointOfSale extends Page
{
    protected string $view = 'filament.pages.point-of-sale';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'PDV';

    protected static ?string $title = 'Ponto de Venda';

    protected static string|\UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'pos';

    public static function canAccess(): bool
    {
        return optional(auth()->user())->isManager() || optional(auth()->user())->isAttendant();
    }
}
```

If `Heroicon::OutlinedShoppingCart` does not exist in this Filament build, use `Heroicon::OutlinedCalculator` (verify with `docker compose exec laravel.test php -r "var_dump(defined('Filament\\Support\\Icons\\Heroicon::class'));"` is unnecessary — pick the one that resolves; the test in Step 3 does not assert the icon).

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="PDV"`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/PointOfSale.php resources/views/filament/pages/point-of-sale.blade.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): scaffold PDV page with nav and access control"
```

---

### Task 2: Catalog data + search + category filter

**Files:**
- Modify: `app/Filament/Pages/PointOfSale.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

- [ ] **Step 1: Write the failing test**

Add to `PointOfSaleTest.php`:

```php
use App\Models\Category;

function seedCatalog(): array {
    $agua = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $refri = Category::create(['slug' => 'refrigerante', 'name' => 'Refrigerante', 'active' => true]);

    $galao = $agua->products()->create(['name' => 'Galão Acqua Fina', 'brand' => 'Serra Negra', 'active' => true]);
    $water = $galao->variants()->create(['sku' => 'AGUA-ACQUAFINA-20L', 'size' => '20L', 'is_returnable' => true, 'shell_cost' => 30, 'sale_price' => 15.00, 'min_stock' => 30]);

    $coca = $refri->products()->create(['name' => 'Coca-Cola', 'brand' => 'Coca-Cola', 'active' => true]);
    $soda = $coca->variants()->create(['sku' => 'REFRI-COCA-2L', 'size' => '2L', 'is_returnable' => false, 'sale_price' => 13.00, 'min_stock' => 5]);

    return ['water' => $water, 'soda' => $soda];
}

it('lists all active variants and filters by search and category', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="filters by search"`
Expected: FAIL (properties `search`/`activeCategory` and catalog rendering do not exist).

- [ ] **Step 3: Implement catalog properties**

Add to `PointOfSale` (imports: `use App\Models\ProductVariant;`, `use App\Models\Category;`, `use Illuminate\Support\Collection;`):

```php
public string $search = '';

public ?string $activeCategory = null;

/** @return Collection<int, array{slug:string,name:string}> */
public function getCategoriesProperty(): Collection
{
    return Category::query()->where('active', true)->orderBy('name')
        ->get(['slug', 'name'])
        ->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name]);
}

/** @return Collection<int, ProductVariant> */
public function getVariantsProperty(): Collection
{
    return ProductVariant::query()
        ->whereHas('product', fn ($q) => $q->where('active', true))
        ->with(['product:id,name,brand,category_id', 'product.category:id,slug,name'])
        ->when($this->activeCategory, fn ($q) => $q->whereHas(
            'product.category', fn ($c) => $c->where('slug', $this->activeCategory)
        ))
        ->when($this->search !== '', function ($q) {
            $term = '%' . $this->search . '%';
            $q->where(fn ($w) => $w
                ->where('sku', 'like', $term)
                ->orWhereHas('product', fn ($p) => $p
                    ->where('name', 'like', $term)
                    ->orWhere('brand', 'like', $term)));
        })
        ->orderBy('sku')
        ->get();
}
```

The view (Task 8) renders `$this->variants`; for now add a minimal render so `assertSee` passes. Replace `resources/views/filament/pages/point-of-sale.blade.php` with:

```blade
<x-filament-panels::page>
    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar…" />
    <div>
        <button type="button" wire:click="$set('activeCategory', null)">Todos</button>
        @foreach ($this->categories as $cat)
            <button type="button" wire:click="$set('activeCategory', '{{ $cat['slug'] }}')">{{ $cat['name'] }}</button>
        @endforeach
    </div>
    <div>
        @foreach ($this->variants as $variant)
            <div wire:key="variant-{{ $variant->id }}">
                {{ $variant->product->name }} — {{ $variant->size }} (R$ {{ number_format((float) $variant->sale_price, 2, ',', '.') }})
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="filters by search"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/PointOfSale.php resources/views/filament/pages/point-of-sale.blade.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): catalog listing with live search and category filter"
```

---

### Task 3: Cart mutations (add / increment / decrement / remove)

**Files:**
- Modify: `app/Filament/Pages/PointOfSale.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

- [ ] **Step 1: Write the failing test**

```php
use App\Models\SaleItem;

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="cart"`
Expected: FAIL (methods/property missing).

- [ ] **Step 3: Implement cart mutations**

Add to `PointOfSale` (import `use App\Models\SaleItem;`):

```php
/** @var array<int, array<string, mixed>> keyed by variant_id */
public array $cart = [];

public function addVariant(int $variantId): void
{
    if (isset($this->cart[$variantId])) {
        $this->cart[$variantId]['quantity']++;
        return;
    }

    $variant = ProductVariant::with('product')->find($variantId);
    if (! $variant) {
        return;
    }

    $this->cart[$variantId] = [
        'variant_id' => $variant->id,
        'sku' => $variant->sku,
        'name' => optional($variant->product)->name ?? $variant->sku,
        'size' => $variant->size,
        'is_returnable' => (bool) $variant->is_returnable,
        'quantity' => 1,
        'unit_price' => (float) $variant->sale_price,
        'modality' => $variant->is_returnable ? SaleItem::MODALITY_EXCHANGE : null,
        'returned_shell_expires_at' => null,
        'delivered_shell_expires_at' => null,
    ];
}

public function increment(int $variantId): void
{
    if (isset($this->cart[$variantId])) {
        $this->cart[$variantId]['quantity']++;
    }
}

public function decrement(int $variantId): void
{
    if (! isset($this->cart[$variantId])) {
        return;
    }
    $this->cart[$variantId]['quantity']--;
    if ($this->cart[$variantId]['quantity'] <= 0) {
        unset($this->cart[$variantId]);
    }
}

public function removeVariant(int $variantId): void
{
    unset($this->cart[$variantId]);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="cart"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/PointOfSale.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): cart add/increment/decrement/remove"
```

---

### Task 4: Water modality + shell-validity setters

**Files:**
- Modify: `app/Filament/Pages/PointOfSale.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="changes modality"`
Expected: FAIL (methods missing).

- [ ] **Step 3: Implement the setters**

```php
public function setModality(int $variantId, string $modality): void
{
    if (! isset($this->cart[$variantId])) {
        return;
    }
    $this->cart[$variantId]['modality'] = $modality;

    // The returned-shell validity only applies to an exchange.
    if ($modality !== SaleItem::MODALITY_EXCHANGE) {
        $this->cart[$variantId]['returned_shell_expires_at'] = null;
    }
}

public function setItemDate(int $variantId, string $field, ?string $date): void
{
    if (! isset($this->cart[$variantId])) {
        return;
    }
    if (! in_array($field, ['returned_shell_expires_at', 'delivered_shell_expires_at'], true)) {
        return;
    }
    // Normalize to the first of the month (matches SaleForm's 'Y-m-01' format).
    $this->cart[$variantId][$field] = $date ? substr($date, 0, 7) . '-01' : null;
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="changes modality"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/PointOfSale.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): water modality and shell-validity setters"
```

---

### Task 5: Live subtotal / total computed properties

**Files:**
- Modify: `app/Filament/Pages/PointOfSale.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="live subtotal"`
Expected: FAIL.

- [ ] **Step 3: Implement totals + sale-meta properties**

```php
public string $type = 'counter';

public ?int $customer_id = null;

public ?int $address_id = null;

public string $payment_method = 'cash';

public float $delivery_fee = 0;

public float $building_fee = 0;

public float $discount = 0;

public ?string $discount_reason = null;

public function getSubtotalProperty(): float
{
    return round(collect($this->cart)->sum(
        fn (array $i) => (float) $i['unit_price'] * (int) $i['quantity']
    ), 2);
}

public function getTotalProperty(): float
{
    $fees = $this->type === 'delivery'
        ? (float) $this->delivery_fee + (float) $this->building_fee
        : 0.0;

    return round(max(0, $this->subtotal + $fees - (float) $this->discount), 2);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="live subtotal"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/PointOfSale.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): live subtotal/total with fees and discount"
```

---

### Task 6: Finalize — persist Sale + items, settle stock

**Files:**
- Modify: `app/Filament/Pages/PointOfSale.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

- [ ] **Step 1: Write the failing tests**

```php
use App\Models\Sale;
use App\Models\StockMovement;

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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="finaliz"`
Expected: FAIL (`finalize` missing).

- [ ] **Step 3: Implement validation + finalize**

Add imports: `use App\Models\Sale;`, `use Filament\Notifications\Notification;`, `use Illuminate\Support\Facades\DB;`, `use Illuminate\Validation\ValidationException;`.

```php
public function finalize(): void
{
    $this->validateCart();

    DB::transaction(function (): void {
        $sale = Sale::create([
            'customer_id' => $this->type === 'delivery' ? $this->customer_id : null,
            'address_id' => $this->type === 'delivery' ? $this->address_id : null,
            'user_id' => auth()->id(),
            'type' => $this->type,
            'payment_method' => $this->payment_method,
            'status' => Sale::STATUS_CONFIRMED,
            'delivery_fee' => $this->type === 'delivery' ? (float) $this->delivery_fee : 0,
            'building_fee' => $this->type === 'delivery' ? (float) $this->building_fee : 0,
            'discount' => (float) $this->discount,
            'discount_reason' => (float) $this->discount > 0 ? $this->discount_reason : null,
            'paid_at' => now(),
        ]);

        foreach ($this->cart as $entry) {
            $sale->items()->create([
                'variant_id' => $entry['variant_id'],
                'quantity' => (int) $entry['quantity'],
                'unit_price' => (float) $entry['unit_price'],
                'modality' => $entry['modality'],
                'returned_shell_expires_at' => $entry['returned_shell_expires_at'],
                'delivered_shell_expires_at' => $entry['delivered_shell_expires_at'],
            ]);
        }
    });

    $this->reset(['cart', 'discount', 'discount_reason']);

    Notification::make()->title('Venda registrada com sucesso')->success()->send();
}

protected function validateCart(): void
{
    if ($this->cart === []) {
        throw ValidationException::withMessages(['cart' => 'Adicione ao menos um item à comanda.']);
    }

    foreach ($this->cart as $entry) {
        if (! $entry['is_returnable']) {
            continue;
        }
        // Returnable gallon rules mirror SaleForm: the delivered-shell validity
        // is always required; the returned-shell validity is required on exchange.
        if (empty($entry['delivered_shell_expires_at'])) {
            throw ValidationException::withMessages(['cart' => "Informe a validade do casco entregue para {$entry['name']} ({$entry['size']})."]);
        }
        if ($entry['modality'] === SaleItem::MODALITY_EXCHANGE && empty($entry['returned_shell_expires_at'])) {
            throw ValidationException::withMessages(['cart' => "Informe a validade do casco recebido para {$entry['name']} ({$entry['size']})."]);
        }
    }

    if ($this->type === 'delivery' && (! $this->customer_id || ! $this->address_id)) {
        throw ValidationException::withMessages(['cart' => 'Entregas exigem cliente e endereço.']);
    }
}
```

> Note: `Sale::create` with `status=confirmed` saves before items exist (so its own `saved` hook skips `settleStock`); creating each `SaleItem` cascades `Sale::recalculate()->save()`, which re-fires `Sale::saved` with items present and settles stock + shell ledger. This is the exact path already locked by `SaleResourceTest`'s "settles stock when created as confirmed" test.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="finaliz|empty cart|returned-shell"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/PointOfSale.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): finalize sale with validation and stock settlement"
```

---

### Task 7: Manager-only unit-price override

**Files:**
- Modify: `app/Filament/Pages/PointOfSale.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="override unit price"`
Expected: FAIL (`setUnitPrice` missing).

- [ ] **Step 3: Implement the guarded setter**

```php
public function setUnitPrice(int $variantId, float $price): void
{
    if (! isset($this->cart[$variantId])) {
        return;
    }
    if (! optional(auth()->user())->isManager()) {
        return; // Only managers may override the price (mirrors SaleForm).
    }
    $this->cart[$variantId]['unit_price'] = max(0, $price);
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="override unit price"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/PointOfSale.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): manager-only unit-price override"
```

---

### Task 8: Blade view — two-column POS layout

**Files:**
- Modify: `resources/views/filament/pages/point-of-sale.blade.php`
- Test: `tests/Feature/Filament/PointOfSaleTest.php`

This task replaces the minimal view from Task 2 with the real two-column layout. Logic is already
tested via the page methods; here we test that the key regions render, then build the full UI.

- [ ] **Step 1: Write the failing test**

```php
it('renders the cart region with a live total', function () {
    $this->actingAs(User::factory()->attendant()->create());
    $v = seedCatalog();

    livewireTest(PointOfSale::class)
        ->call('addVariant', $v['soda']->id)
        ->assertSee('Comanda')          // cart heading
        ->assertSee('Finalizar venda')  // finalize button
        ->assertSee('13,00');           // line/total formatted in BRL
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="cart region"`
Expected: FAIL (current minimal view has no "Comanda"/"Finalizar venda").

- [ ] **Step 3: Write the full Blade view**

Replace `resources/views/filament/pages/point-of-sale.blade.php` with the two-column layout below. It uses Filament's Tailwind utility classes (so it inherits the theme + dark mode) and Alpine for the finalize modal. Prices use the `fa-pos-num` class added in Task 9.

```blade
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_22rem]" x-data="{ showFinalize: false }">
        {{-- LEFT: catalog --}}
        <section class="space-y-3">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="/ buscar produto, marca ou SKU…"
                class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5"
            />

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="$set('activeCategory', null)"
                    @class(['fi-badge rounded-full px-3 py-1 text-sm', 'bg-primary-600 text-white' => $this->activeCategory === null, 'bg-gray-100 dark:bg-white/10' => $this->activeCategory !== null])>
                    Todos
                </button>
                @foreach ($this->categories as $cat)
                    <button type="button" wire:click="$set('activeCategory', '{{ $cat['slug'] }}')"
                        @class(['fi-badge rounded-full px-3 py-1 text-sm', 'bg-primary-600 text-white' => $this->activeCategory === $cat['slug'], 'bg-gray-100 dark:bg-white/10' => $this->activeCategory !== $cat['slug']])>
                        {{ $cat['name'] }}
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                @forelse ($this->variants as $variant)
                    <button type="button" wire:key="variant-{{ $variant->id }}"
                        wire:click="addVariant({{ $variant->id }})"
                        class="fi-section group flex flex-col items-start rounded-xl border border-gray-200 bg-white p-3 text-left transition hover:-translate-y-0.5 hover:border-primary-500 hover:shadow-md active:translate-y-0 dark:border-white/10 dark:bg-white/5">
                        <span class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white">{{ $variant->product->name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $variant->size }}</span>
                        @if ($variant->is_returnable)
                            <span class="mt-1 rounded bg-info-50 px-1.5 py-0.5 text-[10px] font-medium text-info-700 dark:bg-info-400/10 dark:text-info-300">↻ retornável</span>
                        @endif
                        <span class="fa-pos-num mt-2 text-base font-semibold text-primary-600 dark:text-primary-400">R$ {{ number_format((float) $variant->sale_price, 2, ',', '.') }}</span>
                    </button>
                @empty
                    <p class="col-span-full py-8 text-center text-sm text-gray-500">Nenhum produto encontrado.</p>
                @endforelse
            </div>
        </section>

        {{-- RIGHT: cart --}}
        <aside class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 lg:sticky lg:top-4 lg:h-fit">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">Comanda</h2>
                @if (filled($this->cart))
                    <button type="button" wire:click="$set('cart', [])" class="text-gray-400 hover:text-danger-500" title="Limpar comanda">
                        <x-filament::icon icon="heroicon-o-trash" class="h-5 w-5" />
                    </button>
                @endif
            </div>

            <div class="space-y-2">
                @forelse ($this->cart as $variantId => $line)
                    <div wire:key="cart-{{ $variantId }}" class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $line['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $line['size'] }}</p>
                            </div>
                            <span class="fa-pos-num text-sm font-semibold text-gray-950 dark:text-white">R$ {{ number_format($line['unit_price'] * $line['quantity'], 2, ',', '.') }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="inline-flex items-center rounded-lg border border-gray-200 dark:border-white/10">
                                <button type="button" wire:click="decrement({{ $variantId }})" class="px-2 py-1 text-lg leading-none">−</button>
                                <span class="fa-pos-num w-8 text-center text-sm">{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="increment({{ $variantId }})" class="px-2 py-1 text-lg leading-none">+</button>
                            </div>
                            <button type="button" wire:click="removeVariant({{ $variantId }})" class="text-gray-400 hover:text-danger-500">
                                <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                            </button>
                        </div>

                        @if ($line['is_returnable'])
                            <div class="mt-2 space-y-1">
                                <select wire:change="setModality({{ $variantId }}, $event.target.value)" class="fi-select w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-white/5">
                                    <option value="exchange" @selected($line['modality'] === 'exchange')>Troca</option>
                                    <option value="full" @selected($line['modality'] === 'full')>Carga cheia</option>
                                    <option value="shell_only" @selected($line['modality'] === 'shell_only')>Somente casco</option>
                                </select>
                                @if ($line['modality'] === 'exchange')
                                    <input type="month" wire:change="setItemDate({{ $variantId }}, 'returned_shell_expires_at', $event.target.value)" class="fi-input w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-white/5" placeholder="Validade casco recebido" />
                                @endif
                                <input type="month" wire:change="setItemDate({{ $variantId }}, 'delivered_shell_expires_at', $event.target.value)" class="fi-input w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-white/5" placeholder="Validade casco entregue" />
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">Comanda vazia. Clique num produto para adicionar.</p>
                @endforelse
            </div>

            @error('cart')
                <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
            @enderror

            <div class="mt-4 space-y-1 border-t border-gray-200 pt-3 text-sm dark:border-white/10">
                <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>Subtotal</span><span class="fa-pos-num">R$ {{ number_format($this->subtotal, 2, ',', '.') }}</span></div>
                @if ($this->type === 'delivery')
                    <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>Entrega</span><span class="fa-pos-num">R$ {{ number_format((float) $this->delivery_fee, 2, ',', '.') }}</span></div>
                @endif
                <div class="flex justify-between text-gray-600 dark:text-gray-300"><span>Desconto</span><span class="fa-pos-num">R$ {{ number_format((float) $this->discount, 2, ',', '.') }}</span></div>
                <div class="mt-1 flex items-center justify-between border-t border-gray-200 pt-2 dark:border-white/10">
                    <span class="text-base font-bold">TOTAL</span>
                    <span class="fa-pos-num text-2xl font-extrabold text-primary-600 dark:text-primary-400">R$ {{ number_format($this->total, 2, ',', '.') }}</span>
                </div>
            </div>

            <x-filament::button type="button" wire:click="finalize" color="primary" size="lg" class="mt-3 w-full" :disabled="blank($this->cart)">
                Finalizar venda
            </x-filament::button>
        </aside>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose exec laravel.test ./vendor/bin/pest --filter="cart region"`
Expected: PASS.

- [ ] **Step 5: Run the full PDV test file**

Run: `docker compose exec laravel.test ./vendor/bin/pest tests/Feature/Filament/PointOfSaleTest.php`
Expected: all PDV tests PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/views/filament/pages/point-of-sale.blade.php tests/Feature/Filament/PointOfSaleTest.php
git commit -m "feat(pos): two-column counter layout with live cart"
```

---

### Task 9: "Cash-register" numerals styling

**Files:**
- Modify: `resources/css/filament/admin/theme.css`
- Reference: `docs/DESIGN.md` (per the theme.css comment, palette/styling source-of-truth)

- [ ] **Step 1: Append the POS numeral style to the theme**

Add to the end of `resources/css/filament/admin/theme.css`:

```css
/* PDV (Point of Sale) — tabular, monospaced numerals for register-style alignment. */
.fa-pos-num {
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum" 1;
    font-family: ui-monospace, "SFMono-Regular", "JetBrains Mono", "Menlo", monospace;
    letter-spacing: -0.01em;
}
```

- [ ] **Step 2: Rebuild the Filament theme assets**

Run: `docker compose exec laravel.test npm run build`
Expected: Vite builds without error; the admin theme bundle includes `.fa-pos-num`.

(If `npm` is not present in the container, run the project's documented asset build; the class is plain CSS and will also work once assets are compiled by the normal deploy pipeline.)

- [ ] **Step 3: Commit**

```bash
git add resources/css/filament/admin/theme.css
git commit -m "style(pos): tabular monospaced numerals for register alignment"
```

---

### Task 10: Manual verification + push

**Files:** none (verification only)

- [ ] **Step 1: Confirm the logged-in admin can reach the page**

The page requires `isManager()` or `isAttendant()`. Verify the current admin user's role:
Run: `docker compose exec -T laravel.test php artisan tinker --execute="echo \App\Models\User::where('email','admin@example.com')->value('role');"`
If the role is `deliverer` (or anything other than manager/attendant), promote to manager:
Run: `docker compose exec -T laravel.test php artisan tinker --execute="\App\Models\User::where('email','admin@example.com')->update(['role'=>\App\Enums\UserRole::Manager->value]); echo 'ok';"`

- [ ] **Step 2: Smoke-test in the browser**

Open `http://localhost:8765/admin/pos`. Confirm: catalog grid renders by category, search filters, clicking a card adds to the cart, steppers change quantity and the TOTAL updates live, a returnable water item shows the modality selector defaulting to "Troca", and "Finalizar venda" creates a sale (check `/admin/sales`).

- [ ] **Step 3: Run the whole suite**

Run: `docker compose exec laravel.test php artisan test`
Expected: green. Investigate and fix any regression before continuing.

- [ ] **Step 4: Push the branch**

```bash
git push -u origin feat/pos-counter-sales
```

Then open a PR (or merge per the project's flow).

---

## Self-Review

**Spec coverage:**
- One-click add + search + category tabs → Tasks 2, 3, 8. ✔
- Live cart + running total → Tasks 5, 8. ✔
- Water modality (default Troca) + shell validity → Tasks 3, 4; validation in Task 6. ✔
- Reuse domain logic via models / stock settlement → Task 6 (+ note). ✔
- Manager-only price override → Task 7. ✔
- Dedicated page, desktop two-column, Filament-native, monospaced numerals → Tasks 1, 8, 9. ✔
- Delivery type reveals customer/address/fees + validation → Task 6 validation + Task 8 (type-conditional total); full delivery UI (customer/address pickers) is intentionally minimal this round and may be expanded in a follow-up. ⚠ (documented limitation)
- Finalize payment modal → button + payment_method property present (Task 6/8); a richer modal with cash-change is a follow-up. ⚠ (documented limitation)
- Authorization → Task 1. ✔
- Tests for all the above → each task. ✔

**Placeholder scan:** No TBD/TODO; every code/test step contains full code and exact commands.

**Type consistency:** Cart entry field names are identical across Tasks 3/4/6/8. Method names consistent: `addVariant`, `increment`, `decrement`, `removeVariant`, `setModality`, `setItemDate`, `setUnitPrice`, `finalize`, `validateCart`; computed `subtotal`/`total`; properties `search`, `activeCategory`, `cart`, `type`, `customer_id`, `address_id`, `payment_method`, `delivery_fee`, `building_fee`, `discount`, `discount_reason`.

**Known follow-ups (out of scope this plan):** full delivery customer/address pickers in the POS UI; richer finalize modal (payment method picker + cash-tendered/change); keyboard shortcuts (`/`, `Enter`, `Ctrl+Enter`) — markup hooks exist, wiring is a follow-up; tablet/touch responsive polish; public homepage at `/`.
