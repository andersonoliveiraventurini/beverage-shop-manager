<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Database\Seeders\ProductCatalogSeeder;

it('seeds the full catalog from ProductCatalogSeeder', function () {
    $this->seed(ProductCatalogSeeder::class);

    expect(Category::count())->toBeGreaterThanOrEqual(19)
        ->and(Product::count())->toBeGreaterThan(100)
        ->and(ProductVariant::count())->toBeGreaterThan(140);

    $agua20l = ProductVariant::where('sku', 'AGUA-ACQUAFINA-20L')->first();
    expect($agua20l)->not->toBeNull()
        ->and($agua20l->is_returnable)->toBeTrue()
        ->and((string) $agua20l->sale_price)->toBe('15.00')
        ->and((string) $agua20l->shell_cost)->toBe('30.00');

    $heineken = ProductVariant::where('sku', 'CERV-HEINEKEN-LN-330ML')->first();
    expect($heineken)->not->toBeNull()
        ->and($heineken->is_returnable)->toBeFalse()
        ->and((string) $heineken->sale_price)->toBe('9.00');
});

it('runs idempotently when seeded twice', function () {
    $this->seed(ProductCatalogSeeder::class);
    $countAfterFirstRun = ProductVariant::count();

    $this->seed(ProductCatalogSeeder::class);

    expect(ProductVariant::count())->toBe($countAfterFirstRun);
});

it('derives cost_price from sale_price when seeder omits it', function () {
    $this->seed(ProductCatalogSeeder::class);

    $variant = ProductVariant::where('sku', 'REFRI-COCA-200ML')->first();

    expect($variant)->not->toBeNull()
        ->and((float) $variant->cost_price)->toBe(round(((float) $variant->sale_price) * 0.65, 2));
});
