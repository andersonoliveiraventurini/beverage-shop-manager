<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_EN_ROUTE = 'en_route';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_EN_ROUTE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'sale_id',
        'deliverer_id',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deliverer_id');
    }

    public function startRoute(?User $by = null): void
    {
        $this->update([
            'status' => self::STATUS_EN_ROUTE,
            'started_at' => now(),
            'deliverer_id' => $by?->id ?? $this->deliverer_id,
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function cancel(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
        ]);

        // Cascading the cancellation to the parent sale reverses stock and the
        // shell ledger through the existing Sale::saved hook.
        $this->sale?->update(['status' => Sale::STATUS_CANCELLED]);
    }
}
