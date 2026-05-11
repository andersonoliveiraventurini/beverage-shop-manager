<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliverySetting;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WaterShellLedger;

function makeReturnableVariant(): ProductVariant
{
    $category = Category::firstOrCreate(['slug' => 'agua'], ['name' => 'Água', 'active' => true]);
    $product = $category->products()->create(['name' => 'Galão 20L Ledger', 'active' => true]);
    return $product->variants()->create([
        'sku' => 'AGUA-LEDGER-20L-' . uniqid(),
        'size' => '20L',
        'is_returnable' => true,
        'sale_price' => 15,
        'min_stock' => 10,
    ]);
}

it('seeds the delivery_settings singleton with defaults', function () {
    $settings = DeliverySetting::current();

    expect($settings->id)->toBe(1)
        ->and((float) $settings->radius_km)->toBe(2.00)
        ->and((float) $settings->default_delivery_fee)->toBe(2.00)
        ->and((float) $settings->out_of_area_extra_fee)->toBe(1.00)
        ->and((float) $settings->default_building_fee)->toBe(1.00)
        ->and($settings->track_water_shells)->toBeFalse();
});

it('does NOT touch the shell ledger when tracking is off', function () {
    DeliverySetting::current()->update(['track_water_shells' => false]);
    $variant = makeReturnableVariant();
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'open',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 15,
        'modality' => SaleItem::MODALITY_FULL,
        'delivered_shell_expires_at' => '2030-03-01',
    ]);
    $sale->update(['status' => 'confirmed']);

    expect(WaterShellLedger::where('customer_id', $customer->id)->count())->toBe(0);
});

it('increments the ledger on a full sale and decrements on cancellation', function () {
    DeliverySetting::current()->update(['track_water_shells' => true]);
    $variant = makeReturnableVariant();
    $customer = Customer::factory()->create();

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 3,
        'unit_price' => 15,
        'modality' => SaleItem::MODALITY_FULL,
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    $entry = WaterShellLedger::where('customer_id', $customer->id)
        ->where('variant_id', $variant->id)
        ->whereDate('expires_at', '2030-03-01')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->shell_count)->toBe(3);

    $sale->update(['status' => 'cancelled']);

    expect($entry->fresh()->shell_count)->toBe(0);
});

it('shifts the ledger across validities on an exchange sale', function () {
    DeliverySetting::current()->update(['track_water_shells' => true]);
    $variant = makeReturnableVariant();
    $customer = Customer::factory()->create();

    // Customer already has 5 shells at 2027-03 (seeded directly).
    WaterShellLedger::create([
        'customer_id' => $customer->id,
        'variant_id' => $variant->id,
        'expires_at' => '2027-03-01',
        'shell_count' => 5,
        'last_out_at' => now()->subYear(),
    ]);

    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 15,
        'modality' => SaleItem::MODALITY_EXCHANGE,
        'returned_shell_expires_at' => '2027-03-01',
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    $old = WaterShellLedger::where('customer_id', $customer->id)->whereDate('expires_at', '2027-03-01')->first();
    $new = WaterShellLedger::where('customer_id', $customer->id)->whereDate('expires_at', '2030-03-01')->first();

    expect($old->shell_count)->toBe(3)   // 5 - 2 returned
        ->and($new->shell_count)->toBe(2); // 0 + 2 delivered
});

it('clamps the ledger to zero when more shells are returned than the customer holds', function () {
    DeliverySetting::current()->update(['track_water_shells' => true]);
    $variant = makeReturnableVariant();
    $customer = Customer::factory()->create();

    // Customer starts with 0 shells; exchange would otherwise try to go negative.
    $sale = Sale::create([
        'customer_id' => $customer->id,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 15,
        'modality' => SaleItem::MODALITY_EXCHANGE,
        'returned_shell_expires_at' => '2027-03-01',
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    $old = WaterShellLedger::where('customer_id', $customer->id)->whereDate('expires_at', '2027-03-01')->first();
    expect($old)->not->toBeNull()
        ->and($old->shell_count)->toBe(0); // clamped from -2
});

it('skips ledger updates on counter sales with no customer', function () {
    DeliverySetting::current()->update(['track_water_shells' => true]);
    $variant = makeReturnableVariant();

    $sale = Sale::create([
        'customer_id' => null,
        'type' => 'counter',
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
    $sale->items()->create([
        'variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 15,
        'modality' => SaleItem::MODALITY_FULL,
        'delivered_shell_expires_at' => '2030-03-01',
    ]);

    expect(WaterShellLedger::count())->toBe(0);
});
