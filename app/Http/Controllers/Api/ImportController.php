<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessApiImportJob;
use App\Models\ImportRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    /**
     * Upsert categories. Body is a JSON array of category objects
     * (category_id, parent_category_id, name, slug, depth, sort_order, is_active).
     * Accepted for async processing; returns a request_id to poll for status.
     */
    public function categories(Request $request, Store $store): JsonResponse
    {
        if ($denied = $this->authorizeStore($request, $store)) {
            return $denied;
        }

        $rows = $this->decodeArrayBody($request);
        if ($rows === null) {
            return response()->json(['error' => 'Payload must be a JSON array of category objects'], 422);
        }

        $validator = Validator::make($rows, [
            '*.category_id'        => ['required'],
            '*.parent_category_id' => ['nullable'],
            '*.name'               => ['required', 'string', 'max:191'],
            '*.slug'               => ['nullable', 'string', 'max:191'],
            '*.depth'              => ['nullable', 'integer'],
            '*.sort_order'         => ['nullable', 'integer'],
            '*.is_active'          => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        return $this->accept($store, 'categories', $rows);
    }

    /**
     * Upsert products and their category links. Body is a JSON array of product
     * objects (sku, name, slug, brand, description, price, inventory, is_active,
     * attributes, images, meta, sales_rank, product_categories). product_categories
     * is an array of external category ids, first = primary. Accepted for async
     * processing; returns a request_id to poll for status.
     */
    public function products(Request $request, Store $store): JsonResponse
    {
        if ($denied = $this->authorizeStore($request, $store)) {
            return $denied;
        }

        $rows = $this->decodeArrayBody($request);
        if ($rows === null) {
            return response()->json(['error' => 'Payload must be a JSON array of product objects'], 422);
        }

        $validator = Validator::make($rows, [
            '*.sku'                => ['required', 'string', 'max:100'],
            '*.name'               => ['required', 'string', 'max:191'],
            '*.slug'               => ['nullable', 'string', 'max:191'],
            '*.brand'              => ['nullable', 'string', 'max:100'],
            '*.description'        => ['nullable', 'string'],
            '*.price'              => ['nullable', 'numeric'],
            '*.inventory'          => ['nullable', 'integer'],
            '*.is_active'          => ['nullable', 'boolean'],
            '*.attributes'         => ['nullable', 'array'],
            '*.images'             => ['nullable', 'array'],
            '*.meta'               => ['nullable', 'array'],
            '*.sales_rank'         => ['nullable', 'integer'],
            '*.product_categories' => ['nullable', 'array'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        return $this->accept($store, 'products', $rows);
    }

    /**
     * Status of a previously accepted request. Scoped to the authenticated store.
     */
    public function status(Request $request, Store $store, string $requestId): JsonResponse
    {
        if ($denied = $this->authorizeStore($request, $store)) {
            return $denied;
        }

        $importRequest = ImportRequest::where('request_id', $requestId)
            ->where('store_id', $store->id)
            ->first();

        if (!$importRequest) {
            return response()->json(['error' => 'Unknown request_id for this store'], 404);
        }

        return response()->json([
            'request_id' => $importRequest->request_id,
            'type'       => $importRequest->type,
            'status'     => $importRequest->status,
            'total'      => $importRequest->total,
            'result'     => [
                'created' => $importRequest->created_count,
                'updated' => $importRequest->updated_count,
                'failed'  => $importRequest->failed_count,
                'indexed' => $importRequest->indexed_count,
            ],
            'error'        => $importRequest->error,
            'received_at'  => $importRequest->created_at?->toIso8601String(),
            'updated_at'   => $importRequest->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Record the request as in-progress, queue it for processing, and return the
     * request_id the caller polls with (202 Accepted).
     */
    private function accept(Store $store, string $type, array $rows): JsonResponse
    {
        $requestId = (string) Str::uuid();

        ImportRequest::create([
            'request_id' => $requestId,
            'store_id'   => $store->id,
            'type'       => $type,
            'status'     => ImportRequest::STATUS_IN_PROGRESS,
            'payload'    => json_encode($rows),
            'total'      => count($rows),
        ]);

        ProcessApiImportJob::dispatch($requestId);

        return response()->json([
            'request_id' => $requestId,
            'status'     => ImportRequest::STATUS_IN_PROGRESS,
            'total'      => count($rows),
        ], 202);
    }

    /**
     * Decode the raw request body as a top-level JSON array. Read from the raw
     * content (not $request->json()) because the auth middleware merges store_id
     * into the JSON input bag, which would otherwise break list detection.
     * Returns the list, or null if the body is not a JSON array.
     */
    private function decodeArrayBody(Request $request): ?array
    {
        $decoded = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * The bearer token (resolved by the auth.api_token middleware into
     * request 'store_id') must belong to the store named in the URL. Returns a
     * 403 response when they disagree, or null when authorized.
     */
    private function authorizeStore(Request $request, Store $store): ?JsonResponse
    {
        if ((int) $request->integer('store_id') !== (int) $store->id) {
            return response()->json(['error' => 'Token is not authorized for this store'], 403);
        }

        return null;
    }
}
