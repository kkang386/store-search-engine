<?php

namespace Tests\Feature\Api;

use App\DTOs\SuggestResultDTO;
use App\Services\Search\SuggestService;
use Tests\TestCase;
use Mockery;

class SuggestApiTest extends TestCase
{
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
