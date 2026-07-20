<?php

namespace App\Services\Search;

use App\DTOs\SearchQueryDTO;

class QueryBuilder
{
    private array $config;

    public function __construct()
    {
        $this->config = config('search');
    }

    public function build(SearchQueryDTO $dto): array
    {
        $query = $this->buildMainQuery($dto);
        $query = $this->applyFunctionScore($query, $dto);

        $body = [
            'query' => $query,
            'from' => $dto->getOffset(),
            'size' => $dto->perPage,
            'sort' => $this->buildSort($dto->sort),
            'highlight' => $this->buildHighlight(),
            'track_total_hits' => true,
        ];

        if ($dto->withFacets) {
            $body['aggs'] = (new FacetBuilder())->build($dto);
        }

        if ($dto->withExplanation) {
            $body['explain'] = true;
        }

        if (empty(trim($dto->query))) {
            unset($body['highlight']);
        }

        return $body;
    }

    private function buildMainQuery(SearchQueryDTO $dto): array
    {
        $filters = $this->buildFilters($dto);
        $mustQuery = $this->buildTextQuery($dto->query);

        if (empty(trim($dto->query))) {
            return [
                'bool' => [
                    'filter' => $filters,
                    'must' => [['match_all' => new \stdClass()]],
                ],
            ];
        }

        return [
            'bool' => [
                'must' => [$mustQuery],
                'filter' => $filters,
                'should' => $this->buildBoostClauses($dto->query),
            ],
        ];
    }

    private function buildTextQuery(string $query): array
    {
        if (empty(trim($query))) {
            return ['match_all' => new \stdClass()];
        }

        $boost = $this->config['boost'];
        $fuzzy = $this->config['fuzzy'];

        return [
            'bool' => [
                'should' => [
                    [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => [
                                "name^{$boost['name_exact']}",
                                "name.suggest^{$boost['name_phrase']}",
                                "brand.text^{$boost['brand_text']}",
                                "category_names.text^{$boost['category_name']}",
                                "attributes_text^{$boost['attributes_text']}",
                                "description^{$boost['description']}",
                                "sku.text^50",
                            ],
                            'type' => 'best_fields',
                            'tie_breaker' => 0.3,
                            'operator' => 'or',
                            'minimum_should_match' => '60%',
                        ],
                    ],
                    [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => [
                                "name^{$boost['name_phrase']}",
                                "brand.text^{$boost['brand_text']}",
                                "category_names.text^{$boost['category_name']}",
                            ],
                            'type' => 'phrase',
                            'slop' => 2,
                        ],
                    ],
                    [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => [
                                "name^{$boost['name_fuzzy']}",
                                "brand.text^{$boost['brand_text']}",
                                "attributes_text^{$boost['attributes_text']}",
                            ],
                            'fuzziness' => $fuzzy['auto'] ? 'AUTO' : '2',
                            'prefix_length' => $fuzzy['prefix_length'],
                            'max_expansions' => $fuzzy['max_expansions'],
                            'type' => 'best_fields',
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }

    private function buildBoostClauses(string $query): array
    {
        $boost = $this->config['boost'];

        return [
            [
                'term' => [
                    'sku' => [
                        'value' => strtoupper($query),
                        'boost' => $boost['sku_exact'],
                    ],
                ],
            ],
            [
                'term' => [
                    'name.keyword' => [
                        'value' => strtolower($query),
                        'boost' => $boost['name_exact'],
                    ],
                ],
            ],
            [
                'term' => [
                    'brand' => [
                        'value' => $query,
                        'boost' => $boost['brand_exact'],
                    ],
                ],
            ],
            [
                'match' => [
                    'name.suggest' => [
                        'query' => $query,
                        'boost' => $boost['name_phrase'],
                    ],
                ],
            ],
        ];
    }

    private function buildFilters(SearchQueryDTO $dto): array
    {
        $filters = [
            ['term' => ['is_active' => true]],
        ];

        if (!empty($dto->brands)) {
            $filters[] = ['terms' => ['brand' => $dto->brands]];
        }

        if (!empty($dto->categoryIds)) {
            $filters[] = ['terms' => ['category_ids' => $dto->categoryIds]];
        }

        if ($dto->priceMin !== null || $dto->priceMax !== null) {
            $range = [];
            if ($dto->priceMin !== null) {
                $range['gte'] = $dto->priceMin;
            }
            if ($dto->priceMax !== null) {
                $range['lte'] = $dto->priceMax;
            }
            $filters[] = ['range' => ['price' => $range]];
        }

        foreach ($dto->attributes as $key => $values) {
            $filters[] = ['terms' => ["attributes.{$key}" => $values]];
        }

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

        return $filters;
    }

    private function applyFunctionScore(array $query, SearchQueryDTO $dto): array
    {
        $fnConfig = $this->config['function_score'];

        $functions = [
            [
                'filter' => ['range' => ['inventory' => ['gte' => 1]]],
                'weight' => $fnConfig['inventory_weight'],
            ],
            [
                'filter' => ['range' => ['inventory' => ['lt' => $fnConfig['low_inventory_threshold'], 'gte' => 1]]],
                'weight' => 0.7,
            ],
            [
                'filter' => ['term' => ['inventory' => 0]],
                'weight' => $fnConfig['out_of_stock_penalty'],
            ],
            [
                'filter' => ['range' => ['rating' => ['gte' => $fnConfig['high_rating_threshold']]]],
                'weight' => $fnConfig['rating_weight'],
            ],
            [
                'field_value_factor' => [
                    'field' => 'rating',
                    'factor' => 0.2,
                    'modifier' => 'log1p',
                    'missing' => 1,
                ],
            ],
            [
                'field_value_factor' => [
                    'field' => 'review_count',
                    'factor' => 0.05,
                    'modifier' => 'log1p',
                    'missing' => 0,
                ],
            ],
        ];

        if ($dto->storeId !== null) {
            $functions[] = [
                'filter' => [
                    'nested' => [
                        'path' => 'stores',
                        'query' => [
                            'bool' => [
                                'must' => [
                                    ['term' => ['stores.store_id' => $dto->storeId]],
                                    ['term' => ['stores.featured' => true]],
                                ],
                            ],
                        ],
                    ],
                ],
                'weight' => 2.0,
            ];
        }

        return [
            'function_score' => [
                'query' => $query,
                'functions' => $functions,
                'score_mode' => 'sum',
                'boost_mode' => 'multiply',
                'min_score' => $this->config['min_score'],
            ],
        ];
    }

    private function buildSort(string $sort): array
    {
        $sortOptions = $this->config['sort_options'];

        if ($sort === 'relevance' || !isset($sortOptions[$sort])) {
            return [['_score' => 'desc'], ['sales_rank' => 'asc']];
        }

        $sortField = $sortOptions[$sort];
        if (is_string($sortField)) {
            return [[$sortField => 'desc']];
        }

        return [$sortField];
    }

    private function buildHighlight(): array
    {
        return [
            'fields' => [
                'name' => ['number_of_fragments' => 0],
                'description' => ['number_of_fragments' => 2, 'fragment_size' => 150],
                'brand' => ['number_of_fragments' => 0],
            ],
            'pre_tags' => ['<mark>'],
            'post_tags' => ['</mark>'],
        ];
    }

    public function buildPinnedQuery(array $pinnedIds, array $organicQuery): array
    {
        return [
            'pinned' => [
                'ids' => array_map('strval', $pinnedIds),
                'organic' => $organicQuery,
            ],
        ];
    }
}
