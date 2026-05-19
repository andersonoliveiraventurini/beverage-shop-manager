<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;

function setupDeliveryVariant(): \App\Models\ProductVariant
{
    $cat = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = $cat->products()->create(['name' => 'Galão delivery', 'active' => true]);
    $variant = $product->variants()->create([
        'sku' => 'DELIVERY-' . uniqid(),
        'size' => '20L',
        'is_returnable' => true,
        'sale_price' => 15,
        'min_stock' => 10,
    ]);

    // Seed initial stock so we can confirm + reverse the sale.
    StockMovement::create([
        'variant_id' => $variant->id,
        'direction' => 'in',
        'reason' => 'manual_adjust',
        'quantity' => 50,
    ]);

    return $variant;
}

it('auto-creates a pending Delivery row when a delivery-type sale is confirmed', function () {
    $variant = setupDeliveryVariant();
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

    expect($sale->fresh()->delivery)->not->toBeNull()
        ->and($sale->fresh()->delivery->status)->toBe(Delivery::STATUS_PENDING);
});

it('does NOT create a delivery row for counter sales', function () {
    $variant = setupDeliveryVariant();
    $sale = Sale::create([
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create(['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 15]);

    expect($sale->fresh()->delivery)->toBeNull();
});

it('transitions through start route -> completed', function () {
    $variant = setupDeliveryVariant();
    $deliverer = User::factory()->deliverer()->create();

    $sale = Sale::create([
        'type' => 'delivery',
        'payment_method' => 'pix',
        'status' => 'confirmed',
    ]);
    $sale->items()->create(['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 15]);

    $delivery = $sale->fresh()->delivery;
    expect($delivery->status)->toBe('pending');

    $delivery->startRoute($deliverer);
    expect($delivery->fresh()->status)->toBe('en_route')
        ->and($delivery->fresh()->deliverer_id)->toBe($deliverer->id)
        ->and($delivery->fresh()->started_at)->not->toBeNull();

    $delivery->markCompleted();
    expect($delivery->fresh()->status)->toBe('completed')
        ->and($delivery->fresh()->completed_at)->not->toBeNull();
});

it('reverses the underlying sale stock when the delivery is cancelled', function () {
    $variant = setupDeliveryVariant();

    $sale = Sale::create([
        'type' => 'delivery',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create(['variant_id' => $variant->id, 'quantity' => 4, 'unit_price' => 15]);

    expect($variant->refresh()->current_stock)->toBe(46);

    $sale->fresh()->delivery->cancel('Endereço errado');

    expect($variant->refresh()->current_stock)->toBe(50)
        ->and($sale->fresh()->status)->toBe('cancelled')
        ->and($sale->fresh()->delivery->cancellation_reason)->toBe('Endereço errado');
});

it('does NOT create a duplicate delivery when the sale is touched again', function () {
    $variant = setupDeliveryVariant();
    $sale = Sale::create([
        'type' => 'delivery',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create(['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 15]);

    expect(Delivery::count())->toBe(1);

    $sale->update(['notes' => 'change notes']);
    $sale->update(['notes' => 'change again']);

    expect(Delivery::count())->toBe(1);
});
