<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const REASON_SALE = 'sale';
    public const REASON_SALE_REVERSAL = 'sale_reversal';
    public const REASON_MANUAL_ADJUST = 'manual_adjust';
    public const REASON_CARGO = 'cargo';
    public const REASON_WRITE_OFF = 'write_off';

    protected $fillable = [
        'variant_id',
        'direction',
        'reason',
        'quantity',
        'source_type',
        'source_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function signedQuantity(): int
    {
        return $this->direction === self::DIRECTION_IN
            ? (int) $this->quantity
            : -(int) $this->quantity;
    }
}
