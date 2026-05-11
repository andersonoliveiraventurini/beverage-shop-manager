<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaterShellLedger extends Model
{
    use SoftDeletes;

    protected $table = 'water_shell_ledgers';

    protected $fillable = [
        'customer_id',
        'variant_id',
        'expires_at',
        'shell_count',
        'last_out_at',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'shell_count' => 'integer',
        'last_out_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
