<?php

namespace App\Console\Commands;

use App\Jobs\CheckPendingImportsJob;
use Illuminate\Console\Command;

class CheckPendingImports extends Command
{
    protected $signature = 'import:check-pending';

    protected $description = 'Scan active stores for pending import CSVs and queue a StoreImportJob for each (run every 15 min by the scheduler; safe to run manually — ShouldBeUnique prevents duplicates)';

    public function handle(): int
    {
        CheckPendingImportsJob::dispatch();
        $this->info('CheckPendingImportsJob dispatched.');
        return self::SUCCESS;
    }
}
