<?php

namespace App\Domain\Product\Jobs;

use App\Domain\Product\Services\ElasticSearchService;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToElastic implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        private readonly int $productId,
    ) {}

    public function handle(ElasticSearchService $elastic): void
    {
        $product = Product::find($this->productId);

        if (! $product) {
            Log::warning('SyncProductToElastic: product not found, skipping', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        $elastic->indexProduct($product);

        Log::info('Product synced to Elasticsearch via Job', [
            'product_id' => $this->productId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncProductToElastic job failed', [
            'product_id' => $this->productId,
            'error'      => $exception->getMessage(),
        ]);
    }
}
