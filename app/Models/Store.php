<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'region', 'locale', 'currency', 'is_active', 'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'store_categories')
            ->withPivot('is_active', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function queryRules(): HasMany
    {
        return $this->hasMany(QueryRule::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(SearchCampaign::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(\App\Models\StoreApiToken::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
