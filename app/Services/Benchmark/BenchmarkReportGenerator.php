<?php

namespace App\Services\Benchmark;

use App\Models\BenchmarkRun;
use Illuminate\Support\Facades\Storage;

class BenchmarkReportGenerator
{
    public function generate(BenchmarkRun $run, array $results): array
    {
        $jsonPath = $this->generateJson($run, $results);
        $htmlPath = $this->generateHtml($run, $results);

        return [
            'json' => $jsonPath,
            'html' => $htmlPath,
        ];
    }

    private function generateJson(BenchmarkRun $run, array $results): string
    {
        $report = [
            'run_id' => $run->run_id,
            'generated_at' => now()->toISOString(),
            'status' => $run->status,
            'duration_seconds' => $run->getDurationSeconds(),
            'git_commit' => $run->git_commit,
            'metrics' => $run->metrics,
            'latency' => $run->latency,
            'results' => $results,
        ];

        $path = "benchmarks/report_{$run->run_id}.json";
        Storage::put($path, json_encode($report, JSON_PRETTY_PRINT));

        return storage_path("app/{$path}");
    }

    private function generateHtml(BenchmarkRun $run, array $results): string
    {
        $metrics = $run->metrics;
        $latency = $run->latency;

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search Benchmark Report - {$run->run_id}</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
  h1, h2, h3 { color: #333; }
  .card { background: white; border-radius: 8px; padding: 20px; margin: 16px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
  .metric { display: inline-block; margin: 8px; padding: 12px 20px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px; }
  .metric .value { font-size: 2em; font-weight: bold; color: #3b82f6; }
  .metric .label { font-size: 0.85em; color: #666; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #374151; color: white; padding: 10px; text-align: left; }
  td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
  tr:hover { background: #f9fafb; }
  .pass { color: #10b981; font-weight: bold; }
  .fail { color: #ef4444; font-weight: bold; }
  .warn { color: #f59e0b; font-weight: bold; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; }
  .badge-green { background: #d1fae5; color: #065f46; }
  .badge-red { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
<h1>Search Benchmark Report</h1>

<div class="card">
  <h2>Run Info</h2>
  <table>
    <tr><td><strong>Run ID</strong></td><td>{$run->run_id}</td></tr>
    <tr><td><strong>Git Commit</strong></td><td>{$run->git_commit}</td></tr>
    <tr><td><strong>Started At</strong></td><td>{$run->started_at}</td></tr>
    <tr><td><strong>Duration</strong></td><td>{$run->getDurationSeconds()} seconds</td></tr>
    <tr><td><strong>Total Queries</strong></td><td>{$run->total_queries}</td></tr>
    <tr><td><strong>Failed Queries</strong></td><td>{$run->failed_queries}</td></tr>
  </table>
</div>

<div class="card">
  <h2>Quality Metrics</h2>
  <div class="metric">
    <div class="value">{$this->fmt($metrics['precision_at_5'] ?? 0)}</div>
    <div class="label">Precision@5</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmt($metrics['precision_at_10'] ?? 0)}</div>
    <div class="label">Precision@10</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmt($metrics['recall_at_10'] ?? 0)}</div>
    <div class="label">Recall@10</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmt($metrics['ndcg_at_10'] ?? 0)}</div>
    <div class="label">NDCG@10</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmt($metrics['mrr'] ?? 0)}</div>
    <div class="label">MRR</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmtPct($metrics['zero_result_rate'] ?? 0)}</div>
    <div class="label">Zero Result Rate</div>
  </div>
</div>

<div class="card">
  <h2>Latency Metrics</h2>
  <div class="metric">
    <div class="value">{$this->fmtMs($latency['p50_ms'] ?? 0)}</div>
    <div class="label">P50</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmtMs($latency['p95_ms'] ?? 0)}</div>
    <div class="label">P95</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmtMs($latency['p99_ms'] ?? 0)}</div>
    <div class="label">P99</div>
  </div>
  <div class="metric">
    <div class="value">{$this->fmtMs($latency['avg_ms'] ?? 0)}</div>
    <div class="label">Average</div>
  </div>
</div>

<div class="card">
  <h2>Query Results</h2>
  <table>
    <thead>
      <tr>
        <th>Query</th>
        <th>Latency</th>
        <th>Total Hits</th>
        <th>P@5</th>
        <th>P@10</th>
        <th>NDCG@10</th>
        <th>MRR</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
HTML;

        foreach ($results['search'] as $r) {
            $failed = $r['failed'] ?? false;
            $status = $failed ? '<span class="badge badge-red">FAILED</span>' : '<span class="badge badge-green">OK</span>';
            $html .= "<tr>
                <td>{$r['query']}</td>
                <td>{$this->fmtMs($r['latency_ms'] ?? 0)}</td>
                <td>" . ($r['total_hits'] ?? '-') . "</td>
                <td>" . $this->fmt($r['precision_at_5'] ?? 0) . "</td>
                <td>" . $this->fmt($r['precision_at_10'] ?? 0) . "</td>
                <td>" . $this->fmt($r['ndcg_at_10'] ?? 0) . "</td>
                <td>" . $this->fmt($r['mrr'] ?? 0) . "</td>
                <td>{$status}</td>
            </tr>\n";
        }

        $html .= '</tbody></table></div></body></html>';

        $path = "benchmarks/report_{$run->run_id}.html";
        Storage::put($path, $html);

        return storage_path("app/{$path}");
    }

    private function fmt(float $value): string
    {
        return number_format($value, 3);
    }

    private function fmtPct(float $value): string
    {
        return number_format($value * 100, 1) . '%';
    }

    private function fmtMs(float $value): string
    {
        return number_format($value) . 'ms';
    }
}
