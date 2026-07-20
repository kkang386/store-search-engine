<?php

/**
 * This is a sample client library for this search engine.
 * Standalone PHP client for the Search Engine API.
 *
 * Requirements: PHP 7.4+, ext-curl, ext-json
 * No framework dependencies — drop this file into any PHP project.
 *
 * Usage:
 *   $client = new SearchEngineClient('http://localhost:8080', 'your-bearer-token');
 *
 *   $results  = $client->search('airsoft gun', ['sort' => 'price_asc', 'price_max' => 500]);
 *   $suggests = $client->suggest('airso');
 *   $client->trackClick('airsoft gun', productId: 123, position: 0);
 *   $status   = $client->health();
 */
class SearchEngineClient
{
    private string $baseUrl;
    private string $token;
    private int    $timeout;

    /**
     * @param string $baseUrl  Base URL of the search engine (e.g. "http://localhost:8080")
     * @param string $token    Bearer token obtained from Admin → Stores → Tokens
     * @param int    $timeout  Request timeout in seconds (default 10)
     */
    public function __construct(string $baseUrl, string $token, int $timeout = 10)
    {
        $this->baseUrl  = rtrim($baseUrl, '/');
        $this->token    = $token;
        $this->timeout  = $timeout;
    }

    /**
     * Full-text product search.
     *
     * @param string $query   Search query string
     * @param array  $params  Optional parameters:
     *   - page          int      Page number (default 1)
     *   - per_page      int      Results per page, max 100 (default 24)
     *   - sort          string   relevance|price_asc|price_desc|newest|rating|best_selling
     *   - brand         string[] Filter by brand(s),    e.g. ['Nike', 'Adidas']
     *   - category      int[]    Filter by category ID(s)
     *   - price_min     float    Minimum price
     *   - price_max     float    Maximum price
     *   - attributes    array    Attribute filters, e.g. ['color' => ['Black', 'Red']]
     *   - store_id      int      Override store context (normally derived from the token)
     *   - facets        bool     Include facet aggregations (default true)
     *
     * @return array{
     *   products:   array,
     *   facets:     array,
     *   pagination: array{total: int, page: int, per_page: int, total_pages: int, has_more: bool},
     *   meta:       array{query: string, total_hits: int, took_ms: int, corrected_query: string|null, store_id: int},
     *   banner:     array|null,
     *   redirect:   array|null
     * }
     *
     * @throws SearchEngineException
     */
    public function search(string $query, array $params = []): array
    {
        $params['q'] = $query;
        return $this->get('/api/search', $params);
    }

    /**
     * Autocomplete suggestions.
     *
     * @param string   $query   Partial query string
     * @param int      $limit   Max suggestions to return (default 10)
     * @param int|null $storeId Store context override
     *
     * @return array{suggestions: array, took_ms: int}
     *
     * @throws SearchEngineException
     */
    public function suggest(string $query, int $limit = 10, ?int $storeId = null): array
    {
        $params = ['q' => $query, 'limit' => $limit];
        if ($storeId !== null) {
            $params['store_id'] = $storeId;
        }

        return $this->get('/api/search/suggest', $params);
    }

    /**
     * Record a click event for CTR analytics.
     *
     * @param string   $query      The search query that produced the result
     * @param int      $productId  ID of the product that was clicked
     * @param int      $position   0-based position of the product in the result list
     * @param int|null $storeId    Store context override
     *
     * @throws SearchEngineException
     */
    public function trackClick(string $query, int $productId, int $position, ?int $storeId = null): void
    {
        $payload = [
            'query'      => $query,
            'product_id' => $productId,
            'position'   => $position,
        ];
        if ($storeId !== null) {
            $payload['store_id'] = $storeId;
        }

        $this->post('/api/search/click', $payload);
    }

    /**
     * Check the health of all system services (database, Elasticsearch, Redis).
     *
     * Returns HTTP 200 when all services are healthy, 503 when any are degraded.
     * On 503 this method returns the response body rather than throwing — the
     * caller can inspect `$result['status']` to distinguish healthy / degraded.
     *
     * @return array{
     *   status: string,
     *   timestamp: string,
     *   services: array{
     *     database:      array{status: string, latency_ms?: int, error?: string},
     *     elasticsearch: array{status: string, latency_ms?: int, cluster_status?: string, nodes?: int, error?: string},
     *     redis:         array{status: string, latency_ms?: int, error?: string}
     *   }
     * }
     *
     * @throws SearchEngineException on network error or 4xx response
     */
    public function health(): array
    {
        return $this->getPermissive('/api/health');
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    private function get(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->request('GET', $url);
    }

    private function getPermissive(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $this->request('GET', $url, null, [503]);
    }

    private function post(string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;
        return $this->request('POST', $url, json_encode($payload));
    }

    private function request(string $method, string $url, ?string $body = null, array $allowedErrorCodes = []): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json',
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_FAILONERROR    => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError !== '') {
            throw new SearchEngineException("Request failed: {$curlError}");
        }

        $decoded = json_decode((string) $response, true);

        if ($httpCode === 401) {
            $msg = $decoded['error'] ?? 'Unauthorized';
            throw new SearchEngineException("Authentication failed: {$msg}", 401);
        }

        if ($httpCode >= 400 && !in_array($httpCode, $allowedErrorCodes, true)) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? $response;
            throw new SearchEngineException("API error {$httpCode}: {$msg}", $httpCode);
        }

        return $decoded ?? [];
    }
}

class SearchEngineException extends RuntimeException {}
