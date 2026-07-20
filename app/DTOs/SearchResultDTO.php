<?php

namespace App\DTOs;

readonly class SearchResultDTO
{
    public function __construct(
        public array $products,
        public array $facets,
        public PaginationDTO $pagination,
        public SearchMetaDTO $meta,
        public ?array $banner = null,
        public ?string $redirect = null,
    ) {}

    public function toArray(): array
    {
        return [
            'products' => array_map(fn (ProductDTO $p) => $p->toArray(), $this->products),
            'facets' => array_map(fn (FacetDTO $f) => $f->toArray(), $this->facets),
            'pagination' => $this->pagination->toArray(),
            'meta' => $this->meta->toArray(),
            'banner' => $this->banner,
            'redirect' => $this->redirect,
        ];
    }
}
