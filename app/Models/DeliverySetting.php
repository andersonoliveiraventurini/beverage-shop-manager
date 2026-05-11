<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliverySetting extends Model
{
    protected $fillable = [
        'radius_km',
        'default_delivery_fee',
        'out_of_area_extra_fee',
        'default_building_fee',
        'track_water_shells',
    ];

    protected $casts = [
        'radius_km' => 'decimal:2',
        'default_delivery_fee' => 'decimal:2',
        'out_of_area_extra_fee' => 'decimal:2',
        'default_building_fee' => 'decimal:2',
        'track_water_shells' => 'boolean',
    ];

    /**
     * Get the singleton settings row, creating it with defaults if missing.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'radius_km' => 2.00,
            'default_delivery_fee' => 2.00,
            'out_of_area_extra_fee' => 1.00,
            'default_building_fee' => 1.00,
            'track_water_shells' => false,
        ]);
    }

    public static function trackingShells(): bool
    {
        return (bool) static::current()->track_water_shells;
    }
}
