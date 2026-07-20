@extends('admin.layout')

@section('title', 'Analytics')
@section('page-title', 'Search Analytics')

@section('content')
<div x-data="analyticsPage()" x-init="init()">

    <div class="flex gap-3 mb-6">
        <select x-model="days" @change="fetchMetrics()" class="border rounded px-3 py-1.5 text-sm">
            <option value="1">Last 24h</option>
            <option value="7" selected>Last 7 days</option>
            <option value="30">Last 30 days</option>
        </select>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3">Top Queries</h3>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-gray-500"><th class="pb-2">Query</th><th class="pb-2 text-right">Searches</th><th class="pb-2 text-right">Avg Results</th></tr></thead>
                <tbody>
                    <template x-for="q in (metrics.top_queries || [])" :key="q.query">
                        <tr class="border-t border-gray-50"><td class="py-1.5" x-text="q.query"></td><td class="py-1.5 text-right text-gray-600" x-text="q.searches"></td><td class="py-1.5 text-right text-gray-600" x-text="Math.round(q.avg_results)"></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-4">
            <h3 class="font-semibold text-gray-700 mb-3">Zero Result Queries</h3>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-gray-500"><th class="pb-2">Query</th><th class="pb-2 text-right">Times</th><th class="pb-2"></th></tr></thead>
                <tbody>
                    <template x-for="q in (metrics.zero_result_queries || [])" :key="q.query">
                        <tr class="border-t border-gray-50">
                            <td class="py-1.5 text-red-600" x-text="q.query"></td>
                            <td class="py-1.5 text-right" x-text="q.occurrences"></td>
                            <td class="py-1.5 text-right"><a :href="`/admin/synonyms?suggest=${q.query}`" class="text-blue-600 text-xs hover:underline">Fix</a></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm"><div class="text-xs text-gray-500">CTR</div><div class="text-2xl font-bold mt-1" x-text="(metrics.ctr || 0) + '%'"></div></div>
        <div class="bg-white rounded-lg p-4 shadow-sm"><div class="text-xs text-gray-500">P50 Latency</div><div class="text-2xl font-bold mt-1" x-text="(metrics.avg_latency_ms?.p50 || 0) + 'ms'"></div></div>
        <div class="bg-white rounded-lg p-4 shadow-sm"><div class="text-xs text-gray-500">P95 Latency</div><div class="text-2xl font-bold mt-1" :class="(metrics.avg_latency_ms?.p95||0) > 300 ? 'text-red-600' : 'text-green-600'" x-text="(metrics.avg_latency_ms?.p95 || 0) + 'ms'"></div></div>
        <div class="bg-white rounded-lg p-4 shadow-sm"><div class="text-xs text-gray-500">P99 Latency</div><div class="text-2xl font-bold mt-1" x-text="(metrics.avg_latency_ms?.p99 || 0) + 'ms'"></div></div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-4">
        <h3 class="font-semibold text-gray-700 mb-3">Search Volume Over Time</h3>
        <canvas id="volumeChart" height="100"></canvas>
    </div>

</div>
@endsection

@section('scripts')
<script>
function analyticsPage() {
    return {
        metrics: {}, days: 7, chart: null,
        async init() { await this.fetchMetrics(); },
        async fetchMetrics() {
            const res = await fetch(`/api/admin/search/analytics?store_id=${storeId}&days=${this.days}`, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken } });
            this.metrics = await res.json();
            this.$nextTick(() => this.renderChart());
        },
        renderChart() {
            const v = this.metrics.search_volume || [];
            const ctx = document.getElementById('volumeChart');
            if (!ctx) return;
            if (this.chart) this.chart.destroy();
            this.chart = new Chart(ctx, { type: 'bar', data: { labels: v.map(x=>x.date), datasets: [{ label: 'Searches', data: v.map(x=>x.count), backgroundColor: '#3b82f6' }] }, options: { responsive: true, plugins: { legend: { display: false } } } });
        }
    };
}
</script>
@endsection
