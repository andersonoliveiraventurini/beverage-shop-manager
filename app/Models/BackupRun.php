<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'started_at',
        'finished_at',
        'status',
        'file_name',
        'size_bytes',
        'drive_file_id',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    public static function latestRun(): ?self
    {
        return static::query()->latest('started_at')->first();
    }
}
