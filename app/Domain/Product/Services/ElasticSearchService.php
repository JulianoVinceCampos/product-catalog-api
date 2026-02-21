<?php

namespace App\Domain\Product\Services;

use App\Models\Product;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;
use Throwable;

class ElasticSearchService
{
    private Client $client;
    private string $index = 'products';

    public function __construct()
    {
        $host = sprintf(
            '%s://%s:%s',
            config('services.elasticsearch.scheme', 'http'),
            config('services.elasticsearch.host', 'elasticsearch'),
            config('services.elasticsearch.port', '9200'),
        );

        $this->client = ClientBuilder::create()
            ->setHosts([$host])
            ->build();
    }

    // ─── Index Management ──────────────────────────────────────────────

    public function ensureIndexExists(): void
    {
        try {
            $response = $this->client->indices()->exists(['index' => $this->index]);

            if ($response->getStatusCode() === 404) {
                $this->createIndex();
            }
        } catch (Throwable) {
            $this->createIndex();
        }
    }

    private function createIndex(): void
    {
        $this->client->indices()->create([
            'index' => $this->index,
            'body'  => [
                'settings' => [
                    'number_of_shards'   => 1,
                    'number_of_replicas' => 0,
                    'analysis'           => [
                        'analyzer' => [
                            'product_analyzer' => [
                                'type'      => 'custom',
                                'tokenizer' => 'standard',
                                'filter'    => ['lowercase', 'asciifolding'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id'          => ['type' => 'integer'],
                        'sku'         => ['type' => 'keyword'],
                        'name'        => ['type' => 'text', 'analyzer' => 'product_analyzer', 'boost' => 2],
                        'description' => ['type' => 'text', 'analyzer' => 'product_analyzer'],
                        'price'       => ['type' => 'float'],
                        'category'    => ['type' => 'keyword'],
                        'status'      => ['type' => 'keyword'],
                        'created_at'  => ['type' => 'date'],
                    ],
                ],
            ],
        ]);

        Log::info('Elasticsearch index created', ['index' => $this->index]);
    }

    public function deleteIndex(): void
    {
        try {
            $this->client->indices()->delete(['index' => $this->index]);
        } catch (Throwable $e) {
            Log::warning('Failed to delete Elasticsearch index', ['error' => $e->getMessage()]);
        }
    }

    // ─── Document Operations ────────────────────────────────────────────

    public function indexProduct(Product $product): void
    {
        try {
            $this->client->index([
                'index' => $this->index,
                'id'    => $product->id,
                'body'  => [
                    'id'          => $product->id,
                    'sku'         => $product->sku,
                    'name'        => $product->name,
                    'description' => $product->description,
                    'price'       => $product->price,
                    'category'    => $product->category,
                    'status'      => $product->status,
                    'image_url'   => $product->image_url,
                    'created_at'  => $product->created_at?->toIso8601String(),
                    'updated_at'  => $product->updated_at?->toIso8601String(),
                ],
            ]);

            Log::debug('Product indexed in Elasticsearch', ['product_id' => $product->id]);
        } catch (Throwable $e) {
            Log::error('Failed to index product in Elasticsearch', [
                'product_id' => $product->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function deleteProduct(int $id): void
    {
        try {
            $this->client->delete([
                'index' => $this->index,
                'id'    => $id,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to delete product from Elasticsearch', [
                'product_id' => $id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ─── Search ─────────────────────────────────────────────────────────

    public function search(array $params): array
    {
        $must   = [];
        $filter = [];
        $sort   = [];

        // Full-text search on name and description
        if (!empty($params['q'])) {
            $must[] = [
                'multi_match' => [
                    'query'     => $params['q'],
                    'fields'    => ['name^3', 'description'],
                    'type'      => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Keyword filters
        if (!empty($params['category'])) {
            $filter[] = ['term' => ['category' => $params['category']]];
        }

        if (!empty($params['status'])) {
            $filter[] = ['term' => ['status' => $params['status']]];
        }

        // Price range
        $priceRange = [];
        if (isset($params['min_price'])) {
            $priceRange['gte'] = (float) $params['min_price'];
        }
        if (isset($params['max_price'])) {
            $priceRange['lte'] = (float) $params['max_price'];
        }
        if ($priceRange) {
            $filter[] = ['range' => ['price' => $priceRange]];
        }

        // Sorting
        $sortField = in_array($params['sort'] ?? '', ['price', 'created_at'], true)
            ? $params['sort']
            : 'created_at';

        $sortOrder = ($params['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sort[]    = [$sortField => ['order' => $sortOrder]];

        // Pagination
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 15)));
        $from    = ($page - 1) * $perPage;

        // Build query
        $query = ['bool' => []];

        if ($must) {
            $query['bool']['must'] = $must;
        }

        if ($filter) {
            $query['bool']['filter'] = $filter;
        }

        if (empty($query['bool'])) {
            $query = ['match_all' => (object) []];
        }

        $response = $this->client->search([
            'index' => $this->index,
            'body'  => [
                'query' => $query,
                'sort'  => $sort,
                'from'  => $from,
                'size'  => $perPage,
            ],
        ]);

        $hits  = $response['hits'];
        $total = $hits['total']['value'] ?? 0;

        return [
            'data'         => array_map(fn ($h) => $h['_source'], $hits['hits']),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // ─── Health Check ────────────────────────────────────────────────────

    public function ping(): bool
    {
        try {
            $this->client->ping();
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
