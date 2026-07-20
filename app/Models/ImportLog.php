<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'store_id', 'status', 'phase',
        'products_created', 'products_updated', 'products_failed',
        'products_to_index', 'products_indexed',
        'categories_created', 'categories_updated', 'categories_failed',
        'duplicate_skus', 'errors',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'duplicate_skus'    => 'array',
        'errors'            => 'array',
        'products_to_index' => 'integer',
        'products_indexed'  => 'integer',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
