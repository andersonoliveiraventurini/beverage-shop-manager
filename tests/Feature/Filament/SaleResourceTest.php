<?php

declare(strict_types=1);

use App\Filament\Resources\Sales\Pages\CreateSale;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L Acqua Fina', 'brand' => 'Test', 'active' => true]);
    $this->variant = $product->variants()->create([
        'sku' => 'AGUA-TEST-20L',
        'size' => '20L',
        'is_returnable' => true,
        'shell_cost' => 30,
        'sale_price' => 15.00,
        'min_stock' => 30,
    ]);
    $this->customer = Customer::factory()->create(['delivery_fee' => 2.00]);
});

it('renders the Sales list page', function () {
    livewireTest(ListSales::class)->assertSuccessful();
});

it('creates a delivery sale with an exchange-modality water gallon item', function () {
    livewireTest(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'type' => 'delivery',
            'payment_method' => 'pix',
            'status' => 'open',
            'delivery_fee' => 2.00,
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 2,
                    'unit_price' => 15.00,
                    'modality' => SaleItem::MODALITY_EXCHANGE,
                    'returned_shell_expires_at' => '2027-03-01',
                    'delivered_shell_expires_at' => '2030-03-01',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::query()->latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and((float) $sale->subtotal)->toBe(30.00)
        ->and((float) $sale->total)->toBe(32.00)
        ->and($sale->contains_water)->toBeTrue()
        ->and($sale->items)->toHaveCount(1);

    $item = $sale->items->first();
    expect($item->modality)->toBe('exchange')
        ->and($item->returned_shell_expires_at?->format('Y-m'))->toBe('2027-03')
        ->and($item->delivered_shell_expires_at?->format('Y-m'))->toBe('2030-03');
});

it('creates a full-modality sale capturing only the delivered shell validity', function () {
    livewireTest(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'type' => 'counter',
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 1,
                    'unit_price' => 15.00,
                    'modality' => SaleItem::MODALITY_FULL,
                    'delivered_shell_expires_at' => '2030-03-01',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = Sale::query()->latest('id')->first()->items->first();

    expect($item->modality)->toBe('full')
        ->and($item->returned_shell_expires_at)->toBeNull()
        ->and($item->delivered_shell_expires_at?->format('Y-m'))->toBe('2030-03');
});

it('edits a sale and recomputes total when discount is added', function () {
    $sale = Sale::create([
        'customer_id' => $this->customer->id,
        'type' => 'delivery',
        'payment_method' => 'pix',
        'status' => 'open',
        'delivery_fee' => 2.00,
    ]);
    $sale->items()->create([
        'variant_id' => $this->variant->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'modality' => SaleItem::MODALITY_FULL,
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    livewireTest(EditSale::class, ['record' => $sale->getRouteKey()])
        ->fillForm([
            'discount' => 3.00,
            'discount_reason' => 'Cliente fiel',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $sale->fresh()->total)->toBe(14.00)
        ->and($sale->fresh()->discount_reason)->toBe('Cliente fiel');
});

// Locks the contract: when Filament creates a Sale with status=confirmed,
// the parent saves *before* its items exist (Sale::saved sees zero items
// and skips settleStock). The SaleItem::saved cascade is what eventually
// retriggers Sale::saved with items present — this test prevents any
// future refactor from breaking that path silently.
it('settles stock when a sale is created already as confirmed via the Filament form', function () {
    StockMovement::create([
        'variant_id' => $this->variant->id,
        'direction' => StockMovement::DIRECTION_IN,
        'reason' => StockMovement::REASON_MANUAL_ADJUST,
        'quantity' => 10,
    ]);
    expect($this->variant->refresh()->current_stock)->toBe(10);

    livewireTest(CreateSale::class)
        ->fillForm([
            'customer_id' => $this->customer->id,
            'type' => 'counter',
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'quantity' => 3,
                    'unit_price' => 15.00,
                    'modality' => SaleItem::MODALITY_FULL,
                    'delivered_shell_expires_at' => '2030-03-01',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sale = Sale::query()->latest('id')->first();

    expect($sale->stock_settled)->toBeTrue()
        ->and($this->variant->refresh()->current_stock)->toBe(7)
        ->and(StockMovement::query()
            ->where('source_type', SaleItem::class)
            ->where('source_id', $sale->items->first()->id)
            ->where('direction', StockMovement::DIRECTION_OUT)
            ->where('reason', StockMovement::REASON_SALE)
            ->count())->toBe(1);
});

it('exposes the resource navigation metadata', function () {
    expect(SaleResource::getNavigationLabel())->toBe('Vendas')
        ->and(SaleResource::getNavigationGroup())->toBe('Operação');
});
