<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\DeliverySetting;
use App\Models\Store;
use App\Services\CustomerFeeCalculator;

beforeEach(function () {
    // Depot at FA's actual coordinates (rough Jardim Garcia, Campinas).
    Store::current()->update([
        'lat' => -22.9099,
        'lng' => -47.0626,
    ]);

    DeliverySetting::current()->update([
        'radius_km' => 2.00,
        'default_delivery_fee' => 2.00,
        'out_of_area_extra_fee' => 1.00,
        'default_building_fee' => 1.00,
    ]);
});

it('charges the default fee for in-area customers without building', function () {
    $customer = Customer::factory()->create();
    $customer->addresses()->create([
        'street' => 'Rua perto', 'district' => 'Jardim Garcia', 'city' => 'Campinas',
        'is_primary' => true, 'is_building' => false,
        'lat' => -22.9100, 'lng' => -47.0620, // <1km from depot
    ]);

    // The CustomerAddress saved hook already recomputed. Read the customer.
    $customer->refresh();

    expect($customer->in_delivery_area)->toBeTrue()
        ->and((float) $customer->delivery_fee)->toBe(2.00)
        ->and((float) $customer->building_fee)->toBe(0.00);
});

it('adds the out-of-area extra fee for customers beyond the radius', function () {
    $customer = Customer::factory()->create();
    $customer->addresses()->create([
        'street' => 'Rua longe', 'district' => 'Outro Bairro', 'city' => 'Campinas',
        'is_primary' => true, 'is_building' => false,
        'lat' => -22.95, 'lng' => -47.10, // ~5km from depot
    ]);

    $customer->refresh();

    expect($customer->in_delivery_area)->toBeFalse()
        ->and((float) $customer->delivery_fee)->toBe(3.00);
});

it('adds the building extra fee for building customers', function () {
    $customer = Customer::factory()->create();
    $customer->addresses()->create([
        'street' => 'Rua perto', 'district' => 'Jardim Garcia', 'city' => 'Campinas',
        'is_primary' => true, 'is_building' => true,
        'lat' => -22.9100, 'lng' => -47.0620,
    ]);

    $customer->refresh();

    expect((float) $customer->delivery_fee)->toBe(2.00)
        ->and((float) $customer->building_fee)->toBe(1.00);
});

it('skips customers with manual override on applyTo()', function () {
    $customer = Customer::factory()->create([
        'delivery_fee' => 99.00,
        'has_manual_fee_override' => true,
    ]);
    $customer->addresses()->create([
        'street' => 'Rua perto', 'district' => 'Jardim Garcia', 'city' => 'Campinas',
        'is_primary' => true, 'is_building' => true,
        'lat' => -22.9100, 'lng' => -47.0620,
    ]);

    $customer->refresh();

    expect((float) $customer->delivery_fee)->toBe(99.00)
        ->and((float) $customer->building_fee)->toBe(0.00); // untouched
});

it('returns null distance when either point lacks coordinates', function () {
    $customer = Customer::factory()->create();
    $customer->addresses()->create([
        'street' => 'Rua sem coords', 'district' => 'X', 'city' => 'Campinas',
        'is_primary' => true,
        // no lat/lng
    ]);

    $customer->refresh();

    // Without distance we conservatively treat the customer as in-area to avoid
    // surprising the attendant with an out-of-area extra fee on save.
    expect($customer->distance_km)->toBeNull()
        ->and($customer->in_delivery_area)->toBeTrue();
});

it('stamps fees_calculated_at when it persists a recompute', function () {
    $customer = Customer::factory()->create();
    $customer->addresses()->create([
        'street' => 'Rua', 'district' => 'X', 'city' => 'Campinas',
        'is_primary' => true,
        'lat' => -22.9100, 'lng' => -47.0620,
    ]);

    $customer->refresh();

    expect($customer->fees_calculated_at)->not->toBeNull();
});
