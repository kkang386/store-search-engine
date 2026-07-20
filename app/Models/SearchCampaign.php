<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SearchCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id', 'name', 'description', 'type',
        'query_patterns', 'product_ids', 'boost_factor',
        'banner_config', 'is_active', 'starts_at', 'ends_at', 'created_by',
    ];

    protected $casts = [
        'query_patterns' => 'array',
        'product_ids' => 'array',
        'boost_factor' => 'float',
        'banner_config' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function isRunning(): bool
    {
        return $this->is_active
            && $this->starts_at <= now()
            && $this->ends_at >= now();
    }
}
