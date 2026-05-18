<?php

declare(strict_types=1);

use App\Filament\Pages\DepotConfig;
use App\Models\Store;
use App\Models\User;

it('renders the depot config page for a manager', function () {
    $this->actingAs(User::factory()->manager()->create());

    livewireTest(DepotConfig::class)->assertSuccessful();
});

it('hides the depot config page from attendants', function () {
    $this->actingAs(User::factory()->attendant()->create());

    expect(DepotConfig::canAccess())->toBeFalse();
});

it('hides the depot config page from deliverers', function () {
    $this->actingAs(User::factory()->deliverer()->create());

    expect(DepotConfig::canAccess())->toBeFalse();
});

it('persists changes through the save action', function () {
    $this->actingAs(User::factory()->manager()->create());

    livewireTest(DepotConfig::class)
        ->fillForm([
            'name' => 'FA Distribuidora — Filial 1',
            'street' => 'Av. Transamazônica',
            'number' => '1197',
            'district' => 'Jardim Garcia',
            'city' => 'Campinas',
            'state' => 'SP',
            'lat' => -22.9099,
            'lng' => -47.0626,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Store::current()->fresh())
        ->name->toBe('FA Distribuidora — Filial 1')
        ->number->toBe('1197')
        ->and((float) Store::current()->fresh()->lat)->toBe(-22.9099);
});
