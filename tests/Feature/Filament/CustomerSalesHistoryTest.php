<?php

declare(strict_types=1);

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\SalesRelationManager;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->manager()->create());

    $categoryA = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $productA = $categoryA->products()->create(['name' => 'Galão 20L History Test', 'active' => true]);
    $this->variantA = $productA->variants()->create([
        'sku' => 'HIST-AGUA-20L-'.uniqid(),
        'size' => '20L',
        'is_returnable' => true,
        'shell_cost' => 30,
        'sale_price' => 15.00,
        'min_stock' => 5,
    ]);

    $categoryB = Category::firstOrCreate(['slug' => 'refrigerante'], ['name' => 'Refrigerante', 'active' => true]);
    $productB = $categoryB->products()->create(['name' => 'Coca-Cola 2L History Test', 'active' => true]);
    $this->variantB = $productB->variants()->create([
        'sku' => 'HIST-REFRI-2L-'.uniqid(),
        'size' => '2L',
        'is_returnable' => false,
        'shell_cost' => 0,
        'sale_price' => 8.00,
        'min_stock' => 10,
    ]);

    $this->customer = Customer::factory()->create();
});

it('period filter shows only sales within the selected date range', function () {
    // Sale on January 10
    $saleInRange = Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'subtotal' => 15.00,
        'total' => 15.00,
        'created_at' => '2025-01-10 10:00:00',
    ]);

    // Sale on February 20 — outside filter range
    $saleOutOfRange = Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'subtotal' => 30.00,
        'total' => 30.00,
        'created_at' => '2025-02-20 10:00:00',
    ]);

    livewireTest(SalesRelationManager::class, [
        'ownerRecord' => $this->customer,
        'pageClass' => EditCustomer::class,
    ])
        ->filterTable('period', ['from' => '2025-01-01', 'until' => '2025-01-31'])
        ->assertCanSeeTableRecords([$saleInRange])
        ->assertCanNotSeeTableRecords([$saleOutOfRange]);
});

it('period filter with only "from" date shows sales on and after that date', function () {
    $saleBefore = Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'created_at' => '2025-01-05 10:00:00',
    ]);

    $saleAfter = Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'created_at' => '2025-02-10 10:00:00',
    ]);

    livewireTest(SalesRelationManager::class, [
        'ownerRecord' => $this->customer,
        'pageClass' => EditCustomer::class,
    ])
        ->filterTable('period', ['from' => '2025-02-01', 'until' => null])
        ->assertCanSeeTableRecords([$saleAfter])
        ->assertCanNotSeeTableRecords([$saleBefore]);
});

it('variant filter shows only sales containing the selected product variant', function () {
    // Sale with variantA
    $saleWithA = Sale::factory()->create(['customer_id' => $this->customer->id]);
    $saleWithA->items()->create([
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'modality' => 'full',
    ]);
    $saleWithA->recalculate()->save();

    // Sale with variantB
    $saleWithB = Sale::factory()->create(['customer_id' => $this->customer->id]);
    $saleWithB->items()->create([
        'variant_id' => $this->variantB->id,
        'quantity' => 2,
        'unit_price' => 8.00,
        'modality' => 'full',
    ]);
    $saleWithB->recalculate()->save();

    livewireTest(SalesRelationManager::class, [
        'ownerRecord' => $this->customer,
        'pageClass' => EditCustomer::class,
    ])
        ->filterTable('variant_id', $this->variantA->id)
        ->assertCanSeeTableRecords([$saleWithA])
        ->assertCanNotSeeTableRecords([$saleWithB]);
});

it('both period and variant filters can be combined', function () {
    // Sale in range with variantA
    $saleMatch = Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'created_at' => '2025-03-15 10:00:00',
    ]);
    $saleMatch->items()->create([
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'modality' => 'full',
    ]);

    // Sale in range but with variantB — should not appear when filtering for variantA
    $saleWrongVariant = Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'created_at' => '2025-03-20 10:00:00',
    ]);
    $saleWrongVariant->items()->create([
        'variant_id' => $this->variantB->id,
        'quantity' => 1,
        'unit_price' => 8.00,
        'modality' => 'full',
    ]);

    // Sale with variantA but out of date range
    $saleOutOfRange = Sale::factory()->create([
        'customer_id' => $this->customer->id,
        'created_at' => '2025-05-01 10:00:00',
    ]);
    $saleOutOfRange->items()->create([
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
        'unit_price' => 15.00,
        'modality' => 'full',
    ]);

    livewireTest(SalesRelationManager::class, [
        'ownerRecord' => $this->customer,
        'pageClass' => EditCustomer::class,
    ])
        ->filterTable('period', ['from' => '2025-03-01', 'until' => '2025-03-31'])
        ->filterTable('variant_id', $this->variantA->id)
        ->assertCanSeeTableRecords([$saleMatch])
        ->assertCanNotSeeTableRecords([$saleWrongVariant, $saleOutOfRange]);
});
