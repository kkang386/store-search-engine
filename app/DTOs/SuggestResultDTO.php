<?php

namespace App\DTOs;

readonly class SuggestResultDTO
{
    public function __construct(
        public array $queries = [],
        public array $brands = [],
        public array $categories = [],
        public array $products = [],
        public int $tookMs = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'queries' => $this->queries,
            'brands' => $this->brands,
            'categories' => $this->categories,
            'products' => $this->products,
        ];
    }
}
