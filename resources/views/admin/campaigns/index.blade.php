@extends('admin.layout')

@section('title', 'Search Campaigns')
@section('page-title', 'Search Campaigns')

@section('content')
<div x-data="campaignsPage()" x-init="init()">

    <div class="flex justify-end mb-4">
        <button @click="showModal = true; editingId = null; form = { name: '', type: 'boost', patternsStr: '', skusStr: '', boost_factor: 1.5, starts_at: '', ends_at: '', is_active: true }"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            + New Campaign
        </button>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm border-l-4 border-green-500">
            <div class="text-xs text-gray-500">Active Campaigns</div>
            <div class="text-2xl font-bold text-gray-800 mt-1" x-text="activeCampaigns.length"></div>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border-l-4 border-blue-500">
            <div class="text-xs text-gray-500">Scheduled</div>
            <div class="text-2xl font-bold text-gray-800 mt-1" x-text="scheduledCampaigns.length"></div>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm border-l-4 border-gray-300">
            <div class="text-xs text-gray-500">Inactive</div>
            <div class="text-2xl font-bold text-gray-800 mt-1" x-text="campaigns.length - activeCampaigns.length - scheduledCampaigns.length"></div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Campaign</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Type</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Duration</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Patterns</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="campaign in campaigns" :key="campaign.id">
                    <tr class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800" x-text="campaign.name"></div>
                            <div class="text-xs text-gray-400 mt-0.5" x-text="campaign.description"></div>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="{
                                'bg-green-100 text-green-700': campaign.type === 'boost',
                                'bg-blue-100 text-blue-700': campaign.type === 'banner',
                                'bg-red-100 text-red-700': campaign.type === 'exclusion',
                                'bg-yellow-100 text-yellow-700': campaign.type === 'synonym',
                            }" class="px-2 py-0.5 rounded text-xs font-medium" x-text="campaign.type"></span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <span x-text="campaign.starts_at?.slice(0, 10)"></span>
                            <span> – </span>
                            <span x-text="campaign.ends_at?.slice(0, 10)"></span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="isRunning(campaign) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                  class="px-2 py-0.5 rounded text-xs" x-text="isRunning(campaign) ? 'Running' : 'Inactive'"></span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500" x-text="(campaign.query_patterns || []).join(', ')"></td>
                        <td class="px-4 py-3 flex gap-2 justify-end">
                            <button @click="editCampaign(campaign)" class="text-blue-600 text-xs">Edit</button>
                            <button @click="deleteCampaign(campaign.id)" class="text-red-600 text-xs">Delete</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="campaigns.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No campaigns</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-4" x-text="editingId ? 'Edit Campaign' : 'New Campaign'"></h2>
            <div class="space-y-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input x-model="form.name" class="w-full border rounded px-3 py-2 text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select x-model="form.type" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="boost">Boost</option>
                        <option value="banner">Banner</option>
                        <option value="exclusion">Exclusion</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Query Patterns (comma-separated)</label>
                    <input x-model="form.patternsStr" placeholder="camera, dslr, mirrorless" class="w-full border rounded px-3 py-2 text-sm"></div>
                <div x-show="form.type === 'boost'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Boost Factor</label>
                    <input x-model.number="form.boost_factor" type="number" step="0.1" class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div x-show="form.type === 'boost'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">SKUs (comma-separated)</label>
                    <input x-model="form.skusStr" placeholder="SKU-1001, SKU-1002, SKU-1003" class="w-full border rounded px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium mb-1">Starts At</label>
                        <input x-model="form.starts_at" type="datetime-local" class="w-full border rounded px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium mb-1">Ends At</label>
                        <input x-model="form.ends_at" type="datetime-local" class="w-full border rounded px-3 py-2 text-sm"></div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="form.is_active"> Active</label>
            </div>
            <div class="flex gap-3 mt-6">
                <button @click="saveCampaign()" class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm">Save</button>
                <button @click="closeModal()" class="flex-1 border border-gray-300 py-2 rounded-lg text-sm">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function campaignsPage() {
    return {
        campaigns: [], showModal: false, editingId: null,
        form: { name: '', type: 'boost', patternsStr: '', skusStr: '', boost_factor: 1.5, starts_at: '', ends_at: '', is_active: true },

        get activeCampaigns() { return this.campaigns.filter(c => this.isRunning(c)); },
        get scheduledCampaigns() { return this.campaigns.filter(c => c.is_active && new Date(c.starts_at) > new Date()); },

        isRunning(c) { return c.is_active && new Date(c.starts_at) <= new Date() && new Date(c.ends_at) >= new Date(); },

        async init() { await this.fetchCampaigns(); },
        async fetchCampaigns() {
            const res = await fetch(`/api/admin/search/campaigns?store_id=${storeId}`, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
            const data = await res.json(); this.campaigns = data.data || [];
        },
        editCampaign(c) {
            this.editingId = c.id;
            this.form = { name: c.name, type: c.type, patternsStr: (c.query_patterns||[]).join(', '), skusStr: (c.skus||[]).join(', '), boost_factor: c.boost_factor||1.5, starts_at: c.starts_at?.slice(0,16), ends_at: c.ends_at?.slice(0,16), is_active: c.is_active };
            this.showModal = true;
        },
        async saveCampaign() {
            const data = { ...this.form, store_id: parseInt(storeId)||null, query_patterns: this.form.patternsStr.split(',').map(s=>s.trim()).filter(Boolean), skus: this.form.skusStr.split(',').map(s=>s.trim()).filter(Boolean) };
            delete data.patternsStr; delete data.skusStr;
            const url = this.editingId ? `/api/admin/search/campaigns/${this.editingId}` : '/api/admin/search/campaigns';
            await apiRequest(this.editingId ? 'PUT' : 'POST', url, data);
            this.closeModal(); await this.fetchCampaigns();
        },
        async deleteCampaign(id) { if (!confirm('Delete?')) return; await apiRequest('DELETE', `/api/admin/search/campaigns/${id}`); await this.fetchCampaigns(); },
        closeModal() { this.showModal = false; this.editingId = null; this.form = { name: '', type: 'boost', patternsStr: '', skusStr: '', boost_factor: 1.5, starts_at: '', ends_at: '', is_active: true }; },
    };
}
</script>
@endsection
