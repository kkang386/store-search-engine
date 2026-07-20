<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BenchmarkRun;
use App\Services\Benchmark\BenchmarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BenchmarkController extends Controller
{
    private string $datasetPath;

    public function __construct(private readonly BenchmarkService $service)
    {
        $this->datasetPath = config('search.benchmark.dataset_path');
    }

    public function serveHtmlReport(string $runId): Response
    {
        abort_unless(preg_match('/^[0-9a-f\-]{36}$/', $runId), 404);
        abort_unless(Storage::exists("benchmarks/report_{$runId}.html"), 404);

        return response(Storage::get("benchmarks/report_{$runId}.html"), 200)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function run(): JsonResponse
    {
        set_time_limit(180);
        $benchmarkRun = $this->service->run();
        return response()->json($this->formatRun($benchmarkRun));
    }

    public function history(): JsonResponse
    {
        $runs = BenchmarkRun::orderByDesc('id')->limit(20)->get();
        return response()->json($runs->map(fn ($r) => $this->formatRun($r))->values());
    }

    public function downloadDataset(): BinaryFileResponse
    {
        abort_unless(file_exists($this->datasetPath), 404);
        return response()->download($this->datasetPath, 'benchmark.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function uploadDataset(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($content, true);

        abort_unless(
            is_array($data) && isset($data['queries']) && is_array($data['queries']),
            422,
        );

        $dir = dirname($this->datasetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($this->datasetPath)) {
            copy($this->datasetPath, $this->datasetPath . '.bak');
        }

        file_put_contents($this->datasetPath, $content);

        return response()->json([
            'message' => 'Dataset replaced.',
            'queries' => count($data['queries']),
            'suggest_queries' => count($data['suggest_queries'] ?? []),
            'faceted_queries' => count($data['faceted_queries'] ?? []),
        ]);
    }

    private function formatRun(BenchmarkRun $run): array
    {
        $metrics = $run->metrics ?? [];
        $latency = $run->latency ?? [];

        return [
            'id'              => $run->id,
            'run_id'          => $run->run_id,
            'status'          => $run->status,
            'git_commit'      => $run->git_commit,
            'total_queries'   => $run->total_queries,
            'failed_queries'  => $run->failed_queries ?? 0,
            'duration_seconds'=> $run->getDurationSeconds(),
            'started_at'      => $run->started_at?->toDateTimeString(),
            'has_report'      => Storage::exists("benchmarks/report_{$run->run_id}.html"),
            'metrics' => [
                'precision_at_5'  => round($metrics['precision_at_5'] ?? 0, 3),
                'precision_at_10' => round($metrics['precision_at_10'] ?? 0, 3),
                'recall_at_10'    => round($metrics['recall_at_10'] ?? 0, 3),
                'ndcg_at_10'      => round($metrics['ndcg_at_10'] ?? 0, 3),
                'mrr'             => round($metrics['mrr'] ?? 0, 3),
                'zero_result_rate'=> round(($metrics['zero_result_rate'] ?? 0) * 100, 1),
            ],
            'latency' => [
                'p50_ms' => $latency['p50_ms'] ?? 0,
                'p95_ms' => $latency['p95_ms'] ?? 0,
                'p99_ms' => $latency['p99_ms'] ?? 0,
                'avg_ms' => round($latency['avg_ms'] ?? 0),
            ],
        ];
    }
}
