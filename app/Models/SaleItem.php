<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    public const MODALITY_FULL = 'full';
    public const MODALITY_EXCHANGE = 'exchange';
    public const MODALITY_SHELL_ONLY = 'shell_only';

    public const MODALITIES = [self::MODALITY_FULL, self::MODALITY_EXCHANGE, self::MODALITY_SHELL_ONLY];

    protected $fillable = [
        'sale_id',
        'variant_id',
        'quantity',
        'unit_price',
        'line_total',
        'modality',
        'returned_shell_expires_at',
        'delivered_shell_expires_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'returned_shell_expires_at' => 'date',
        'delivered_shell_expires_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (SaleItem $item): void {
            $item->line_total = round(((float) $item->unit_price) * ((int) $item->quantity), 2);
        });

        $cascade = function (SaleItem $item): void {
            $sale = $item->sale()->first();
            $sale?->recalculate()->save();
        };

        static::saved($cascade);
        static::deleted($cascade);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
