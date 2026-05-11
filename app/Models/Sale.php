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
        'stock_settled',
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
        'stock_settled' => 'boolean',
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

        static::saved(function (Sale $sale): void {
            // Settle (decrement) stock when a sale becomes 'confirmed'.
            // Guarded by items existing — the Filament create flow saves Sale before items.
            if ($sale->status === self::STATUS_CONFIRMED && $sale->items()->exists()) {
                $sale->settleStock();
            }
            // Reverse stock when a sale is moved to 'cancelled'. Driven by actual OUT
            // movements (not the in-memory stock_settled flag, which can be stale
            // because the test/UI may hold a sale instance whose flag was flipped via
            // a different model instance).
            if ($sale->status === self::STATUS_CANCELLED) {
                $sale->reverseStock();
            }
        });
    }

    /**
     * Create an OUT movement for every item that does not yet have one.
     * Idempotent: re-running does not create duplicates.
     */
    public function settleStock(): void
    {
        $this->loadMissing('items');
        foreach ($this->items as $item) {
            $exists = \App\Models\StockMovement::query()
                ->where('source_type', \App\Models\SaleItem::class)
                ->where('source_id', $item->id)
                ->where('direction', \App\Models\StockMovement::DIRECTION_OUT)
                ->where('reason', \App\Models\StockMovement::REASON_SALE)
                ->exists();
            if ($exists) {
                continue;
            }
            \App\Models\StockMovement::create([
                'variant_id' => $item->variant_id,
                'direction' => \App\Models\StockMovement::DIRECTION_OUT,
                'reason' => \App\Models\StockMovement::REASON_SALE,
                'quantity' => (int) $item->quantity,
                'source_type' => \App\Models\SaleItem::class,
                'source_id' => $item->id,
                'user_id' => $this->user_id,
            ]);
        }
        if (! $this->stock_settled) {
            $this->stock_settled = true;
            $this->saveQuietly();
        }
    }

    /**
     * Reverse every unreversed OUT movement for this sale's items.
     * Driven by the actual movement table (not the stock_settled flag) so it's
     * safe to call from a stale model instance. Idempotent.
     */
    public function reverseStock(): void
    {
        $this->loadMissing('items');
        $hadAnyOut = false;
        foreach ($this->items as $item) {
            $out = \App\Models\StockMovement::query()
                ->where('source_type', \App\Models\SaleItem::class)
                ->where('source_id', $item->id)
                ->where('direction', \App\Models\StockMovement::DIRECTION_OUT)
                ->where('reason', \App\Models\StockMovement::REASON_SALE)
                ->first();
            if (! $out) {
                continue;
            }
            $hadAnyOut = true;

            $alreadyReversed = \App\Models\StockMovement::query()
                ->where('source_type', \App\Models\SaleItem::class)
                ->where('source_id', $item->id)
                ->where('direction', \App\Models\StockMovement::DIRECTION_IN)
                ->where('reason', \App\Models\StockMovement::REASON_SALE_REVERSAL)
                ->exists();
            if ($alreadyReversed) {
                continue;
            }

            \App\Models\StockMovement::create([
                'variant_id' => $item->variant_id,
                'direction' => \App\Models\StockMovement::DIRECTION_IN,
                'reason' => \App\Models\StockMovement::REASON_SALE_REVERSAL,
                'quantity' => (int) $out->quantity,
                'source_type' => \App\Models\SaleItem::class,
                'source_id' => $item->id,
                'user_id' => $this->user_id,
            ]);
        }

        if ($hadAnyOut) {
            // Flip the flag from whatever DB has — fresh read avoids stale-instance issues.
            \App\Models\Sale::query()->whereKey($this->id)->update(['stock_settled' => false]);
            $this->stock_settled = false;
        }
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
