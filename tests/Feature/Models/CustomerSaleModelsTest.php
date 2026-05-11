<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;

function makeAguaVariant(array $variant = []): ProductVariant
{
    $category = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = Product::create(['category_id' => $category->id, 'name' => 'Galão 20L Test', 'active' => true]);

    return ProductVariant::create(array_merge([
        'product_id' => $product->id,
        'sku' => 'AGUA-TEST-20L-' . uniqid(),
        'size' => '20L',
        'is_returnable' => true,
        'shell_cost' => 30,
        'sale_price' => 15.00,
        'min_stock' => 30,
    ], $variant));
}

function makeNonWaterVariant(array $variant = []): ProductVariant
{
    $category = Category::firstOrCreate(['slug' => 'refrigerante'], ['name' => 'Refrigerante', 'active' => true]);
    $product = Product::create(['category_id' => $category->id, 'name' => 'Refri 2L Test', 'active' => true]);

    return ProductVariant::create(array_merge([
        'product_id' => $product->id,
        'sku' => 'REFRI-TEST-2L-' . uniqid(),
        'size' => '2L',
        'is_returnable' => false,
        'sale_price' => 10.00,
    ], $variant));
}

it('soft-deletes a customer', function () {
    $customer = Customer::factory()->create();

    $customer->delete();

    expect($customer->fresh()->trashed())->toBeTrue()
        ->and(Customer::query()->find($customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->id))->not->toBeNull();
});

it('cascades phones when a customer is force-deleted', function () {
    $customer = Customer::factory()->create();
    $customer->phones()->create(['number' => '(19) 99999-0000', 'is_primary' => true]);

    expect($customer->phones)->toHaveCount(1);

    $customer->forceDelete();

    expect(\App\Models\CustomerPhone::query()->where('customer_id', $customer->id)->count())->toBe(0);
});

it('recalculates subtotal, total and contains_water flag when items change', function () {
    $variant = makeAguaVariant();
    $customer = Customer::factory()->create(['delivery_fee' => 2.00]);

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'delivery',
        'payment_method' => 'pix',
        'status' => 'open',
        'delivery_fee' => 2.00,
    ]);

    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'unit_price' => 15.00,
        'modality' => SaleItem::MODALITY_EXCHANGE,
        'returned_shell_expires_at' => '2027-03-01',
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    $sale->refresh();

    expect((float) $sale->subtotal)->toBe(45.00)
        ->and((float) $sale->total)->toBe(47.00)
        ->and($sale->contains_water)->toBeTrue();
});

it('keeps contains_water false when no water product is in the cart', function () {
    $variant = makeNonWaterVariant();
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'open',
    ]);

    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 10.00,
    ]);

    $sale->refresh();

    expect((float) $sale->subtotal)->toBe(20.00)
        ->and((float) $sale->total)->toBe(20.00)
        ->and($sale->contains_water)->toBeFalse();
});

it('snapshots both gallon validities for an exchange item', function () {
    $variant = makeAguaVariant();
    $customer = Customer::factory()->create();
    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'delivery',
        'payment_method' => 'pix',
        'status' => 'open',
    ]);

    $item = $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'modality' => SaleItem::MODALITY_EXCHANGE,
        'returned_shell_expires_at' => '2027-03-01',
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    $fresh = $item->fresh();

    expect($fresh->modality)->toBe('exchange')
        ->and($fresh->returned_shell_expires_at?->format('Y-m'))->toBe('2027-03')
        ->and($fresh->delivered_shell_expires_at?->format('Y-m'))->toBe('2030-03');
});

it('updates total when a sale fee changes without item changes', function () {
    $variant = makeAguaVariant();
    $customer = Customer::factory()->create();
    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'delivery',
        'payment_method' => 'pix',
        'status' => 'open',
        'delivery_fee' => 2.00,
    ]);

    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'modality' => SaleItem::MODALITY_FULL,
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    $sale->refresh();
    expect((float) $sale->total)->toBe(17.00);

    $sale->delivery_fee = 5.00;
    $sale->save();

    expect((float) $sale->refresh()->total)->toBe(20.00);
});
