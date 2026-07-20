<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Search\IndexingService;
use Illuminate\Console\Command;

class SearchIndexProduct extends Command
{
    protected $signature = 'search:index-product
                            {id : The product ID to index}
                            {--delete : Delete the product from the index instead}';

    protected $description = 'Index or delete a single product in Elasticsearch';

    public function handle(IndexingService $indexingService): int
    {
        $id = (int) $this->argument('id');

        if ($this->option('delete')) {
            $indexingService->deleteProduct($id);
            $this->info("Product {$id} deleted from index.");
            return self::SUCCESS;
        }

        $product = Product::with(['categories', 'storeProducts.store'])->find($id);

        if (!$product) {
            $this->error("Product {$id} not found.");
            return self::FAILURE;
        }

        $indexingService->indexProduct($product);
        $this->info("Product {$id} ({$product->name}) indexed successfully.");

        return self::SUCCESS;
    }
}
