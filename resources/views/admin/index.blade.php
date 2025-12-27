@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')

@section('content')
<div x-data="adminPage()">
    <h1 class="text-2xl font-bold mb-6">Admin Dashboard</h1>
    
    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <p class="text-gray-400 text-sm">Toplam Kullanıcı</p>
            <p class="text-3xl font-bold mt-2" x-text="stats.total_users || 0"></p>
        </div>
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <p class="text-gray-400 text-sm">Aktif Trial</p>
            <p class="text-3xl font-bold mt-2 text-green-400" x-text="stats.active_trials || 0"></p>
        </div>
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <p class="text-gray-400 text-sm">Ücretli Abonelik</p>
            <p class="text-3xl font-bold mt-2 text-brand-400" x-text="stats.paid_subscriptions || 0"></p>
        </div>
        <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
            <p class="text-gray-400 text-sm">Bugün İstek</p>
            <p class="text-3xl font-bold mt-2" x-text="stats.today_requests || 0"></p>
        </div>
    </div>
    
    <!-- Recent Users -->
    <div class="bg-dark-900 rounded-xl border border-dark-800">
        <div class="p-4 border-b border-dark-800">
            <h2 class="font-semibold">Son Kayıt Olan Kullanıcılar</h2>
        </div>
        <table class="w-full">
            <thead class="bg-dark-800">
                <tr>
                    <th class="px-4 py-3 text-left text-sm">Ad</th>
                    <th class="px-4 py-3 text-left text-sm">Email</th>
                    <th class="px-4 py-3 text-left text-sm">Plan</th>
                    <th class="px-4 py-3 text-left text-sm">Durum</th>
                    <th class="px-4 py-3 text-left text-sm">Kayıt</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-dark-800">
                <template x-for="user in users" :key="user.id">
                    <tr>
                        <td class="px-4 py-3 text-sm" x-text="user.name"></td>
                        <td class="px-4 py-3 text-sm text-gray-400" x-text="user.email"></td>
                        <td class="px-4 py-3 text-sm" x-text="user.plan || 'Trial'"></td>
                        <td class="px-4 py-3">
                            <span :class="user.status === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'"
                                  class="px-2 py-1 text-xs rounded" x-text="user.status"></span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-400" x-text="new Date(user.created_at).toLocaleDateString('tr')"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
function adminPage() {
    return {
        stats: {},
        users: [],
        
        async init() {
            // Mock data for now
            this.stats = {
                total_users: 12,
                active_trials: 8,
                paid_subscriptions: 4,
                today_requests: 1247
            };
            
            this.users = [
                { id: 1, name: 'Ahmet Yılmaz', email: 'ahmet@example.com', plan: 'Pro', status: 'active', created_at: new Date() },
                { id: 2, name: 'Mehmet Demir', email: 'mehmet@example.com', plan: 'Trial', status: 'active', created_at: new Date() },
            ];
        }
    }
}
</script>
@endpush
@endsection

