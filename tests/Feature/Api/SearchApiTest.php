<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Services\Search\SearchService;
use App\DTOs\SearchQueryDTO;
use App\DTOs\SearchResultDTO;
use App\DTOs\PaginationDTO;
use App\DTOs\SearchMetaDTO;
use Tests\TestCase;
use Mockery;

class SearchApiTest extends TestCase
{
    public function test_search_endpoint_returns_200(): void
    {
        $this->mockSearchService();

        $response = $this->getJson('/api/search?q=camera');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products',
                'facets',
                'pagination' => ['total', 'page', 'per_page', 'total_pages', 'has_more'],
                'meta' => ['query', 'total_hits', 'took_ms', 'timed_out'],
            ]);
    }

    public function test_search_without_query_returns_200(): void
    {
        $this->mockSearchService();

        $response = $this->getJson('/api/search');

        $response->assertStatus(200);
    }

    public function test_search_with_brand_filter(): void
    {
        $this->mockSearchService();

        $response = $this->getJson('/api/search?q=camera&brand[]=Canon&brand[]=Sony');

        $response->assertStatus(200);
    }

    public function test_search_with_price_range(): void
    {
        $this->mockSearchService();

        $response = $this->getJson('/api/search?q=camera&price_min=100&price_max=500');

        $response->assertStatus(200);
    }

    public function test_search_invalid_sort_returns_422(): void
    {
        $response = $this->getJson('/api/search?q=camera&sort=invalid_sort');

        $response->assertStatus(422);
    }

    public function test_search_per_page_over_limit_returns_422(): void
    {
        $response = $this->getJson('/api/search?q=camera&per_page=999');

        $response->assertStatus(422);
    }

    public function test_suggest_endpoint_returns_200(): void
    {
        $this->mockSuggestService();

        $response = $this->getJson('/api/search/suggest?q=can');

        $response->assertStatus(200)
            ->assertJsonStructure(['queries', 'brands', 'categories', 'products']);
    }

    public function test_suggest_requires_query(): void
    {
        $response = $this->getJson('/api/search/suggest');

        $response->assertStatus(422);
    }

    public function test_suggest_with_short_query(): void
    {
        $this->mockSuggestService();

        $response = $this->getJson('/api/search/suggest?q=c');

        $response->assertStatus(200);
    }

    public function test_click_tracking_endpoint(): void
    {
        $this->mockAnalyticsService();

        $response = $this->postJson('/api/search/click', [
            'query' => 'camera',
            'product_id' => 1,
            'position' => 0,
            'store_id' => 1,
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
    }

    public function test_click_tracking_validates_required_fields(): void
    {
        $response = $this->postJson('/api/search/click', []);

        $response->assertStatus(422);
    }

    private function mockSearchService(): void
    {
        $mock = Mockery::mock(\App\Services\Search\SearchService::class);
        $mock->shouldReceive('search')
            ->andReturn(new SearchResultDTO(
                products: [],
                facets: [],
                pagination: PaginationDTO::fromTotal(0, 1, 24),
                meta: new SearchMetaDTO('camera', 0, 0.0, 5, false),
            ));

        $this->app->instance(\App\Services\Search\SearchService::class, $mock);
    }

    private function mockSuggestService(): void
    {
        $mock = Mockery::mock(\App\Services\Search\SuggestService::class);
        $mock->shouldReceive('suggest')
            ->andReturn(new \App\DTOs\SuggestResultDTO());

        $this->app->instance(\App\Services\Search\SuggestService::class, $mock);
    }

    private function mockAnalyticsService(): void
    {
        $mock = Mockery::mock(\App\Services\Admin\AnalyticsService::class);
        $mock->shouldReceive('trackClick')->once();

        $this->app->instance(\App\Services\Admin\AnalyticsService::class, $mock);
    }
}
