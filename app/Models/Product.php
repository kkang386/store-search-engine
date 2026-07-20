<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'slug', 'brand', 'description',
        'price', 'inventory', 'rating', 'review_count',
        'sales_rank', 'is_active', 'attributes', 'images',
        'meta', 'indexed_at',
    ];

    protected $casts = [
        'price' => 'float',
        'inventory' => 'integer',
        'rating' => 'float',
        'review_count' => 'integer',
        'sales_rank' => 'integer',
        'is_active' => 'boolean',
        'attributes' => 'array',
        'images' => 'array',
        'meta' => 'array',
        'indexed_at' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
            ->withPivot('is_primary');
    }

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }

    public function storeProduct(int $storeId): ?StoreProduct
    {
        return $this->storeProducts->where('store_id', $storeId)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNeedsReindex($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('indexed_at')
              ->orWhereColumn('updated_at', '>', 'indexed_at');
        });
    }

    public function toSearchDocument(?int $storeId = null): array
    {
        $doc = [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'category_ids' => $this->categories->pluck('id')->toArray(),
            'category_names' => $this->categories->pluck('name')->toArray(),
            'description' => $this->description,
            'meta'   => $this->meta ?? null,
            'images' => $this->images ?? [],
            'price' => $this->price,
            'inventory' => $this->inventory,
            'rating' => $this->rating,
            'review_count' => $this->review_count,
            'sales_rank' => $this->sales_rank,
            'is_active' => $this->is_active,
            'attributes' => $this->getAttribute('attributes') ?? [],
            'attributes_text' => $this->buildAttributesText(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'completion' => [
                'input' => array_values(array_filter([
                    $this->name,
                    $this->brand,
                    $this->sku,
                ])),
                'weight' => max(1, (int) ($this->sales_rank > 0 ? 1000 / $this->sales_rank : 1)),
            ],
            'stores' => $this->buildStoresData(),
        ];

        return $doc;
    }

    private function buildAttributesText(): string
    {
        $attrs = $this->getAttribute('attributes');
        if (empty($attrs)) {
            return '';
        }
        return implode(' ', array_values($attrs));
    }

    private function buildStoresData(): array
    {
        return $this->storeProducts->map(fn (StoreProduct $sp) => [
            'store_id' => $sp->store_id,
            'price' => $sp->price ?? $this->price,
            'sale_price' => $sp->sale_price,
            'inventory' => $sp->inventory,
            'is_active' => $sp->is_active,
            'boost' => $sp->boost,
            'featured' => $sp->featured,
            'region' => $sp->store?->region,
        ])->toArray();
    }
}
