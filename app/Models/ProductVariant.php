<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'is_returnable',
        'shell_cost',
        'sale_price',
        'cost_price',
        'min_stock',
    ];

    protected $casts = [
        'is_returnable' => 'boolean',
        'shell_cost' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'min_stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    /**
     * Live stock balance derived from the stock_movements table.
     * IN movements add, OUT movements subtract. No cargo support yet — initial
     * stock must be entered as a 'manual_adjust' IN movement.
     */
    public function getCurrentStockAttribute(): int
    {
        return (int) (
            (int) $this->stockMovements()->where('direction', 'in')->sum('quantity')
            - (int) $this->stockMovements()->where('direction', 'out')->sum('quantity')
        );
    }

    public function isLowStock(): bool
    {
        return $this->current_stock < (int) $this->min_stock;
    }

    public function cargoItems()
    {
        return $this->hasMany(CargoItem::class, 'variant_id');
    }

    /**
     * Weighted-average purchase cost across all cargo receipts. Falls back to
     * the static cost_price column when the variant has never been received.
     */
    public function getWeightedAverageCostAttribute(): float
    {
        $items = $this->cargoItems()->get(['quantity', 'purchase_price']);

        $totalQty = (int) $items->sum('quantity');
        if ($totalQty === 0) {
            return (float) ($this->cost_price ?? 0);
        }

        $totalSpend = (float) $items->reduce(
            fn (float $acc, $row) => $acc + ((float) $row->purchase_price * (int) $row->quantity),
            0.0,
        );

        return round($totalSpend / $totalQty, 2);
    }
}
