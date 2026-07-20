<?php

namespace App\Services\Benchmark;

class MetricsCalculator
{
    public function precisionAtK(array $retrievedIds, array $relevantIds, int $k): float
    {
        if (empty($retrievedIds) || $k === 0) {
            return 0.0;
        }

        $topK = array_slice($retrievedIds, 0, $k);
        $relevant = array_intersect($topK, $relevantIds);

        return count($relevant) / $k;
    }

    public function recallAtK(array $retrievedIds, array $relevantIds, int $k): float
    {
        if (empty($relevantIds)) {
            return 1.0;
        }

        $topK = array_slice($retrievedIds, 0, $k);
        $relevant = array_intersect($topK, $relevantIds);

        return count($relevant) / count($relevantIds);
    }

    public function ndcgAtK(array $retrievedIds, array $relevantIds, int $k): float
    {
        if (empty($relevantIds) || empty($retrievedIds)) {
            return 0.0;
        }

        $dcg = $this->dcg($retrievedIds, $relevantIds, $k);
        $idcg = $this->idealDcg($relevantIds, $k);

        if ($idcg === 0.0) {
            return 0.0;
        }

        return $dcg / $idcg;
    }

    public function meanReciprocalRank(array $retrievedIds, array $relevantIds): float
    {
        foreach ($retrievedIds as $position => $id) {
            if (in_array($id, $relevantIds)) {
                return 1.0 / ($position + 1);
            }
        }

        return 0.0;
    }

    private function dcg(array $retrievedIds, array $relevantIds, int $k): float
    {
        $dcg = 0.0;
        $topK = array_slice($retrievedIds, 0, $k);

        foreach ($topK as $position => $id) {
            $relevance = in_array($id, $relevantIds) ? 1 : 0;
            $dcg += $relevance / log($position + 2, 2);
        }

        return $dcg;
    }

    private function idealDcg(array $relevantIds, int $k): float
    {
        $idcg = 0.0;
        $count = min(count($relevantIds), $k);

        for ($i = 0; $i < $count; $i++) {
            $idcg += 1.0 / log($i + 2, 2);
        }

        return $idcg;
    }

    public function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return (float) $values[$lower];
        }

        return $values[$lower] + ($values[$upper] - $values[$lower]) * ($index - $lower);
    }

    public function throughput(int $requests, float $totalSeconds): float
    {
        if ($totalSeconds <= 0) {
            return 0.0;
        }
        return $requests / $totalSeconds;
    }

    public function compareMetrics(array $current, array $previous, float $threshold = 5.0): array
    {
        $regressions = [];

        $metricsToCompare = [
            'precision_at_10' => 'Precision@10',
            'precision_at_5' => 'Precision@5',
            'ndcg_at_10' => 'NDCG@10',
            'mrr' => 'MRR',
        ];

        foreach ($metricsToCompare as $key => $label) {
            if (!isset($current[$key], $previous[$key])) {
                continue;
            }

            $drop = (($previous[$key] - $current[$key]) / max(0.001, $previous[$key])) * 100;

            if ($drop > $threshold) {
                $regressions[] = [
                    'metric' => $label,
                    'current' => round($current[$key], 4),
                    'previous' => round($previous[$key], 4),
                    'drop_percent' => round($drop, 2),
                ];
            }
        }

        return $regressions;
    }
}
