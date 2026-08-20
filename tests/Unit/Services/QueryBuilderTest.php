<?php

namespace Tests\Unit\Services;

use App\DTOs\SearchQueryDTO;
use App\Services\Search\QueryBuilder;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new QueryBuilder();
    }

    public function test_build_basic_query(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'canon camera']);
        $body = $this->builder->build($dto);

        $this->assertArrayHasKey('query', $body);
        $this->assertArrayHasKey('from', $body);
        $this->assertArrayHasKey('size', $body);
        $this->assertArrayHasKey('sort', $body);
        $this->assertEquals(0, $body['from']);
        $this->assertEquals(24, $body['size']);
    }

    public function test_build_with_pagination(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'camera', 'page' => 3, 'per_page' => 12]);
        $body = $this->builder->build($dto);

        $this->assertEquals(24, $body['from']);
        $this->assertEquals(12, $body['size']);
    }

    public function test_empty_query_uses_match_all(): void
    {
        $dto = SearchQueryDTO::fromRequest([]);
        $body = $this->builder->build($dto);

        $this->assertArrayHasKey('query', $body);
    }

    public function test_query_has_function_score(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'camera']);
        $body = $this->builder->build($dto);

        $this->assertArrayHasKey('function_score', $body['query']);
        $this->assertArrayHasKey('functions', $body['query']['function_score']);
    }

    public function test_facets_included_by_default(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'camera']);
        $body = $this->builder->build($dto);

        $this->assertArrayHasKey('aggs', $body);
    }

    public function test_facets_excluded_when_disabled(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'camera', 'facets' => false]);
        $body = $this->builder->build($dto);

        $this->assertArrayNotHasKey('aggs', $body);
    }

    public function test_sort_price_asc(): void
    {
        $dto = SearchQueryDTO::fromRequest(['sort' => 'price_asc']);
        $body = $this->builder->build($dto);

        $this->assertNotEmpty($body['sort']);
    }

    public function test_brand_filter_applied(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'camera', 'brand' => ['Canon']]);
        $body = $this->builder->build($dto);

        $query = json_encode($body['query']);
        $this->assertStringContainsString('brand', $query);
        $this->assertStringContainsString('Canon', $query);
    }

    public function test_price_range_filter_applied(): void
    {
        $dto = SearchQueryDTO::fromRequest(['price_min' => 100, 'price_max' => 500]);
        $body = $this->builder->build($dto);

        $query = json_encode($body['query']);
        $this->assertStringContainsString('price', $query);
        $this->assertStringContainsString('100', $query);
    }

    public function test_store_id_filter_applied(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'camera', 'store_id' => 2]);
        $body = $this->builder->build($dto);

        $query = json_encode($body['query']);
        $this->assertStringContainsString('stores', $query);
        $this->assertStringContainsString('store_id', $query);
    }

    public function test_sku_prefix_clause_present_for_text_query(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'SKU-GBB']);
        $body = $this->builder->build($dto);

        $query = json_encode($body['query']);
        $this->assertStringContainsString('match_bool_prefix', $query);
        $this->assertStringContainsString('sku.text', $query);
    }

    public function test_no_sku_prefix_for_empty_query(): void
    {
        $dto = SearchQueryDTO::fromRequest([]); // match_all path
        $body = $this->builder->build($dto);

        $this->assertStringNotContainsString('match_bool_prefix', json_encode($body['query']));
    }

    public function test_exact_sku_term_pinned_to_top(): void
    {
        $dto = SearchQueryDTO::fromRequest(['q' => 'SKU-GBB004']);
        $query = json_encode($this->builder->build($dto)['query']);

        // exact-SKU keyword term with a large boost + case-insensitive match
        $this->assertStringContainsString('case_insensitive', $query);
        $this->assertStringContainsString('100000', $query);
    }
}
