<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Services\AddressGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('translates a complete address to lat/lng through Nominatim', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '-22.9099',
            'lon' => '-47.0626',
        ]], 200),
    ]);

    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create([
        'street' => 'Av. Transamazônica', 'number' => '1197',
        'district' => 'Jardim Garcia', 'city' => 'Campinas', 'state' => 'SP',
        'is_primary' => false,
    ]);

    $coords = (new AddressGeocoder())->lookup($address);

    expect($coords)->toBe(['lat' => -22.9099, 'lng' => -47.0626]);
    Http::assertSent(fn ($r) => str_contains((string) $r->url(), 'nominatim.openstreetmap.org/search')
        && str_contains((string) $r->url(), '1197'));
});

it('returns null when Nominatim has no results', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create([
        'street' => 'Rua inexistente', 'district' => 'X', 'city' => 'Campinas', 'state' => 'SP',
        'is_primary' => false,
    ]);

    expect((new AddressGeocoder())->lookup($address))->toBeNull();
});

it('returns null when the address is missing required components', function () {
    Http::fake();

    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create([
        'street' => '', 'district' => '', 'city' => '', 'state' => '',
        'is_primary' => false,
    ]);

    expect((new AddressGeocoder())->lookup($address))->toBeNull();
    Http::assertNothingSent();
});

it('caches the lookup result so repeat calls do not hit Nominatim', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '-22.9099', 'lon' => '-47.0626',
        ]], 200),
    ]);

    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create([
        'street' => 'Av. Cached', 'district' => 'X', 'city' => 'Campinas', 'state' => 'SP',
        'is_primary' => false,
    ]);

    $g = new AddressGeocoder();
    $g->lookup($address);
    $g->lookup($address);
    $g->lookup($address);

    Http::assertSentCount(1);
});

it('fills the address coords through ->fill() but respects existing values', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([[
            'lat' => '-22.91', 'lon' => '-47.07',
        ]], 200),
    ]);

    $customer = Customer::factory()->create();
    $address = $customer->addresses()->create([
        'street' => 'Av. Fill', 'district' => 'X', 'city' => 'Campinas', 'state' => 'SP',
        'is_primary' => false,
    ]);

    $g = new AddressGeocoder();
    expect($g->fill($address))->toBeTrue()
        ->and((float) $address->fresh()->lat)->toBe(-22.91)
        ->and((float) $address->fresh()->lng)->toBe(-47.07);

    // Second call must not overwrite existing coords.
    expect($g->fill($address->fresh()))->toBeFalse();
});
