<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cargo extends Model
{
    protected $fillable = [
        'supplier',
        'received_at',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CargoItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Total receipt value (sum of qty * purchase_price across items).
     */
    public function getTotalAttribute(): float
    {
        return (float) $this->items->reduce(
            fn (float $acc, CargoItem $item) => $acc + ((float) $item->purchase_price * (int) $item->quantity),
            0.0,
        );
    }
}
