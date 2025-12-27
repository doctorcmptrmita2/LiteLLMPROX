@extends('layouts.app')

@section('title', 'Ücretsiz Dene - CodexFlow.dev')

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
        
        <!-- Trial badge -->
        <div class="text-center mb-6">
            <span class="inline-flex items-center px-4 py-2 rounded-full glass text-sm">
                <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                24 Saat Ücretsiz • Kredi Kartı Gerektirmez
            </span>
        </div>
        
        <!-- Form -->
        <div class="glass rounded-2xl p-8" x-data="registerForm()">
            <h1 class="text-2xl font-bold text-center mb-6">Hesap Oluşturun</h1>
            
            <div x-show="error" x-cloak class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300 text-sm">
                <span x-text="error"></span>
            </div>
            
            <form @submit.prevent="submit">
                <div class="mb-4">
                    <label class="block text-dark-300 text-sm mb-2">Ad Soyad</label>
                    <input type="text" x-model="name" required
                           class="w-full px-4 py-3 bg-dark-800 border border-dark-700 rounded-lg focus:border-brand-500 focus:outline-none transition"
                           placeholder="Adınız Soyadınız">
                </div>
                
                <div class="mb-4">
                    <label class="block text-dark-300 text-sm mb-2">Email</label>
                    <input type="email" x-model="email" required
                           class="w-full px-4 py-3 bg-dark-800 border border-dark-700 rounded-lg focus:border-brand-500 focus:outline-none transition"
                           placeholder="email@example.com">
                </div>
                
                <div class="mb-4">
                    <label class="block text-dark-300 text-sm mb-2">Şifre</label>
                    <input type="password" x-model="password" required minlength="8"
                           class="w-full px-4 py-3 bg-dark-800 border border-dark-700 rounded-lg focus:border-brand-500 focus:outline-none transition"
                           placeholder="En az 8 karakter">
                </div>
                
                <div class="mb-6">
                    <label class="block text-dark-300 text-sm mb-2">Şifre Tekrar</label>
                    <input type="password" x-model="password_confirmation" required
                           class="w-full px-4 py-3 bg-dark-800 border border-dark-700 rounded-lg focus:border-brand-500 focus:outline-none transition"
                           placeholder="Şifrenizi tekrar girin">
                </div>
                
                <button type="submit" :disabled="loading"
                        class="w-full py-3 bg-brand-600 hover:bg-brand-500 disabled:opacity-50 rounded-lg font-medium transition">
                    <span x-show="!loading">24 Saat Ücretsiz Başla</span>
                    <span x-show="loading">Hesap oluşturuluyor...</span>
                </button>
            </form>
            
            <div class="mt-6 text-center text-dark-400 text-sm">
                Zaten hesabınız var mı? 
                <a href="/login" class="text-brand-400 hover:text-brand-300">Giriş Yapın</a>
            </div>
        </div>
    </div>
</div>

<script>
function registerForm() {
    return {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        loading: false,
        error: null,
        
        async submit() {
            if (this.password !== this.password_confirmation) {
                this.error = 'Şifreler eşleşmiyor';
                return;
            }
            
            this.loading = true;
            this.error = null;
            
            try {
                const response = await fetch('/api/v1/auth/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: this.name,
                        email: this.email,
                        password: this.password,
                        password_confirmation: this.password_confirmation
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    this.error = data.message || Object.values(data.errors || {})[0]?.[0] || 'Kayıt başarısız';
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

