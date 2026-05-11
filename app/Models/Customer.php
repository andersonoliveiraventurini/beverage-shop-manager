<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'document',
        'notes',
        'in_delivery_area',
        'distance_km',
        'delivery_fee',
        'building_fee',
        'has_manual_fee_override',
        'fees_calculated_at',
        'google_contact_id',
        'google_synced_at',
    ];

    protected $casts = [
        'in_delivery_area' => 'boolean',
        'distance_km' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'building_fee' => 'decimal:2',
        'has_manual_fee_override' => 'boolean',
        'fees_calculated_at' => 'datetime',
        'google_synced_at' => 'datetime',
    ];

    public function phones(): HasMany
    {
        return $this->hasMany(CustomerPhone::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function primaryAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_primary', true);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
