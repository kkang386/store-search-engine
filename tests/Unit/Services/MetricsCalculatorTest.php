<?php

namespace Tests\Unit\Services;

use App\Services\Benchmark\MetricsCalculator;
use PHPUnit\Framework\TestCase;

class MetricsCalculatorTest extends TestCase
{
    private MetricsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new MetricsCalculator();
    }

    public function test_precision_at_k_perfect(): void
    {
        $retrieved = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $relevant = [1, 2, 3, 4, 5];

        $this->assertEqualsWithDelta(0.5, $this->calculator->precisionAtK($retrieved, $relevant, 10), 0.001);
        $this->assertEqualsWithDelta(1.0, $this->calculator->precisionAtK($retrieved, $relevant, 5), 0.001);
    }

    public function test_precision_at_k_empty_results(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->calculator->precisionAtK([], [1, 2, 3], 10), 0.001);
    }

    public function test_recall_at_k(): void
    {
        $retrieved = [1, 2, 6, 7, 8];
        $relevant = [1, 2, 3, 4, 5];

        $this->assertEqualsWithDelta(0.4, $this->calculator->recallAtK($retrieved, $relevant, 5), 0.001);
    }

    public function test_recall_at_k_empty_relevant(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->calculator->recallAtK([1, 2, 3], [], 5), 0.001);
    }

    public function test_ndcg_at_k_perfect_ranking(): void
    {
        $retrieved = [1, 2, 3];
        $relevant = [1, 2, 3];

        $ndcg = $this->calculator->ndcgAtK($retrieved, $relevant, 3);
        $this->assertEqualsWithDelta(1.0, $ndcg, 0.001);
    }

    public function test_ndcg_at_k_no_relevant_retrieved(): void
    {
        $retrieved = [4, 5, 6];
        $relevant = [1, 2, 3];

        $ndcg = $this->calculator->ndcgAtK($retrieved, $relevant, 3);
        $this->assertEqualsWithDelta(0.0, $ndcg, 0.001);
    }

    public function test_mean_reciprocal_rank_first_position(): void
    {
        $retrieved = [1, 2, 3];
        $relevant = [1];

        $mrr = $this->calculator->meanReciprocalRank($retrieved, $relevant);
        $this->assertEqualsWithDelta(1.0, $mrr, 0.001);
    }

    public function test_mean_reciprocal_rank_third_position(): void
    {
        $retrieved = [4, 5, 1, 2, 3];
        $relevant = [1];

        $mrr = $this->calculator->meanReciprocalRank($retrieved, $relevant);
        $this->assertEqualsWithDelta(1 / 3, $mrr, 0.001);
    }

    public function test_mean_reciprocal_rank_not_found(): void
    {
        $retrieved = [4, 5, 6];
        $relevant = [1, 2, 3];

        $mrr = $this->calculator->meanReciprocalRank($retrieved, $relevant);
        $this->assertEqualsWithDelta(0.0, $mrr, 0.001);
    }

    public function test_percentile_p50(): void
    {
        $values = [10, 20, 30, 40, 50];
        $this->assertEqualsWithDelta(30.0, $this->calculator->percentile($values, 50), 1.0);
    }

    public function test_percentile_p95(): void
    {
        $values = range(1, 100);
        $p95 = $this->calculator->percentile($values, 95);
        $this->assertGreaterThan(90, $p95);
        $this->assertLessThanOrEqual(100, $p95);
    }

    public function test_percentile_empty_values(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->calculator->percentile([], 95), 0.001);
    }

    public function test_compare_metrics_detects_regression(): void
    {
        $current = ['precision_at_10' => 0.5, 'ndcg_at_10' => 0.6];
        $previous = ['precision_at_10' => 0.7, 'ndcg_at_10' => 0.8];

        $regressions = $this->calculator->compareMetrics($current, $previous, 5.0);

        $this->assertNotEmpty($regressions);
        $this->assertCount(2, $regressions);
    }

    public function test_compare_metrics_no_regression(): void
    {
        $current = ['precision_at_10' => 0.68, 'ndcg_at_10' => 0.75];
        $previous = ['precision_at_10' => 0.70, 'ndcg_at_10' => 0.77];

        $regressions = $this->calculator->compareMetrics($current, $previous, 5.0);

        $this->assertEmpty($regressions);
    }
}
