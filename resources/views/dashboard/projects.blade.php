@extends('layouts.dashboard')

@section('title', 'Projeler')

@section('content')
<div x-data="projectsPage()">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Projeler</h1>
        <button @click="showCreateModal = true" class="px-4 py-2 bg-brand-600 hover:bg-brand-500 rounded-lg text-sm font-medium">
            + Yeni Proje
        </button>
    </div>
    
    <!-- Projects List -->
    <div class="space-y-4">
        <template x-for="project in projects" :key="project.id">
            <div class="bg-dark-900 rounded-xl p-6 border border-dark-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold" x-text="project.name"></h3>
                        <p class="text-sm text-gray-500" x-text="project.api_keys_count + ' API key'"></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span :class="project.status === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'"
                              class="px-2 py-1 text-xs rounded" x-text="project.status"></span>
                        <button @click="showKeys(project)" class="px-3 py-1.5 bg-dark-800 hover:bg-dark-700 rounded text-sm">
                            API Keys
                        </button>
                    </div>
                </div>
            </div>
        </template>
        
        <div x-show="projects.length === 0" class="text-center py-12 text-gray-500">
            Henüz proje yok. İlk projenizi oluşturun!
        </div>
    </div>
    
    <!-- Create Project Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="showCreateModal = false">
        <div class="bg-dark-900 rounded-2xl p-6 w-full max-w-md border border-dark-800">
            <h2 class="text-xl font-semibold mb-4">Yeni Proje</h2>
            <form @submit.prevent="createProject">
                <div class="mb-4">
                    <label class="block text-sm text-gray-400 mb-2">Proje Adı</label>
                    <input type="text" x-model="newProjectName" required
                           class="w-full px-4 py-3 bg-dark-800 border border-dark-700 rounded-lg focus:border-brand-500 focus:outline-none"
                           placeholder="My Awesome Project">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-gray-400 hover:text-white">
                        İptal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-brand-600 hover:bg-brand-500 rounded-lg">
                        Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- API Keys Modal -->
    <div x-show="showKeysModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="showKeysModal = false">
        <div class="bg-dark-900 rounded-2xl p-6 w-full max-w-2xl border border-dark-800">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold">API Keys - <span x-text="selectedProject?.name"></span></h2>
                <button @click="createKey" class="px-3 py-1.5 bg-brand-600 hover:bg-brand-500 rounded text-sm">
                    + Yeni Key
                </button>
            </div>
            
            <!-- New Key Display -->
            <div x-show="newKey" x-cloak class="mb-4 p-4 bg-green-500/20 border border-green-500/50 rounded-lg">
                <p class="text-sm text-green-400 mb-2">⚠️ Bu key sadece bir kez gösterilir!</p>
                <div class="flex items-center space-x-2">
                    <code class="flex-1 font-mono text-sm bg-dark-950 px-3 py-2 rounded" x-text="newKey"></code>
                    <button @click="copyKey" class="px-3 py-2 bg-dark-800 rounded hover:bg-dark-700">📋</button>
                </div>
            </div>
            
            <!-- Keys List -->
            <div class="space-y-2 max-h-96 overflow-y-auto">
                <template x-for="key in keys" :key="key.id">
                    <div class="flex items-center justify-between p-3 bg-dark-800 rounded-lg">
                        <div>
                            <p class="font-medium" x-text="key.name"></p>
                            <p class="text-sm text-gray-500 font-mono" x-text="key.masked_key"></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span x-show="key.is_active" class="text-xs text-green-400">Aktif</span>
                            <span x-show="!key.is_active" class="text-xs text-red-400">İptal</span>
                            <button x-show="key.is_active" @click="revokeKey(key)" class="px-2 py-1 text-xs text-red-400 hover:bg-red-500/20 rounded">
                                İptal Et
                            </button>
                        </div>
                    </div>
                </template>
            </div>
            
            <div class="mt-4 text-right">
                <button @click="showKeysModal = false" class="px-4 py-2 text-gray-400 hover:text-white">
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function projectsPage() {
    return {
        projects: [],
        showCreateModal: false,
        showKeysModal: false,
        newProjectName: '',
        selectedProject: null,
        keys: [],
        newKey: null,
        
        async init() {
            await this.fetchProjects();
        },
        
        async fetchProjects() {
            const token = localStorage.getItem('token');
            const response = await fetch('/api/v1/projects', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            if (response.ok) {
                const data = await response.json();
                this.projects = data.data;
            }
        },
        
        async createProject() {
            const token = localStorage.getItem('token');
            const response = await fetch('/api/v1/projects', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name: this.newProjectName })
            });
            
            if (response.ok) {
                await this.fetchProjects();
                this.showCreateModal = false;
                this.newProjectName = '';
            }
        },
        
        async showKeys(project) {
            this.selectedProject = project;
            this.newKey = null;
            const token = localStorage.getItem('token');
            
            const response = await fetch(`/api/v1/projects/${project.id}/keys`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            if (response.ok) {
                const data = await response.json();
                this.keys = data.data;
                this.showKeysModal = true;
            }
        },
        
        async createKey() {
            const token = localStorage.getItem('token');
            const response = await fetch(`/api/v1/projects/${this.selectedProject.id}/keys`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name: 'API Key ' + new Date().toLocaleDateString('tr') })
            });
            
            if (response.ok) {
                const data = await response.json();
                this.newKey = data.data.key;
                await this.showKeys(this.selectedProject);
            }
        },
        
        async revokeKey(key) {
            if (!confirm('Bu API key iptal edilecek. Devam?')) return;
            
            const token = localStorage.getItem('token');
            await fetch(`/api/v1/projects/${this.selectedProject.id}/keys/${key.id}`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${token}` }
            });
            
            await this.showKeys(this.selectedProject);
        },
        
        copyKey() {
            navigator.clipboard.writeText(this.newKey);
            alert('Kopyalandı!');
        }
    }
}
</script>
@endpush
@endsection


