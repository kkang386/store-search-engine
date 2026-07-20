<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\QueryRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class QueryRuleService
{
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

    private function clearCache(): void
    {
        Cache::put('query_rules_version', microtime(true));
    }
}
