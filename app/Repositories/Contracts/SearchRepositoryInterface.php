<?php

namespace App\Repositories\Contracts;

use App\DTOs\SearchQueryDTO;
use App\DTOs\SuggestQueryDTO;

interface SearchRepositoryInterface
{
    public function search(SearchQueryDTO $dto): array;

    public function suggest(SuggestQueryDTO $dto): array;

    public function indexDocument(string $index, string $id, array $body): array;

    public function deleteDocument(string $index, string $id): array;

    public function bulkIndex(string $index, array $documents): array;

    public function deleteIndex(string $index): void;

    public function createIndex(string $index, array $mapping): void;

    public function indexExists(string $index): bool;

    public function refresh(string $index): void;

    public function updateByQuery(string $index, array $query, array $script): array;
}
