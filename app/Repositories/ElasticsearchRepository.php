<?php

namespace App\Repositories;

use App\DTOs\SearchQueryDTO;
use App\DTOs\SuggestQueryDTO;
use App\Repositories\Contracts\SearchRepositoryInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Illuminate\Support\Facades\Log;

class ElasticsearchRepository implements SearchRepositoryInterface
{
    public function __construct(private readonly Client $client) {}

    public function search(SearchQueryDTO $dto): array
    {
        $index = config('elasticsearch.indices.products');
        $response = $this->client->search([
            'index' => $index,
            'body' => $dto->toElasticsearchBody(),
        ]);

        return $response->asArray();
    }

    public function suggest(SuggestQueryDTO $dto): array
    {
        $index = config('elasticsearch.indices.products');
        $response = $this->client->search([
            'index' => $index,
            'body' => $dto->toElasticsearchBody(),
        ]);

        return $response->asArray();
    }

    public function indexDocument(string $index, string $id, array $body): array
    {
        $response = $this->client->index([
            'index' => $index,
            'id' => $id,
            'body' => $body,
            'refresh' => 'wait_for',
        ]);

        return $response->asArray();
    }

    public function deleteDocument(string $index, string $id): array
    {
        try {
            $response = $this->client->delete([
                'index' => $index,
                'id' => $id,
                'refresh' => 'wait_for',
            ]);
            return $response->asArray();
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return ['result' => 'not_found'];
            }
            throw $e;
        }
    }

    public function bulkIndex(string $index, array $documents): array
    {
        $params = ['body' => []];

        foreach ($documents as $doc) {
            $params['body'][] = [
                'index' => [
                    '_index' => $index,
                    '_id' => $doc['id'],
                ],
            ];
            $params['body'][] = $doc;
        }

        if (empty($params['body'])) {
            return ['items' => [], 'errors' => false];
        }

        $response = $this->client->bulk($params);
        $result = $response->asArray();

        if ($result['errors']) {
            $errors = array_filter(
                $result['items'],
                fn ($item) => isset($item['index']['error'])
            );
            Log::warning('Bulk index errors', ['errors' => array_values($errors)]);
        }

        return $result;
    }

    /**
     * Remove many documents in a single _bulk request (no per-doc refresh).
     * Missing docs come back as not_found and are ignored — no exception.
     */
    public function bulkDelete(string $index, array $ids): array
    {
        if (empty($ids)) {
            return ['items' => [], 'errors' => false];
        }

        $params = ['body' => []];
        foreach ($ids as $id) {
            $params['body'][] = [
                'delete' => [
                    '_index' => $index,
                    '_id' => (string) $id,
                ],
            ];
        }

        return $this->client->bulk($params)->asArray();
    }

    public function deleteIndex(string $index): void
    {
        try {
            $this->client->indices()->delete(['index' => $index]);
        } catch (ClientResponseException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    public function createIndex(string $index, array $mapping): void
    {
        $this->client->indices()->create([
            'index' => $index,
            'body' => $mapping,
        ]);
    }

    public function indexExists(string $index): bool
    {
        $response = $this->client->indices()->exists(['index' => $index]);
        return $response->getStatusCode() === 200;
    }

    public function refresh(string $index): void
    {
        $this->client->indices()->refresh(['index' => $index]);
    }

    public function updateByQuery(string $index, array $query, array $script): array
    {
        $response = $this->client->updateByQuery([
            'index' => $index,
            'body' => [
                'query' => $query,
                'script' => $script,
            ],
        ]);

        return $response->asArray();
    }

    public function rawSearch(string $index, array $body): array
    {
        $response = $this->client->search([
            'index' => $index,
            'body' => $body,
        ]);

        return $response->asArray();
    }

    public function reloadSearchAnalyzers(string $index): void
    {
        $this->client->indices()->reloadSearchAnalyzers(['index' => $index]);
    }

    /**
     * Create or replace a named synonym set via the Elasticsearch Synonyms API.
     * $rules: [['synonyms' => 'a, b, c'], ['synonyms' => 'from => to'], ...].
     */
    public function putSynonymSet(string $set, array $rules): void
    {
        $this->client->synonyms()->putSynonym([
            'id'   => $set,
            'body' => ['synonyms_set' => array_values($rules)],
        ]);
    }

    public function getDocument(string $index, string $id): ?array
    {
        try {
            $response = $this->client->get([
                'index' => $index,
                'id' => $id,
            ]);
            return $response->asArray();
        } catch (ClientResponseException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function count(string $index, array $query = []): int
    {
        $params = ['index' => $index];
        if (!empty($query)) {
            $params['body'] = ['query' => $query];
        }

        $response = $this->client->count($params);
        return $response->asArray()['count'] ?? 0;
    }
}
