<?php

namespace App\DTOs;

readonly class FacetDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public array $values,
        public ?float $min = null,
        public ?float $max = null,
    ) {}

    public static function fromAggregation(string $key, array $aggregation): self
    {
        $type = match (true) {
            str_contains($key, 'price') => 'range',
            isset($aggregation['buckets']) => 'terms',
            default => 'terms',
        };

        $label = self::keyToLabel($key);

        if ($type === 'range') {
            return new self(
                key: $key,
                label: $label,
                type: $type,
                values: [],
                min: $aggregation['min'] ?? null,
                max: $aggregation['max'] ?? null,
            );
        }

        $values = array_map(
            fn (array $bucket) => [
                'value' => $bucket['key'],
                'label' => $bucket['key'],
                'count' => $bucket['doc_count'],
            ],
            $aggregation['buckets'] ?? []
        );

        return new self(
            key: $key,
            label: $label,
            type: $type,
            values: $values,
        );
    }

    private static function keyToLabel(string $key): string
    {
        return ucwords(str_replace(['_', '.'], ' ', $key));
    }

    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'values' => $this->values,
        ];

        if ($this->type === 'range') {
            $data['min'] = $this->min;
            $data['max'] = $this->max;
        }

        return $data;
    }
}
