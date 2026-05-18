<?php

declare(strict_types=1);

use App\Filament\Pages\DepotConfig;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\WaterShellLedgers\WaterShellLedgerResource;
use App\Models\User;

/*
 * Locks the cross-resource access matrix introduced by Phase A.
 * Updating the matrix (e.g. allowing deliverers to view Sales) means updating
 * this test in the same PR. Each row of the matrix is one expectation.
 */

it('lets the manager view every resource', function () {
    $this->actingAs(User::factory()->manager()->create());

    expect(CategoryResource::canViewAny())->toBeTrue()
        ->and(ProductResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeTrue()
        ->and(SaleResource::canViewAny())->toBeTrue()
        ->and(StockMovementResource::canViewAny())->toBeTrue()
        ->and(WaterShellLedgerResource::canViewAny())->toBeTrue()
        ->and(Settings::canAccess())->toBeTrue()
        ->and(DepotConfig::canAccess())->toBeTrue();
});

it('lets the attendant view operational resources but not settings pages', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(CategoryResource::canViewAny())->toBeTrue()
        ->and(ProductResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeTrue()
        ->and(SaleResource::canViewAny())->toBeTrue()
        ->and(StockMovementResource::canViewAny())->toBeTrue()
        ->and(WaterShellLedgerResource::canViewAny())->toBeTrue()
        ->and(Settings::canAccess())->toBeFalse()
        ->and(DepotConfig::canAccess())->toBeFalse();
});

it('lets the deliverer view only Sales and nothing else', function () {
    $this->actingAs(User::factory()->deliverer()->create());

    expect(SaleResource::canViewAny())->toBeTrue()
        ->and(CategoryResource::canViewAny())->toBeFalse()
        ->and(ProductResource::canViewAny())->toBeFalse()
        ->and(CustomerResource::canViewAny())->toBeFalse()
        ->and(StockMovementResource::canViewAny())->toBeFalse()
        ->and(WaterShellLedgerResource::canViewAny())->toBeFalse()
        ->and(Settings::canAccess())->toBeFalse()
        ->and(DepotConfig::canAccess())->toBeFalse();
});

it('restricts category and product create to the manager', function () {
    $manager = User::factory()->manager()->create();
    $attendant = User::factory()->attendant()->create();

    $this->actingAs($manager);
    expect(CategoryResource::canCreate())->toBeTrue()
        ->and(ProductResource::canCreate())->toBeTrue();

    $this->actingAs($attendant);
    expect(CategoryResource::canCreate())->toBeFalse()
        ->and(ProductResource::canCreate())->toBeFalse();
});

it('lets the attendant create customers and sales', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(CustomerResource::canCreate())->toBeTrue()
        ->and(SaleResource::canCreate())->toBeTrue();
});

it('restricts stock-movement create to the manager and forbids everyone from updating one', function () {
    $manager = User::factory()->manager()->create();
    $attendant = User::factory()->attendant()->create();

    $this->actingAs($manager);
    expect(StockMovementResource::canCreate())->toBeTrue();

    $this->actingAs($attendant);
    expect(StockMovementResource::canCreate())->toBeFalse();
});
