<?php

namespace App\Services\Search;

use App\DTOs\FacetDTO;
use App\DTOs\SearchQueryDTO;

class FacetBuilder
{
    private const ALWAYS_PRESENT_FACETS = ['brand', 'price_range'];

    private const KNOWN_ATTRIBUTE_FACETS = [
        'color', 'sensor_size', 'mount', 'resolution', 'refresh_rate',
        'panel_type', 'storage', 'ram', 'connectivity',
    ];

    public function build(SearchQueryDTO $dto): array
    {
        $aggs = [];

        $aggs['brands'] = [
            'terms' => [
                'field' => 'brand',
                'size' => config('search.facets.max_buckets', 50),
                'min_doc_count' => 1,
                'order' => ['_count' => 'desc'],
            ],
        ];

        $aggs['price_stats'] = [
            'stats' => ['field' => 'price'],
        ];

        $aggs['price_ranges'] = [
            'auto_date_histogram' => ['field' => 'price', 'buckets' => 10],
        ];

        $aggs['categories'] = [
            'terms' => [
                'field' => 'category_names',
                'size' => 20,
                'min_doc_count' => 1,
            ],
        ];

        foreach (self::KNOWN_ATTRIBUTE_FACETS as $attr) {
            $aggs["attr_{$attr}"] = [
                'terms' => [
                    'field' => "attributes.{$attr}",
                    'size' => 30,
                    'min_doc_count' => 1,
                ],
            ];
        }

        if ($dto->storeId !== null) {
            $aggs['store_inventory'] = [
                'nested' => ['path' => 'stores'],
                'aggs' => [
                    'filtered_store' => [
                        'filter' => ['term' => ['stores.store_id' => $dto->storeId]],
                        'aggs' => [
                            'in_stock' => [
                                'filter' => ['range' => ['stores.inventory' => ['gte' => 1]]],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $aggs;
    }

    public function parse(array $aggregations): array
    {
        $facets = [];

        if (isset($aggregations['brands']['buckets']) && !empty($aggregations['brands']['buckets'])) {
            $facets[] = FacetDTO::fromAggregation('brand', $aggregations['brands']);
        }

        if (isset($aggregations['price_stats'])) {
            $stats = $aggregations['price_stats'];
            if (($stats['count'] ?? 0) > 0) {
                $facets[] = new FacetDTO(
                    key: 'price_range',
                    label: 'Price',
                    type: 'range',
                    values: [],
                    min: $stats['min'] ?? null,
                    max: $stats['max'] ?? null,
                );
            }
        }

        if (isset($aggregations['categories']['buckets']) && !empty($aggregations['categories']['buckets'])) {
            $facets[] = FacetDTO::fromAggregation('category', $aggregations['categories']);
        }

        foreach (self::KNOWN_ATTRIBUTE_FACETS as $attr) {
            $key = "attr_{$attr}";
            if (isset($aggregations[$key]['buckets']) && !empty($aggregations[$key]['buckets'])) {
                $facets[] = FacetDTO::fromAggregation($attr, $aggregations[$key]);
            }
        }

        return $facets;
    }
}
