<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoItem extends Model
{
    protected $fillable = [
        'cargo_id',
        'variant_id',
        'quantity',
        'purchase_price',
        'expires_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'purchase_price' => 'decimal:2',
        'expires_at' => 'date',
    ];

    protected static function booted(): void
    {
        // Every saved cargo item ensures one matching IN stock movement exists.
        // Idempotent — re-saving the cargo item does not create duplicates.
        static::saved(function (CargoItem $item): void {
            $exists = StockMovement::query()
                ->where('source_type', static::class)
                ->where('source_id', $item->id)
                ->where('direction', StockMovement::DIRECTION_IN)
                ->where('reason', StockMovement::REASON_CARGO)
                ->exists();

            if ($exists) {
                return;
            }

            StockMovement::create([
                'variant_id' => $item->variant_id,
                'direction' => StockMovement::DIRECTION_IN,
                'reason' => StockMovement::REASON_CARGO,
                'quantity' => (int) $item->quantity,
                'source_type' => static::class,
                'source_id' => $item->id,
                'user_id' => optional($item->cargo)->user_id,
            ]);
        });

        // If a cargo item is removed, reverse the corresponding IN movement.
        static::deleted(function (CargoItem $item): void {
            StockMovement::query()
                ->where('source_type', static::class)
                ->where('source_id', $item->id)
                ->delete();
        });
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
