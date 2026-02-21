<?php

namespace App\Console\Commands;

use App\Domain\Product\Services\ElasticSearchService;
use App\Models\Product;
use Illuminate\Console\Command;

class ElasticReindex extends Command
{
    protected $signature   = 'elastic:reindex {--fresh : Delete and recreate the index}';
    protected $description = 'Reindex all products to Elasticsearch';

    public function handle(ElasticSearchService $elastic): int
    {
        if ($this->option('fresh')) {
            $this->warn('Deleting existing index...');
            $elastic->deleteIndex();
        }

        $this->info('Ensuring index exists...');
        $elastic->ensureIndexExists();

        $total = Product::count();
        $this->info("Indexing {$total} products...");

        $bar   = $this->output->createProgressBar($total);
        $count = 0;

        Product::chunk(100, function ($products) use ($elastic, $bar, &$count) {
            foreach ($products as $product) {
                $elastic->indexProduct($product);
                $bar->advance();
                $count++;
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$count} products indexed successfully.");

        return self::SUCCESS;
    }
}
