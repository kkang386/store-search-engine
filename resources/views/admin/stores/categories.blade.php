@extends('admin.layout')

@section('title', $store->name . ' — Categories')
@section('page-title', $store->name . ' — Category Assignment')

@section('content')
<div x-data="storeCategoriesPage({{ $store->id }})" x-init="init()">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('admin.stores.index') }}" class="hover:text-blue-600">Stores</a>
        <span>/</span>
        <span class="text-gray-800 font-medium">{{ $store->name }}</span>
        <span>/</span>
        <span class="text-gray-800">Categories</span>
    </div>

    <!-- Toolbar -->
    <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <input x-model="search" type="text" placeholder="Filter categories…"
                   class="border rounded px-3 py-1.5 text-sm w-64 focus:outline-none focus:ring-1 focus:ring-blue-500">
            <span class="text-sm text-gray-500">
                <span x-text="linkedCount"></span> of <span x-text="categories.length"></span> linked
            </span>
        </div>
        <div class="flex items-center gap-2">
            <button @click="selectAll()" class="text-xs text-blue-600 hover:underline">Select all</button>
            <span class="text-gray-300">|</span>
            <button @click="selectNone()" class="text-xs text-gray-500 hover:underline">Deselect all</button>
            <button @click="save()" :disabled="saving"
                    class="ml-4 bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                <span x-text="saving ? 'Saving…' : 'Save Changes'"></span>
            </button>
        </div>
    </div>

    <!-- Success / error banner -->
    <div x-show="saved" class="mb-4 px-4 py-2 bg-green-50 border border-green-200 rounded text-green-700 text-sm">
        Categories saved successfully.
    </div>
    <div x-show="saveError" class="mb-4 px-4 py-2 bg-red-50 border border-red-200 rounded text-red-600 text-sm"
         x-text="saveError"></div>

    <!-- Category tree -->
    <div class="bg-white rounded-lg shadow-sm">
        <div class="divide-y divide-gray-50">
            <template x-for="cat in filtered" :key="cat.id">
                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer"
                       :style="`padding-left: ${16 + cat.depth * 24}px`">
                    <input type="checkbox"
                           :checked="state[cat.id]?.linked"
                           @change="toggle(cat.id, $event.target.checked)"
                           :disabled="!cat.is_active"
                           class="rounded text-blue-600">
                    <span class="flex-1 text-sm" :class="cat.is_active ? 'text-gray-800' : 'text-gray-400'">
                        <span x-show="cat.depth > 0" class="text-gray-300 mr-1">└</span>
                        <span x-text="cat.name"></span>
                        <span class="text-xs text-gray-400 ml-1 font-mono" x-text="'/' + cat.slug"></span>
                    </span>
                    <span x-show="!cat.is_active"
                          class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">inactive globally</span>
                    <span x-show="cat.is_active && state[cat.id]?.linked"
                          class="text-xs text-green-600 bg-green-50 px-1.5 py-0.5 rounded">linked</span>
                </label>
            </template>
            <div x-show="filtered.length === 0" class="px-4 py-8 text-center text-gray-400 text-sm">
                No categories match your filter.
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function storeCategoriesPage(storeId) {
    return {
        storeId,
        categories: [],
        state: {},     // { [category_id]: { linked: bool, sort_order: int } }
        search: '',
        saving: false,
        saved: false,
        saveError: '',

        get filtered() {
            const q = this.search.toLowerCase();
            return q
                ? this.categories.filter(c => c.name.toLowerCase().includes(q) || c.slug.includes(q))
                : this.categories;
        },

        get linkedCount() {
            return Object.values(this.state).filter(s => s.linked).length;
        },

        async init() {
            const res = await fetch(`/api/admin/stores/${this.storeId}/categories`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await res.json();
            this.categories = data.categories;
            this.state = {};
            for (const cat of data.categories) {
                this.state[cat.id] = { linked: cat.linked, sort_order: cat.sort_order ?? 0 };
            }
        },

        toggle(id, checked) {
            this.state[id] = { ...this.state[id], linked: checked };
        },

        selectAll() {
            for (const cat of this.categories) {
                if (cat.is_active) this.state[cat.id] = { ...this.state[cat.id], linked: true };
            }
        },

        selectNone() {
            for (const id of Object.keys(this.state)) {
                this.state[id] = { ...this.state[id], linked: false };
            }
        },

        async save() {
            this.saving = true;
            this.saved = false;
            this.saveError = '';
            try {
                const payload = Object.entries(this.state)
                    .filter(([, s]) => s.linked)
                    .map(([id, s]) => ({
                        category_id: parseInt(id),
                        is_active: true,
                        sort_order: s.sort_order ?? 0,
                    }));

                await apiRequest('PUT', `/api/admin/stores/${this.storeId}/categories`, { categories: payload });
                this.saved = true;
                setTimeout(() => this.saved = false, 3000);
            } catch (e) {
                try { this.saveError = Object.values(JSON.parse(e.message).errors)[0][0]; }
                catch { this.saveError = e.message || 'Could not save.'; }
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endsection
