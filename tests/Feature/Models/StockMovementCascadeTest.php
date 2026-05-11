<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;

function setupVariantWithStock(int $initialStock): ProductVariant
{
    $category = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão Test', 'active' => true]);
    $variant = $product->variants()->create([
        'sku' => 'TEST-STOCK-' . uniqid(),
        'size' => '20L',
        'is_returnable' => true,
        'sale_price' => 15,
        'min_stock' => 10,
    ]);

    if ($initialStock > 0) {
        StockMovement::create([
            'variant_id' => $variant->id,
            'direction' => StockMovement::DIRECTION_IN,
            'reason' => StockMovement::REASON_MANUAL_ADJUST,
            'quantity' => $initialStock,
        ]);
    }

    return $variant;
}

it('reports current_stock as the sum of IN minus OUT movements', function () {
    $variant = setupVariantWithStock(50);
    expect($variant->current_stock)->toBe(50);

    StockMovement::create([
        'variant_id' => $variant->id,
        'direction' => 'out',
        'reason' => 'manual_adjust',
        'quantity' => 7,
    ]);

    expect($variant->refresh()->current_stock)->toBe(43);
});

it('flags low stock when current is below min_stock', function () {
    $variant = setupVariantWithStock(5);

    expect($variant->isLowStock())->toBeTrue()
        ->and($variant->refresh()->current_stock)->toBe(5)
        ->and((int) $variant->min_stock)->toBe(10);
});

it('does not move stock while a sale is open', function () {
    $variant = setupVariantWithStock(50);
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'open',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'unit_price' => 15,
    ]);

    expect($variant->refresh()->current_stock)->toBe(50)
        ->and($sale->fresh()->stock_settled)->toBeFalse();
});

it('decrements stock when a sale is confirmed and marks stock_settled', function () {
    $variant = setupVariantWithStock(50);
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'open',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'unit_price' => 15,
    ]);

    $sale->update(['status' => 'confirmed']);

    expect($variant->refresh()->current_stock)->toBe(47)
        ->and($sale->fresh()->stock_settled)->toBeTrue();
});

it('reverses stock when a confirmed sale is cancelled', function () {
    $variant = setupVariantWithStock(50);
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 4,
        'unit_price' => 15,
    ]);

    expect($variant->refresh()->current_stock)->toBe(46)
        ->and($sale->fresh()->stock_settled)->toBeTrue();

    $sale->update(['status' => 'cancelled']);

    expect($variant->refresh()->current_stock)->toBe(50)
        ->and($sale->fresh()->stock_settled)->toBeFalse();
});

it('does not double-decrement when a confirmed sale is saved twice', function () {
    $variant = setupVariantWithStock(50);
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 15,
    ]);

    expect($variant->refresh()->current_stock)->toBe(48);

    // Touching the sale again should NOT generate another OUT movement.
    $sale->update(['notes' => 'updated notes']);

    expect($variant->refresh()->current_stock)->toBe(48)
        ->and(StockMovement::where('source_id', $sale->id)->where('direction', 'out')->count())->toBe(1);
});

it('records every stock change in the stock_movements audit log', function () {
    $variant = setupVariantWithStock(20);
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create(['variant_id' => $variant->id, 'quantity' => 5, 'unit_price' => 15]);
    $sale->update(['status' => 'cancelled']);

    $movements = StockMovement::where('variant_id', $variant->id)->orderBy('id')->get();

    expect($movements)->toHaveCount(3)
        ->and($movements[0]->reason)->toBe('manual_adjust')
        ->and($movements[1]->reason)->toBe('sale')
        ->and($movements[1]->direction)->toBe('out')
        ->and($movements[2]->reason)->toBe('sale_reversal')
        ->and($movements[2]->direction)->toBe('in');
});
