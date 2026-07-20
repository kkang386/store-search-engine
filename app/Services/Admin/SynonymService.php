<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Synonym;
use App\Services\Search\IndexingService;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Writer;

class SynonymService
{
    public function __construct(private readonly IndexingService $indexingService) {}

    public function create(array $data): Synonym
    {
        $synonym = Synonym::create($data);
        AuditLog::record('create', Synonym::class, $synonym->id, [], $synonym->toArray());
        $this->syncSynonyms();
        return $synonym;
    }

    public function update(Synonym $synonym, array $data): Synonym
    {
        $old = $synonym->toArray();
        $synonym->update($data);
        AuditLog::record('update', Synonym::class, $synonym->id, $old, $synonym->fresh()->toArray());
        $this->syncSynonyms();
        return $synonym->fresh();
    }

    public function delete(Synonym $synonym): void
    {
        $snapshot = $synonym->toArray();
        $synonym->delete();
        AuditLog::record('delete', Synonym::class, $synonym->id, $snapshot, []);
        $this->syncSynonyms();
    }

    public function bulkImport(string $csvContent): array
    {
        $csv = Reader::createFromString($csvContent);
        $csv->setHeaderOffset(0);

        $created = 0;
        $failed = 0;
        $errors = [];

        foreach ($csv->getRecords() as $row) {
            try {
                $this->importRow($row);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        if ($created > 0) {
            $this->syncSynonyms();
        }

        return compact('created', 'failed', 'errors');
    }

    private function importRow(array $row): void
    {
        $type = $row['type'] ?? 'equivalent';

        if ($type === 'one_way') {
            Synonym::create([
                'type' => 'one_way',
                'terms' => [],
                'from_term' => $row['from_term'],
                'to_term' => $row['to_term'],
                'is_active' => true,
            ]);
        } else {
            $terms = array_map('trim', explode(',', $row['terms'] ?? ''));
            Synonym::create([
                'type' => 'equivalent',
                'terms' => array_filter($terms),
                'is_active' => true,
            ]);
        }
    }

    public function exportCsv(): string
    {
        $synonyms = Synonym::active()->get();

        $csv = Writer::createFromString('');
        $csv->insertOne(['type', 'terms', 'from_term', 'to_term']);

        foreach ($synonyms as $synonym) {
            $csv->insertOne([
                $synonym->type,
                implode(', ', $synonym->terms ?? []),
                $synonym->from_term ?? '',
                $synonym->to_term ?? '',
            ]);
        }

        return $csv->toString();
    }

    /**
     * Push the current synonyms to the Elasticsearch synonym set and reload the
     * index's search analyzers so the change takes effect immediately.
     */
    public function syncSynonyms(): void
    {
        try {
            $this->indexingService->syncSynonymSet();
        } catch (\Throwable $e) {
            Log::warning('Failed to sync synonyms', ['error' => $e->getMessage()]);
        }
    }
}
