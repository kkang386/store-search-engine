<?php

namespace Tests\Feature\Api;

use App\DTOs\SuggestResultDTO;
use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Store;
use App\Services\Search\SuggestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class SuggestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise controller/validation with a mocked service; bypass the
        // API-token middleware so no seeded store token is required.
        $this->withoutMiddleware(AuthenticateApiToken::class);
        // store_id is validated with exists:stores,id — ensure store id 2 exists
        // so the store_id=2 case passes validation and reaches the mocked service.
        // Force the id explicitly: RefreshDatabase uses transactions (not
        // migrate:fresh), so auto-increment is not reset between tests.
        Store::forceCreate(['id' => 2, 'name' => 'Store Two', 'code' => 'store-2']);
    }

    public function test_suggest_returns_correct_structure(): void
    {
        $mock = Mockery::mock(SuggestService::class);
        $mock->shouldReceive('suggest')->andReturn(new SuggestResultDTO(
            queries: ['canon', 'canon camera', 'canon lens'],
            brands: [['value' => 'Canon', 'label' => 'Canon', 'count' => 150]],
            categories: [['value' => 'Cameras', 'label' => 'Cameras', 'count' => 50]],
            products: [['id' => 1, 'name' => 'Canon EOS R6', 'price' => 1999.99]],
        ));
        $this->app->instance(SuggestService::class, $mock);

        $response = $this->getJson('/api/search/suggest?q=canon');

        $response->assertStatus(200)
            ->assertJsonPath('queries.0', 'canon')
            ->assertJsonPath('brands.0.value', 'Canon')
            ->assertJsonPath('categories.0.value', 'Cameras');
    }

    public function test_suggest_respects_limit(): void
    {
        $mock = Mockery::mock(SuggestService::class);
        $mock->shouldReceive('suggest')->once()->withArgs(
            fn ($dto) => $dto->limit === 5
        )->andReturn(new SuggestResultDTO());
        $this->app->instance(SuggestService::class, $mock);

        $this->getJson('/api/search/suggest?q=canon&limit=5');
    }

    public function test_suggest_with_store_id(): void
    {
        $mock = Mockery::mock(SuggestService::class);
        $mock->shouldReceive('suggest')->once()->withArgs(
            fn ($dto) => $dto->storeId === 2
        )->andReturn(new SuggestResultDTO());
        $this->app->instance(SuggestService::class, $mock);

        $this->getJson('/api/search/suggest?q=canon&store_id=2');
    }

    public function test_suggest_query_too_long_fails(): void
    {
        $response = $this->getJson('/api/search/suggest?q=' . str_repeat('a', 300));

        $response->assertStatus(422);
    }
}
