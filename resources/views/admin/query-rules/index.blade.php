@extends('admin.layout')

@section('title', 'Query Rules')
@section('page-title', 'Query Rules')

@section('content')
<div x-data="queryRulesPage()" x-init="init()">

    <div class="flex justify-end mb-4">
        <button @click="showModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + Add Rule
        </button>
    </div>

    <!-- Info Banner -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-sm text-yellow-800">
        <strong>Query Rules</strong> let you pin products to the top, exclude products, boost relevance, or redirect queries.
        Use <strong>Scope Filters</strong> to restrict a rule to specific categories or brands.
        Rules are applied in priority order and take effect immediately without reindexing.
    </div>

    <!-- Rules Table -->
    <div class="bg-white rounded-lg shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Pattern</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Match</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Action</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Scope</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Priority</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Period</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="rule in rules" :key="rule.id">
                    <tr class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs" x-text="rule.query_pattern"></td>
                        <td class="px-4 py-3">
                            <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs" x-text="rule.match_type"></span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="{
                                'bg-blue-100 text-blue-700': rule.action === 'pin',
                                'bg-red-100 text-red-700': rule.action === 'exclude',
                                'bg-green-100 text-green-700': rule.action === 'boost',
                                'bg-yellow-100 text-yellow-700': rule.action === 'banner',
                                'bg-purple-100 text-purple-700': rule.action === 'redirect',
                                'bg-orange-100 text-orange-700': rule.action === 'bury',
                            }" class="px-2 py-0.5 rounded text-xs font-medium" x-text="rule.action"></span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <template x-if="(rule.include_category_ids||[]).length || (rule.exclude_category_ids||[]).length || (rule.include_brands||[]).length">
                                <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded">scoped</span>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-gray-600" x-text="rule.priority"></td>
                        <td class="px-4 py-3">
                            <span :class="rule.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="px-2 py-0.5 rounded text-xs" x-text="rule.is_active ? 'Active' : 'Inactive'"></span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500" x-text="formatPeriod(rule)"></td>
                        <td class="px-4 py-3 flex gap-2 justify-end">
                            <button @click="editRule(rule)" class="text-blue-600 hover:text-blue-800 text-xs">Edit</button>
                            <button @click="deleteRule(rule.id)" class="text-red-600 hover:text-red-800 text-xs">Delete</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="rules.length === 0">
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">No query rules configured</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Add/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-4" x-text="editingId ? 'Edit Rule' : 'Add Query Rule'"></h2>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Query Pattern</label>
                        <input x-model="form.query_pattern" placeholder="canon" class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Match Type</label>
                        <select x-model="form.match_type" class="w-full border rounded px-3 py-2 text-sm">
                            <option value="exact">Exact</option>
                            <option value="contains">Contains</option>
                            <option value="starts_with">Starts With</option>
                            <option value="regex">Regex</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                        <select x-model="form.action" class="w-full border rounded px-3 py-2 text-sm">
                            <option value="pin">Pin Products</option>
                            <option value="exclude">Exclude Products</option>
                            <option value="boost">Boost Products</option>
                            <option value="bury">Bury Products</option>
                            <option value="redirect">Redirect</option>
                            <option value="banner">Banner</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <input x-model.number="form.priority" type="number" min="0" max="1000"
                               class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                </div>

                <div x-show="['pin','exclude','boost','bury'].includes(form.action)">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product IDs (comma-separated)</label>
                    <input x-model="form.productIdsStr" placeholder="123, 456, 789" class="w-full border rounded px-3 py-2 text-sm">
                </div>

                <div x-show="form.action === 'boost'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Boost Factor</label>
                    <input x-model.number="form.boost_factor" type="number" step="0.1" min="0.1" max="100"
                           class="w-full border rounded px-3 py-2 text-sm">
                </div>

                <div x-show="form.action === 'redirect'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Redirect URL</label>
                    <input x-model="form.redirect_url" placeholder="/sale" class="w-full border rounded px-3 py-2 text-sm">
                </div>

                <div x-show="form.action === 'banner'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Banner HTML</label>
                    <textarea x-model="form.banner_html" rows="3" placeholder="<div>Promotion banner</div>"
                              class="w-full border rounded px-3 py-2 text-sm font-mono"></textarea>
                </div>

                <!-- Scope Filters -->
                <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                    <div class="text-sm font-semibold text-gray-700">Scope Filters <span class="text-gray-400 font-normal">(optional — leave empty to match all)</span></div>

                    <!-- Include Categories -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-600">Include Categories — only show results in these categories</label>
                            <button type="button" @click="clearPicker('includeCat')" x-show="pickers.includeCat.items.length > 0"
                                    class="text-xs text-gray-400 hover:text-red-500">Clear</button>
                        </div>
                        <div class="flex flex-wrap gap-1 mb-1">
                            <template x-for="item in pickers.includeCat.items" :key="item.id">
                                <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded text-xs">
                                    <span x-text="item.name"></span>
                                    <button type="button" @click="removePickerItem('includeCat', item.id)" class="hover:text-red-600 ml-0.5">×</button>
                                </span>
                            </template>
                        </div>
                        <div class="relative" @click.outside="pickers.includeCat.open = false">
                            <input x-model="pickers.includeCat.search"
                                   @input.debounce.200ms="searchScope('includeCat', 'categories')"
                                   @focus="pickers.includeCat.open = true"
                                   placeholder="Search categories…"
                                   class="w-full border rounded px-3 py-1.5 text-sm">
                            <div x-show="pickers.includeCat.open && pickers.includeCat.results.length > 0"
                                 class="absolute z-50 mt-1 w-full bg-white border rounded shadow-lg max-h-44 overflow-y-auto">
                                <template x-for="item in pickers.includeCat.results" :key="item.id">
                                    <button type="button" @click="togglePickerItem('includeCat', item)"
                                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 flex items-center gap-2">
                                        <input type="checkbox" :checked="isPickerSelected('includeCat', item.id)" class="pointer-events-none shrink-0">
                                        <span :class="'pl-' + (item.depth * 2) + 'px'" class="truncate" x-text="item.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Exclude Categories -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-600">Exclude Categories — hide results in these categories</label>
                            <button type="button" @click="clearPicker('excludeCat')" x-show="pickers.excludeCat.items.length > 0"
                                    class="text-xs text-gray-400 hover:text-red-500">Clear</button>
                        </div>
                        <div class="flex flex-wrap gap-1 mb-1">
                            <template x-for="item in pickers.excludeCat.items" :key="item.id">
                                <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2 py-0.5 rounded text-xs">
                                    <span x-text="item.name"></span>
                                    <button type="button" @click="removePickerItem('excludeCat', item.id)" class="hover:text-red-600 ml-0.5">×</button>
                                </span>
                            </template>
                        </div>
                        <div class="relative" @click.outside="pickers.excludeCat.open = false">
                            <input x-model="pickers.excludeCat.search"
                                   @input.debounce.200ms="searchScope('excludeCat', 'categories')"
                                   @focus="pickers.excludeCat.open = true"
                                   placeholder="Search categories…"
                                   class="w-full border rounded px-3 py-1.5 text-sm">
                            <div x-show="pickers.excludeCat.open && pickers.excludeCat.results.length > 0"
                                 class="absolute z-50 mt-1 w-full bg-white border rounded shadow-lg max-h-44 overflow-y-auto">
                                <template x-for="item in pickers.excludeCat.results" :key="item.id">
                                    <button type="button" @click="togglePickerItem('excludeCat', item)"
                                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 flex items-center gap-2">
                                        <input type="checkbox" :checked="isPickerSelected('excludeCat', item.id)" class="pointer-events-none shrink-0">
                                        <span :class="'pl-' + (item.depth * 2) + 'px'" class="truncate" x-text="item.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Include Brands -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-600">Include Brands — only show results from these brands</label>
                            <button type="button" @click="clearPicker('includeBrand')" x-show="pickers.includeBrand.items.length > 0"
                                    class="text-xs text-gray-400 hover:text-red-500">Clear</button>
                        </div>
                        <div class="flex flex-wrap gap-1 mb-1">
                            <template x-for="item in pickers.includeBrand.items" :key="item.id">
                                <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 px-2 py-0.5 rounded text-xs">
                                    <span x-text="item.name"></span>
                                    <button type="button" @click="removePickerItem('includeBrand', item.id)" class="hover:text-red-600 ml-0.5">×</button>
                                </span>
                            </template>
                        </div>
                        <div class="relative" @click.outside="pickers.includeBrand.open = false">
                            <input x-model="pickers.includeBrand.search"
                                   @input.debounce.200ms="searchScope('includeBrand', 'brands')"
                                   @focus="pickers.includeBrand.open = true"
                                   placeholder="Search brands…"
                                   class="w-full border rounded px-3 py-1.5 text-sm">
                            <div x-show="pickers.includeBrand.open && pickers.includeBrand.results.length > 0"
                                 class="absolute z-50 mt-1 w-full bg-white border rounded shadow-lg max-h-44 overflow-y-auto">
                                <template x-for="item in pickers.includeBrand.results" :key="item.id">
                                    <button type="button" @click="togglePickerItem('includeBrand', item)"
                                            class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 flex items-center gap-2">
                                        <input type="checkbox" :checked="isPickerSelected('includeBrand', item.id)" class="pointer-events-none shrink-0">
                                        <span x-text="item.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Starts At (optional)</label>
                        <input x-model="form.starts_at" type="datetime-local" class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ends At (optional)</label>
                        <input x-model="form.ends_at" type="datetime-local" class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" x-model="form.is_active">
                    Active
                </label>
            </div>

            <div class="flex gap-3 mt-6">
                <button @click="saveRule()" class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700">Save</button>
                <button @click="closeModal()" class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function queryRulesPage() {
    return {
        rules: [], showModal: false, editingId: null,
        form: {
            query_pattern: '', match_type: 'exact', action: 'pin', priority: 0,
            productIdsStr: '', boost_factor: 1.5, redirect_url: '', banner_html: '',
            starts_at: '', ends_at: '', is_active: true,
        },
        pickers: {
            includeCat:   { search: '', results: [], items: [], open: false },
            excludeCat:   { search: '', results: [], items: [], open: false },
            includeBrand: { search: '', results: [], items: [], open: false },
        },

        async init() { await this.fetchRules(); },

        async fetchRules() {
            const data = await apiRequest('GET', `/api/admin/search/query-rules?store_id=${storeId}`);
            this.rules = data.data || [];
        },

        async searchScope(pickerKey, type) {
            const q = this.pickers[pickerKey].search.trim();
            if (!q) { this.pickers[pickerKey].results = []; return; }
            const url = `/api/admin/search/scope/${type}?q=${encodeURIComponent(q)}`;
            this.pickers[pickerKey].results = await apiRequest('GET', url);
        },

        isPickerSelected(pickerKey, id) {
            return this.pickers[pickerKey].items.some(i => i.id === id);
        },

        togglePickerItem(pickerKey, item) {
            const idx = this.pickers[pickerKey].items.findIndex(i => i.id === item.id);
            if (idx >= 0) this.pickers[pickerKey].items.splice(idx, 1);
            else this.pickers[pickerKey].items.push({ id: item.id, name: item.name });
        },

        removePickerItem(pickerKey, id) {
            this.pickers[pickerKey].items = this.pickers[pickerKey].items.filter(i => i.id !== id);
        },

        clearPicker(pickerKey) {
            this.pickers[pickerKey].items = [];
        },

        resetPickers() {
            for (const key of Object.keys(this.pickers)) {
                this.pickers[key] = { search: '', results: [], items: [], open: false };
            }
        },

        async loadPickerItems(ids, pickerKey, type) {
            if (!ids || ids.length === 0) return;
            const url = `/api/admin/search/scope/${type}?ids=${ids.join(',')}`;
            this.pickers[pickerKey].items = await apiRequest('GET', url);
        },

        async editRule(rule) {
            this.editingId = rule.id;
            this.form = {
                query_pattern: rule.query_pattern,
                match_type: rule.match_type,
                action: rule.action,
                priority: rule.priority,
                productIdsStr: (rule.product_ids || []).join(', '),
                boost_factor: rule.boost_factor || 1.5,
                redirect_url: rule.redirect_url || '',
                banner_html: rule.banner_html || '',
                starts_at: rule.starts_at ? rule.starts_at.replace(' ', 'T').slice(0, 16) : '',
                ends_at: rule.ends_at ? rule.ends_at.replace(' ', 'T').slice(0, 16) : '',
                is_active: rule.is_active,
            };
            this.resetPickers();
            await Promise.all([
                this.loadPickerItems(rule.include_category_ids, 'includeCat', 'categories'),
                this.loadPickerItems(rule.exclude_category_ids, 'excludeCat', 'categories'),
                this.loadPickerItems(rule.include_brands, 'includeBrand', 'brands'),
            ]);
            this.showModal = true;
        },

        async saveRule() {
            const data = { ...this.form, store_id: parseInt(storeId) || null };
            data.product_ids = this.form.productIdsStr.split(',').map(s => parseInt(s.trim())).filter(Boolean);
            delete data.productIdsStr;
            data.include_category_ids = this.pickers.includeCat.items.map(i => i.id);
            data.exclude_category_ids = this.pickers.excludeCat.items.map(i => i.id);
            data.include_brands = this.pickers.includeBrand.items.map(i => i.id);

            const url = this.editingId ? `/api/admin/search/query-rules/${this.editingId}` : '/api/admin/search/query-rules';
            const method = this.editingId ? 'PUT' : 'POST';
            await apiRequest(method, url, data);
            this.closeModal();
            await this.fetchRules();
        },

        async deleteRule(id) {
            if (!confirm('Delete this rule?')) return;
            await apiRequest('DELETE', `/api/admin/search/query-rules/${id}`);
            await this.fetchRules();
        },

        formatPeriod(rule) {
            if (!rule.starts_at && !rule.ends_at) return 'Always';
            return (rule.starts_at ? rule.starts_at.slice(0, 10) : '?') + ' – ' + (rule.ends_at ? rule.ends_at.slice(0, 10) : '?');
        },

        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.form = {
                query_pattern: '', match_type: 'exact', action: 'pin', priority: 0,
                productIdsStr: '', boost_factor: 1.5, redirect_url: '', banner_html: '',
                starts_at: '', ends_at: '', is_active: true,
            };
            this.resetPickers();
        },
    };
}
</script>
@endsection
