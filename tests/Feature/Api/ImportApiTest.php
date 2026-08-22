<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\ImportRequest;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreApiToken;
use App\Services\Search\IndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Run dispatched jobs inline so a request reaches a terminal status
        // within the test (the container env sets QUEUE_CONNECTION=redis, which
        // overrides phpunit.xml's non-forced sync env).
        config(['queue.default' => 'sync']);
    }

    /** Create a store and a matching API token; returns [Store, plainToken]. */
    private function storeWithToken(string $code = 'store-a'): array
    {
        $store = Store::create(['name' => 'Store ' . $code, 'code' => $code]);
        $plain = 'tok-' . Str::random(24);
        StoreApiToken::create([
            'store_id' => $store->id,
            'name'     => 'test-token',
            'token'    => hash('sha256', $plain),
        ]);

        return [$store, $plain];
    }

    private function auth(string $plain): array
    {
        return ['Authorization' => 'Bearer ' . $plain];
    }

    /** Stub the ES indexer so the (sync-queued) inline reindex never hits Elasticsearch. */
    private function fakeIndexer(): void
    {
        $mock = Mockery::mock(IndexingService::class);
        $mock->shouldReceive('bulkIndex')->andReturnUsing(fn ($products) => [
            'successIds' => $products->pluck('id')->all(),
            'failedIds'  => [],
        ]);
        $this->app->instance(IndexingService::class, $mock);
    }

    // ---------------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------------

    public function test_missing_token_returns_401(): void
    {
        [$store] = $this->storeWithToken();

        $this->postJson("/api/import/{$store->id}/products", [])
            ->assertStatus(401);
    }

    public function test_token_for_another_store_returns_403(): void
    {
        [$storeA, $tokenA] = $this->storeWithToken('store-a');
        [$storeB]          = $this->storeWithToken('store-b');

        $this->postJson("/api/import/{$storeB->id}/products", [], $this->auth($tokenA))
            ->assertStatus(403)
            ->assertJson(['error' => 'Token is not authorized for this store']);
    }

    // ---------------------------------------------------------------------
    // Payload validation
    // ---------------------------------------------------------------------

    public function test_non_array_body_returns_422(): void
    {
        [$store, $token] = $this->storeWithToken();

        // An object body (not a list) is rejected.
        $this->json('POST', "/api/import/{$store->id}/categories", ['not' => 'a list'], $this->auth($token))
            ->assertStatus(422);
    }

    public function test_oversized_product_name_returns_422(): void
    {
        [$store, $token] = $this->storeWithToken();

        $this->postJson("/api/import/{$store->id}/products", [
            ['sku' => 'A1', 'name' => str_repeat('X', 200)],
        ], $this->auth($token))->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // Categories upsert
    // ---------------------------------------------------------------------

    public function test_categories_upsert_accepts_and_completes(): void
    {
        [$store, $token] = $this->storeWithToken();

        $response = $this->postJson("/api/import/{$store->id}/categories", [
            ['category_id' => '100', 'parent_category_id' => null, 'name' => 'Root', 'slug' => 'root', 'depth' => 0, 'sort_order' => 1, 'is_active' => true],
            ['category_id' => '101', 'parent_category_id' => '100', 'name' => 'Child', 'slug' => 'child', 'depth' => 1, 'sort_order' => 2, 'is_active' => true],
        ], $this->auth($token));

        $response->assertStatus(202)->assertJsonStructure(['request_id', 'status', 'total']);
        $requestId = $response->json('request_id');

        // Sync queue => job already ran; status is terminal.
        $this->getJson("/api/import/{$store->id}/status/{$requestId}", $this->auth($token))
            ->assertStatus(200)
            ->assertJson([
                'status' => 'completed',
                'type'   => 'categories',
                'result' => ['created' => 2, 'updated' => 0, 'failed' => 0],
            ]);

        $root  = Category::where('slug', 'root')->first();
        $child = Category::where('slug', 'child')->first();
        $this->assertNotNull($root);
        $this->assertSame($root->id, $child->parent_id, 'child should be parented to root');

        // External id persisted on the store pivot for later product resolution.
        $this->assertDatabaseHas('store_categories', [
            'store_id'    => $store->id,
            'category_id' => $child->id,
            'external_id' => '101',
        ]);
    }

    // ---------------------------------------------------------------------
    // Products upsert
    // ---------------------------------------------------------------------

    public function test_products_upsert_links_categories_and_reports_indexed(): void
    {
        $this->fakeIndexer();
        [$store, $token] = $this->storeWithToken();

        // Categories first, so the product can resolve external ids 100/101.
        $this->postJson("/api/import/{$store->id}/categories", [
            ['category_id' => '100', 'name' => 'Root', 'slug' => 'root'],
            ['category_id' => '101', 'name' => 'Child', 'slug' => 'child'],
        ], $this->auth($token))->assertStatus(202);

        $response = $this->postJson("/api/import/{$store->id}/products", [
            [
                'sku' => 'SKU-1', 'name' => 'Product One', 'brand' => 'Acme',
                'price' => 19.99, 'inventory' => 5, 'is_active' => true,
                'attributes' => ['color' => 'black'], 'images' => ['/a.jpg'],
                'meta' => ['x' => 1], 'sales_rank' => 3,
                'product_categories' => [101, 100],
            ],
        ], $this->auth($token));

        $response->assertStatus(202);
        $requestId = $response->json('request_id');

        $this->getJson("/api/import/{$store->id}/status/{$requestId}", $this->auth($token))
            ->assertStatus(200)
            ->assertJson([
                'status' => 'completed',
                'type'   => 'products',
                'result' => ['created' => 1, 'updated' => 0, 'failed' => 0, 'indexed' => 1],
            ]);

        $product = Product::where('sku', 'SKU-1')->first();
        $this->assertNotNull($product);
        $this->assertSame(19.99, (float) $product->price);
        $this->assertSame(['color' => 'black'], $product->attributes);

        // Per-store price/inventory stored.
        $this->assertDatabaseHas('store_products', [
            'store_id'   => $store->id,
            'product_id' => $product->id,
            'inventory'  => 5,
        ]);

        // First product_categories entry (101/child) is primary.
        $child = Category::where('slug', 'child')->first();
        $root  = Category::where('slug', 'root')->first();
        $this->assertEquals(1, (int) $product->categories()->where('category_id', $child->id)->first()->pivot->is_primary);
        $this->assertEquals(0, (int) $product->categories()->where('category_id', $root->id)->first()->pivot->is_primary);
    }

    public function test_products_upsert_is_idempotent(): void
    {
        $this->fakeIndexer();
        [$store, $token] = $this->storeWithToken();

        $payload = [['sku' => 'SKU-DUP', 'name' => 'First Name', 'price' => 1]];
        $this->postJson("/api/import/{$store->id}/products", $payload, $this->auth($token))->assertStatus(202);

        $second = $this->postJson("/api/import/{$store->id}/products", [
            ['sku' => 'SKU-DUP', 'name' => 'Updated Name', 'price' => 2],
        ], $this->auth($token));
        $requestId = $second->json('request_id');

        $this->getJson("/api/import/{$store->id}/status/{$requestId}", $this->auth($token))
            ->assertJson(['result' => ['created' => 0, 'updated' => 1]]);

        $this->assertSame(1, Product::where('sku', 'SKU-DUP')->count());
        $this->assertSame('Updated Name', Product::where('sku', 'SKU-DUP')->first()->name);
    }

    public function test_row_failure_marks_status_error(): void
    {
        [$store, $token] = $this->storeWithToken();

        // Force the service to report a failed row without touching ES.
        $mock = Mockery::mock(\App\Services\Admin\ApiImportService::class);
        $mock->shouldReceive('upsertProducts')->andReturn([
            'created' => 0, 'updated' => 0, 'failed' => 1, 'affected_ids' => [],
        ]);
        $this->app->instance(\App\Services\Admin\ApiImportService::class, $mock);

        $response = $this->postJson("/api/import/{$store->id}/products", [
            ['sku' => 'SKU-X', 'name' => 'Whatever'],
        ], $this->auth($token));
        $requestId = $response->json('request_id');

        $this->getJson("/api/import/{$store->id}/status/{$requestId}", $this->auth($token))
            ->assertStatus(200)
            ->assertJson(['status' => 'error', 'result' => ['failed' => 1]]);
    }

    // ---------------------------------------------------------------------
    // Status endpoint
    // ---------------------------------------------------------------------

    public function test_status_unknown_request_id_returns_404(): void
    {
        [$store, $token] = $this->storeWithToken();

        $this->getJson("/api/import/{$store->id}/status/" . Str::uuid(), $this->auth($token))
            ->assertStatus(404);
    }

    public function test_status_is_scoped_to_store(): void
    {
        [$storeA, $tokenA] = $this->storeWithToken('store-a');
        [$storeB, $tokenB] = $this->storeWithToken('store-b');

        // A request owned by store A.
        $req = ImportRequest::create([
            'request_id' => (string) Str::uuid(),
            'store_id'   => $storeA->id,
            'type'       => 'categories',
            'status'     => 'completed',
            'total'      => 0,
        ]);

        // Store B cannot see store A's request.
        $this->getJson("/api/import/{$storeB->id}/status/{$req->request_id}", $this->auth($tokenB))
            ->assertStatus(404);

        // Store A can.
        $this->getJson("/api/import/{$storeA->id}/status/{$req->request_id}", $this->auth($tokenA))
            ->assertStatus(200)
            ->assertJson(['request_id' => $req->request_id]);
    }
}
