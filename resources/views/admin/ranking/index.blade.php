@extends('admin.layout')

@section('title', 'Ranking Explainer')
@section('page-title', 'How Search Ranking Works')

@section('content')
<div class="space-y-6 max-w-4xl">

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
        This page explains how products are ranked in search results. Understanding the ranking helps merchandisers create effective query rules, campaigns, and synonyms.
    </div>

    <!-- Ranking Stages -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Ranking Stages</h2>
        <div class="flex items-stretch gap-2">
            @foreach([
                ['1', 'Retrieval', 'Full-text search retrieves all matching documents', 'bg-blue-100 text-blue-700'],
                ['2', 'Text Score', 'BM25 scoring ranks by text relevance', 'bg-purple-100 text-purple-700'],
                ['3', 'Function Score', 'Business signals modify relevance score', 'bg-green-100 text-green-700'],
                ['4', 'Query Rules', 'Manual pins, boosts, and exclusions applied', 'bg-yellow-100 text-yellow-700'],
                ['5', 'Campaigns', 'Active promotion boosts applied', 'bg-orange-100 text-orange-700'],
                ['6', 'Final Rank', 'Products sorted and paginated', 'bg-gray-100 text-gray-700'],
            ] as [$step, $title, $desc, $class])
            <div class="flex-1 text-center p-3 rounded-lg {{ $class }}">
                <div class="text-2xl font-bold">{{ $step }}</div>
                <div class="font-semibold text-sm mt-1">{{ $title }}</div>
                <div class="text-xs mt-1 opacity-75">{{ $desc }}</div>
            </div>
            @if(!$loop->last)
            <div class="flex items-center text-gray-400 text-lg">→</div>
            @endif
            @endforeach
        </div>
    </div>

    <!-- Text Relevance Boosts -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Text Relevance Field Boosts</h2>
        <p class="text-sm text-gray-500 mb-4">When a query matches different fields, each field is boosted differently:</p>
        <div class="space-y-2">
            @foreach([
                ['Exact SKU Match', 100, 'bg-red-500'],
                ['Exact Name Match', 50, 'bg-orange-500'],
                ['Name Phrase Match', 20, 'bg-yellow-500'],
                ['Brand Exact Match', 15, 'bg-green-500'],
                ['Name Fuzzy Match', 10, 'bg-teal-500'],
                ['Brand Text Match', 8, 'bg-blue-500'],
                ['Category Match', 5, 'bg-indigo-500'],
                ['Attributes Match', 3, 'bg-purple-500'],
                ['Description Match', 1, 'bg-gray-500'],
            ] as [$field, $boost, $color])
            <div class="flex items-center gap-3">
                <div class="w-48 text-sm font-medium text-gray-700">{{ $field }}</div>
                <div class="flex-1 bg-gray-100 rounded-full h-5 relative">
                    <div class="{{ $color }} h-5 rounded-full flex items-center justify-end pr-2"
                         style="width: {{ ($boost / 100) * 100 }}%">
                        <span class="text-white text-xs font-bold">{{ $boost }}×</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Business Ranking Factors -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Business Ranking Modifiers</h2>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <h3 class="font-medium text-green-700 mb-2 flex items-center gap-1">
                    <span>↑</span> Boosted Factors
                </h3>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-center justify-between border-b pb-1">
                        <span class="text-gray-700">In-stock products</span>
                        <span class="text-green-600 font-semibold">×1.5</span>
                    </li>
                    <li class="flex items-center justify-between border-b pb-1">
                        <span class="text-gray-700">High rating (≥4.0 stars)</span>
                        <span class="text-green-600 font-semibold">×1.2</span>
                    </li>
                    <li class="flex items-center justify-between border-b pb-1">
                        <span class="text-gray-700">High review count</span>
                        <span class="text-green-600 font-semibold">log factor</span>
                    </li>
                    <li class="flex items-center justify-between pb-1">
                        <span class="text-gray-700">Featured (store-specific)</span>
                        <span class="text-green-600 font-semibold">×2.0</span>
                    </li>
                </ul>
            </div>
            <div>
                <h3 class="font-medium text-red-700 mb-2 flex items-center gap-1">
                    <span>↓</span> Demoted Factors
                </h3>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-center justify-between border-b pb-1">
                        <span class="text-gray-700">Out of stock</span>
                        <span class="text-red-600 font-semibold">×0.3</span>
                    </li>
                    <li class="flex items-center justify-between border-b pb-1">
                        <span class="text-gray-700">Low inventory (&lt;5 units)</span>
                        <span class="text-red-600 font-semibold">×0.7</span>
                    </li>
                </ul>
            </div>
        </div>
        <div class="mt-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
            <strong>Note:</strong> Out-of-stock products are still searchable and returned in results — they are simply ranked lower than in-stock alternatives.
        </div>
    </div>

    <!-- Synonyms -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Synonyms</h2>
        <p class="text-sm text-gray-500 mb-3">Synonyms expand the query before matching. There are two types:</p>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="border border-green-200 rounded-lg p-3 bg-green-50">
                <div class="font-semibold text-green-700 mb-1">Equivalent (bidirectional)</div>
                <code class="text-xs">tv, television, telly</code>
                <p class="text-gray-600 text-xs mt-1">Query for any term finds results containing all terms.</p>
            </div>
            <div class="border border-blue-200 rounded-lg p-3 bg-blue-50">
                <div class="font-semibold text-blue-700 mb-1">One-way (directional)</div>
                <code class="text-xs">iphone → apple iphone</code>
                <p class="text-gray-600 text-xs mt-1">Query for "iphone" also finds "apple iphone" but NOT vice versa.</p>
            </div>
        </div>
    </div>

    <!-- Typo Tolerance -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Typo Tolerance</h2>
        <p class="text-sm text-gray-500 mb-3">Fuzzy matching using Levenshtein edit distance:</p>
        <div class="text-sm">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium text-gray-600">Query Length</th>
                        <th class="text-left px-3 py-2 font-medium text-gray-600">Max Typos Allowed</th>
                        <th class="text-left px-3 py-2 font-medium text-gray-600">Example</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="px-3 py-2">1-2 chars</td><td class="px-3 py-2">0 (exact match)</td><td class="px-3 py-2 font-mono">TV</td></tr>
                    <tr><td class="px-3 py-2">3-5 chars</td><td class="px-3 py-2">1 edit</td><td class="px-3 py-2 font-mono">camon → canon</td></tr>
                    <tr><td class="px-3 py-2">6+ chars</td><td class="px-3 py-2">2 edits</td><td class="px-3 py-2 font-mono">canoon → canon</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Query Rules Priority -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Query Rules Priority</h2>
        <p class="text-sm text-gray-500 mb-3">When multiple rules match a query, they are applied in this order:</p>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
            <li><strong>Redirect</strong> — Sends user to a different URL (highest priority, bypasses search)</li>
            <li><strong>Exclusions</strong> — Remove specific products from all results</li>
            <li><strong>Pins</strong> — Force specific products to the top positions</li>
            <li><strong>Boosts</strong> — Multiply the relevance score of specific products</li>
            <li><strong>Buries</strong> — Demote specific products lower in results</li>
            <li><strong>Banners</strong> — Display promotional HTML above results</li>
        </ol>
    </div>

</div>
@endsection
