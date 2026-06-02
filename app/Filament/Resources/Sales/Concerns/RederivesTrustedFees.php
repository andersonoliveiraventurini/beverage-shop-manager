<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales\Concerns;

use App\Models\Customer;

trait RederivesTrustedFees
{
    /**
     * Non-managers cannot set delivery/building fees. The SaleForm fields are
     * disabled for them but still dehydrated, so a crafted Livewire payload
     * could submit arbitrary values. Re-derive the fees from the customer (or
     * zero them for counter sales) before the record is written.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function rederiveTrustedFees(array $data): array
    {
        if (optional(auth()->user())->isManager()) {
            return $data;
        }

        $isDelivery = ($data['type'] ?? null) === 'delivery';
        $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;

        $data['delivery_fee'] = $isDelivery && $customer ? (float) $customer->delivery_fee : 0.0;
        $data['building_fee'] = $isDelivery && $customer ? (float) $customer->building_fee : 0.0;

        return $data;
    }
}
