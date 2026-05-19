<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliverySetting;

/**
 * Pure fee computation for a single customer based on:
 *   - the current delivery_settings singleton
 *   - the customer's primary address (lat/lng + is_building)
 *
 * PRD F10: fees live on the customer, never recomputed at sale time.
 * This service is the only writer of customer.delivery_fee / building_fee /
 * in_delivery_area / distance_km, *except* when has_manual_fee_override = true,
 * in which case the bulk recompute action skips the customer entirely.
 */
class CustomerFeeCalculator
{
    public function __construct(
        private readonly DeliverySetting $settings,
    ) {
    }

    public static function make(): self
    {
        return new self(DeliverySetting::current());
    }

    /**
     * Compute the fee triplet for one customer, returning the new column
     * values without persisting. Caller decides when to ->save().
     *
     * @return array{delivery_fee: float, building_fee: float, in_delivery_area: bool, distance_km: ?float}
     */
    public function compute(Customer $customer): array
    {
        $address = $customer->primaryAddress;

        $distance = $address ? $this->distanceFromDepot($address) : null;
        $inArea = $distance === null ? true : $distance <= (float) $this->settings->radius_km;

        $delivery = (float) $this->settings->default_delivery_fee;
        if (! $inArea) {
            $delivery += (float) $this->settings->out_of_area_extra_fee;
        }

        $building = $address && $address->is_building
            ? (float) $this->settings->default_building_fee
            : 0.0;

        return [
            'delivery_fee' => round($delivery, 2),
            'building_fee' => round($building, 2),
            'in_delivery_area' => $inArea,
            'distance_km' => $distance,
        ];
    }

    /**
     * Apply the computed values to the customer, marking fees_calculated_at.
     * Returns true when persisted, false when the customer carries a manual
     * override and is skipped.
     */
    public function applyTo(Customer $customer): bool
    {
        if ($customer->has_manual_fee_override) {
            return false;
        }

        $values = $this->compute($customer);

        $customer->fill([
            'delivery_fee' => $values['delivery_fee'],
            'building_fee' => $values['building_fee'],
            'in_delivery_area' => $values['in_delivery_area'],
            'distance_km' => $values['distance_km'],
            'fees_calculated_at' => now(),
        ]);
        $customer->save();

        return true;
    }

    /**
     * Haversine distance in km between the depot (Store::current) and a
     * customer address. Returns null when either point lacks coordinates.
     */
    private function distanceFromDepot(CustomerAddress $address): ?float
    {
        $depot = \App\Models\Store::current();

        if ($depot->lat === null || $depot->lng === null
            || $address->lat === null || $address->lng === null) {
            return null;
        }

        $earthKm = 6371.0;
        $lat1 = deg2rad((float) $depot->lat);
        $lat2 = deg2rad((float) $address->lat);
        $dLat = deg2rad((float) $address->lat - (float) $depot->lat);
        $dLon = deg2rad((float) $address->lng - (float) $depot->lng);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthKm * $c, 2);
    }
}
