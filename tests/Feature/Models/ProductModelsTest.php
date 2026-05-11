<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;

it('soft-deletes a category and keeps the row in the table', function () {
    $category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);

    $category->delete();

    expect(Category::query()->find($category->id))->toBeNull()
        ->and(Category::withTrashed()->find($category->id))->not->toBeNull()
        ->and($category->fresh()->trashed())->toBeTrue();
});

it('soft-deletes a product without removing its category', function () {
    $category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L', 'brand' => 'Acme', 'active' => true]);

    $product->delete();

    expect($product->fresh()->trashed())->toBeTrue()
        ->and(Category::query()->find($category->id))->not->toBeNull();
});

it('casts variant prices and flags correctly', function () {
    $category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L', 'active' => true]);

    $variant = $product->variants()->create([
        'sku' => 'AGUA-TEST-20L',
        'size' => '20L',
        'is_returnable' => 1,
        'shell_cost' => 30,
        'sale_price' => 15.5,
        'cost_price' => 10,
        'min_stock' => 30,
    ]);

    $fresh = $variant->fresh();

    expect($fresh->is_returnable)->toBeTrue()
        ->and((string) $fresh->sale_price)->toBe('15.50')
        ->and((string) $fresh->shell_cost)->toBe('30.00')
        ->and($fresh->min_stock)->toBe(30);
});

it('exposes the expected relationships', function () {
    $category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L', 'active' => true]);
    $product->variants()->create([
        'sku' => 'AGUA-REL-20L',
        'size' => '20L',
        'sale_price' => 15,
    ]);

    expect($category->products)->toHaveCount(1)
        ->and($product->category->is($category))->toBeTrue()
        ->and($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->product->is($product))->toBeTrue();
});

it('enforces a unique SKU across variants', function () {
    $category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L', 'active' => true]);
    $product->variants()->create(['sku' => 'AGUA-UNIQUE-20L', 'size' => '20L', 'sale_price' => 15]);

    expect(fn () => $product->variants()->create(['sku' => 'AGUA-UNIQUE-20L', 'size' => '20L', 'sale_price' => 15]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
