@extends('admin.layout')

@section('title', 'Search Preview')
@section('page-title', 'Search Preview')

@section('content')
<div x-data="previewPage()" class="space-y-6">

    <!-- Query Input -->
    <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex gap-3">
            <input x-model="query"
                   @keydown.enter="runSearch()"
                   placeholder="Enter a search query..."
                   class="flex-1 border rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <select x-model="sort" class="border rounded-lg px-3 py-2 text-sm">
                <option value="relevance">Relevance</option>
                <option value="price_asc">Price Low-High</option>
                <option value="price_desc">Price High-Low</option>
                <option value="rating">Rating</option>
                <option value="best_selling">Best Selling</option>
            </select>
            <button @click="runSearch()" :disabled="loading"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                <span x-show="!loading">Search</span>
                <span x-show="loading">...</span>
            </button>
        </div>
    </div>

    <!-- Results and Facets -->
    <div x-show="results" class="grid grid-cols-4 gap-6">

        <!-- Facets Sidebar -->
        <div class="col-span-1 space-y-4">
            <template x-for="facet in (results?.facets || [])" :key="facet.key">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h4 class="font-medium text-gray-700 text-sm mb-2" x-text="facet.label"></h4>
                    <div class="space-y-1">
                        <template x-for="val in (facet.values || []).slice(0, 8)" :key="val.value">
                            <label class="flex items-center justify-between text-sm cursor-pointer hover:text-blue-600">
                                <span class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded">
                                    <span x-text="val.label"></span>
                                </span>
                                <span class="text-gray-400 text-xs" x-text="'(' + val.count + ')'"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Results -->
        <div class="col-span-3">
            <!-- Meta Bar -->
            <div class="flex items-center justify-between mb-4 text-sm text-gray-500">
                <span x-text="`${results?.pagination?.total?.toLocaleString() || 0} results for &quot;${results?.meta?.query}&quot;`"></span>
                <span x-text="`${results?.meta?.took_ms || 0}ms`"></span>
            </div>

            <!-- Banner -->
            <div x-show="results?.banner" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm" x-html="results?.banner?.html"></div>

            <!-- Products Grid -->
            <div class="grid grid-cols-3 gap-4">
                <template x-for="(product, index) in (results?.products || [])" :key="product.id">
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100 hover:shadow-md transition-shadow cursor-pointer"
                         @click="explainProduct(product.id)">

                        <!-- Product Image Placeholder -->
                        <div class="bg-gray-100 h-40 flex items-center justify-center">
                            <svg class="w-12 h-12 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/>
                            </svg>
                        </div>

                        <div class="p-3">
                            <!-- Rank Badge -->
                            <div class="flex items-center justify-between mb-1">
                                <span class="bg-blue-100 text-blue-700 text-xs px-1.5 py-0.5 rounded" x-text="'#' + (index + 1)"></span>
                                <span class="text-xs text-gray-400" x-text="product.score ? 'Score: ' + product.score.toFixed(2) : ''"></span>
                            </div>
                            <div class="text-sm font-medium text-gray-800 line-clamp-2" x-text="product.name"></div>
                            <div class="text-xs text-gray-500 mt-0.5" x-text="product.brand"></div>
                            <div class="text-sm font-semibold text-green-700 mt-1" x-text="'$' + product.price?.toFixed(2)"></div>
                            <div class="flex items-center gap-1 mt-1">
                                <span class="text-yellow-400 text-xs">★</span>
                                <span class="text-xs text-gray-500" x-text="product.rating + ' (' + product.review_count + ')'"></span>
                            </div>
                            <div class="mt-1.5">
                                <span :class="product.inventory > 0 ? 'text-green-600' : 'text-red-500'"
                                      class="text-xs" x-text="product.inventory > 0 ? 'In Stock' : 'Out of Stock'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty State -->
            <div x-show="results?.products?.length === 0"
                 class="bg-white rounded-lg shadow-sm p-12 text-center text-gray-400">
                <div class="text-4xl mb-3">🔍</div>
                <div class="font-medium">No results found</div>
                <div class="text-sm mt-1">Try a different query or remove filters</div>
            </div>
        </div>
    </div>

    <!-- Ranking Explanation Modal -->
    <div x-show="explanation" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[80vh] overflow-y-auto p-6" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Ranking Explanation</h3>
                <button @click="explanation = null" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <template x-if="explanation">
                <div>
                    <div class="text-2xl font-bold text-blue-600 mb-4" x-text="'Total Score: ' + explanation.score?.toFixed(3)"></div>

                    <div class="space-y-3">
                        <template x-for="(value, factor) in (explanation.ranking_factors || {})" :key="factor">
                            <div x-show="typeof value === 'number'" class="flex items-center justify-between py-2 border-b border-gray-50">
                                <span class="text-sm text-gray-700 capitalize" x-text="factor.replace(/_/g, ' ')"></span>
                                <div class="flex items-center gap-2">
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full"
                                             :style="`width: ${Math.min(100, (value / 3) * 100)}%`"></div>
                                    </div>
                                    <span class="text-sm font-medium w-12 text-right" x-text="typeof value === 'number' ? value.toFixed(2) : value"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Query DSL Toggle -->
    <div x-show="queryDsl" class="bg-white rounded-lg shadow-sm">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-medium text-gray-700 text-sm">Generated Query DSL</h3>
            <button @click="showDsl = !showDsl" class="text-blue-600 text-xs hover:underline" x-text="showDsl ? 'Hide' : 'Show'"></button>
        </div>
        <div x-show="showDsl" class="p-4">
            <pre class="text-xs text-gray-600 bg-gray-50 p-3 rounded overflow-x-auto" x-text="JSON.stringify(queryDsl, null, 2)"></pre>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function previewPage() {
    return {
        query: '',
        sort: 'relevance',
        loading: false,
        results: null,
        queryDsl: null,
        explanation: null,
        showDsl: false,

        async runSearch() {
            if (!this.query.trim()) return;
            this.loading = true;
            try {
                const res = await fetch('/api/admin/search/preview', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ q: this.query, sort: this.sort, store_id: parseInt(storeId) }),
                });
                const data = await res.json();
                this.results = data.results;
                this.queryDsl = data.query_dsl;
            } catch(e) {
                console.error('Preview failed', e);
            } finally {
                this.loading = false;
            }
        },

        async explainProduct(productId) {
            try {
                const res = await fetch('/api/admin/search/preview/explain', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ q: this.query, product_id: productId, store_id: parseInt(storeId) }),
                });
                this.explanation = await res.json();
            } catch(e) {
                console.error('Explain failed', e);
            }
        }
    };
}
</script>
@endsection
