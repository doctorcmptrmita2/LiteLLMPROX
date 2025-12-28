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
    
    <!-- Loading State -->
    <div x-show="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
        <span class="ml-3 text-gray-400">Yükleniyor...</span>
    </div>
    
    <!-- Error State -->
    <div x-show="error" x-cloak class="bg-red-900/20 border border-red-500 rounded-xl p-4 mb-6">
        <p class="text-red-400" x-text="error"></p>
    </div>
    
    <!-- Empty State -->
    <div x-show="!loading && !error && usageData.length === 0" x-cloak 
         class="bg-dark-900 rounded-xl border border-dark-800 p-12 text-center">
        <div class="text-4xl mb-4">📊</div>
        <h3 class="text-lg font-semibold mb-2">Henüz veri yok</h3>
        <p class="text-gray-400">Seçilen tarih aralığında kullanım kaydı bulunamadı.</p>
        <p class="text-gray-500 text-sm mt-2">API istekleri yaptığınızda burada görünecek.</p>
    </div>
    
    <!-- Table -->
    <div x-show="!loading && !error && usageData.length > 0" x-cloak 
         class="bg-dark-900 rounded-xl border border-dark-800 overflow-hidden">
        <table class="w-full">
            <thead class="bg-dark-800">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Tarih</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">⚡ Fast</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">🧠 Deep</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">🤖 Agent</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">🆓 Grace</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Toplam</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-400">Maliyet</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-800">
                <template x-for="row in usageData" :key="row.date">
                    <tr class="hover:bg-dark-800/50 transition-colors">
                        <td class="px-4 py-3 text-sm font-medium" x-text="row.date"></td>
                        <td class="px-4 py-3 text-sm text-blue-400" x-text="formatNumber(row.fast?.tokens || 0)"></td>
                        <td class="px-4 py-3 text-sm text-purple-400" x-text="formatNumber(row.deep?.tokens || 0)"></td>
                        <td class="px-4 py-3 text-sm text-orange-400" x-text="formatNumber(row.agent?.tokens || 0)"></td>
                        <td class="px-4 py-3 text-sm text-green-400" x-text="formatNumber(row.grace?.tokens || 0)"></td>
                        <td class="px-4 py-3 text-sm font-semibold" x-text="formatNumber(row.total?.tokens || 0)"></td>
                        <td class="px-4 py-3 text-sm text-emerald-400 font-medium" x-text="'$' + (row.total?.cost_usd || 0).toFixed(4)"></td>
                    </tr>
                </template>
            </tbody>
            <!-- Totals Row -->
            <tfoot class="bg-dark-800/50 border-t border-dark-700">
                <tr>
                    <td class="px-4 py-3 text-sm font-bold">TOPLAM</td>
                    <td class="px-4 py-3 text-sm text-blue-400 font-medium" x-text="formatNumber(usageData.reduce((sum, r) => sum + (r.fast?.tokens || 0), 0))"></td>
                    <td class="px-4 py-3 text-sm text-purple-400 font-medium" x-text="formatNumber(usageData.reduce((sum, r) => sum + (r.deep?.tokens || 0), 0))"></td>
                    <td class="px-4 py-3 text-sm text-orange-400 font-medium" x-text="formatNumber(usageData.reduce((sum, r) => sum + (r.agent?.tokens || 0), 0))"></td>
                    <td class="px-4 py-3 text-sm text-green-400 font-medium" x-text="formatNumber(usageData.reduce((sum, r) => sum + (r.grace?.tokens || 0), 0))"></td>
                    <td class="px-4 py-3 text-sm font-bold" x-text="formatNumber(usageData.reduce((sum, r) => sum + (r.total?.tokens || 0), 0))"></td>
                    <td class="px-4 py-3 text-sm text-emerald-400 font-bold" x-text="'$' + usageData.reduce((sum, r) => sum + (r.total?.cost_usd || 0), 0).toFixed(4)"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@push('scripts')
<script>
function usagePage() {
    return {
        usageData: [],
        chart: null,
        loading: true,
        error: null,
        fromDate: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
        toDate: new Date().toISOString().slice(0, 10),
        
        async init() {
            await this.fetchUsage();
        },
        
        async fetchUsage() {
            this.loading = true;
            this.error = null;
            
            try {
                // Use session-based auth (CSRF token from Laravel)
                const response = await fetch(`/api/v1/usage/daily?from=${this.fromDate}&to=${this.toDate}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin' // Include session cookies
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.usageData = data.data || [];
                    this.renderChart();
                } else if (response.status === 401) {
                    this.error = 'Oturum süresi dolmuş. Lütfen yeniden giriş yapın.';
                } else {
                    this.error = 'Veri yüklenirken hata oluştu.';
                }
            } catch (e) {
                this.error = 'Bağlantı hatası: ' + e.message;
            }
            
            this.loading = false;
        },
        
        renderChart() {
            const ctx = document.getElementById('usageChart')?.getContext('2d');
            if (!ctx || this.usageData.length === 0) return;
            
            if (this.chart) this.chart.destroy();
            
            this.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.usageData.map(d => d.date),
                    datasets: [
                        {
                            label: '⚡ Fast',
                            data: this.usageData.map(d => d.fast?.tokens || 0),
                            backgroundColor: '#3b82f6',
                            borderRadius: 4,
                        },
                        {
                            label: '🧠 Deep',
                            data: this.usageData.map(d => d.deep?.tokens || 0),
                            backgroundColor: '#8b5cf6',
                            borderRadius: 4,
                        },
                        {
                            label: '🤖 Agent',
                            data: this.usageData.map(d => d.agent?.tokens || 0),
                            backgroundColor: '#f97316',
                            borderRadius: 4,
                        },
                        {
                            label: '🆓 Grace',
                            data: this.usageData.map(d => d.grace?.tokens || 0),
                            backgroundColor: '#22c55e',
                            borderRadius: 4,
                        },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { color: '#94a3b8' }
                        }
                    },
                    scales: {
                        x: { 
                            stacked: true,
                            ticks: { color: '#64748b' },
                            grid: { color: '#1e293b' }
                        },
                        y: { 
                            stacked: true,
                            ticks: { color: '#64748b' },
                            grid: { color: '#1e293b' }
                        }
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



