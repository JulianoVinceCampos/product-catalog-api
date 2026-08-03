<?php

use App\Domain\Product\Services\CacheService;
use App\Domain\Product\Services\ElasticSearchService;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->mock(ElasticSearchService::class)->shouldIgnoreMissing();
});

describe('Cache behaviour', function () {

    it('caches product on first request and hits cache on second', function () {
        Cache::flush();

        $product  = Product::factory()->create();
        $cacheKey = "product:{$product->id}";

        // First request: cache MISS
        expect(Cache::has($cacheKey))->toBeFalse();

        $this->getJson("/api/v1/products/{$product->id}")->assertOk();

        // Second request: cache HIT
        expect(Cache::has($cacheKey))->toBeTrue();

        $cached = Cache::get($cacheKey);
        expect($cached)->not->toBeNull()
            ->and($cached->id)->toBe($product->id);
    });

    it('invalidates cache after product update', function () {
        Cache::flush();

        $product  = Product::factory()->create();
        $cacheKey = "product:{$product->id}";

        // Prime cache
        $this->getJson("/api/v1/products/{$product->id}")->assertOk();
        expect(Cache::has($cacheKey))->toBeTrue();

        // Update should invalidate cache
        $this->putJson("/api/v1/products/{$product->id}", ['name' => 'Updated Name'])->assertOk();

        expect(Cache::has($cacheKey))->toBeFalse();
    });

    it('invalidates cache after product deletion', function () {
        Cache::flush();

        $product  = Product::factory()->create();
        $cacheKey = "product:{$product->id}";

        // Prime cache
        $this->getJson("/api/v1/products/{$product->id}")->assertOk();
        expect(Cache::has($cacheKey))->toBeTrue();

        // Delete should invalidate cache
        $this->deleteJson("/api/v1/products/{$product->id}")->assertOk();

        expect(Cache::has($cacheKey))->toBeFalse();
    });

});
