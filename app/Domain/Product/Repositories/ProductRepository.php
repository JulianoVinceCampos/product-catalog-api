<?php

namespace App\Domain\Product\Repositories;

use App\Domain\Product\DTOs\ProductDTO;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ProductRepository
{
    private const ALLOWED_SORTS  = ['price', 'created_at'];
    private const ALLOWED_ORDERS = ['asc', 'desc'];

    // ─── Queries ───────────────────────────────────────────────────────

    public function findById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function findAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        $sort  = in_array($filters['sort'] ?? '', self::ALLOWED_SORTS, true)
            ? $filters['sort']
            : 'created_at';

        $order = in_array($filters['order'] ?? '', self::ALLOWED_ORDERS, true)
            ? $filters['order']
            : 'desc';

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    // ─── Commands ──────────────────────────────────────────────────────

    public function create(ProductDTO $dto): Product
    {
        $product = Product::create($dto->toArray());

        Log::info('Product created', [
            'product_id' => $product->id,
            'sku'        => $product->sku,
        ]);

        return $product;
    }

    public function update(Product $product, ProductDTO $dto): Product
    {
        $product->update($dto->toArray());

        Log::info('Product updated', [
            'product_id' => $product->id,
            'sku'        => $product->sku,
        ]);

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        Log::info('Product soft-deleted', [
            'product_id' => $product->id,
            'sku'        => $product->sku,
        ]);

        $product->delete();
    }
}
