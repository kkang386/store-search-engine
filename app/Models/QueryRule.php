<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QueryRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id', 'query_pattern', 'match_type', 'action',
        'product_ids', 'conditions', 'metadata', 'boost_factor',
        'pin_position', 'redirect_url', 'banner_html',
        'include_category_ids', 'exclude_category_ids', 'include_brands',
        'is_active', 'starts_at', 'ends_at', 'priority', 'created_by',
    ];

    protected $casts = [
        'product_ids' => 'array',
        'conditions' => 'array',
        'metadata' => 'array',
        'include_category_ids' => 'array',
        'exclude_category_ids' => 'array',
        'include_brands' => 'array',
        'boost_factor' => 'float',
        'pin_position' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForStore($query, ?int $storeId)
    {
        return $query->where(function ($q) use ($storeId) {
            $q->whereNull('store_id');
            if ($storeId) {
                $q->orWhere('store_id', $storeId);
            }
        });
    }

    public function matchesQuery(string $query): bool
    {
        return match ($this->match_type) {
            'exact' => strtolower($query) === strtolower($this->query_pattern),
            'contains' => str_contains(strtolower($query), strtolower($this->query_pattern)),
            'starts_with' => str_starts_with(strtolower($query), strtolower($this->query_pattern)),
            'regex' => (bool) preg_match('/' . $this->query_pattern . '/i', $query),
            default => false,
        };
    }
}
