<?php

namespace Tests\Unit\DTOs;

use App\DTOs\SearchQueryDTO;
use Tests\TestCase;

class SearchQueryDTOTest extends TestCase
{
    public function test_from_request_defaults(): void
    {
        $dto = SearchQueryDTO::fromRequest([]);

        $this->assertEquals('', $dto->query);
        $this->assertEquals(1, $dto->page);
        $this->assertEquals(24, $dto->perPage);
        $this->assertEquals('relevance', $dto->sort);
        $this->assertEmpty($dto->brands);
        $this->assertEmpty($dto->categoryIds);
        $this->assertNull($dto->priceMin);
        $this->assertNull($dto->priceMax);
        $this->assertEmpty($dto->attributes);
        $this->assertNull($dto->storeId);
    }

    public function test_from_request_with_all_params(): void
    {
        $dto = SearchQueryDTO::fromRequest([
            'q' => 'canon camera',
            'page' => 2,
            'per_page' => 48,
            'sort' => 'price_asc',
            'brand' => ['Canon', 'Sony'],
            'category' => [1, 5],
            'price_min' => 100,
            'price_max' => 500,
            'attributes' => ['color' => ['Black', 'Silver']],
            'store_id' => 3,
        ]);

        $this->assertEquals('canon camera', $dto->query);
        $this->assertEquals(2, $dto->page);
        $this->assertEquals(48, $dto->perPage);
        $this->assertEquals('price_asc', $dto->sort);
        $this->assertEquals(['Canon', 'Sony'], $dto->brands);
        $this->assertEquals([1, 5], $dto->categoryIds);
        $this->assertEquals(100.0, $dto->priceMin);
        $this->assertEquals(500.0, $dto->priceMax);
        $this->assertEquals(['color' => ['Black', 'Silver']], $dto->attributes);
        $this->assertEquals(3, $dto->storeId);
    }

    public function test_get_offset(): void
    {
        $dto = SearchQueryDTO::fromRequest(['page' => 3, 'per_page' => 24]);
        $this->assertEquals(48, $dto->getOffset());
    }

    public function test_has_filters(): void
    {
        $noFilter = SearchQueryDTO::fromRequest(['q' => 'camera']);
        $this->assertFalse($noFilter->hasFilters());

        $withBrand = SearchQueryDTO::fromRequest(['brand' => ['Canon']]);
        $this->assertTrue($withBrand->hasFilters());

        $withPrice = SearchQueryDTO::fromRequest(['price_min' => 100]);
        $this->assertTrue($withPrice->hasFilters());
    }

    public function test_per_page_max_capped(): void
    {
        // Pin the cap so the assertion is independent of the ambient
        // SEARCH_PER_PAGE_MAX env (the container sets it to 192).
        config(['search.per_page_max' => 100]);

        $dto = SearchQueryDTO::fromRequest(['per_page' => 9999]);
        $this->assertLessThanOrEqual(100, $dto->perPage);
    }

    public function test_page_min_is_one(): void
    {
        $dto = SearchQueryDTO::fromRequest(['page' => -5]);
        $this->assertEquals(1, $dto->page);
    }

    public function test_query_is_trimmed(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => '  canon  ']);
        $this->assertEquals('canon', $dto->query);
    }
}
