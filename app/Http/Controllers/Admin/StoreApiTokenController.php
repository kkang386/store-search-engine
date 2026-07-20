<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StoreApiTokenController extends Controller
{
    public function index(Store $store): JsonResponse
    {
        $tokens = $store->apiTokens()
            ->select(['id', 'name', 'last_used_at', 'created_at'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($tokens);
    }

    public function store(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $plain = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $plain);

        $token = $store->apiTokens()->create([
            'name'  => $data['name'],
            'token' => $hashed,
        ]);

        return response()->json([
            'id'         => $token->id,
            'name'       => $token->name,
            'token'      => $plain,
            'created_at' => $token->created_at,
        ], Response::HTTP_CREATED);
    }

    public function destroy(Store $store, StoreApiToken $apiToken): Response
    {
        abort_if($apiToken->store_id !== $store->id, 404);
        $apiToken->delete();

        return response()->noContent();
    }
}
