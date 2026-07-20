<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Elastic\Elasticsearch\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __construct(private readonly Client $es) {}

    public function check(): JsonResponse
    {
        $checks = [
            'database'      => $this->checkDatabase(),
            'elasticsearch' => $this->checkElasticsearch(),
            'redis'         => $this->checkRedis(),
        ];

        $allHealthy = collect($checks)->every(fn ($c) => $c['status'] === 'healthy');

        return response()->json([
            'status'    => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'services'  => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::selectOne('SELECT 1');
            return [
                'status'     => 'healthy',
                'latency_ms' => $this->ms($start),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error'  => $e->getMessage(),
            ];
        }
    }

    private function checkElasticsearch(): array
    {
        $start = microtime(true);
        try {
            $info = $this->es->cluster()->health()->asArray();
            return [
                'status'         => 'healthy',
                'latency_ms'     => $this->ms($start),
                'cluster_status' => $info['status'] ?? 'unknown',
                'nodes'          => $info['number_of_nodes'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error'  => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            Redis::ping();
            return [
                'status'     => 'healthy',
                'latency_ms' => $this->ms($start),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error'  => $e->getMessage(),
            ];
        }
    }

    private function ms(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
