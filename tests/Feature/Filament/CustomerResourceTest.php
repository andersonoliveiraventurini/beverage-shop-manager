<?php

declare(strict_types=1);

use App\Filament\Pages\Settings;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\PhonesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\SalesRelationManager;
use App\Models\Customer;
use App\Models\DeliverySetting;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the Customers list page', function () {
    livewireTest(ListCustomers::class)->assertSuccessful();
});

it('creates a customer through the Filament form', function () {
    livewireTest(CreateCustomer::class)
        ->fillForm([
            'name' => 'João da Silva',
            'document' => '111.111.111-11',
            'in_delivery_area' => true,
            'delivery_fee' => 2.00,
            'building_fee' => 1.00,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Customer::where('name', 'João da Silva')->first())
        ->not->toBeNull()
        ->document->toBe('111.111.111-11')
        ->in_delivery_area->toBeTrue();
});

it('rejects negative delivery and building fees on the customer form', function () {
    livewireTest(CreateCustomer::class)
        ->fillForm([
            'name' => 'Cliente Inválido',
            'delivery_fee' => -1,
            'building_fee' => -2,
        ])
        ->call('create')
        ->assertHasFormErrors(['delivery_fee', 'building_fee']);
});

it('renders the Sales (purchase-history) relation manager', function () {
    $customer = Customer::factory()->create();

    livewireTest(SalesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])->assertSuccessful();
});

it('renders the Phones relation manager and creates a phone', function () {
    $customer = Customer::factory()->create();

    livewireTest(PhonesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->callTableAction('create', data: [
            'number' => '(19) 98177-8284',
            'label' => 'Celular',
            'is_primary' => true,
        ])
        ->assertOk();

    expect($customer->phones()->count())->toBe(1)
        ->and($customer->phones()->first()->number)->toBe('(19) 98177-8284')
        ->and($customer->phones()->first()->is_primary)->toBeTrue();
});

it('exposes the resource navigation metadata', function () {
    expect(CustomerResource::getNavigationLabel())->toBe('Clientes')
        ->and(CustomerResource::getNavigationGroup())->toBe('Operação')
        ->and(CustomerResource::getRelations())->toHaveCount(4);
});

it('persists has_manual_fee_override when creating a customer through the Filament form', function () {
    livewireTest(CreateCustomer::class)
        ->fillForm([
            'name' => 'Cliente Manual Override',
            'in_delivery_area' => true,
            'delivery_fee' => 15.00,
            'building_fee' => 0.00,
            'has_manual_fee_override' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Customer::where('name', 'Cliente Manual Override')->first())
        ->not->toBeNull()
        ->has_manual_fee_override->toBeTrue();
});

it('renders the ViewCustomer page and shows customer name', function () {
    $customer = Customer::factory()->create(['name' => 'João Infolist']);

    livewireTest(ViewCustomer::class, ['record' => $customer->getKey()])
        ->assertOk()
        ->assertSee('João Infolist');
});

it('shows "Manual" fee source label for customers with manual override', function () {
    $customer = Customer::factory()->create([
        'name' => 'Cliente Taxa Manual',
        'has_manual_fee_override' => true,
    ]);

    livewireTest(ViewCustomer::class, ['record' => $customer->getKey()])
        ->assertOk()
        ->assertSee('Manual');
});

it('shows "Automática" fee source label for customers without manual override', function () {
    $customer = Customer::factory()->create([
        'name' => 'Cliente Taxa Auto',
        'has_manual_fee_override' => false,
    ]);

    livewireTest(ViewCustomer::class, ['record' => $customer->getKey()])
        ->assertOk()
        ->assertSee('Automática');
});

it('recomputeFees skips a customer whose has_manual_fee_override is true', function () {
    $this->actingAs(User::factory()->manager()->create());

    Store::current()->update(['lat' => -22.9099, 'lng' => -47.0626]);
    DeliverySetting::current()->update([
        'radius_km' => 2.00,
        'default_delivery_fee' => 5.00,
        'out_of_area_extra_fee' => 1.00,
        'default_building_fee' => 1.00,
    ]);

    $manualCustomer = Customer::factory()->create([
        'delivery_fee' => 99.00,
        'has_manual_fee_override' => true,
    ]);
    $manualCustomer->addresses()->create([
        'street' => 'Perto', 'district' => 'JG', 'city' => 'Campinas',
        'is_primary' => true, 'lat' => -22.9100, 'lng' => -47.0620,
    ]);

    livewireTest(Settings::class)->call('recomputeFees');

    expect((float) $manualCustomer->fresh()->delivery_fee)->toBe(99.00);
});
