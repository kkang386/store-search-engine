<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenchmarkRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'run_id', 'version', 'git_commit', 'metrics', 'latency',
        'config', 'status', 'error', 'total_queries', 'failed_queries',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'metrics' => 'array',
        'latency' => 'array',
        'config' => 'array',
        'total_queries' => 'integer',
        'failed_queries' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getDurationSeconds(): ?float
    {
        if (!$this->completed_at) {
            return null;
        }
        return $this->started_at->diffInMilliseconds($this->completed_at) / 1000;
    }

    public function getPrecision10(): float
    {
        return $this->metrics['precision_at_10'] ?? 0.0;
    }

    public function getNdcg10(): float
    {
        return $this->metrics['ndcg_at_10'] ?? 0.0;
    }

    public function getP95LatencyMs(): float
    {
        return $this->latency['p95_ms'] ?? 0.0;
    }
}
