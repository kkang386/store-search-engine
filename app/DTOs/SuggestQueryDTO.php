<?php

namespace App\DTOs;

readonly class SuggestQueryDTO
{
    public function __construct(
        public string $query = '',
        public int $limit = 10,
        public ?int $storeId = null,
        public array $types = ['queries', 'brands', 'categories', 'products'],
    ) {}

    public static function fromRequest(array $data, ?int $storeId = null): self
    {
        return new self(
            query: trim($data['q'] ?? ''),
            limit: min(20, max(1, (int) ($data['limit'] ?? config('search.autocomplete_limit', 10)))),
            storeId: $storeId ?? (isset($data['store_id']) ? (int) $data['store_id'] : null),
            types: (array) ($data['types'] ?? ['queries', 'brands', 'categories', 'products']),
        );
    }
}
