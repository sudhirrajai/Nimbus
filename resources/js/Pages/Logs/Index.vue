<template>
    <MainLayout>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-dark">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h4 class="text-white mb-0">
                                        <i class="material-symbols-rounded me-2">description</i>
                                        System Logs
                                    </h4>
                                    <p class="text-white text-sm mb-0 opacity-8">View and manage server log files</p>
                                </div>
                                <div class="col-4 text-end">
                                    <button class="btn btn-outline-white btn-sm" @click="loadLogFiles">
                                        <i class="material-symbols-rounded text-sm me-1">refresh</i>
                                        Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Log Files List -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">Available Logs</h6>
                        </div>
                        <div class="card-body p-3">
                            <div v-if="loading" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div v-else>
                                <!-- Log Type Filters -->
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    <button class="btn btn-xs"
                                        :class="filter === 'all' ? 'btn-primary' : 'btn-outline-primary'"
                                        @click="filter = 'all'">All</button>
                                    <button class="btn btn-xs"
                                        :class="filter === 'nginx' ? 'btn-info' : 'btn-outline-info'"
                                        @click="filter = 'nginx'">Nginx</button>
                                    <button class="btn btn-xs"
                                        :class="filter === 'laravel' ? 'btn-danger' : 'btn-outline-danger'"
                                        @click="filter = 'laravel'">Laravel</button>
                                    <button class="btn btn-xs"
                                        :class="filter === 'system' ? 'btn-warning' : 'btn-outline-warning'"
                                        @click="filter = 'system'">System</button>
                                </div>

                                <div class="list-group list-group-flush">
                                    <a v-for="log in filteredLogs" :key="log.path" href="#"
                                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                        :class="{ active: selectedLog?.path === log.path }"
                                        @click.prevent="selectLog(log)">
                                        <div>
                                            <i class="material-symbols-rounded text-sm me-2"
                                                :class="getLogIcon(log.type)">description</i>
                                            <span class="text-sm">{{ log.name }}</span>
                                            <br>
                                            <small class="text-muted">{{ log.size }} • {{ log.modified }}</small>
                                        </div>
                                        <span :class="getLogBadge(log.type)">{{ log.type }}</span>
                                    </a>
                                </div>

                                <p v-if="filteredLogs.length === 0" class="text-muted text-center py-4">
                                    No log files found
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Content Viewer -->
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">{{ selectedLog?.name || 'Select a log file' }}</h6>
                                <small v-if="selectedLog" class="text-muted">{{ selectedLog.path }}</small>
                            </div>
                            <div v-if="selectedLog" class="d-flex gap-2">
                                <select class="form-select form-select-sm" v-model="lines" @change="loadLogContent"
                                    style="width: auto;">
                                    <option :value="50">Last 50 lines</option>
                                    <option :value="100">Last 100 lines</option>
                                    <option :value="200">Last 200 lines</option>
                                    <option :value="500">Last 500 lines</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" @click="loadLogContent" title="Refresh">
                                    <i class="material-symbols-rounded text-sm">refresh</i>
                                </button>
                                <a :href="`/logs/download?path=${encodeURIComponent(selectedLog.path)}`"
                                    class="btn btn-sm btn-outline-success" title="Download">
                                    <i class="material-symbols-rounded text-sm">download</i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger" @click="clearLog" title="Clear Log">
                                    <i class="material-symbols-rounded text-sm">delete</i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div v-if="!selectedLog" class="text-center py-5">
                                <i class="material-symbols-rounded text-5xl text-muted">file_open</i>
                                <p class="text-muted mt-2">Select a log file from the list to view its contents</p>
                            </div>
                            <div v-else-if="loadingContent" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <pre v-else class="log-viewer">{{ logContent || 'No content or empty log file' }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                <div class="toast align-items-center border-0"
                    :class="toastType === 'success' ? 'bg-success' : 'bg-danger'"
                    :style="showToast ? 'display: block;' : 'display: none;'" role="alert">
                    <div class="d-flex">
                        <div class="toast-body text-white">{{ toastMessage }}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            @click="showToast = false"></button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(true)
const loadingContent = ref(false)
const logFiles = ref([])
const selectedLog = ref(null)
const logContent = ref('')
const lines = ref(100)
const filter = ref('all')

// Toast
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const filteredLogs = computed(() => {
    if (filter.value === 'all') return logFiles.value
    return logFiles.value.filter(log => log.type === filter.value)
})

onMounted(async () => {
    await loadLogFiles()
})

const loadLogFiles = async () => {
    loading.value = true
    try {
        const response = await axios.get('/logs/files')
        logFiles.value = response.data.logs || []
    } catch (error) {
        notify('Failed to load log files', 'error')
    } finally {
        loading.value = false
    }
}

const selectLog = async (log) => {
    selectedLog.value = log
    await loadLogContent()
}

const loadLogContent = async () => {
    if (!selectedLog.value) return
    loadingContent.value = true
    try {
        const response = await axios.get('/logs/read', {
            params: { path: selectedLog.value.path, lines: lines.value }
        })
        logContent.value = response.data.content || ''
    } catch (error) {
        logContent.value = 'Error loading log content: ' + (error.response?.data?.error || error.message)
    } finally {
        loadingContent.value = false
    }
}

const clearLog = async () => {
    if (!selectedLog.value) return
    if (!confirm(`Clear ${selectedLog.value.name}? This cannot be undone.`)) return

    try {
        await axios.post('/logs/clear', { path: selectedLog.value.path })
        notify('Log cleared successfully', 'success')
        await loadLogContent()
    } catch (error) {
        notify('Failed to clear log', 'error')
    }
}

const getLogIcon = (type) => {
    const icons = {
        'nginx': 'text-info',
        'laravel': 'text-danger',
        'system': 'text-warning',
        'php': 'text-purple',
        'supervisor': 'text-success'
    }
    return icons[type] || 'text-secondary'
}

const getLogBadge = (type) => {
    const badges = {
        'nginx': 'badge bg-gradient-info',
        'laravel': 'badge bg-gradient-danger',
        'system': 'badge bg-gradient-warning',
        'php': 'badge bg-gradient-primary',
        'supervisor': 'badge bg-gradient-success'
    }
    return badges[type] || 'badge bg-gradient-secondary'
}

const notify = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 4000)
}
</script>

<style scoped>
.log-viewer {
    background-color: #1e1e2e;
    color: #cdd6f4;
    font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
    font-size: 12px;
    line-height: 1.5;
    padding: 16px;
    border-radius: 8px;
    height: 500px;
    overflow: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
    margin: 0;
}

.list-group-item.active {
    background-color: #e91e63 !important;
    border-color: #e91e63 !important;
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>
