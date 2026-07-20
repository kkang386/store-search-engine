<?php

return [
    'hosts' => [
        env('ELASTICSEARCH_SCHEME', 'http') . '://' . env('ELASTICSEARCH_HOST', 'localhost') . ':' . env('ELASTICSEARCH_PORT', 9200),
    ],

    'user' => env('ELASTICSEARCH_USER', ''),
    'pass' => env('ELASTICSEARCH_PASSWORD', ''),

    // Company/tenant identifier (string, no spaces). Drives the index and synonym-set names.
    'company_id' => env('COMPANY_ID', 'default'),

    'indices' => [
        'products' => env('COMPANY_ID', 'default') . '_products',
        'analytics' => env('ELASTICSEARCH_ANALYTICS_INDEX', 'search_analytics'),
    ],

    // Named Elasticsearch synonym set (managed live via the Synonyms API).
    'synonym_set' => env('COMPANY_ID', 'default') . '_synonyms',

    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', ''),

    'shards' => (int) env('ELASTICSEARCH_SHARD_COUNT', 3),
    'replicas' => (int) env('ELASTICSEARCH_REPLICA_COUNT', 1),

    'bulk_size' => (int) env('ELASTICSEARCH_BULK_SIZE', 500),

    'timeout' => (int) env('ELASTICSEARCH_TIMEOUT', 30),
    'connect_timeout' => (int) env('ELASTICSEARCH_CONNECT_TIMEOUT', 5),

    'mappings_path' => base_path('elasticsearch/mappings'),
];
