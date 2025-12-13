<template>
    <MainLayout>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-primary">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h4 class="text-white mb-0">
                                        <i class="material-symbols-rounded me-2">monitoring</i>
                                        Server Resources
                                    </h4>
                                    <p class="text-white text-sm mb-0 opacity-8">
                                        Real-time server monitoring • Uptime: {{ data.uptime?.formatted || 'Loading...'
                                        }}
                                    </p>
                                </div>
                                <div class="col-4 text-end">
                                    <button class="btn btn-outline-white btn-sm" @click="loadUsage">
                                        <i class="material-symbols-rounded text-sm me-1">refresh</i>
                                        Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Loading server metrics...</p>
            </div>

            <div v-else>
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <!-- CPU -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">CPU Usage</p>
                                            <h5 class="font-weight-bolder mb-0">
                                                {{ data.cpu?.usage || 0 }}%
                                            </h5>
                                            <span class="text-sm text-secondary">{{ data.cpu?.cores }} cores</span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div
                                            class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                            <i class="material-symbols-rounded text-lg opacity-10">memory</i>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar bg-gradient-primary"
                                        :style="{ width: (data.cpu?.usage || 0) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Memory -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">Memory</p>
                                            <h5 class="font-weight-bolder mb-0">
                                                {{ data.memory?.percentage || 0 }}%
                                            </h5>
                                            <span class="text-sm text-secondary">{{ data.memory?.used }} / {{
                                                data.memory?.total }}</span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div
                                            class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                            <i class="material-symbols-rounded text-lg opacity-10">database</i>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar bg-gradient-success"
                                        :style="{ width: (data.memory?.percentage || 0) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Load Average -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">Load Average</p>
                                            <h5 class="font-weight-bolder mb-0">
                                                {{ data.load?.['1min'] || 0 }}
                                            </h5>
                                            <span class="text-sm text-secondary">{{ data.load?.['5min'] }} / {{
                                                data.load?.['15min'] }}</span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div
                                            class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                                            <i class="material-symbols-rounded text-lg opacity-10">speed</i>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar bg-gradient-warning"
                                        :style="{ width: Math.min(data.load?.percentage || 0, 100) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Uptime -->
                    <div class="col-xl-3 col-sm-6">
                        <div class="card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">Uptime</p>
                                            <h5 class="font-weight-bolder mb-0">
                                                {{ data.uptime?.days || 0 }} days
                                            </h5>
                                            <span class="text-sm text-secondary">{{ data.uptime?.hours }}h {{
                                                data.uptime?.minutes }}m</span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div
                                            class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                            <i class="material-symbols-rounded text-lg opacity-10">schedule</i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Disk Usage -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header pb-0">
                                <h6 class="mb-0">
                                    <i class="material-symbols-rounded text-sm me-1">hard_drive</i>
                                    Disk Usage
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div v-for="disk in data.disk" :key="disk.mount" class="mb-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-sm fw-bold">{{ disk.mount }}</span>
                                        <span class="text-sm text-secondary">{{ disk.used }} / {{ disk.total }}</span>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar"
                                            :class="disk.percentage > 90 ? 'bg-danger' : disk.percentage > 75 ? 'bg-warning' : 'bg-success'"
                                            :style="{ width: disk.percentage + '%' }">
                                        </div>
                                    </div>
                                    <small class="text-muted">{{ disk.percentage }}% used • {{ disk.available }}
                                        free</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Network -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header pb-0">
                                <h6 class="mb-0">
                                    <i class="material-symbols-rounded text-sm me-1">lan</i>
                                    Network Interfaces
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">
                                                    Interface</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">RX
                                                    (Received)</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">TX
                                                    (Sent)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="net in data.network" :key="net.interface">
                                                <td>
                                                    <span class="text-sm font-weight-bold">{{ net.interface }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-gradient-success">↓ {{ net.rx }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-gradient-info">↑ {{ net.tx }}</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Processes -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6 class="mb-0">
                                    <i class="material-symbols-rounded text-sm me-1">terminal</i>
                                    Top Processes (by CPU)
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">
                                                    PID</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">
                                                    User</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">
                                                    CPU %</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">
                                                    MEM %</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">
                                                    Command</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="proc in data.processes" :key="proc.pid">
                                                <td><span class="text-sm">{{ proc.pid }}</span></td>
                                                <td><span class="text-sm text-secondary">{{ proc.user }}</span></td>
                                                <td>
                                                    <span class="badge"
                                                        :class="parseFloat(proc.cpu) > 50 ? 'bg-danger' : 'bg-success'">
                                                        {{ proc.cpu }}%
                                                    </span>
                                                </td>
                                                <td><span class="text-sm">{{ proc.memory }}%</span></td>
                                                <td><code class="text-xs">{{ proc.command }}</code></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CPU Info -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card bg-gradient-dark">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <i class="material-symbols-rounded text-white me-3">memory</i>
                                    <div>
                                        <p class="text-white mb-0 text-sm">CPU Model</p>
                                        <h6 class="text-white mb-0">{{ data.cpu?.model || 'Unknown' }}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const loading = ref(true)
const data = ref({
    cpu: {},
    memory: {},
    disk: [],
    load: {},
    uptime: {},
    network: [],
    processes: []
})

let refreshInterval = null

onMounted(async () => {
    await loadUsage()
    // Auto-refresh every 10 seconds
    refreshInterval = setInterval(loadUsage, 10000)
})

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval)
})

const loadUsage = async () => {
    try {
        const response = await axios.get('/resources/usage')
        if (response.data.success) {
            data.value = response.data.data
        }
    } catch (error) {
        console.error('Failed to load resource usage:', error)
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.icon-shape {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress {
    background-color: #e9ecef;
    border-radius: 0.5rem;
}
</style>
