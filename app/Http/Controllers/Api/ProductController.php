<?php

namespace App\Http\Controllers\Api;

use App\Domain\Product\DTOs\ProductDTO;
use App\Domain\Product\Services\ProductService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\SearchProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UploadImageRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
    ) {}

    /**
     * GET /api/v1/products
     * List products with optional filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->service->list($request->query());

        return response()->json([
            'success' => true,
            'data'    => ProductResource::collection($products->items()),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/products
     * Create a new product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $dto     = ProductDTO::fromArray($request->validated());
        $product = $this->service->create($dto);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data'    => new ProductResource($product),
        ], 201);
    }

    /**
     * GET /api/v1/products/{id}
     * Retrieve a single product by ID (Redis cached).
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->service->findById($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new ProductResource($product),
        ]);
    }

    /**
     * PUT /api/v1/products/{product}
     * Update an existing product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $dto     = ProductDTO::fromArrayPartial($product->toArray(), $request->validated());
        $updated = $this->service->update($product, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data'    => new ProductResource($updated),
        ]);
    }

    /**
     * DELETE /api/v1/products/{product}
     * Soft-delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * GET /api/v1/search/products
     * Full-text search via Elasticsearch (Redis cached).
     */
    public function search(SearchProductRequest $request): JsonResponse
    {
        $result = $this->service->search($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => [
                'current_page' => $result['current_page'],
                'last_page'    => $result['last_page'],
                'per_page'     => $result['per_page'],
                'total'        => $result['total'],
            ],
        ]);
    }

    /**
     * POST /api/v1/products/{product}/image
     * Upload product image to S3 (LocalStack in dev).
     */
    public function uploadImage(UploadImageRequest $request, Product $product): JsonResponse
    {
        $updated = $this->service->uploadImage($product, $request->file('image'));

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully.',
            'data'    => new ProductResource($updated),
        ]);
    }
}
