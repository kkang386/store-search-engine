<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreApiToken extends Model
{
    protected $fillable = ['store_id', 'name', 'token'];

    protected $hidden = ['token'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
