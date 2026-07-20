<?php

namespace App\Services\Search;

use App\DTOs\SuggestQueryDTO;

class SuggestQueryBuilder
{
    public function build(SuggestQueryDTO $dto): array
    {
        $query = $dto->query;
        $limit = $dto->limit;

        return [
            'size' => 0,
            'suggest' => $this->buildSuggest($query, $limit),
            'query' => $this->buildFilter($dto),
            'aggs' => $this->buildAggregations($query, $limit),
            '_source' => ['id', 'name', 'slug', 'brand', 'price', 'rating', 'category_names'],
        ];
    }

    public function buildProductSearch(SuggestQueryDTO $dto): array
    {
        $textQuery = [
            'bool' => [
                'should' => [
                    // Primary: full-text match on analyzed name + SKU — finds tokens anywhere in the field
                    [
                        'multi_match' => [
                            'query'                  => $dto->query,
                            'fields'                 => ['name^3', 'sku.text^5', 'brand.text^2'],
                            'type'                   => 'best_fields',
                            'operator'               => 'or',
                            'minimum_should_match'   => '60%',
                        ],
                    ],
                    // Secondary: prefix/autocomplete match — rewards leading matches and n-grams
                    [
                        'multi_match' => [
                            'query'         => $dto->query,
                            'fields'        => [
                                'name.suggest^2',
                                'name.suggest._2gram',
                                'name.suggest._3gram',
                                'name.autocomplete',
                                'brand.suggest',
                                'brand.autocomplete',
                            ],
                            'type'          => 'bool_prefix',
                            'fuzziness'     => 'AUTO',
                            'prefix_length' => 1,
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
                'filter' => [
                    ['term' => ['is_active' => true]],
                ],
            ],
        ];

        $body = [
            'size'  => $dto->limit,
            'query' => [
                'function_score' => [
                    'query'      => $textQuery,
                    'functions'  => [
                        [
                            'filter' => ['range' => ['inventory' => ['gte' => 1]]],
                            'weight' => 1.5,
                        ],
                        [
                            'filter' => ['range' => ['rating' => ['gte' => 4.0]]],
                            'weight' => 1.2,
                        ],
                        [
                            'field_value_factor' => [
                                'field'    => 'sales_rank',
                                'factor'   => 2.0,
                                'modifier' => 'reciprocal',
                                'missing'  => 0.5,
                            ],
                        ],
                    ],
                    'score_mode' => 'sum',
                    'boost_mode' => 'multiply',
                ],
            ],
            'sort'    => [['_score' => 'desc']],
            '_source' => ['id', 'sku', 'name', 'slug', 'brand', 'price', 'rating', 'category_names', 'inventory', 'description', 'meta', 'images'],
        ];

        if ($dto->storeId !== null) {
            $body['query']['function_score']['query']['bool']['filter'][] = [
                'nested' => [
                    'path'  => 'stores',
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['stores.store_id' => $dto->storeId]],
                                ['term' => ['stores.is_active' => true]],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $body;
    }

    private function buildSuggest(string $query, int $limit): array
    {
        return [
            'name_suggest' => [
                'prefix' => $query,
                'completion' => [
                    'field' => 'completion',
                    'size' => $limit,
                    'fuzzy' => [
                        'fuzziness' => 'AUTO',
                        'min_length' => 3,
                        'prefix_length' => 1,
                    ],
                    'skip_duplicates' => true,
                ],
            ],
        ];
    }

    private function buildFilter(SuggestQueryDTO $dto): array
    {
        $filters = [['term' => ['is_active' => true]]];

        if ($dto->storeId !== null) {
            $filters[] = [
                'nested' => [
                    'path' => 'stores',
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['stores.store_id' => $dto->storeId]],
                                ['term' => ['stores.is_active' => true]],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            'bool' => [
                'filter' => $filters,
                'should' => [
                    [
                        'multi_match' => [
                            'query' => $dto->query,
                            'fields' => [
                                'name.suggest',
                                'name.suggest._2gram',
                                'name.suggest._3gram',
                            ],
                            'type' => 'bool_prefix',
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    private function buildAggregations(string $query, int $limit): array
    {
        return [
            'brands' => [
                'filter' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['brand.autocomplete', 'brand.suggest'],
                        'type' => 'bool_prefix',
                    ],
                ],
                'aggs' => [
                    'top_brands' => [
                        'terms' => [
                            'field' => 'brand',
                            'size' => $limit,
                            'order' => ['_count' => 'desc'],
                        ],
                    ],
                ],
            ],
            'categories' => [
                'filter' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => ['category_names.autocomplete', 'category_names.suggest'],
                        'type' => 'bool_prefix',
                    ],
                ],
                'aggs' => [
                    'top_categories' => [
                        'terms' => [
                            'field' => 'category_names',
                            'size' => $limit,
                            'order' => ['_count' => 'desc'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
