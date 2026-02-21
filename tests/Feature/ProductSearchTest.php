<?php

use App\Domain\Product\Services\CacheService;
use App\Domain\Product\Services\ElasticSearchService;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    // Default search result
    $this->searchResult = [
        'data'         => [
            [
                'id'       => 1,
                'sku'      => 'SKU-001',
                'name'     => 'Laptop Pro',
                'price'    => 999.99,
                'category' => 'Electronics',
                'status'   => 'active',
            ],
            [
                'id'       => 2,
                'sku'      => 'SKU-002',
                'name'     => 'Laptop Air',
                'price'    => 799.00,
                'category' => 'Electronics',
                'status'   => 'active',
            ],
        ],
        'total'        => 2,
        'per_page'     => 15,
        'current_page' => 1,
        'last_page'    => 1,
    ];

    $this->mock(ElasticSearchService::class, function ($mock) {
        $mock->shouldReceive('search')->andReturn($this->searchResult);
    });

    $this->mock(CacheService::class, function ($mock) {
        $mock->shouldReceive('shouldSkipCache')->andReturn(false);
        $mock->shouldReceive('searchKey')->andReturn('search_key');
        $mock->shouldReceive('remember')->andReturnUsing(fn ($k, $cb) => $cb());
    });
});

describe('GET /api/v1/search/products', function () {

    it('returns search results with correct structure', function () {
        $this->getJson('/api/v1/search/products?q=laptop')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    it('accepts category filter', function () {
        $this->getJson('/api/v1/search/products?category=Electronics')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('accepts price range filter', function () {
        $this->getJson('/api/v1/search/products?min_price=100&max_price=2000')
            ->assertOk();
    });

    it('accepts status filter', function () {
        $this->getJson('/api/v1/search/products?status=active')
            ->assertOk();
    });

    it('accepts combined filters', function () {
        $this->getJson('/api/v1/search/products?q=laptop&category=Electronics&min_price=500&max_price=1500&status=active&sort=price&order=asc')
            ->assertOk()
            ->assertJsonPath('success', true);
    });

    it('rejects invalid sort field', function () {
        $this->getJson('/api/v1/search/products?sort=invalid_field')
            ->assertStatus(422);
    });

    it('rejects invalid order direction', function () {
        $this->getJson('/api/v1/search/products?order=random')
            ->assertStatus(422);
    });

    it('rejects invalid status value', function () {
        $this->getJson('/api/v1/search/products?status=discontinued')
            ->assertStatus(422);
    });

    it('bypasses cache when page exceeds 50', function () {
        $this->mock(CacheService::class, function ($mock) {
            $mock->shouldReceive('shouldSkipCache')->with(
                \Mockery::on(fn ($p) => ($p['page'] ?? 1) > 50)
            )->andReturn(true);
            $mock->shouldReceive('searchKey')->andReturn('key');
        });

        $this->mock(ElasticSearchService::class, function ($mock) {
            $mock->shouldReceive('search')->once()->andReturn($this->searchResult);
        });

        $this->getJson('/api/v1/search/products?page=51')->assertOk();
    });

});
