<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchAnalytic extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'query',
        'session_id',
        'user_id',
        'result_count',
        'clicked_product_id',
        'click_position',
        'converted',
        'revenue',
        'latency_ms',
        'filters_applied',
        'facets_used',
        'sort_order',
        'endpoint',
        'created_at',
    ];

    protected $casts = [
        'filters_applied' => 'array',
        'facets_used' => 'array',
        'converted' => 'boolean',
        'created_at' => 'datetime',
    ];
}
