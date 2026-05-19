<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;

it('records an audit row through AuditLog::record', function () {
    $this->actingAs($user = User::factory()->manager()->create());
    $customer = Customer::factory()->create();

    AuditLog::record($customer, AuditLog::EVENT_FEE_OVERRIDE, 'delivery_fee', 2.00, 5.00, 'campaign promo');

    $row = AuditLog::query()->latest('id')->first();

    expect($row)->not->toBeNull()
        ->and($row->auditable_type)->toBe(Customer::class)
        ->and($row->auditable_id)->toBe($customer->id)
        ->and($row->event)->toBe(AuditLog::EVENT_FEE_OVERRIDE)
        ->and($row->field)->toBe('delivery_fee')
        ->and($row->before)->toBe('2')
        ->and($row->after)->toBe('5')
        ->and($row->reason)->toBe('campaign promo')
        ->and($row->user_id)->toBe($user->id);
});

it('captures out_of_area_override edits on a Sale automatically', function () {
    $this->actingAs(User::factory()->manager()->create());
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'delivery',
        'payment_method' => 'cash',
        'status' => 'open',
        'delivery_fee' => 2.00,
        'out_of_area_override' => 1.00,
    ]);

    expect(AuditLog::count())->toBe(0);

    $sale->update(['out_of_area_override' => 3.00]);

    expect(AuditLog::count())->toBe(1)
        ->and(AuditLog::first()->event)->toBe(AuditLog::EVENT_OUT_OF_AREA_EDIT)
        ->and(AuditLog::first()->field)->toBe('out_of_area_override')
        ->and(AuditLog::first()->before)->toBe('1.00')
        ->and(AuditLog::first()->after)->toBe('3.00');
});
