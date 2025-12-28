@extends('layouts.dashboard')

@section('title', 'Genel Bakış')

@section('content')
<div x-data="overviewPage()">
    <h1 class="text-2xl font-bold mb-6">Genel Bakış</h1>
    
    <!-- Trial Banner -->
    <div x-show="subscription?.is_trial" x-cloak class="mb-6 p-4 bg-gradient-to-r from-brand-600/20 to-purple-600/20 border border-brand-500/50 rounded-xl">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="text-2xl mr-3">⏰</span>
                <div>
                    <p class="font-medium">Deneme Süreniz Devam Ediyor</p>
                    <p class="text-sm text-gray-400" x-text="'Bitiş: ' + new Date(subscription?.trial_ends_at).toLocaleString('tr-TR')"></p>
                </div>
            </div>
            <a href="/app/upgrade" class="px-4 py-2 bg-brand-600 hover:bg-brand-500 rounded-lg text-sm font-medium">
                Pro'ya Geç
            </a>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Fast Tokens -->
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <div class="flex items-center justify-between mb-4">
                <span class="text-gray-400 text-sm">Fast Tokens</span>
                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 text-xs rounded">Claude Haiku</span>
            </div>
            <div class="text-2xl font-bold mb-2" x-text="formatNumber(usage?.fast_tokens || 0)"></div>
            <div class="w-full bg-dark-800 rounded-full h-2">
                <div class="bg-blue-500 rounded-full h-2 transition-all" :style="'width: ' + (percentages?.fast || 0) + '%'"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2" x-text="percentages?.fast + '% kullanıldı'"></p>
        </div>
        
        <!-- Deep Tokens -->
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <div class="flex items-center justify-between mb-4">
                <span class="text-gray-400 text-sm">Deep Tokens</span>
                <span class="px-2 py-1 bg-purple-500/20 text-purple-400 text-xs rounded">Claude Sonnet</span>
            </div>
            <div class="text-2xl font-bold mb-2" x-text="formatNumber(usage?.deep_tokens || 0)"></div>
            <div class="w-full bg-dark-800 rounded-full h-2">
                <div class="bg-purple-500 rounded-full h-2 transition-all" :style="'width: ' + (percentages?.deep || 0) + '%'"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2" x-text="percentages?.deep + '% kullanıldı'"></p>
        </div>
        
        <!-- Grace Tokens -->
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <div class="flex items-center justify-between mb-4">
                <span class="text-gray-400 text-sm">Grace (Bugün)</span>
                <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded">ÜCRETSİZ</span>
            </div>
            <div class="text-2xl font-bold mb-2" x-text="formatNumber(daily?.grace?.tokens || 0)"></div>
            <p class="text-xs text-gray-500">Llama 405B - Kota bitince aktif</p>
        </div>
        
        <!-- Requests Today -->
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <div class="flex items-center justify-between mb-4">
                <span class="text-gray-400 text-sm">Bugün İstek</span>
            </div>
            <div class="text-2xl font-bold mb-2" x-text="(daily?.fast?.requests || 0) + (daily?.deep?.requests || 0)"></div>
            <p class="text-xs text-gray-500">Fast + Deep toplam</p>
        </div>
    </div>
    
    <!-- Quick Start -->
    <div class="bg-dark-900 rounded-xl p-6 border border-dark-800 mb-8">
        <h2 class="text-lg font-semibold mb-4">🚀 Hızlı Başlangıç</h2>
        
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm text-gray-400 mb-2">Cursor Settings</h3>
                <pre class="bg-dark-950 p-4 rounded-lg text-sm font-mono overflow-x-auto"><code>{
  "models": {
    "cursor": {
      "provider": "openai",
      "api_key": "<span class="text-brand-400">API_KEY_İNİZ</span>",
      "base_url": "https://codexflow.dev/api/v1"
    }
  }
}</code></pre>
            </div>
            
            <div>
                <h3 class="text-sm text-gray-400 mb-2">API Key Alın</h3>
                <p class="text-sm text-gray-300 mb-4">
                    Projeler sayfasından yeni bir API key oluşturun ve Cursor ayarlarınıza ekleyin.
                </p>
                <a href="/app/projects" class="inline-flex items-center px-4 py-2 bg-brand-600 hover:bg-brand-500 rounded-lg text-sm font-medium">
                    Projelere Git
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function overviewPage() {
    return {
        usage: null,
        daily: null,
        percentages: { fast: 0, deep: 0 },
        subscription: null,
        
        async init() {
            await this.fetchSummary();
            this.subscription = JSON.parse(localStorage.getItem('subscription') || 'null');
        },
        
        async fetchSummary() {
            const token = localStorage.getItem('token');
            const month = new Date().toISOString().slice(0, 7);
            
            try {
                const response = await fetch(`/api/v1/usage/summary?month=${month}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.usage = {
                        fast_tokens: (data.usage?.fast?.input_tokens || 0) + (data.usage?.fast?.output_tokens || 0),
                        deep_tokens: (data.usage?.deep?.input_tokens || 0) + (data.usage?.deep?.output_tokens || 0),
                    };
                    this.daily = data.daily?.usage;
                    this.percentages = data.percentages;
                    this.subscription = data.plan;
                    localStorage.setItem('subscription', JSON.stringify(data.plan));
                }
            } catch (e) {
                console.error('Failed to fetch summary', e);
            }
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


