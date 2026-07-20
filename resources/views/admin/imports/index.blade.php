@extends('admin.layout')

@section('title', 'Product Import')
@section('page-title', 'Product Import')

@section('content')
<div x-data="importsPage()" x-init="init()">

    <!-- Global status bar -->
    <div x-show="statusMsg"
         :class="statusErr ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'"
         class="mb-4 px-4 py-2 border rounded text-sm"
         x-text="statusMsg"></div>

    <!-- Per-store cards -->
    <div class="space-y-4">
        <template x-for="row in stores" :key="row.id">
            <div class="bg-white rounded-lg shadow-sm border border-gray-100">

                <!-- Card header -->
                <div class="px-5 py-4 flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-800" x-text="row.name"></span>
                            <span class="font-mono text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded"
                                  x-text="row.code"></span>
                            <template x-if="row.deleted_at">
                                <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">deleted</span>
                            </template>
                            <template x-if="row.has_pending_files">
                                <span class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded">files pending</span>
                            </template>
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            Upload folder: <span class="font-mono" x-text="row.folder"></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button @click="toggleHistory(row)"
                                class="text-xs text-indigo-600 hover:underline">
                            <span x-text="expanded === row.id ? 'Hide history' : 'History'"></span>
                        </button>

                        <!-- Cancel button — visible only while a job is running -->
                        <template x-if="row.last_import?.status === 'running' || pendingStoreIds.includes(row.id)">
                            <button @click="cancelImport(row)"
                                    :disabled="cancelling[row.id]"
                                    :class="cancelling[row.id] ? 'bg-gray-300 text-gray-500 cursor-wait' : 'bg-red-600 text-white hover:bg-red-700 cursor-pointer'"
                                    class="px-3 py-1.5 rounded text-xs transition-colors">
                                <span x-text="cancelling[row.id] ? 'Cancelling…' : 'Cancel'"></span>
                            </button>
                        </template>

                        <button @click="runImport(row)"
                                :disabled="running[row.id] || row.last_import?.status === 'running' || pendingStoreIds.includes(row.id) || !row.has_pending_files"
                                :title="!row.has_pending_files ? 'No CSV files found in the import folder' : ''"
                                :class="(running[row.id] || row.last_import?.status === 'running' || pendingStoreIds.includes(row.id))
                                    ? 'bg-yellow-500 text-white cursor-wait'
                                    : (!row.has_pending_files
                                        ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                        : 'bg-blue-600 text-white hover:bg-blue-700 cursor-pointer')"
                                class="px-3 py-1.5 rounded text-xs transition-colors">
                            <span x-text="(running[row.id] || row.last_import?.status === 'running' || pendingStoreIds.includes(row.id))
                                ? 'Running…'
                                : (row.has_pending_files ? 'Run Import' : 'No Files')"></span>
                        </button>
                    </div>
                </div>

                <!-- Last import summary -->
                <template x-if="row.last_import">
                    <div class="border-t border-gray-50 px-5 py-3 bg-gray-50/50">
                        <div class="flex flex-wrap gap-4 text-xs">
                            <div>
                                <span class="text-gray-500">Last run:</span>
                                <span class="text-gray-700 ml-1" x-text="formatDate(row.last_import.started_at)"></span>
                                <span :class="{
                                        'text-green-600 bg-green-50':   row.last_import.status === 'completed',
                                        'text-red-600 bg-red-50':       row.last_import.status === 'failed',
                                        'text-yellow-600 bg-yellow-50': row.last_import.status === 'running',
                                        'text-orange-600 bg-orange-50': row.last_import.status === 'cancelled',
                                      }"
                                      class="ml-2 px-1.5 py-0.5 rounded font-medium"
                                      x-text="row.last_import.status"></span>
                                <template x-if="row.last_import.duration_seconds !== null">
                                    <span class="text-gray-400 ml-1" x-text="'(' + row.last_import.duration_seconds + 's)'"></span>
                                </template>
                                <!-- Current step next to the running status: phase name, or live % during indexing -->
                                <template x-if="row.last_import.status === 'running' && row.last_import.phase === 'indexing' && row.last_import.products_to_index > 0">
                                    <span class="ml-2 text-blue-600 font-medium"
                                          x-text="'indexing ' + Math.min(100, Math.round(row.last_import.products_indexed / row.last_import.products_to_index * 100)) + '%'"></span>
                                </template>
                                <template x-if="row.last_import.status === 'running' && row.last_import.phase && row.last_import.phase !== 'indexing' && row.last_import.phase !== 'done'">
                                    <span class="ml-2 text-blue-600 font-medium" x-text="row.last_import.phase"></span>
                                </template>
                            </div>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-6 text-xs">
                            <!-- Products -->
                            <div>
                                <span class="text-gray-500 font-medium">Products</span>
                                <span class="ml-2 text-green-600">
                                    +<span x-text="row.last_import.products_created"></span> created
                                </span>
                                <span class="ml-1 text-blue-600">
                                    ~<span x-text="row.last_import.products_updated"></span> updated
                                </span>
                                <template x-if="row.last_import.products_failed > 0">
                                    <span class="ml-1 text-red-600">
                                        <span x-text="row.last_import.products_failed"></span> failed
                                    </span>
                                </template>
                            </div>
                            <!-- Categories -->
                            <div>
                                <span class="text-gray-500 font-medium">Categories</span>
                                <span class="ml-2 text-green-600">
                                    +<span x-text="row.last_import.categories_created"></span> created
                                </span>
                                <span class="ml-1 text-blue-600">
                                    ~<span x-text="row.last_import.categories_updated"></span> updated
                                </span>
                                <template x-if="row.last_import.categories_failed > 0">
                                    <span class="ml-1 text-red-600">
                                        <span x-text="row.last_import.categories_failed"></span> failed
                                    </span>
                                </template>
                            </div>
                        </div>

                        <!-- Duplicate SKUs -->
                        <template x-if="row.last_import.duplicate_skus && row.last_import.duplicate_skus.length > 0">
                            <div class="mt-2">
                                <div class="text-xs text-amber-700 font-medium">
                                    Duplicate SKUs in file (last value kept):
                                </div>
                                <div class="mt-1 text-xs font-mono text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 break-all"
                                     x-text="row.last_import.duplicate_skus.join(', ')"></div>
                            </div>
                        </template>

                        <!-- Errors -->
                        <template x-if="row.last_import.errors && row.last_import.errors.length > 0">
                            <div class="mt-2">
                                <div class="text-xs text-red-600 font-medium">Errors:</div>
                                <ul class="mt-1 text-xs text-red-600 list-disc list-inside space-y-0.5">
                                    <template x-for="err in row.last_import.errors" :key="err">
                                        <li x-text="err"></li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="!row.last_import">
                    <div class="border-t border-gray-50 px-5 py-2 text-xs text-gray-400 italic bg-gray-50/50">
                        No imports yet.
                    </div>
                </template>

                <!-- Expandable history table -->
                <div x-show="expanded === row.id" x-cloak class="border-t border-gray-100">
                    <div class="px-5 py-3">
                        <div class="text-xs font-medium text-gray-500 mb-2">Import History (last 20)</div>
                        <template x-if="!history[row.id]">
                            <div class="text-xs text-gray-400">Loading…</div>
                        </template>
                        <template x-if="history[row.id] && history[row.id].length === 0">
                            <div class="text-xs text-gray-400 italic">No history.</div>
                        </template>
                        <template x-if="history[row.id] && history[row.id].length > 0">
                            <table class="w-full text-xs">
                                <thead class="text-gray-400 uppercase border-b border-gray-100">
                                    <tr>
                                        <th class="text-left py-1.5 pr-4 font-medium">Started</th>
                                        <th class="text-left py-1.5 pr-4 font-medium">Status</th>
                                        <th class="text-right py-1.5 pr-4 font-medium">Products</th>
                                        <th class="text-right py-1.5 pr-4 font-medium">Categories</th>
                                        <th class="text-right py-1.5 font-medium">Duration</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <template x-for="log in history[row.id]" :key="log.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-1.5 pr-4" x-text="formatDate(log.started_at)"></td>
                                            <td class="py-1.5 pr-4">
                                                <span :class="{
                                                    'text-green-600':  log.status === 'completed',
                                                    'text-red-600':    log.status === 'failed',
                                                    'text-yellow-600': log.status === 'running',
                                                    'text-orange-600': log.status === 'cancelled',
                                                }" x-text="log.status"></span>
                                            </td>
                                            <td class="py-1.5 pr-4 text-right">
                                                <span class="text-green-600" x-text="'+' + log.products_created"></span>
                                                <span class="text-gray-400 mx-0.5">/</span>
                                                <span class="text-blue-600" x-text="'~' + log.products_updated"></span>
                                                <template x-if="log.products_failed > 0">
                                                    <span class="text-red-500 ml-0.5" x-text="'/' + log.products_failed + 'f'"></span>
                                                </template>
                                            </td>
                                            <td class="py-1.5 pr-4 text-right">
                                                <span class="text-green-600" x-text="'+' + log.categories_created"></span>
                                                <span class="text-gray-400 mx-0.5">/</span>
                                                <span class="text-blue-600" x-text="'~' + log.categories_updated"></span>
                                            </td>
                                            <td class="py-1.5 text-right text-gray-500"
                                                x-text="log.duration_seconds !== null ? log.duration_seconds + 's' : '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>
                    </div>
                </div>

            </div>
        </template>

        <div x-show="stores.length === 0" class="text-center py-12 text-gray-400">
            No stores found.
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function importsPage() {
    return {
        stores:          [],
        running:         {},    // { store_id: bool } — run button spinner
        cancelling:      {},    // { store_id: bool } — cancel button spinner
        pendingStoreIds: [],    // store IDs we dispatched but haven't seen finish yet
        pendingPrevLogIds: {},  // { store_id: log_id } — log ID at dispatch time, to detect new log
        expanded:        null,
        history:        {},
        statusMsg:      '',
        statusErr:      false,
        pollTimer:      null,

        async init() {
            await this.fetchStores();

            // Poll every 4 seconds when something is in-flight.
            // Conditions that keep polling active:
            //   1. We just dispatched a job (pendingStoreIds not empty) — worker may not have started yet
            //   2. DB shows status === 'running' — worker is actively processing
            this.pollTimer = setInterval(async () => {
                const anyRunning     = this.stores.some(s => s.last_import?.status === 'running');
                const anyPendingFiles = this.stores.some(s => s.has_pending_files);
                if (this.pendingStoreIds.length === 0 && !anyRunning && !anyPendingFiles) return;

                await this.fetchStores();

                // Drop a store from the pending list once the DB confirms the NEW job is done.
                // Use the log ID saved at dispatch time: if the current log ID hasn't changed,
                // the new job's ImportLog hasn't been created yet — keep waiting.
                this.pendingStoreIds = this.pendingStoreIds.filter(storeId => {
                    const s = this.stores.find(s => s.id === storeId);
                    if (!s) return false;
                    const currentLogId = s.last_import?.id ?? null;
                    const prevLogId    = this.pendingPrevLogIds[storeId] ?? null;
                    // Same log as before dispatch (or no log) — new ImportLog not yet created
                    if (currentLogId === prevLogId) return true;
                    // New log exists — only remove once it reaches a terminal state
                    const status = s.last_import?.status;
                    return status !== 'completed' && status !== 'failed' && status !== 'cancelled';
                });

                // Refresh the open history panel so it picks up the new row
                if (this.expanded !== null && !this.pendingStoreIds.includes(this.expanded)) {
                    const data = await apiRequest('GET', `/api/admin/imports/${this.expanded}/history`);
                    this.history = { ...this.history, [this.expanded]: data };
                }
            }, 4000);
        },

        async fetchStores() {
            try {
                const data = await apiRequest('GET', '/api/admin/imports');
                this.stores = data;
            } catch {}
        },

        async runImport(row) {
            this.running  = { ...this.running, [row.id]: true };
            this.statusMsg = '';
            this.statusErr = false;
            try {
                const res = await apiRequest('POST', `/api/admin/imports/${row.id}/run`);
                this.statusMsg = res.message;

                // Add to pending so the poll loop keeps running until the job finishes.
                // Record the current log ID so the poll knows when a NEW log has been created.
                if (!this.pendingStoreIds.includes(row.id)) {
                    this.pendingPrevLogIds = { ...this.pendingPrevLogIds, [row.id]: row.last_import?.id ?? null };
                    this.pendingStoreIds   = [...this.pendingStoreIds, row.id];
                }

                await this.fetchStores();
            } catch (e) {
                this.statusErr = true;
                try { this.statusMsg = JSON.parse(e.message).message || e.message; }
                catch { this.statusMsg = e.message || 'Could not start import.'; }
            } finally {
                this.running = { ...this.running, [row.id]: false };
            }
        },

        async cancelImport(row) {
            this.cancelling  = { ...this.cancelling, [row.id]: true };
            this.statusMsg   = '';
            this.statusErr   = false;
            try {
                const res = await apiRequest('POST', `/api/admin/imports/${row.id}/cancel`);
                this.statusMsg = res.message;
                // Remove from pending so the poll loop stops
                this.pendingStoreIds = this.pendingStoreIds.filter(id => id !== row.id);
                await this.fetchStores();
            } catch (e) {
                this.statusErr = true;
                try { this.statusMsg = JSON.parse(e.message).message || e.message; }
                catch { this.statusMsg = e.message || 'Could not cancel import.'; }
            } finally {
                this.cancelling = { ...this.cancelling, [row.id]: false };
            }
        },

        async toggleHistory(row) {
            if (this.expanded === row.id) {
                this.expanded = null;
                return;
            }
            this.expanded = row.id;
            const data = await apiRequest('GET', `/api/admin/imports/${row.id}/history`);
            this.history = { ...this.history, [row.id]: data };
        },

        formatDate(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
@endsection
