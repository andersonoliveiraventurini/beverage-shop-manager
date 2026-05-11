<?php

declare(strict_types=1);

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;


beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->category = Category::create(['slug' => 'agua', 'name' => 'Água', 'active' => true]);
});

it('renders the Products list page', function () {
    livewireTest(ListProducts::class)->assertSuccessful();
});

it('creates a product through the Filament form', function () {
    livewireTest(CreateProduct::class)
        ->fillForm([
            'category_id' => $this->category->id,
            'name' => 'Galão de Água Mineral',
            'brand' => 'Acme',
            'description' => 'Galão retornável',
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('name', 'Galão de Água Mineral')->first())
        ->not->toBeNull()
        ->brand->toBe('Acme');
});

it('edits a product', function () {
    $product = $this->category->products()->create([
        'name' => 'Galão 20L',
        'brand' => 'Acme',
        'active' => true,
    ]);

    livewireTest(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['brand' => 'Acqua Fina'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->brand)->toBe('Acqua Fina');
});

it('renders the variants relation manager on the edit page', function () {
    $product = $this->category->products()->create(['name' => 'Galão 20L', 'active' => true]);

    livewireTest(VariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ])->assertSuccessful();
});

it('creates a variant through the relation manager and uppercases the SKU', function () {
    $product = $this->category->products()->create(['name' => 'Galão 20L', 'active' => true]);

    livewireTest(VariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ])
        ->callTableAction('create', data: [
            'sku' => 'agua-test-20l',
            'size' => '20L',
            'is_returnable' => true,
            'shell_cost' => 30,
            'sale_price' => 15,
            'cost_price' => 9.75,
            'min_stock' => 30,
        ])
        ->assertOk();

    expect($product->variants()->first())
        ->not->toBeNull()
        ->sku->toBe('AGUA-TEST-20L')
        ->is_returnable->toBeTrue();
});

it('exposes the Filament resource navigation metadata', function () {
    expect(ProductResource::getNavigationLabel())->toBe('Produtos')
        ->and(ProductResource::getNavigationGroup())->toBe('Catálogo')
        ->and(ProductResource::getNavigationSort())->toBe(2);
});
