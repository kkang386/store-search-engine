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
        'skus', 'conditions', 'metadata', 'boost_factor',
        'pin_position', 'redirect_url', 'banner_html',
        'include_category_ids', 'exclude_category_ids', 'include_brands',
        'is_active', 'starts_at', 'ends_at', 'priority', 'created_by',
    ];

    protected $casts = [
        'skus' => 'array',
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
            // Whole-word match, not a raw substring: pattern "bag" matches "gun bag"
            // but NOT "sku-gbag004". Prevents short patterns from hijacking SKUs/words.
            // Lookarounds (not \b) so patterns ending in punctuation like "c++"/".308"
            // still work; preg_quote guards against regex-special chars in the pattern.
            'contains' => (bool) preg_match(
                '/(?<!\w)' . preg_quote($this->query_pattern, '/') . '(?!\w)/i',
                $query
            ),
            'starts_with' => str_starts_with(strtolower($query), strtolower($this->query_pattern)),
            'regex' => (bool) preg_match('/' . $this->query_pattern . '/i', $query),
            default => false,
        };
    }
}
