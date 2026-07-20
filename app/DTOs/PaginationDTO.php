<?php

namespace App\DTOs;

readonly class PaginationDTO
{
    public function __construct(
        public int $total,
        public int $page,
        public int $perPage,
        public int $totalPages,
        public bool $hasMore,
    ) {}

    public static function fromTotal(int $total, int $page, int $perPage): self
    {
        $totalPages = (int) ceil($total / max(1, $perPage));
        return new self(
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
            hasMore: $page < $totalPages,
        );
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total_pages' => $this->totalPages,
            'has_more' => $this->hasMore,
        ];
    }
}
