<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\QueryRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use League\Csv\Reader;
use League\Csv\Writer;

class QueryRuleService
{
    /** CSV column order for import/export. Array fields are comma-joined in a cell. */
    private const CSV_HEADER = [
        'query_pattern', 'match_type', 'action', 'skus', 'boost_factor', 'pin_position',
        'redirect_url', 'banner_html', 'include_category_ids', 'exclude_category_ids',
        'include_brands', 'priority', 'is_active', 'starts_at', 'ends_at',
    ];

    public function getActiveRules(string $query, ?int $storeId): Collection
    {
        $version = Cache::get('query_rules_version', 0);
        $cacheKey = "query_rules:v{$version}:{$storeId}:" . md5($query);

        return Cache::remember($cacheKey, 60, function () use ($query, $storeId) {
            return QueryRule::active()
                ->forStore($storeId)
                ->orderByDesc('priority')
                ->get()
                ->filter(fn (QueryRule $rule) => $rule->matchesQuery($query));
        });
    }

    public function getRedirect(string $query, ?int $storeId): ?string
    {
        $rule = QueryRule::active()
            ->forStore($storeId)
            ->where('action', 'redirect')
            ->get()
            ->first(fn (QueryRule $rule) => $rule->matchesQuery($query));

        return $rule?->redirect_url;
    }

    public function getBanner(string $query, ?int $storeId): ?array
    {
        $rule = QueryRule::active()
            ->forStore($storeId)
            ->where('action', 'banner')
            ->get()
            ->first(fn (QueryRule $rule) => $rule->matchesQuery($query));

        if (!$rule || empty($rule->banner_html)) {
            return null;
        }

        return [
            'html' => $rule->banner_html,
            'metadata' => $rule->metadata,
        ];
    }

    public function create(array $data): QueryRule
    {
        $rule = QueryRule::create($data);
        AuditLog::record('create', QueryRule::class, $rule->id, [], $rule->toArray());
        $this->clearCache();
        return $rule;
    }

    public function update(QueryRule $rule, array $data): QueryRule
    {
        $old = $rule->toArray();
        $rule->update($data);
        AuditLog::record('update', QueryRule::class, $rule->id, $old, $rule->fresh()->toArray());
        $this->clearCache();
        return $rule->fresh();
    }

    public function delete(QueryRule $rule): void
    {
        $snapshot = $rule->toArray();
        $rule->delete();
        AuditLog::record('delete', QueryRule::class, $rule->id, $snapshot, []);
        $this->clearCache();
    }

    public function restore(QueryRule $rule): void
    {
        $rule->restore();
        AuditLog::record('restore', QueryRule::class, $rule->id, [], $rule->toArray());
        $this->clearCache();
    }

    public function exportCsv(?int $storeId): string
    {
        $rules = QueryRule::forStore($storeId)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $csv = Writer::fromString('');
        $csv->insertOne(self::CSV_HEADER);

        foreach ($rules as $rule) {
            $csv->insertOne([
                $rule->query_pattern,
                $rule->match_type,
                $rule->action,
                implode(', ', $rule->skus ?? []),
                $rule->boost_factor ?? '',
                $rule->pin_position ?? '',
                $rule->redirect_url ?? '',
                $rule->banner_html ?? '',
                implode(', ', $rule->include_category_ids ?? []),
                implode(', ', $rule->exclude_category_ids ?? []),
                implode(', ', $rule->include_brands ?? []),
                $rule->priority ?? 0,
                $rule->is_active ? '1' : '0',
                $rule->starts_at?->toDateTimeString() ?? '',
                $rule->ends_at?->toDateTimeString() ?? '',
            ]);
        }

        return $csv->toString();
    }

    public function bulkImport(string $csvContent, ?int $storeId): array
    {
        $csv = Reader::fromString($csvContent);
        $csv->setHeaderOffset(0);

        $created = 0;
        $failed = 0;
        $errors = [];

        foreach ($csv->getRecords() as $row) {
            try {
                $this->importRow($row, $storeId);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }

        if ($created > 0) {
            $this->clearCache();
        }

        return compact('created', 'failed', 'errors');
    }

    private function importRow(array $row, ?int $storeId): void
    {
        $pattern = trim((string) ($row['query_pattern'] ?? ''));
        if ($pattern === '') {
            throw new \InvalidArgumentException('query_pattern is required');
        }

        QueryRule::create([
            'store_id'             => $storeId,
            'query_pattern'        => $pattern,
            'match_type'           => $this->orDefault($row['match_type'] ?? '', 'exact'),
            'action'               => $this->orDefault($row['action'] ?? '', 'pin'),
            'skus'                 => $this->splitList($row['skus'] ?? ''),
            'boost_factor'         => $this->numericOrNull($row['boost_factor'] ?? ''),
            'pin_position'         => $this->intOrNull($row['pin_position'] ?? ''),
            'redirect_url'         => $this->stringOrNull($row['redirect_url'] ?? ''),
            'banner_html'          => $this->stringOrNull($row['banner_html'] ?? ''),
            'include_category_ids' => array_map('intval', $this->splitList($row['include_category_ids'] ?? '')),
            'exclude_category_ids' => array_map('intval', $this->splitList($row['exclude_category_ids'] ?? '')),
            'include_brands'       => $this->splitList($row['include_brands'] ?? ''),
            'priority'             => (int) ($row['priority'] ?? 0),
            'is_active'            => filter_var($row['is_active'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'starts_at'            => $this->stringOrNull($row['starts_at'] ?? ''),
            'ends_at'              => $this->stringOrNull($row['ends_at'] ?? ''),
        ]);
    }

    /** Split a comma-separated cell into a trimmed, non-empty list. */
    private function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
    }

    private function orDefault(string $value, string $default): string
    {
        return trim($value) !== '' ? trim($value) : $default;
    }

    private function stringOrNull(string $value): ?string
    {
        return trim($value) !== '' ? $value : null;
    }

    private function numericOrNull(string $value): ?float
    {
        return trim($value) !== '' ? (float) $value : null;
    }

    private function intOrNull(string $value): ?int
    {
        return trim($value) !== '' ? (int) $value : null;
    }

    private function clearCache(): void
    {
        Cache::put('query_rules_version', microtime(true));
    }
}
