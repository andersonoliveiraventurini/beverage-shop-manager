<?php

declare(strict_types=1);

use App\Models\Cargo;
use App\Models\CargoItem;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;

function makeVariant(): ProductVariant
{
    $category = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão Cargo', 'active' => true]);

    return $product->variants()->create([
        'sku' => 'CARGO-' . uniqid(),
        'size' => '20L',
        'is_returnable' => true,
        'sale_price' => 15,
        'min_stock' => 10,
    ]);
}

it('records one IN stock_movement per cargo item on save', function () {
    $variant = makeVariant();
    $user = User::factory()->manager()->create();

    $cargo = Cargo::create([
        'supplier' => 'Fornecedor X',
        'received_at' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    $cargo->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 50,
        'purchase_price' => 6.00,
    ]);

    expect($variant->refresh()->current_stock)->toBe(50)
        ->and(StockMovement::query()
            ->where('source_type', CargoItem::class)
            ->where('direction', 'in')
            ->where('reason', 'cargo')
            ->count())->toBe(1);
});

it('does not duplicate the IN movement when the cargo item is touched again', function () {
    $variant = makeVariant();

    $cargo = Cargo::create(['received_at' => now()->toDateString()]);
    $item = $cargo->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 30,
        'purchase_price' => 5.00,
    ]);

    expect($variant->refresh()->current_stock)->toBe(30);

    $item->update(['quantity' => 30]); // no-op-ish update; saved fires again

    expect($variant->refresh()->current_stock)->toBe(30)
        ->and(StockMovement::where('source_id', $item->id)->count())->toBe(1);
});

it('removes the IN movement when the cargo item is deleted', function () {
    $variant = makeVariant();

    $cargo = Cargo::create(['received_at' => now()->toDateString()]);
    $item = $cargo->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 12,
        'purchase_price' => 5.00,
    ]);

    expect($variant->refresh()->current_stock)->toBe(12);

    $item->delete();

    expect($variant->refresh()->current_stock)->toBe(0)
        ->and(StockMovement::where('source_id', $item->id)->count())->toBe(0);
});

it('computes the cargo total from items', function () {
    $variant = makeVariant();

    $cargo = Cargo::create(['received_at' => now()->toDateString()]);
    $cargo->items()->create(['variant_id' => $variant->id, 'quantity' => 10, 'purchase_price' => 6.00]);
    $cargo->items()->create(['variant_id' => $variant->id, 'quantity' => 5, 'purchase_price' => 8.50]);

    expect((float) $cargo->fresh()->total)->toBe(60.0 + 42.5);
});

it('computes weighted-average cost across cargo receipts', function () {
    $variant = makeVariant();

    $cargo = Cargo::create(['received_at' => now()->toDateString()]);
    $cargo->items()->create(['variant_id' => $variant->id, 'quantity' => 100, 'purchase_price' => 6.00]);
    $cargo->items()->create(['variant_id' => $variant->id, 'quantity' => 50,  'purchase_price' => 9.00]);

    // (100*6 + 50*9) / 150 = 1050 / 150 = 7.00
    expect($variant->fresh()->weighted_average_cost)->toBe(7.00);
});

it('falls back to cost_price when the variant has never been received', function () {
    $variant = makeVariant();
    $variant->update(['cost_price' => 4.20]);

    expect($variant->fresh()->weighted_average_cost)->toBe(4.20);
});

it('exposes near_expiry_threshold_days on DeliverySetting with default 30', function () {
    expect(\App\Models\DeliverySetting::nearExpiryThresholdDays())->toBe(30);

    \App\Models\DeliverySetting::current()->update(['near_expiry_threshold_days' => 45]);

    expect(\App\Models\DeliverySetting::nearExpiryThresholdDays())->toBe(45);
});
