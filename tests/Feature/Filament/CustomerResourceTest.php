<?php

declare(strict_types=1);

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\RelationManagers\PhonesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\SalesRelationManager;
use App\Models\Customer;
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
