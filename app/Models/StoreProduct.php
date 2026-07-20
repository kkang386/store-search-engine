<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreProduct extends Model
{
    protected $fillable = [
        'store_id', 'product_id', 'price', 'sale_price',
        'inventory', 'is_active', 'boost', 'featured',
    ];

    protected $casts = [
        'price' => 'float',
        'sale_price' => 'float',
        'inventory' => 'integer',
        'is_active' => 'boolean',
        'boost' => 'float',
        'featured' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getEffectivePrice(): float
    {
        return $this->price ?? $this->product->price;
    }
}
