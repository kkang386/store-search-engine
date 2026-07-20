<?php

namespace App\DTOs;

readonly class ProductDTO
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public string $slug,
        public ?string $brand,
        public float $price,
        public int $inventory,
        public float $rating,
        public int $reviewCount,
        public int $salesRank,
        public bool $isActive,
        public array $categoryIds,
        public array $categoryNames,
        public array $attributes,
        public array $images,
        public ?string $description = null,
        public ?array $meta = null,
        public ?float $score = null,
        public ?array $highlight = null,
        public ?array $explanation = null,
    ) {}

    public static function fromElasticsearch(array $hit, ?int $storeId = null): self
    {
        $source = $hit['_source'];
        $price = $source['price'] ?? 0.0;

        if ($storeId && !empty($source['stores'])) {
            foreach ($source['stores'] as $store) {
                if ($store['store_id'] === $storeId) {
                    $price = $store['sale_price'] ?? $store['price'] ?? $price;
                    break;
                }
            }
        }

        return new self(
            id: (int) $source['id'],
            sku: $source['sku'] ?? '',
            name: $source['name'] ?? '',
            slug: $source['slug'] ?? '',
            brand: $source['brand'] ?? null,
            price: (float) $price,
            inventory: (int) ($source['inventory'] ?? 0),
            rating: (float) ($source['rating'] ?? 0),
            reviewCount: (int) ($source['review_count'] ?? 0),
            salesRank: (int) ($source['sales_rank'] ?? 0),
            isActive: (bool) ($source['is_active'] ?? true),
            categoryIds: $source['category_ids'] ?? [],
            categoryNames: $source['category_names'] ?? [],
            attributes: $source['attributes'] ?? [],
            images: $source['images'] ?? [],
            description: $source['description'] ?? null,
            meta: $source['meta'] ?? null,
            score: $hit['_score'] ?? null,
            highlight: $hit['highlight'] ?? null,
            explanation: $hit['_explanation'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'price' => $this->price,
            'inventory' => $this->inventory,
            'rating' => $this->rating,
            'review_count' => $this->reviewCount,
            'sales_rank' => $this->salesRank,
            'is_active' => $this->isActive,
            'category_ids' => $this->categoryIds,
            'category_names' => $this->categoryNames,
            'attributes' => $this->attributes,
            'images' => $this->images,
            'description' => $this->description,
            'meta' => $this->meta,
            'score' => $this->score,
            'highlight' => $this->highlight,
        ];
    }
}
