<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverySettingRevision extends Model
{
    protected $fillable = [
        'radius_km',
        'default_delivery_fee',
        'out_of_area_extra_fee',
        'default_building_fee',
        'track_water_shells',
        'near_expiry_threshold_days',
        'customers_recomputed',
        'customers_skipped',
        'user_id',
    ];

    protected $casts = [
        'radius_km' => 'decimal:2',
        'default_delivery_fee' => 'decimal:2',
        'out_of_area_extra_fee' => 'decimal:2',
        'default_building_fee' => 'decimal:2',
        'track_water_shells' => 'boolean',
        'near_expiry_threshold_days' => 'integer',
        'customers_recomputed' => 'integer',
        'customers_skipped' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
