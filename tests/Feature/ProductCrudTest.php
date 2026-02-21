<?php

use App\Domain\Product\Services\CacheService;
use App\Domain\Product\Services\ElasticSearchService;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake queue to prevent real job dispatch
    Queue::fake();

    // Mock Elasticsearch — no real ES needed for CRUD tests
    $this->mock(ElasticSearchService::class)->shouldIgnoreMissing();

    // Mock CacheService to use array driver behaviour
    $this->mock(CacheService::class, function ($mock) {
        $mock->shouldReceive('productKey')->andReturnUsing(fn ($id) => "product:{$id}");
        $mock->shouldReceive('remember')->andReturnUsing(fn ($key, $cb) => $cb());
        $mock->shouldReceive('invalidateProduct')->andReturn(null);
        $mock->shouldReceive('invalidateSearch')->andReturn(null);
        $mock->shouldReceive('shouldSkipCache')->andReturn(false);
        $mock->shouldReceive('searchKey')->andReturn('search_key');
    });
});

// ─── CREATE ─────────────────────────────────────────────────────────────────

describe('POST /api/v1/products', function () {

    it('creates a product with valid data', function () {
        $response = $this->postJson('/api/v1/products', [
            'sku'         => 'SKU-TEST-001',
            'name'        => 'Test Laptop',
            'description' => 'A great test laptop',
            'price'       => 999.99,
            'category'    => 'Electronics',
            'status'      => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'SKU-TEST-001')
            ->assertJsonPath('data.name', 'Test Laptop')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonStructure(['success', 'message', 'data' => [
                'id', 'sku', 'name', 'description', 'price', 'category', 'status', 'created_at',
            ]]);

        $this->assertDatabaseHas('products', ['sku' => 'SKU-TEST-001']);
    });

    it('rejects duplicate SKU', function () {
        Product::factory()->create(['sku' => 'DUPE-001']);

        $this->postJson('/api/v1/products', [
            'sku'   => 'DUPE-001',
            'name'  => 'Another Product',
            'price' => 10.0,
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['sku']]);
    });

    it('rejects name shorter than 3 characters', function () {
        $this->postJson('/api/v1/products', [
            'sku'   => 'SKU-X',
            'name'  => 'AB',
            'price' => 10.0,
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name']]);
    });

    it('rejects zero price', function () {
        $this->postJson('/api/v1/products', [
            'sku'   => 'SKU-ZERO',
            'name'  => 'Free Product',
            'price' => 0,
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['price']]);
    });

    it('rejects negative price', function () {
        $this->postJson('/api/v1/products', [
            'sku'   => 'SKU-NEG',
            'name'  => 'Negative Product',
            'price' => -5.00,
        ])->assertStatus(422);
    });

    it('rejects missing required fields', function () {
        $this->postJson('/api/v1/products', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['sku', 'name', 'price']]);
    });

    it('defaults status to active', function () {
        $response = $this->postJson('/api/v1/products', [
            'sku'   => 'SKU-STATUS',
            'name'  => 'Status Product',
            'price' => 1.0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active');
    });

});

// ─── READ ────────────────────────────────────────────────────────────────────

describe('GET /api/v1/products/{id}', function () {

    it('returns a product by ID', function () {
        $product = Product::factory()->create();

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku);
    });

    it('returns 404 for non-existent product', function () {
        $this->getJson('/api/v1/products/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    });

    it('returns paginated list', function () {
        Product::factory()->count(20)->create();

        $this->getJson('/api/v1/products?per_page=5&page=1')
            ->assertOk()
            ->assertJsonStructure(['success', 'data', 'meta' => [
                'current_page', 'last_page', 'per_page', 'total',
            ]])
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 1);
    });

    it('filters by status', function () {
        Product::factory()->count(3)->active()->create();
        Product::factory()->count(2)->inactive()->create();

        $response = $this->getJson('/api/v1/products?status=active');
        $response->assertOk();

        $items = $response->json('data');
        foreach ($items as $item) {
            expect($item['status'])->toBe('active');
        }
    });

});

// ─── UPDATE ──────────────────────────────────────────────────────────────────

describe('PUT /api/v1/products/{id}', function () {

    it('updates a product', function () {
        $product = Product::factory()->create(['name' => 'Old Name', 'price' => 100.00]);

        $this->putJson("/api/v1/products/{$product->id}", [
            'name'  => 'Updated Name',
            'price' => 199.99,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.price', 199.99);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Name']);
    });

    it('returns 404 when updating non-existent product', function () {
        $this->putJson('/api/v1/products/999999', ['name' => 'Ghost'])
            ->assertNotFound();
    });

    it('rejects invalid status value', function () {
        $product = Product::factory()->create();

        $this->putJson("/api/v1/products/{$product->id}", ['status' => 'pending'])
            ->assertStatus(422);
    });

});

// ─── DELETE ──────────────────────────────────────────────────────────────────

describe('DELETE /api/v1/products/{id}', function () {

    it('soft-deletes a product', function () {
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id, 'deleted_at' => null]);
    });

    it('returns 404 for already deleted product', function () {
        $product = Product::factory()->create();
        $product->delete();

        $this->deleteJson("/api/v1/products/{$product->id}")
            ->assertNotFound();
    });

});
