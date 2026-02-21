<?php

namespace App\Domain\Product\Observers;

use App\Domain\Product\Jobs\SyncProductToElastic;
use App\Domain\Product\Services\CacheService;
use App\Domain\Product\Services\ElasticSearchService;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    public function __construct(
        private readonly CacheService         $cache,
        private readonly ElasticSearchService $elastic,
    ) {}

    public function created(Product $product): void
    {
        Log::debug('Observer: product created', ['product_id' => $product->id]);
        SyncProductToElastic::dispatch($product->id);
    }

    public function updated(Product $product): void
    {
        Log::debug('Observer: product updated', ['product_id' => $product->id]);
        SyncProductToElastic::dispatch($product->id);
        $this->cache->invalidateProduct($product->id);
        $this->cache->invalidateSearch();
    }

    public function deleted(Product $product): void
    {
        Log::debug('Observer: product deleted', ['product_id' => $product->id]);
        $this->elastic->deleteProduct($product->id);
        $this->cache->invalidateProduct($product->id);
        $this->cache->invalidateSearch();
    }
}
