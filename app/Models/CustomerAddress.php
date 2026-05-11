<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'label',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'zip',
        'lat',
        'lng',
        'is_building',
        'is_primary',
        'reference',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'is_building' => 'boolean',
        'is_primary' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->street,
            $this->number ? ", {$this->number}" : null,
            $this->complement ? " — {$this->complement}" : null,
            $this->district ? " · {$this->district}" : null,
            $this->city ? " · {$this->city}" : null,
        ])->filter()->implode('');
    }
}
