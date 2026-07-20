<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchScopeController extends Controller
{
    // Search categories by name, or fetch by IDs for pre-populating pickers.
    public function categories(Request $request): JsonResponse
    {
        if ($request->filled('ids')) {
            $ids = array_filter(array_map('intval', explode(',', $request->string('ids'))));
            $categories = Category::whereIn('id', $ids)
                ->select('id', 'name', 'depth')
                ->orderBy('depth')
                ->orderBy('name')
                ->get();
            return response()->json($categories);
        }

        $q = $request->string('q');
        $categories = Category::where('name', 'like', "%{$q}%")
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('name')
            ->limit(25)
            ->select('id', 'name', 'depth')
            ->get();

        return response()->json($categories);
    }

    // Search distinct brand names, or fetch by exact names for pre-populating pickers.
    public function brands(Request $request): JsonResponse
    {
        if ($request->filled('ids')) {
            $ids = array_values(array_filter(array_map('trim', explode(',', $request->string('ids')))));
            $brands = DB::table('products')
                ->whereIn('brand', $ids)
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand')
                ->map(fn ($b) => ['id' => $b, 'name' => $b])
                ->values();
            return response()->json($brands);
        }

        $q = $request->string('q');
        $brands = DB::table('products')
            ->where('brand', 'like', "%{$q}%")
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->limit(25)
            ->pluck('brand')
            ->map(fn ($b) => ['id' => $b, 'name' => $b])
            ->values();

        return response()->json($brands);
    }
}
