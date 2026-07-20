@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Analytics Dashboard')

@section('content')
<div x-data="dashboard()" x-init="init()">

    <!-- Date Range Controls -->
    <div class="flex items-center gap-4 mb-6">
        <label class="text-sm text-gray-600">Time Range:</label>
        <select x-model="days" @change="fetchMetrics()" class="border rounded px-3 py-1.5 text-sm">
            <option value="1">Last 24h</option>
            <option value="7" selected>Last 7 days</option>
            <option value="30">Last 30 days</option>
            <option value="90">Last 90 days</option>
        </select>
        <button @click="fetchMetrics()" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700">
            Refresh
        </button>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Total Searches</div>
            <div class="text-2xl font-bold text-gray-800 mt-1" x-text="metrics.total_searches?.toLocaleString() || '-'"></div>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Click-Through Rate</div>
            <div class="text-2xl font-bold text-gray-800 mt-1" x-text="(metrics.ctr || 0) + '%'"></div>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wider">P95 Latency</div>
            <div class="text-2xl font-bold mt-1"
                 :class="(metrics.avg_latency_ms?.p95 || 0) > 300 ? 'text-red-600' : 'text-green-600'"
                 x-text="(metrics.avg_latency_ms?.p95 || 0) + 'ms'"></div>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="text-xs text-gray-500 uppercase tracking-wider">Zero Result Rate</div>
            <div class="text-2xl font-bold mt-1"
                 :class="zeroResultPct > 5 ? 'text-red-600' : 'text-green-600'"
                 x-text="zeroResultPct + '%'"></div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold text-gray-700 mb-3">Search Volume</h3>
            <canvas id="volumeChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold text-gray-700 mb-3">Latency Distribution</h3>
            <canvas id="latencyChart" height="200"></canvas>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Queries -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-700">Top Queries</h3>
                <a href="{{ route('admin.analytics') }}" class="text-blue-600 text-xs hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium text-gray-600">Query</th>
                            <th class="text-right px-4 py-2 font-medium text-gray-600">Searches</th>
                            <th class="text-right px-4 py-2 font-medium text-gray-600">Avg Results</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in (metrics.top_queries || [])" :key="row.query">
                            <tr class="border-t border-gray-50 hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium" x-text="row.query"></td>
                                <td class="px-4 py-2 text-right text-gray-600" x-text="row.searches"></td>
                                <td class="px-4 py-2 text-right text-gray-600" x-text="Math.round(row.avg_results)"></td>
                            </tr>
                        </template>
                        <tr x-show="!metrics.top_queries?.length">
                            <td colspan="3" class="px-4 py-4 text-center text-gray-400 text-sm">No data</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Zero Result Queries -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-700">Zero-Result Queries</h3>
                <span class="text-xs text-gray-400">Needs attention</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium text-gray-600">Query</th>
                            <th class="text-right px-4 py-2 font-medium text-gray-600">Occurrences</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in (metrics.zero_result_queries || [])" :key="row.query">
                            <tr class="border-t border-gray-50 hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-red-600" x-text="row.query"></td>
                                <td class="px-4 py-2 text-right" x-text="row.occurrences"></td>
                                <td class="px-4 py-2 text-right">
                                    <a :href="`/admin/search/synonyms/create?suggest=${row.query}`"
                                       class="text-blue-600 text-xs hover:underline">Add synonym</a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!metrics.zero_result_queries?.length">
                            <td colspan="3" class="px-4 py-4 text-center text-gray-400 text-sm">No zero-result queries!</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
function dashboard() {
    return {
        metrics: {},
        days: 7,
        volumeChart: null,
        latencyChart: null,

        get zeroResultPct() {
            const total = this.metrics.total_searches || 0;
            const zero = this.metrics.zero_result_queries?.reduce((s, r) => s + r.occurrences, 0) || 0;
            if (!total) return 0;
            return Math.round((zero / total) * 100 * 10) / 10;
        },

        async init() {
            await this.fetchMetrics();
        },

        async fetchMetrics() {
            try {
                const res = await fetch(`/api/admin/search/analytics?store_id=${storeId}&days=${this.days}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
                });
                this.metrics = await res.json();
                this.$nextTick(() => {
                    this.renderVolumeChart();
                    this.renderLatencyChart();
                });
            } catch(e) {
                console.error('Failed to fetch metrics', e);
            }
        },

        renderVolumeChart() {
            const volume = this.metrics.search_volume || [];
            const ctx = document.getElementById('volumeChart');
            if (!ctx) return;
            if (this.volumeChart) this.volumeChart.destroy();
            this.volumeChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: volume.map(v => v.date),
                    datasets: [{ label: 'Searches', data: volume.map(v => v.count), borderColor: '#3b82f6', tension: 0.3, fill: true, backgroundColor: 'rgba(59,130,246,0.08)' }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        },

        renderLatencyChart() {
            const lat = this.metrics.avg_latency_ms || {};
            const ctx = document.getElementById('latencyChart');
            if (!ctx) return;
            if (this.latencyChart) this.latencyChart.destroy();
            this.latencyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['P50', 'P95', 'P99', 'Avg'],
                    datasets: [{ label: 'Latency (ms)', data: [lat.p50, lat.p95, lat.p99, lat.avg], backgroundColor: ['#10b981', lat.p95 > 300 ? '#ef4444' : '#3b82f6', lat.p99 > 500 ? '#ef4444' : '#8b5cf6', '#6b7280'] }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }
    };
}
</script>
@endsection
