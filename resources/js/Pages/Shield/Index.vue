<template>
    <MainLayout>
        <div class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card bg-gradient-dark shadow-dark">
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-8">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon icon-shape bg-gradient-primary shadow-primary text-center border-radius-md me-3">
                                            <i class="material-symbols-rounded opacity-10">shield</i>
                                        </div>
                                        <h3 class="text-white mb-0">Nimbus Shield</h3>
                                    </div>
                                    <p class="text-white opacity-8 mb-4">
                                        Active protection for your server. Scan for threats, manage firewall rules, and monitor system integrity.
                                    </p>
                                    <div class="d-flex gap-2">
                                        <button @click="startScan('/var/www')" :disabled="scanning" class="btn btn-primary mb-0">
                                            <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin': scanning }">search</i>
                                            {{ scanning ? 'Scanning...' : 'Quick Scan' }}
                                        </button>
                                        <button @click="startScan('/usr/local/nimbus')" :disabled="scanning" class="btn btn-outline-white mb-0">
                                            Full System Scan
                                        </button>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="chart">
                                        <div class="status-badge" :class="stats.active_threats > 0 ? 'bg-danger' : 'bg-success'">
                                            <i class="material-symbols-rounded text-white" style="font-size: 48px;">{{ stats.active_threats > 0 ? 'warning' : 'check_circle' }}</i>
                                        </div>
                                        <h5 class="text-white mt-3">{{ stats.active_threats > 0 ? 'Threats Detected' : 'System Secure' }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header pb-0 p-3">
                            <h6 class="mb-0">Security Overview</h6>
                        </div>
                        <div class="card-body p-3">
                            <ul class="list-group">
                                <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                    <div class="d-flex align-items-center">
                                        <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center">
                                            <i class="material-symbols-rounded opacity-10">local_fire_department</i>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <h6 class="mb-1 text-dark text-sm">Firewall (UFW)</h6>
                                            <span class="text-xs">System Level Protection</span>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <span class="badge" :class="stats.firewall_status === 'Active' ? 'badge-success' : 'badge-danger'">{{ stats.firewall_status }}</span>
                                    </div>
                                </li>
                                <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                                    <div class="d-flex align-items-center">
                                        <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center">
                                            <i class="material-symbols-rounded opacity-10">history</i>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <h6 class="mb-1 text-dark text-sm">Last Scan</h6>
                                            <span class="text-xs">{{ stats.last_scan }}</span>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item border-0 d-flex justify-content-between ps-0 border-radius-lg">
                                    <div class="d-flex align-items-center">
                                        <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center">
                                            <i class="material-symbols-rounded opacity-10">inventory_2</i>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <h6 class="mb-1 text-dark text-sm">Quarantined</h6>
                                            <span class="text-xs">{{ stats.quarantined }} files isolated</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-dark shadow-dark border-radius-lg pt-4 pb-3 d-flex justify-content-between align-items-center px-3">
                                <h6 class="text-white text-capitalize ps-3">Threat Detection Log</h6>
                                <div class="input-group input-group-sm w-25">
                                    <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                                    <input v-model="searchQuery" type="text" class="form-control" placeholder="Filter threats...">
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div v-if="loading" class="text-center py-5">
                                <div class="spinner-border text-dark" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <div v-else-if="filteredThreats.length === 0" class="text-center py-5">
                                <i class="material-symbols-rounded text-secondary mb-2" style="font-size: 48px;">verified_user</i>
                                <p class="text-secondary">No active threats detected. Your system is clean.</p>
                            </div>
                            <div v-else class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">File Path</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Type</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Detected</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-secondary opacity-7"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="threat in filteredThreats" :key="threat.id">
                                            <td>
                                                <div class="d-flex px-3 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm">{{ threat.file_path }}</h6>
                                                        <p class="text-xs text-secondary mb-0">{{ threat.details }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-sm" :class="threat.type.includes('Shell') ? 'bg-gradient-danger' : 'bg-gradient-warning'">
                                                    {{ threat.type }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold">{{ threat.detected_at }}</span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="badge badge-sm border" :class="getStatusBadgeClass(threat.status)">
                                                    {{ threat.status }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-right px-3">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button v-if="threat.status === 'detected'" @click="quarantineThreat(threat.id)" class="btn btn-link text-warning text-gradient px-3 mb-0">
                                                        <i class="material-symbols-rounded text-sm me-2">inventory_2</i>Quarantine
                                                    </button>
                                                    <button @click="deleteThreat(threat.id)" class="btn btn-link text-danger text-gradient px-3 mb-0">
                                                        <i class="material-symbols-rounded text-sm me-2">delete</i>Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notifications -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div v-if="showToast" class="toast show align-items-center text-white border-0" :class="'bg-' + toastType" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        {{ toastMessage }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="showToast = false"></button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const loading = ref(true)
const scanning = ref(false)
const threats = ref([])
const stats = ref({
    active_threats: 0,
    quarantined: 0,
    last_scan: 'Never',
    firewall_status: 'Checking...'
})

const searchQuery = ref('')
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const loadStatus = async () => {
    try {
        const response = await axios.get('/shield/status')
        if (response.data.success) {
            threats.value = response.data.threats
            stats.value = response.data.stats
        }
    } catch (error) {
        showNotification('Failed to load status', 'danger')
    } finally {
        loading.value = false
    }
}

const startScan = async (path) => {
    scanning.value = true
    showNotification('Starting scan in ' + path + '...', 'info')
    try {
        const response = await axios.post('/shield/scan', { path })
        if (response.data.success) {
            showNotification('Scan completed! ' + response.data.findings_count + ' findings.', 'success')
            await loadStatus()
        }
    } catch (error) {
        showNotification('Scan failed: ' + (error.response?.data?.error || error.message), 'danger')
    } finally {
        scanning.value = false
    }
}

const quarantineThreat = async (id) => {
    if (!confirm('Are you sure you want to quarantine this file? It will be moved to a secure location and made inaccessible.')) return
    try {
        const response = await axios.post('/shield/quarantine', { id })
        if (response.data.success) {
            showNotification('File quarantined', 'success')
            await loadStatus()
        }
    } catch (error) {
        showNotification('Quarantine failed', 'danger')
    }
}

const deleteThreat = async (id) => {
    if (!confirm('PERMANENTLY DELETE this file? This cannot be undone.')) return
    try {
        const response = await axios.post('/shield/delete', { id })
        if (response.data.success) {
            showNotification('File deleted permanently', 'success')
            await loadStatus()
        }
    } catch (error) {
        showNotification('Delete failed', 'danger')
    }
}

const filteredThreats = computed(() => {
    if (!searchQuery.value) return threats.value
    const q = searchQuery.value.toLowerCase()
    return threats.value.filter(t => 
        t.file_path.toLowerCase().includes(q) || 
        t.type.toLowerCase().includes(q)
    )
})

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'detected': return 'border-danger text-danger'
        case 'quarantined': return 'border-warning text-warning'
        case 'deleted': return 'border-secondary text-secondary'
        default: return 'border-secondary text-secondary'
    }
}

const showNotification = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => { showToast.value = false }, 4000)
}

onMounted(() => {
    loadStatus()
})
</script>

<style scoped>
.spin {
    animation: fa-spin 2s infinite linear;
}
@keyframes fa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(359deg); }
}
.status-badge {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 0 20px rgba(0,0,0,0.2);
}
</style>
