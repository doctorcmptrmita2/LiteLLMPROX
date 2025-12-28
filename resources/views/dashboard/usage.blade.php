@extends('layouts.dashboard')

@section('title', 'Kullanım')

@section('content')
<div x-data="usagePage()">
    <h1 class="text-2xl font-bold mb-6">Kullanım İstatistikleri</h1>
    
    <!-- Date Range -->
    <div class="flex items-center space-x-4 mb-6">
        <div>
            <label class="text-sm text-gray-400">Başlangıç</label>
            <input type="date" x-model="fromDate" @change="fetchUsage"
                   class="block mt-1 px-3 py-2 bg-dark-800 border border-dark-700 rounded-lg">
        </div>
        <div>
            <label class="text-sm text-gray-400">Bitiş</label>
            <input type="date" x-model="toDate" @change="fetchUsage"
                   class="block mt-1 px-3 py-2 bg-dark-800 border border-dark-700 rounded-lg">
        </div>
    </div>
    
    <!-- Chart -->
    <div class="bg-dark-900 rounded-xl p-6 border border-dark-800 mb-6">
        <h2 class="text-lg font-semibold mb-4">Günlük Token Kullanımı</h2>
        <canvas id="usageChart" height="100"></canvas>
    </div>
    
    <!-- Table -->
    <div class="bg-dark-900 rounded-xl border border-dark-800 overflow-hidden">
        <table class="w-full">
            <thead class="bg-dark-800">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Tarih</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Fast</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Deep</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Grace</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Toplam</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Maliyet</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-800">
                <template x-for="row in usageData" :key="row.date">
                    <tr>
                        <td class="px-4 py-3 text-sm" x-text="row.date"></td>
                        <td class="px-4 py-3 text-sm" x-text="formatNumber(row.fast.tokens)"></td>
                        <td class="px-4 py-3 text-sm" x-text="formatNumber(row.deep.tokens)"></td>
                        <td class="px-4 py-3 text-sm" x-text="formatNumber(row.grace.tokens)"></td>
                        <td class="px-4 py-3 text-sm font-medium" x-text="formatNumber(row.total.tokens)"></td>
                        <td class="px-4 py-3 text-sm text-green-400" x-text="'$' + row.total.cost_usd.toFixed(4)"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
function usagePage() {
    return {
        usageData: [],
        chart: null,
        fromDate: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
        toDate: new Date().toISOString().slice(0, 10),
        
        async init() {
            await this.fetchUsage();
        },
        
        async fetchUsage() {
            const token = localStorage.getItem('token');
            const response = await fetch(`/api/v1/usage/daily?from=${this.fromDate}&to=${this.toDate}`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            if (response.ok) {
                const data = await response.json();
                this.usageData = data.data || [];
                this.renderChart();
            }
        },
        
        renderChart() {
            const ctx = document.getElementById('usageChart')?.getContext('2d');
            if (!ctx) return;
            
            if (this.chart) this.chart.destroy();
            
            this.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.usageData.map(d => d.date),
                    datasets: [
                        {
                            label: 'Fast',
                            data: this.usageData.map(d => d.fast.tokens),
                            backgroundColor: '#3b82f6',
                        },
                        {
                            label: 'Deep',
                            data: this.usageData.map(d => d.deep.tokens),
                            backgroundColor: '#8b5cf6',
                        },
                        {
                            label: 'Grace',
                            data: this.usageData.map(d => d.grace.tokens),
                            backgroundColor: '#22c55e',
                        },
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true }
                    }
                }
            });
        },
        
        formatNumber(num) {
            if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
            if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
            return num.toString();
        }
    }
}
</script>
@endpush
@endsection



