@extends('layouts.app')

@section('title', 'CodexFlow.dev - Cursor AI için TL Bazlı LLM Gateway')

@section('content')
<div class="relative overflow-hidden">
    <!-- Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-dark-950 via-dark-900 to-dark-950"></div>
    <div class="absolute inset-0 opacity-30">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-brand-500/20 rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl animate-float" style="animation-delay: -3s"></div>
    </div>
    
    <!-- Nav -->
    <nav class="relative z-10 border-b border-dark-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-2">
                    <svg class="w-9 h-9" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="logoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#6366f1"/>
                                <stop offset="50%" style="stop-color:#8b5cf6"/>
                                <stop offset="100%" style="stop-color:#d946ef"/>
                            </linearGradient>
                        </defs>
                        <rect x="2" y="2" width="36" height="36" rx="10" fill="url(#logoGrad)"/>
                        <path d="M12 14L20 10L28 14V20L20 24L12 20V14Z" stroke="white" stroke-width="1.5" fill="none"/>
                        <path d="M20 24V30" stroke="white" stroke-width="1.5"/>
                        <path d="M12 20L20 24L28 20" stroke="white" stroke-width="1.5" fill="none"/>
                        <circle cx="20" cy="17" r="3" fill="white" fill-opacity="0.9"/>
                    </svg>
                    <span class="font-semibold text-lg tracking-tight">CodexFlow</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="#fiyatlandirma" class="text-dark-300 hover:text-white transition">Fiyatlandırma</a>
                    <a href="#nasil" class="text-dark-300 hover:text-white transition">Nasıl Çalışır</a>
                    <a href="/login" class="text-dark-300 hover:text-white transition">Giriş</a>
                    <a href="/register" class="px-4 py-2 bg-brand-600 hover:bg-brand-500 rounded-lg font-medium transition">
                        Ücretsiz Dene
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Hero -->
    <section class="relative z-10 py-24 lg:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center px-3 py-1 rounded-full glass text-sm mb-8">
                <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                24 Saat Ücretsiz Deneme
            </div>
            
            <h1 class="text-4xl md:text-6xl lg:text-7xl font-bold mb-6 leading-tight">
                Cursor AI için<br>
                <span class="gradient-text">TL Bazlı LLM Gateway</span>
            </h1>
            
            <p class="text-xl text-dark-300 max-w-2xl mx-auto mb-8">
                Claude ve GPT modellerine tek API key ile erişin.
                Akıllı yük dengeleme, otomatik failover ve %99.9 uptime garantisi.
                Cursor AI entegrasyonu dakikalar içinde hazır.
            </p>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4 mb-12">
                <a href="/register" class="px-8 py-4 bg-brand-600 hover:bg-brand-500 rounded-xl font-semibold text-lg transition transform hover:scale-105">
                    24 Saat Ücretsiz Başla
                </a>
                <a href="#nasil" class="px-8 py-4 glass hover:bg-white/10 rounded-xl font-semibold text-lg transition">
                    Nasıl Çalışır?
                </a>
            </div>
            
            <!-- Code snippet -->
            <div class="max-w-2xl mx-auto glass rounded-2xl p-6 text-left">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-dark-400 text-sm">Cursor Settings</span>
                    <button onclick="navigator.clipboard.writeText(document.getElementById('api-snippet').innerText)" 
                            class="text-brand-400 text-sm hover:text-brand-300">
                        Kopyala
                    </button>
                </div>
                <pre id="api-snippet" class="font-mono text-sm text-dark-200 overflow-x-auto"><code>{
  "models": {
    "cursor": {
      "provider": "openai",
      "api_key": "cf_xxxxx",
      "base_url": "https://api.codexflow.dev/v1"
    }
  }
}</code></pre>
            </div>
        </div>
    </section>
    
    <!-- Pricing -->
    <section id="fiyatlandirma" class="relative z-10 py-24 border-t border-dark-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Şeffaf Fiyatlandırma</h2>
                <p class="text-dark-400 text-lg">Gizli maliyet yok. TL bazlı faturalandırma.</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Trial -->
                <div class="glass rounded-2xl p-8 relative overflow-hidden">
                    <div class="absolute top-4 right-4 px-3 py-1 bg-green-500/20 text-green-400 text-xs rounded-full">
                        ÜCRETSİZ
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Deneme</h3>
                    <div class="text-4xl font-bold mb-1">₺0</div>
                    <p class="text-dark-400 text-sm mb-6">24 saat geçerli</p>
                    
                    <ul class="space-y-3 mb-8 text-dark-300">
                        <li class="flex items-center"><span class="text-green-400 mr-2">✓</span> 200K fast token</li>
                        <li class="flex items-center"><span class="text-green-400 mr-2">✓</span> 50K deep token</li>
                        <li class="flex items-center"><span class="text-green-400 mr-2">✓</span> Sınırsız fallback desteği</li>
                        <li class="flex items-center"><span class="text-green-400 mr-2">✓</span> Kredi kartı gerektirmez</li>
                    </ul>
                    
                    <a href="/register" class="block w-full py-3 text-center glass hover:bg-white/10 rounded-lg font-medium transition">
                        Hemen Başla
                    </a>
                </div>
                
                <!-- Pro -->
                <div class="glass rounded-2xl p-8 relative overflow-hidden border-2 border-brand-500/50 transform scale-105">
                    <div class="absolute top-4 right-4 px-3 py-1 bg-brand-500/20 text-brand-400 text-xs rounded-full">
                        POPÜLER
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Pro</h3>
                    <div class="text-4xl font-bold mb-1">₺1,000</div>
                    <p class="text-dark-400 text-sm mb-6">aylık</p>
                    
                    <ul class="space-y-3 mb-8 text-dark-300">
                        <li class="flex items-center"><span class="text-brand-400 mr-2">✓</span> 6M fast token</li>
                        <li class="flex items-center"><span class="text-brand-400 mr-2">✓</span> 400K deep token</li>
                        <li class="flex items-center"><span class="text-brand-400 mr-2">✓</span> 50/gün grace fallback</li>
                        <li class="flex items-center"><span class="text-brand-400 mr-2">✓</span> Decompose pipeline</li>
                        <li class="flex items-center"><span class="text-brand-400 mr-2">✓</span> Öncelikli destek</li>
                    </ul>
                    
                    <a href="/register" class="block w-full py-3 text-center bg-brand-600 hover:bg-brand-500 rounded-lg font-medium transition">
                        Pro'ya Geç
                    </a>
                </div>
                
                <!-- Team -->
                <div class="glass rounded-2xl p-8">
                    <h3 class="text-xl font-semibold mb-2">Team</h3>
                    <div class="text-4xl font-bold mb-1">₺2,500</div>
                    <p class="text-dark-400 text-sm mb-6">aylık / 5 kullanıcı</p>
                    
                    <ul class="space-y-3 mb-8 text-dark-300">
                        <li class="flex items-center"><span class="text-purple-400 mr-2">✓</span> 15M fast token</li>
                        <li class="flex items-center"><span class="text-purple-400 mr-2">✓</span> 1M deep token</li>
                        <li class="flex items-center"><span class="text-purple-400 mr-2">✓</span> 120/gün grace fallback</li>
                        <li class="flex items-center"><span class="text-purple-400 mr-2">✓</span> Ortak projeler</li>
                        <li class="flex items-center"><span class="text-purple-400 mr-2">✓</span> Admin paneli</li>
                    </ul>
                    
                    <a href="/register" class="block w-full py-3 text-center glass hover:bg-white/10 rounded-lg font-medium transition">
                        İletişime Geç
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- How it works -->
    <section id="nasil" class="relative z-10 py-24 border-t border-dark-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Nasıl Çalışır?</h2>
                <p class="text-dark-400 text-lg">3 adımda Cursor AI'nızı CodexFlow'a bağlayın</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-2xl bg-brand-500/20 flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl">1</span>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Kaydol</h3>
                    <p class="text-dark-400">Email ile kayıt olun. Kredi kartı gerekmez. 24 saat ücretsiz deneme başlar.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 rounded-2xl bg-purple-500/20 flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl">2</span>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">API Key Al</h3>
                    <p class="text-dark-400">Dashboard'dan proje oluşturun ve API key'inizi alın. Tek seferlik gösterilir!</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 rounded-2xl bg-pink-500/20 flex items-center justify-center mx-auto mb-6">
                        <span class="text-3xl">3</span>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Cursor'a Ekle</h3>
                    <p class="text-dark-400">Cursor Settings > Models > OpenAI kısmına API key ve base URL'i ekleyin. Hazır!</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="relative z-10 py-12 border-t border-dark-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center space-x-2 mb-4 md:mb-0">
                    <svg class="w-8 h-8" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="logoGradFooter" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#6366f1"/>
                                <stop offset="50%" style="stop-color:#8b5cf6"/>
                                <stop offset="100%" style="stop-color:#d946ef"/>
                            </linearGradient>
                        </defs>
                        <rect x="2" y="2" width="36" height="36" rx="10" fill="url(#logoGradFooter)"/>
                        <path d="M12 14L20 10L28 14V20L20 24L12 20V14Z" stroke="white" stroke-width="1.5" fill="none"/>
                        <path d="M20 24V30" stroke="white" stroke-width="1.5"/>
                        <path d="M12 20L20 24L28 20" stroke="white" stroke-width="1.5" fill="none"/>
                        <circle cx="20" cy="17" r="3" fill="white" fill-opacity="0.9"/>
                    </svg>
                    <span class="font-semibold">CodexFlow.dev</span>
                </div>
                
                <p class="text-dark-500 text-sm">
                    © {{ date('Y') }} CodexFlow. TL bazlı LLM Gateway.
                </p>
            </div>
        </div>
    </footer>
</div>
@endsection
