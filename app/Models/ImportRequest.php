<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRequest extends Model
{
    public const STATUS_IN_PROGRESS = 'in-progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_ERROR       = 'error';

    protected $fillable = [
        'request_id', 'store_id', 'type', 'status', 'payload', 'total',
        'created_count', 'updated_count', 'failed_count', 'indexed_count', 'error',
    ];

    protected $casts = [
        'total'         => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'failed_count'  => 'integer',
        'indexed_count' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
