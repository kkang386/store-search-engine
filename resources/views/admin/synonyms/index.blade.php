@extends('admin.layout')

@section('title', 'Synonyms')
@section('page-title', 'Synonym Management')

@section('content')
<div x-data="synonymsPage()" x-init="init()">

    <div class="flex justify-end gap-2 mb-4">
        <button @click="showModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + Add Synonym
        </button>
        <a href="/api/admin/search/synonyms/export" class="border border-gray-300 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 bg-white">
            Export CSV
        </a>
    </div>

    <!-- Import Zone -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-center gap-4">
        <div class="flex-1">
            <div class="font-medium text-blue-800 text-sm">Bulk Import</div>
            <div class="text-blue-600 text-xs">Upload a CSV with columns: type, terms, from_term, to_term</div>
        </div>
        <form action="{{ route('admin.synonyms.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".csv" class="text-sm" required>
            <button type="submit" class="ml-2 bg-blue-600 text-white px-3 py-1.5 rounded text-sm">Import</button>
        </form>
    </div>

    <!-- Search + Filters -->
    <div class="bg-white rounded-lg shadow-sm mb-4 p-4 flex gap-4">
        <input x-model="search" @input.debounce.300ms="fetchSynonyms()"
               placeholder="Filter synonyms..."
               class="flex-1 border rounded px-3 py-2 text-sm">
        <select x-model="typeFilter" @change="fetchSynonyms()" class="border rounded px-3 py-2 text-sm">
            <option value="">All types</option>
            <option value="equivalent">Equivalent</option>
            <option value="one_way">One-way</option>
        </select>
    </div>

    <!-- Synonyms Table -->
    <div class="bg-white rounded-lg shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Type</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Terms</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="syn in synonyms" :key="syn.id">
                    <tr class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <span :class="syn.type === 'equivalent' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                                  class="px-2 py-0.5 rounded text-xs font-medium"
                                  x-text="syn.type"></span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs" x-text="formatSynonym(syn)"></td>
                        <td class="px-4 py-3">
                            <span :class="syn.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="px-2 py-0.5 rounded text-xs"
                                  x-text="syn.is_active ? 'Active' : 'Inactive'"></span>
                        </td>
                        <td class="px-4 py-3 flex gap-2 justify-end">
                            <button @click="editSynonym(syn)"
                                    class="text-blue-600 hover:text-blue-800 text-xs">Edit</button>
                            <button @click="deleteSynonym(syn.id)"
                                    class="text-red-600 hover:text-red-800 text-xs">Delete</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="synonyms.length === 0">
                    <td colspan="4" class="px-4 py-8 text-center text-gray-400">No synonyms found</td>
                </tr>
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
            <span x-text="`Showing ${synonyms.length} of ${total}`"></span>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-4" x-text="editingId ? 'Edit Synonym' : 'Add Synonym'"></h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select x-model="form.type" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="equivalent">Equivalent (bidirectional)</option>
                        <option value="one_way">One-way (A → B)</option>
                    </select>
                </div>

                <div x-show="form.type === 'equivalent'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Terms (comma-separated)</label>
                    <input x-model="form.termsStr" placeholder="tv, television, telly"
                           class="w-full border rounded px-3 py-2 text-sm">
                </div>

                <template x-if="form.type === 'one_way'">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                            <input x-model="form.from_term" placeholder="iphone" class="w-full border rounded px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                            <input x-model="form.to_term" placeholder="apple iphone" class="w-full border rounded px-3 py-2 text-sm">
                        </div>
                    </div>
                </template>

                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" x-model="form.is_active">
                        Active
                    </label>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button @click="saveSynonym()"
                        class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700">
                    Save
                </button>
                <button @click="closeModal()"
                        class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function synonymsPage() {
    return {
        synonyms: [], total: 0, search: '', typeFilter: '',
        showModal: false, editingId: null,
        form: { type: 'equivalent', termsStr: '', from_term: '', to_term: '', is_active: true },

        async init() { await this.fetchSynonyms(); },

        async fetchSynonyms() {
            const data = await apiRequest('GET', `/api/admin/search/synonyms?q=${this.search}&type=${this.typeFilter}`);
            this.synonyms = data.data || [];
            this.total = data.total || 0;
        },

        formatSynonym(syn) {
            if (syn.type === 'one_way') return `${syn.from_term} → ${syn.to_term}`;
            return (syn.terms || []).join(' ⟷ ');
        },

        editSynonym(syn) {
            this.editingId = syn.id;
            this.form = {
                type: syn.type,
                termsStr: (syn.terms || []).join(', '),
                from_term: syn.from_term || '',
                to_term: syn.to_term || '',
                is_active: syn.is_active,
            };
            this.showModal = true;
        },

        async saveSynonym() {
            const data = {
                type: this.form.type,
                is_active: this.form.is_active,
            };

            if (this.form.type === 'equivalent') {
                data.terms = this.form.termsStr.split(',').map(t => t.trim()).filter(Boolean);
            } else {
                data.from_term = this.form.from_term;
                data.to_term = this.form.to_term;
            }

            const url = this.editingId ? `/api/admin/search/synonyms/${this.editingId}` : '/api/admin/search/synonyms';
            await apiRequest(this.editingId ? 'PUT' : 'POST', url, data);
            this.closeModal();
            await this.fetchSynonyms();
        },

        async deleteSynonym(id) {
            if (!confirm('Delete this synonym?')) return;
            await apiRequest('DELETE', `/api/admin/search/synonyms/${id}`);
            await this.fetchSynonyms();
        },

        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.form = { type: 'equivalent', termsStr: '', from_term: '', to_term: '', is_active: true };
        },
    };
}
</script>
@endsection
