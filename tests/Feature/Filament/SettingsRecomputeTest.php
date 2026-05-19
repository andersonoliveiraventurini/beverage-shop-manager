<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use App\Models\Customer;
use App\Models\DeliverySetting;
use App\Models\DeliverySettingRevision;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->manager()->create());

    Store::current()->update(['lat' => -22.9099, 'lng' => -47.0626]);
    DeliverySetting::current()->update([
        'radius_km' => 2.00,
        'default_delivery_fee' => 2.00,
        'out_of_area_extra_fee' => 1.00,
        'default_building_fee' => 1.00,
    ]);
});

it('recomputes every non-override customer and persists a revision', function () {
    // Two customers: one in-area, one out-of-area, one with manual override.
    $inArea = Customer::factory()->create();
    $inArea->addresses()->create([
        'street' => 'Perto', 'district' => 'JG', 'city' => 'Campinas',
        'is_primary' => true, 'lat' => -22.9100, 'lng' => -47.0620,
    ]);

    $outArea = Customer::factory()->create();
    $outArea->addresses()->create([
        'street' => 'Longe', 'district' => 'X', 'city' => 'Campinas',
        'is_primary' => true, 'lat' => -22.96, 'lng' => -47.12,
    ]);

    $manual = Customer::factory()->create([
        'delivery_fee' => 99,
        'has_manual_fee_override' => true,
    ]);
    $manual->addresses()->create([
        'street' => 'Perto', 'district' => 'JG', 'city' => 'Campinas',
        'is_primary' => true, 'lat' => -22.9100, 'lng' => -47.0620,
    ]);

    // Bump the default fee from 2.00 to 5.00 and trigger the recompute.
    DeliverySetting::current()->update(['default_delivery_fee' => 5.00]);

    livewireTest(Settings::class)->call('recomputeFees');

    expect((float) $inArea->fresh()->delivery_fee)->toBe(5.00)
        ->and((float) $outArea->fresh()->delivery_fee)->toBe(6.00) // 5 + 1
        ->and((float) $manual->fresh()->delivery_fee)->toBe(99.00) // preserved
        ->and(DeliverySettingRevision::count())->toBe(1)
        ->and(DeliverySettingRevision::first()->customers_recomputed)->toBe(2)
        ->and(DeliverySettingRevision::first()->customers_skipped)->toBe(1);
});

it('is not exposed to attendants', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(Settings::canAccess())->toBeFalse();
});
