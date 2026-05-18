<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'name',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'state',
        'zip',
        'lat',
        'lng',
        'phone_landline',
        'phone_mobile',
        'whatsapp',
        'hours',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    /**
     * Get the singleton store row, creating it from config('brand.*') defaults
     * if missing. The depot is a single-row entity — there is only one FA.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'name' => config('brand.name'),
            'street' => 'Av. Transamazônica, 1197',
            'district' => config('brand.address.district'),
            'city' => config('brand.address.city'),
            'state' => config('brand.address.state'),
            'phone_landline' => config('brand.phones.landline'),
            'phone_mobile' => config('brand.phones.mobile'),
            'whatsapp' => config('brand.phones.whatsapp'),
            'hours' => config('brand.hours.one_line'),
        ]);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->street,
            $this->number ? ", {$this->number}" : null,
            $this->complement ? " — {$this->complement}" : null,
            $this->district ? " · {$this->district}" : null,
            $this->city && $this->state ? " · {$this->city}–{$this->state}" : null,
        ])->filter()->implode('');
    }
}
