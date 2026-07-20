<?php

namespace App\DTOs;

readonly class SearchQueryDTO
{
    public function __construct(
        public string $query = '',
        public int $page = 1,
        public int $perPage = 24,
        public string $sort = 'relevance',
        public array $brands = [],
        public array $categoryIds = [],
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public array $attributes = [],
        public ?int $storeId = null,
        public bool $withFacets = true,
        public bool $withExplanation = false,
    ) {}

    public static function fromRequest(array $data, ?int $storeId = null): self
    {
        return new self(
            query: trim($data['q'] ?? ''),
            page: max(1, (int) ($data['page'] ?? 1)),
            perPage: min(
                config('search.per_page_max', 100),
                max(1, (int) ($data['per_page'] ?? config('search.per_page_default', 24)))
            ),
            sort: $data['sort'] ?? 'relevance',
            brands: (array) ($data['brand'] ?? []),
            categoryIds: array_map('intval', (array) ($data['category'] ?? [])),
            priceMin: isset($data['price_min']) ? (float) $data['price_min'] : null,
            priceMax: isset($data['price_max']) ? (float) $data['price_max'] : null,
            attributes: self::parseAttributes($data['attributes'] ?? []),
            storeId: $storeId ?? (isset($data['store_id']) ? (int) $data['store_id'] : null),
            withFacets: (bool) ($data['facets'] ?? true),
            withExplanation: (bool) ($data['explain'] ?? false),
        );
    }

    private static function parseAttributes(array $raw): array
    {
        $parsed = [];
        foreach ($raw as $key => $value) {
            $parsed[$key] = is_array($value) ? $value : [$value];
        }
        return $parsed;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function hasFilters(): bool
    {
        return !empty($this->brands)
            || !empty($this->categoryIds)
            || $this->priceMin !== null
            || $this->priceMax !== null
            || !empty($this->attributes);
    }

    public function isEmpty(): bool
    {
        return $this->query === '' && !$this->hasFilters();
    }
}
