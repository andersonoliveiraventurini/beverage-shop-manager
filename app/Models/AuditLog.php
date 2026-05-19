<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const EVENT_FEE_OVERRIDE = 'fee_override';
    public const EVENT_PRICE_OVERRIDE = 'price_override';
    public const EVENT_OUT_OF_AREA_EDIT = 'out_of_area_edit';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'field',
        'before',
        'after',
        'reason',
        'user_id',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a one-shot audit event for any model. Captures auth()->id()
     * when called inside a request context.
     */
    public static function record(
        Model $auditable,
        string $event,
        ?string $field = null,
        $before = null,
        $after = null,
        ?string $reason = null,
    ): self {
        return static::create([
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'event' => $event,
            'field' => $field,
            'before' => $before === null ? null : (string) $before,
            'after' => $after === null ? null : (string) $after,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }
}
