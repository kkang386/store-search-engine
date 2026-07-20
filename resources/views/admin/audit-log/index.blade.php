@extends('admin.layout')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')

@section('content')
<div x-data="auditLogPage()" x-init="init()">

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-4 flex flex-wrap gap-3">
        <select x-model="filters.entity_type" @change="fetchLogs()" class="border rounded px-3 py-2 text-sm">
            <option value="">All entities</option>
            <option value="App\Models\QueryRule">Query Rules</option>
            <option value="App\Models\Synonym">Synonyms</option>
            <option value="App\Models\SearchCampaign">Campaigns</option>
        </select>
        <select x-model="filters.action" @change="fetchLogs()" class="border rounded px-3 py-2 text-sm">
            <option value="">All actions</option>
            <option value="create">Create</option>
            <option value="update">Update</option>
            <option value="delete">Delete</option>
            <option value="restore">Restore</option>
        </select>
        <input x-model="filters.from" @change="fetchLogs()" type="date" class="border rounded px-3 py-2 text-sm">
        <input x-model="filters.to" @change="fetchLogs()" type="date" class="border rounded px-3 py-2 text-sm">
    </div>

    <!-- Log Table -->
    <div class="bg-white rounded-lg shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Time</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">User</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Action</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Entity</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Changes</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="log in logs" :key="log.id">
                    <tr class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3 text-xs text-gray-500" x-text="log.created_at?.slice(0, 16)"></td>
                        <td class="px-4 py-3 text-xs" x-text="log.user_email || 'System'"></td>
                        <td class="px-4 py-3">
                            <span :class="{
                                'bg-green-100 text-green-700': log.action === 'create',
                                'bg-blue-100 text-blue-700': log.action === 'update',
                                'bg-red-100 text-red-700': log.action === 'delete',
                                'bg-yellow-100 text-yellow-700': log.action === 'restore',
                            }" class="px-2 py-0.5 rounded text-xs font-medium capitalize" x-text="log.action"></span>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono" x-text="log.entity_type?.split('\\').pop() + ' #' + log.entity_id"></td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <span x-show="log.new_values" x-text="Object.keys(log.new_values || {}).join(', ')"></span>
                        </td>
                        <td class="px-4 py-3">
                            <button @click="viewDetails(log)" class="text-blue-600 text-xs hover:underline">Details</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="logs.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No audit log entries</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Details Modal -->
    <div x-show="selectedLog" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] overflow-y-auto p-6" @click.stop>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Change Details</h3>
                <button @click="selectedLog = null" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <template x-if="selectedLog">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs font-medium text-gray-500 mb-1">Previous Values</div>
                            <pre class="text-xs bg-red-50 p-3 rounded overflow-auto" x-text="JSON.stringify(selectedLog.old_values, null, 2) || 'none'"></pre>
                        </div>
                        <div>
                            <div class="text-xs font-medium text-gray-500 mb-1">New Values</div>
                            <pre class="text-xs bg-green-50 p-3 rounded overflow-auto" x-text="JSON.stringify(selectedLog.new_values, null, 2) || 'none'"></pre>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function auditLogPage() {
    return {
        logs: [], selectedLog: null,
        filters: { entity_type: '', action: '', from: '', to: '' },

        async init() { await this.fetchLogs(); },

        async fetchLogs() {
            const params = new URLSearchParams(Object.fromEntries(Object.entries(this.filters).filter(([,v]) => v)));
            const res = await fetch(`/api/admin/search/audit-log?${params}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            const data = await res.json();
            this.logs = data.data || [];
        },

        viewDetails(log) { this.selectedLog = log; },
    };
}
</script>
@endsection
