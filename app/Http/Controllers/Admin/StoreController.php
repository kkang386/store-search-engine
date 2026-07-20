<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::withTrashed()->withCount('storeProducts')->orderBy('id')->get();
        return response()->json($stores);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'code'     => ['required', 'string', 'max:32', 'unique:stores,code', 'regex:/^[a-z0-9_\-]+$/'],
            'region'   => ['nullable', 'string', 'max:32'],
            'locale'   => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active'=> ['boolean'],
        ]);

        $store = Store::create([
            'name'      => $data['name'],
            'code'      => $data['code'],
            'region'    => $data['region'] ?? null,
            'locale'    => $data['locale'] ?? 'en_US',
            'currency'  => strtoupper($data['currency'] ?? 'USD'),
            'is_active' => $data['is_active'] ?? true,
        ]);

        Storage::disk('local')->makeDirectory("imports/{$store->code}");

        return response()->json($store, Response::HTTP_CREATED);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:200'],
            'code'     => ['sometimes', 'string', 'max:32', 'regex:/^[a-z0-9_\-]+$/', 'unique:stores,code,' . $store->id],
            'region'   => ['nullable', 'string', 'max:32'],
            'locale'   => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active'=> ['boolean'],
        ]);

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $store->update($data);

        return response()->json($store->fresh());
    }

    public function destroy(Store $store): Response
    {
        // Prevent deleting the last active store
        if ($store->is_active && Store::active()->count() <= 1) {
            abort(422, 'Cannot delete the last active store.');
        }

        $store->delete();

        return response()->noContent();
    }

    public function restore(int $id): JsonResponse
    {
        $store = Store::withTrashed()->findOrFail($id);
        $store->restore();

        return response()->json($store->fresh());
    }
}
