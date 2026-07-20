<?php

return [
    'per_page_default' => (int) env('SEARCH_PER_PAGE_DEFAULT', 24),
    'per_page_max' => (int) env('SEARCH_PER_PAGE_MAX', 100),
    'autocomplete_limit' => (int) env('SEARCH_AUTOCOMPLETE_LIMIT', 10),

    'fuzzy' => [
        'enabled' => (bool) env('SEARCH_FUZZY_ENABLED', true),
        'auto' => (bool) env('SEARCH_FUZZY_AUTO', true),
        'min_length' => 4,
        'prefix_length' => 1,
        'max_expansions' => 50,
    ],

    'min_score' => (float) env('SEARCH_MIN_SCORE', 0.01),

    'boost' => [
        'sku_exact' => 100.0,
        'name_exact' => 50.0,
        'name_phrase' => 20.0,
        'name_fuzzy' => 10.0,
        'brand_exact' => 15.0,
        'brand_text' => 8.0,
        'category_name' => 5.0,
        'attributes_text' => 3.0,
        'description' => 1.0,
    ],

    'function_score' => [
        'inventory_weight' => 1.5,
        'rating_weight' => 1.2,
        'sales_rank_weight' => 2.0,
        'out_of_stock_penalty' => 0.3,
        'low_inventory_threshold' => 5,
        'high_rating_threshold' => 4.0,
    ],

    'sort_options' => [
        'relevance' => ["_score"=> "desc", "sales_rank"=> "asc"], // '_score',
        'price_asc' => ['price' => 'asc'],
        'price_desc' => ['price' => 'desc'],
        'newest' => ['created_at' => 'desc'],
        'rating' => ['rating' => 'desc'],
        'best_selling' => ['sales_rank' => 'asc'],
    ],

    'facets' => [
        'default' => ['brand', 'price_range'],
        'max_buckets' => 50,
        'min_doc_count' => 1,
    ],

    'benchmark' => [
        'p95_search_threshold_ms' => (int) env('BENCHMARK_P95_SEARCH_THRESHOLD_MS', 300),
        'p95_suggest_threshold_ms' => (int) env('BENCHMARK_P95_SUGGEST_THRESHOLD_MS', 100),
        'precision10_drop_threshold' => (float) env('BENCHMARK_PRECISION10_DROP_THRESHOLD', 5.0),
        'ndcg10_drop_threshold' => (float) env('BENCHMARK_NDCG10_DROP_THRESHOLD', 5.0),
        'dataset_path' => base_path('storage/benchmarks/dataset.json'),
        'results_path' => base_path('storage/benchmarks'),
    ],

    'stores' => [
        'fallback_hierarchy' => ['store', 'region', 'global'],
        'default_store_id' => 1,
    ],

    'admin' => [
        'roles' => ['search_admin', 'merchandiser', 'analyst', 'read_only'],
        'audit_retention_days' => 90,
    ],
];
