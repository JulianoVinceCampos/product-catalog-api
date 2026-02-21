<?php

namespace App\Domain\Product\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    private int $ttl;

    public function __construct()
    {
        $this->ttl = (int) config('cache.ttl', 120);
    }

    // ─── Key Builders ──────────────────────────────────────────────────

    public function productKey(int $id): string
    {
        return "product:{$id}";
    }

    public function searchKey(array $params): string
    {
        ksort($params);
        return 'search:products:' . md5(serialize($params));
    }

    // ─── Cache Operations ──────────────────────────────────────────────

    public function remember(string $key, callable $callback): mixed
    {
        return Cache::remember($key, $this->ttl, $callback);
    }

    public function invalidateProduct(int $id): void
    {
        $key = $this->productKey($id);
        Cache::forget($key);

        Log::debug('Cache invalidated for product', ['product_id' => $id, 'key' => $key]);
    }

    public function invalidateSearch(): void
    {
        // Flush keys matching pattern — using Redis SCAN
        try {
            $redis = Cache::getStore()->getRedis();
            $prefix = Cache::getStore()->getPrefix();
            $pattern = $prefix . 'search:products:*';

            $cursor = null;
            do {
                [$cursor, $keys] = $redis->scan($cursor ?? '0', 'MATCH', $pattern, 'COUNT', 100);
                if (!empty($keys)) {
                    $redis->del(...$keys);
                }
            } while ($cursor !== '0');

            Log::debug('Search cache flushed');
        } catch (\Throwable $e) {
            Log::warning('Failed to flush search cache via Redis SCAN, using fallback', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── Guards ───────────────────────────────────────────────────────

    /**
     * Skip cache for very high page numbers to avoid memory pressure.
     */
    public function shouldSkipCache(array $params): bool
    {
        return ((int) ($params['page'] ?? 1)) > 50;
    }
}
