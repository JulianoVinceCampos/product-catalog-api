<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Product CRUD
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{id}', [ProductController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::post('/{product}/image', [ProductController::class, 'uploadImage']);
    });

    // Search via ElasticSearch
    Route::get('search/products', [ProductController::class, 'search']);

});

// Health check
Route::get('/health', fn() => response()->json([
    'status'  => 'ok',
    'service' => 'Product Catalog API',
    'version' => '1.0.0',
]));
