<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_COUNTER = 'counter';
    public const TYPE_DELIVERY = 'delivery';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_PIX = 'pix';
    public const PAYMENT_DEBIT = 'debit';
    public const PAYMENT_CREDIT = 'credit';

    public const STATUS_OPEN = 'open';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPES = [self::TYPE_COUNTER, self::TYPE_DELIVERY];
    public const PAYMENT_METHODS = [self::PAYMENT_CASH, self::PAYMENT_PIX, self::PAYMENT_DEBIT, self::PAYMENT_CREDIT];
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_CONFIRMED, self::STATUS_CANCELLED];

    protected $fillable = [
        'customer_id',
        'address_id',
        'user_id',
        'type',
        'payment_method',
        'status',
        'subtotal',
        'delivery_fee',
        'building_fee',
        'out_of_area_override',
        'card_fee',
        'discount',
        'discount_reason',
        'total',
        'contains_water',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'building_fee' => 'decimal:2',
        'out_of_area_override' => 'decimal:2',
        'card_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'contains_water' => 'boolean',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Sale $sale): void {
            $sale->total = round(max(0, (float) $sale->subtotal
                + (float) $sale->delivery_fee
                + (float) ($sale->out_of_area_override ?? 0)
                + (float) $sale->building_fee
                + (float) $sale->card_fee
                - (float) $sale->discount), 2);
        });
    }

    /**
     * Recompute subtotal and contains_water from the items in DB.
     * Caller is responsible for ->save() afterwards.
     */
    public function recalculate(): self
    {
        $this->load(['items.variant.product.category']);

        $this->subtotal = round((float) $this->items->sum('line_total'), 2);
        $this->contains_water = $this->items->contains(
            fn ($item) => optional(optional(optional($item->variant)->product)->category)->slug === 'agua'
        );

        return $this;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
