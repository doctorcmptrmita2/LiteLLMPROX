<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - CodexFlow.dev</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|jetbrains-mono:400,500" rel="stylesheet" />
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1' },
                        dark: { 700: '#334155', 800: '#1e293b', 900: '#0f172a', 950: '#020617' }
                    },
                    fontFamily: { sans: ['Instrument Sans', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans bg-dark-950 text-gray-100 min-h-screen" x-data="dashboard()">
    
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-dark-900 border-r border-dark-800 flex flex-col">
            <!-- Logo -->
            <div class="p-4 border-b border-dark-800">
                <a href="/app" class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-500 to-purple-500 flex items-center justify-center">
                        <span class="text-white font-bold text-sm">CF</span>
                    </div>
                    <span class="font-semibold">CodexFlow</span>
                </a>
            </div>
            
            <!-- Nav -->
            <nav class="flex-1 p-4 space-y-1">
                <a href="/app" class="flex items-center px-3 py-2 rounded-lg {{ request()->is('app') ? 'bg-brand-600 text-white' : 'text-gray-400 hover:bg-dark-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Genel Bakış
                </a>
                
                <a href="/app/projects" class="flex items-center px-3 py-2 rounded-lg {{ request()->is('app/projects*') ? 'bg-brand-600 text-white' : 'text-gray-400 hover:bg-dark-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    Projeler
                </a>
                
                <a href="/app/usage" class="flex items-center px-3 py-2 rounded-lg {{ request()->is('app/usage*') ? 'bg-brand-600 text-white' : 'text-gray-400 hover:bg-dark-800' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Kullanım
                </a>
            </nav>
            
            <!-- User -->
            <div class="p-4 border-t border-dark-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center">
                            <span x-text="user?.name?.[0]?.toUpperCase() || 'U'"></span>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium" x-text="user?.name || 'Kullanıcı'"></p>
                            <p class="text-xs text-gray-500" x-text="subscription?.plan_name || 'Deneme'"></p>
                        </div>
                    </div>
                    <button @click="logout()" class="text-gray-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="p-8">
                @yield('content')
            </div>
        </main>
    </div>
    
    <script>
    function dashboard() {
        return {
            user: JSON.parse(localStorage.getItem('user') || 'null'),
            subscription: null,
            token: localStorage.getItem('token'),
            
            init() {
                if (!this.token) {
                    window.location.href = '/login';
                    return;
                }
                this.fetchUser();
            },
            
            async fetchUser() {
                try {
                    const response = await fetch('/api/v1/auth/me', {
                        headers: { 'Authorization': `Bearer ${this.token}` }
                    });
                    
                    if (!response.ok) {
                        this.logout();
                        return;
                    }
                    
                    const data = await response.json();
                    this.user = data.user;
                    this.subscription = data.subscription;
                    localStorage.setItem('user', JSON.stringify(data.user));
                } catch (e) {
                    console.error('Failed to fetch user', e);
                }
            },
            
            async logout() {
                try {
                    await fetch('/api/v1/auth/logout', {
                        method: 'POST',
                        headers: { 'Authorization': `Bearer ${this.token}` }
                    });
                } catch (e) {}
                
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                window.location.href = '/login';
            }
        }
    }
    </script>
    
    @stack('scripts')
</body>
</html>

