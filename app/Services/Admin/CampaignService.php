<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\SearchCampaign;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CampaignService
{
    public function getActiveCampaigns(string $query, ?int $storeId): Collection
    {
        $version = Cache::get('campaigns_version', 0);
        $cacheKey = "campaigns:v{$version}:{$storeId}:" . md5($query);

        return Cache::remember($cacheKey, 60, function () use ($query, $storeId) {
            return SearchCampaign::active()
                ->where(function ($q) use ($storeId) {
                    $q->whereNull('store_id');
                    if ($storeId) {
                        $q->orWhere('store_id', $storeId);
                    }
                })
                ->get()
                ->filter(fn (SearchCampaign $campaign) => $this->campaignMatchesQuery($campaign, $query));
        });
    }

    private function campaignMatchesQuery(SearchCampaign $campaign, string $query): bool
    {
        if (empty($campaign->query_patterns)) {
            return true;
        }

        foreach ($campaign->query_patterns as $pattern) {
            if (str_contains(strtolower($query), strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function create(array $data): SearchCampaign
    {
        $campaign = SearchCampaign::create($data);
        AuditLog::record('create', SearchCampaign::class, $campaign->id, [], $campaign->toArray());
        $this->clearCache();
        return $campaign;
    }

    public function update(SearchCampaign $campaign, array $data): SearchCampaign
    {
        $old = $campaign->toArray();
        $campaign->update($data);
        AuditLog::record('update', SearchCampaign::class, $campaign->id, $old, $campaign->fresh()->toArray());
        $this->clearCache();
        return $campaign->fresh();
    }

    public function delete(SearchCampaign $campaign): void
    {
        $snapshot = $campaign->toArray();
        $campaign->delete();
        AuditLog::record('delete', SearchCampaign::class, $campaign->id, $snapshot, []);
        $this->clearCache();
    }

    private function clearCache(): void
    {
        Cache::put('campaigns_version', microtime(true));
    }
}
