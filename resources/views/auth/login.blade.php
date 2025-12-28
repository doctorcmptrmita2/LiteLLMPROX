@extends('layouts.app')

@section('title', 'Giriş Yap - CodexFlow.dev')

@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="/" class="inline-flex items-center space-x-2">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-500 to-purple-500 flex items-center justify-center">
                    <span class="text-white font-bold">CF</span>
                </div>
                <span class="font-semibold text-xl">CodexFlow</span>
            </a>
        </div>
        
        <!-- Form -->
        <div class="glass rounded-2xl p-8" x-data="loginForm()">
            <h1 class="text-2xl font-bold text-center mb-6">Hesabınıza Giriş Yapın</h1>
            
            <div x-show="error" x-cloak class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300 text-sm">
                <span x-text="error"></span>
            </div>
            
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="block text-dark-300 text-sm mb-2">Email</label>
                    <input type="email" x-model="email" required
                           class="w-full px-4 py-3 bg-dark-800 border border-dark-700 rounded-lg focus:border-brand-500 focus:outline-none transition"
                           placeholder="email@example.com">
                </div>
                
                <div class="mb-6">
                    <label class="block text-dark-300 text-sm mb-2">Şifre</label>
                    <input type="password" x-model="password" required
                           class="w-full px-4 py-3 bg-dark-800 border border-dark-700 rounded-lg focus:border-brand-500 focus:outline-none transition"
                           placeholder="••••••••">
                </div>
                
                <button type="submit" :disabled="loading"
                        class="w-full py-3 bg-brand-600 hover:bg-brand-500 disabled:opacity-50 rounded-lg font-medium transition">
                    <span x-show="!loading">Giriş Yap</span>
                    <span x-show="loading">Yükleniyor...</span>
                </button>
            </form>
            
            <div class="mt-6 text-center text-dark-400 text-sm">
                Hesabınız yok mu? 
                <a href="/register" class="text-brand-400 hover:text-brand-300">Ücretsiz Deneyin</a>
            </div>
        </div>
    </div>
</div>

<script>
function loginForm() {
    return {
        email: '',
        password: '',
        loading: false,
        error: null,
        
        async submit() {
            this.loading = true;
            this.error = null;
            
            try {
                const response = await fetch('/api/v1/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        email: this.email,
                        password: this.password
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    this.error = data.message || 'Giriş başarısız';
                    return;
                }
                
                // Store token
                localStorage.setItem('token', data.token);
                localStorage.setItem('user', JSON.stringify(data.user));
                
                // Redirect to dashboard
                window.location.href = '/app';
                
            } catch (e) {
                this.error = 'Bağlantı hatası';
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection


