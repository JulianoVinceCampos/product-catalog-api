<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\DTOs\ProductDTO;
use App\Domain\Product\Jobs\SyncProductToElastic;
use App\Domain\Product\Repositories\ProductRepository;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(
        private readonly ProductRepository   $repository,
        private readonly CacheService        $cache,
        private readonly ElasticSearchService $elastic,
    ) {}

    // ─── Queries ───────────────────────────────────────────────────────

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function findById(int $id): ?Product
    {
        return $this->cache->remember(
            $this->cache->productKey($id),
            fn () => $this->repository->findById($id),
        );
    }

    public function search(array $params): array
    {
        if ($this->cache->shouldSkipCache($params)) {
            return $this->elastic->search($params);
        }

        return $this->cache->remember(
            $this->cache->searchKey($params),
            fn () => $this->elastic->search($params),
        );
    }

    // ─── Commands ──────────────────────────────────────────────────────

    public function create(ProductDTO $dto): Product
    {
        $product = $this->repository->create($dto);

        SyncProductToElastic::dispatch($product->id);

        return $product;
    }

    public function update(Product $product, ProductDTO $dto): Product
    {
        $updated = $this->repository->update($product, $dto);

        SyncProductToElastic::dispatch($updated->id);
        $this->cache->invalidateProduct($product->id);
        $this->cache->invalidateSearch();

        return $updated;
    }

    public function delete(Product $product): void
    {
        $id = $product->id;

        $this->repository->delete($product);

        $this->elastic->deleteProduct($id);
        $this->cache->invalidateProduct($id);
        $this->cache->invalidateSearch();
    }

    public function uploadImage(Product $product, UploadedFile $file): Product
    {
        // Delete old image if exists
        if ($product->image_url) {
            $this->deleteOldImage($product->image_url);
        }

        $path = $file->store("products/{$product->id}", 's3');
        $url  = Storage::disk('s3')->url($path);

        $product->update(['image_url' => $url]);

        $this->cache->invalidateProduct($product->id);

        Log::info('Product image uploaded', [
            'product_id' => $product->id,
            'path'       => $path,
        ]);

        return $product->fresh();
    }

    // ─── Private ───────────────────────────────────────────────────────

    private function deleteOldImage(string $url): void
    {
        try {
            $path = parse_url($url, PHP_URL_PATH);
            $path = ltrim($path, '/');
            Storage::disk('s3')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete old product image', ['error' => $e->getMessage()]);
        }
    }
}
