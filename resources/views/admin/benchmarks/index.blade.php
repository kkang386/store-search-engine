@extends('admin.layout')

@section('title', 'Benchmarks')
@section('page-title', 'Benchmarks')

@section('content')
<div x-data="benchmarkPage()" x-init="init()">

    <!-- Top bar: run button + dataset controls -->
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <button @click="runBenchmark()"
                    :disabled="running"
                    class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                <svg x-show="!running" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <svg x-show="running" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span x-text="running ? 'Running…' : 'Run Benchmark'"></span>
            </button>
            <span x-show="runError" class="text-red-500 text-sm" x-text="runError"></span>
        </div>

        <!-- Dataset management -->
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">Dataset:</span>
            <a href="/api/admin/benchmarks/dataset"
               class="border border-gray-300 bg-white px-3 py-1.5 rounded-lg text-sm hover:bg-gray-50 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
            </a>
            <label class="cursor-pointer border border-gray-300 bg-white px-3 py-1.5 rounded-lg text-sm hover:bg-gray-50 flex items-center gap-1.5">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
                </svg>
                Upload
                <input type="file" accept=".json" class="hidden" @change="uploadDataset($event)">
            </label>
            <span x-show="uploadMsg" :class="uploadError ? 'text-red-500' : 'text-green-600'"
                  class="text-sm" x-text="uploadMsg"></span>
        </div>
    </div>

    <!-- Latest run metric cards -->
    <template x-if="latest">
        <div>
            <div class="text-xs text-gray-500 mb-2">
                Latest run: <span class="font-mono" x-text="latest.started_at"></span>
                <span x-show="latest.git_commit" class="ml-2 font-mono text-gray-400" x-text="'@ ' + latest.git_commit"></span>
                <span x-show="latest.duration_seconds !== null" class="ml-2" x-text="'(' + latest.duration_seconds + 's)'"></span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
                <div class="bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-500">
                    <div class="text-xs text-gray-500">Precision@5</div>
                    <div class="text-xl font-bold text-gray-800 mt-1" x-text="latest.metrics.precision_at_5"></div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm border-l-4 border-blue-400">
                    <div class="text-xs text-gray-500">Precision@10</div>
                    <div class="text-xl font-bold text-gray-800 mt-1" x-text="latest.metrics.precision_at_10"></div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm border-l-4 border-indigo-500">
                    <div class="text-xs text-gray-500">Recall@10</div>
                    <div class="text-xl font-bold text-gray-800 mt-1" x-text="latest.metrics.recall_at_10"></div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm border-l-4 border-purple-500">
                    <div class="text-xs text-gray-500">NDCG@10</div>
                    <div class="text-xl font-bold text-gray-800 mt-1" x-text="latest.metrics.ndcg_at_10"></div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm border-l-4 border-green-500">
                    <div class="text-xs text-gray-500">MRR</div>
                    <div class="text-xl font-bold text-gray-800 mt-1" x-text="latest.metrics.mrr"></div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm border-l-4 border-yellow-500">
                    <div class="text-xs text-gray-500">P95 Latency</div>
                    <div class="text-xl font-bold text-gray-800 mt-1" x-text="latest.latency.p95_ms + 'ms'"></div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm border-l-4 border-red-400">
                    <div class="text-xs text-gray-500">Zero Results</div>
                    <div class="text-xl font-bold text-gray-800 mt-1" x-text="latest.metrics.zero_result_rate + '%'"></div>
                </div>
            </div>
        </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Run history -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-4 py-3 border-b border-gray-100 font-medium text-sm text-gray-700">Run History</div>
                <div class="divide-y divide-gray-50">
                    <template x-for="run in history" :key="run.id">
                        <div @click="selectRun(run)"
                             class="px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
                             :class="selectedRun?.id === run.id ? 'bg-blue-50 border-l-2 border-blue-500' : ''">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-mono text-gray-500" x-text="run.started_at?.slice(0, 16)"></span>
                                <span :class="run.status === 'completed' ? 'bg-green-100 text-green-700' : run.status === 'failed' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-700'"
                                      class="px-1.5 py-0.5 rounded text-xs" x-text="run.status"></span>
                            </div>
                            <div class="mt-1 flex gap-3 text-xs text-gray-600">
                                <span x-text="'P@10: ' + run.metrics.precision_at_10"></span>
                                <span x-text="'NDCG: ' + run.metrics.ndcg_at_10"></span>
                                <span x-text="'P95: ' + run.latency.p95_ms + 'ms'"></span>
                            </div>
                            <div x-show="run.git_commit" class="mt-0.5 text-xs text-gray-400 font-mono" x-text="run.git_commit"></div>
                        </div>
                    </template>
                    <div x-show="history.length === 0" class="px-4 py-8 text-center text-gray-400 text-sm">
                        No runs yet. Click "Run Benchmark" to start.
                    </div>
                </div>
            </div>
        </div>

        <!-- HTML report iframe -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="font-medium text-sm text-gray-700">
                        <span x-show="!selectedRun">Select a run to view report</span>
                        <span x-show="selectedRun">
                            Report — <span class="font-mono text-xs text-gray-500" x-text="selectedRun?.run_id?.slice(0,8)"></span>
                        </span>
                    </span>
                    <a x-show="selectedRun?.has_report"
                       :href="'/admin/benchmarks/' + selectedRun?.run_id + '/report'"
                       target="_blank"
                       class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                        Open full page
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
                <div x-show="!selectedRun" class="flex items-center justify-center h-64 text-gray-400 text-sm">
                    No report selected
                </div>
                <div x-show="selectedRun && !selectedRun.has_report" class="flex items-center justify-center h-64 text-gray-400 text-sm">
                    Report file not found for this run.
                </div>
                <iframe x-show="selectedRun?.has_report"
                        :src="selectedRun ? '/admin/benchmarks/' + selectedRun.run_id + '/report' : ''"
                        class="w-full border-0"
                        style="height: 600px;"
                        :key="selectedRun?.run_id">
                </iframe>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
function benchmarkPage() {
    return {
        history: [],
        latest: null,
        selectedRun: null,
        running: false,
        runError: '',
        uploadMsg: '',
        uploadError: false,

        async init() {
            await this.fetchHistory();
        },

        async fetchHistory() {
            const res = await fetch('/api/admin/benchmarks/history', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            if (!res.ok) return;
            this.history = await res.json();
            const completed = this.history.filter(r => r.status === 'completed');
            if (completed.length > 0) {
                this.latest = completed[0];
                if (!this.selectedRun) this.selectedRun = completed[0];
            }
        },

        selectRun(run) {
            this.selectedRun = run;
        },

        async runBenchmark() {
            this.running = true;
            this.runError = '';
            try {
                const res = await fetch('/api/admin/benchmarks/run', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Benchmark failed');
                }
                const run = await res.json();
                await this.fetchHistory();
                this.selectedRun = this.history.find(r => r.run_id === run.run_id) || this.history[0];
            } catch (e) {
                this.runError = e.message;
            } finally {
                this.running = false;
            }
        },

        async uploadDataset(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.uploadMsg = 'Uploading…';
            this.uploadError = false;

            const form = new FormData();
            form.append('file', file);
            form.append('_token', csrfToken);

            try {
                const res = await fetch('/api/admin/benchmarks/dataset', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: form,
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Upload failed');
                this.uploadMsg = `Replaced — ${data.queries} queries`;
            } catch (e) {
                this.uploadError = true;
                this.uploadMsg = e.message;
            }
            event.target.value = '';
            setTimeout(() => this.uploadMsg = '', 5000);
        },
    };
}
</script>
@endsection
