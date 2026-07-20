<?php

namespace App\Console\Commands;

use App\Services\Search\IndexingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatalogReset extends Command
{
    protected $signature = 'catalog:reset
                            {--force : Skip the confirmation prompt}
                            {--db-only : Only wipe the database tables, skip Elasticsearch}
                            {--es-only : Only recreate the Elasticsearch index, skip the database}';

    protected $description = 'Wipe all product and category data from MySQL and rebuild the Elasticsearch index';

    public function handle(IndexingService $indexingService): int
    {
        if (!$this->option('force') && !$this->confirm(
            'This will permanently delete ALL products, categories, product_categories, store_products, store_categories and import_logs. Continue?'
        )) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        // Clear queues first so stale IndexProductJob entries cannot reference
        // records we are about to delete, and so new StoreImportJob jobs are not
        // blocked behind thousands of now-invalid jobs.
        $this->clearQueues();

        if (!$this->option('es-only')) {
            $this->wipeTables();
        }

        if (!$this->option('db-only')) {
            $this->rebuildIndex($indexingService);
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function clearQueues(): void
    {
        $this->info('Clearing Redis queues…');
        foreach (['imports', 'indexing', 'default'] as $queue) {
            $this->callSilent('queue:clear', ['connection' => 'redis', '--queue' => $queue]);
            $this->line("  cleared: {$queue}");
        }
    }

    private function wipeTables(): void
    {
        $this->info('Wiping database tables…');

        // Disable FK checks so we can truncate in any order
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'product_categories',
            'store_products',
            'store_categories',
            'import_logs',
            'products',
            'categories',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->line("  truncated: {$table}");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Database wiped.');
    }

    private function rebuildIndex(IndexingService $indexingService): void
    {
        $this->info('Recreating Elasticsearch index…');
        $indexingService->recreateIndex();
        $this->info('Elasticsearch index recreated (empty).');
    }
}
