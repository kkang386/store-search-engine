<?php

namespace App\DTOs;

readonly class SearchMetaDTO
{
    public function __construct(
        public string $query,
        public int $totalHits,
        public float $maxScore,
        public int $tookMs,
        public bool $timedOut,
        public ?string $correctedQuery = null,
        public ?int $storeId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'total_hits' => $this->totalHits,
            'max_score' => $this->maxScore,
            'took_ms' => $this->tookMs,
            'timed_out' => $this->timedOut,
            'corrected_query' => $this->correctedQuery,
            'store_id' => $this->storeId,
        ];
    }
}
