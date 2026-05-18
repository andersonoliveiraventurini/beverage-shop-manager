<?php

declare(strict_types=1);

use App\Models\Store;

it('returns the singleton store and creates it on first access with brand defaults', function () {
    expect(Store::count())->toBe(0);

    $store = Store::current();

    expect(Store::count())->toBe(1)
        ->and($store->id)->toBe(1)
        ->and($store->name)->toBe(config('brand.name'))
        ->and($store->city)->toBe(config('brand.address.city'))
        ->and($store->phone_landline)->toBe(config('brand.phones.landline'))
        ->and($store->whatsapp)->toBe(config('brand.phones.whatsapp'));
});

it('does not create a second row on repeated current() calls', function () {
    Store::current();
    Store::current();
    Store::current();

    expect(Store::count())->toBe(1);
});

it('produces a one-line full address from the components', function () {
    $store = Store::current();
    $store->update([
        'street' => 'Av. Transamazônica',
        'number' => '1197',
        'district' => 'Jardim Garcia',
        'city' => 'Campinas',
        'state' => 'SP',
    ]);

    expect($store->fresh()->full_address)->toBe('Av. Transamazônica, 1197 · Jardim Garcia · Campinas–SP');
});
