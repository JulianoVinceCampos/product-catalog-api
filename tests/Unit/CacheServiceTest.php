<?php

use App\Domain\Product\Services\CacheService;

describe('CacheService', function () {

    beforeEach(function () {
        $this->cache = new CacheService();
    });

    it('generates consistent product cache key', function () {
        $key1 = $this->cache->productKey(42);
        $key2 = $this->cache->productKey(42);

        expect($key1)->toBe($key2)->toBe('product:42');
    });

    it('generates different product keys for different IDs', function () {
        expect($this->cache->productKey(1))->not->toBe($this->cache->productKey(2));
    });

    it('generates consistent search key for same params', function () {
        $params = ['q' => 'laptop', 'category' => 'Electronics', 'page' => 1];

        $key1 = $this->cache->searchKey($params);
        $key2 = $this->cache->searchKey($params);

        expect($key1)->toBe($key2);
    });

    it('generates different search keys for different params', function () {
        $key1 = $this->cache->searchKey(['q' => 'laptop']);
        $key2 = $this->cache->searchKey(['q' => 'phone']);

        expect($key1)->not->toBe($key2);
    });

    it('generates same search key regardless of param order', function () {
        $key1 = $this->cache->searchKey(['q' => 'laptop', 'category' => 'Electronics']);
        $key2 = $this->cache->searchKey(['category' => 'Electronics', 'q' => 'laptop']);

        expect($key1)->toBe($key2);
    });

    it('should skip cache for page numbers above 50', function () {
        expect($this->cache->shouldSkipCache(['page' => 51]))->toBeTrue();
        expect($this->cache->shouldSkipCache(['page' => 100]))->toBeTrue();
    });

    it('should not skip cache for normal page numbers', function () {
        expect($this->cache->shouldSkipCache(['page' => 1]))->toBeFalse();
        expect($this->cache->shouldSkipCache(['page' => 50]))->toBeFalse();
        expect($this->cache->shouldSkipCache([]))->toBeFalse();
    });

});
