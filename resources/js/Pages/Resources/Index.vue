<template>
    <MainLayout>
    <Head title="Resources" />
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
                        <div class="card h-100">
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
                        <div class="card h-100">
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
                        <div class="card h-100">
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
                        <div class="card h-100">
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

                <!-- 1-Month Resource Usage History & Spike Analysis -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow-sm border">
                            <div class="card-header pb-0 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <h6 class="mb-0 d-flex align-items-center">
                                            <i class="material-symbols-rounded text-primary me-2">history</i>
                                            Resource Usage History &amp; Spike Analysis
                                        </h6>
                                        <p class="text-xs text-secondary mb-0">
                                            Accurate 30-day recorded time-series data with peak spike detection
                                        </p>
                                    </div>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm"
                                            :class="selectedRange === '24h' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="changeRange('24h')">24 Hours</button>
                                        <button type="button" class="btn btn-sm"
                                            :class="selectedRange === '7d' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="changeRange('7d')">7 Days</button>
                                        <button type="button" class="btn btn-sm"
                                            :class="selectedRange === '30d' ? 'btn-primary' : 'btn-outline-primary'"
                                            @click="changeRange('30d')">30 Days</button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <!-- Peak Summary Badges -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <span class="text-xs text-uppercase font-weight-bold text-secondary">Highest CPU Peak</span>
                                            <h5 class="mb-0 text-danger mt-1">
                                                {{ historySummary.peak_cpu?.value || 0 }}%
                                            </h5>
                                            <p class="text-xxs text-muted mb-0 mt-1" v-if="historySummary.peak_cpu">
                                                {{ historySummary.peak_cpu.time }} &bull; <span class="text-dark font-weight-bold">{{ historySummary.peak_cpu.process }}</span>
                                            </p>
                                            <p class="text-xxs text-muted mb-0 mt-1" v-else>No peak recorded</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <span class="text-xs text-uppercase font-weight-bold text-secondary">Highest RAM Peak</span>
                                            <h5 class="mb-0 text-warning mt-1">
                                                {{ historySummary.peak_memory?.value || 0 }}%
                                            </h5>
                                            <p class="text-xxs text-muted mb-0 mt-1" v-if="historySummary.peak_memory">
                                                {{ historySummary.peak_memory.time }} &bull; <span class="text-dark font-weight-bold">{{ historySummary.peak_memory.process }}</span>
                                            </p>
                                            <p class="text-xxs text-muted mb-0 mt-1" v-else>No peak recorded</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <span class="text-xs text-uppercase font-weight-bold text-secondary">Average CPU / RAM</span>
                                            <h5 class="mb-0 text-dark mt-1">
                                                {{ historySummary.avg_cpu || 0 }}% / {{ historySummary.avg_memory || 0 }}%
                                            </h5>
                                            <p class="text-xxs text-muted mb-0 mt-1">Avg 1m load: {{ historySummary.avg_load || 0 }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <span class="text-xs text-uppercase font-weight-bold text-secondary">High-Usage Incidents</span>
                                            <h5 class="mb-0 mt-1" :class="(historySummary.total_incidents || 0) > 0 ? 'text-danger' : 'text-success'">
                                                {{ historySummary.total_incidents || 0 }}
                                            </h5>
                                            <p class="text-xxs text-muted mb-0 mt-1">Times CPU &gt; 80% or RAM &gt; 85%</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Charts Row -->
                                <div class="row">
                                    <div class="col-lg-6 mb-4">
                                        <div class="border rounded-3 p-3 h-100 bg-white">
                                            <h6 class="text-sm font-weight-bold mb-2 d-flex align-items-center">
                                                <span class="badge bg-primary me-2">CPU</span>
                                                CPU Usage &amp; System Load Trend
                                            </h6>
                                            <div style="height: 250px; position: relative;">
                                                <canvas id="chart-history-cpu"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 mb-4">
                                        <div class="border rounded-3 p-3 h-100 bg-white">
                                            <h6 class="text-sm font-weight-bold mb-2 d-flex align-items-center">
                                                <span class="badge bg-info me-2">RAM</span>
                                                Memory Usage Trend (%)
                                            </h6>
                                            <div style="height: 250px; position: relative;">
                                                <canvas id="chart-history-memory"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Incident Log Table (if any) -->
                                <div v-if="incidents.length > 0" class="mt-2">
                                    <h6 class="text-xs text-uppercase font-weight-bold text-secondary mb-2">
                                        High Resource Spike Incidents Log
                                    </h6>
                                    <div class="table-responsive border rounded-3">
                                        <table class="table align-items-center mb-0 text-sm">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="text-xxs text-secondary">Time</th>
                                                    <th class="text-xxs text-secondary">CPU</th>
                                                    <th class="text-xxs text-secondary">RAM</th>
                                                    <th class="text-xxs text-secondary">1m Load</th>
                                                    <th class="text-xxs text-secondary">User</th>
                                                    <th class="text-xxs text-secondary">Top Process</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(inc, idx) in incidents" :key="idx">
                                                    <td class="text-xs">{{ inc.time }}</td>
                                                    <td><span class="badge bg-danger">{{ inc.cpu }}%</span></td>
                                                    <td><span class="badge bg-warning">{{ inc.memory }}%</span></td>
                                                    <td class="text-xs">{{ inc.load }}</td>
                                                    <td class="text-xs text-secondary">{{ inc.user }}</td>
                                                    <td class="text-xs text-break"><code>{{ inc.process }}</code></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
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
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import Chart from 'chart.js/auto'

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

const selectedRange = ref('24h')
const historySummary = ref({})
const incidents = ref([])
let historyCpuChart = null
let historyMemoryChart = null

let refreshInterval = null

const handleVisibilityChange = () => {
    if (typeof document !== 'undefined' && !document.hidden) {
        loadUsage()
        loadHistory()
    }
}

onMounted(async () => {
    await loadUsage()
    await loadHistory()
    // Auto-refresh instantaneous metrics every 10 seconds
    refreshInterval = setInterval(loadUsage, 10000)
    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleVisibilityChange)
    }
})

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval)
    if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', handleVisibilityChange)
    }
    destroyHistoryCharts()
})

const loadUsage = async () => {
    if (typeof document !== 'undefined' && document.hidden) return

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

const loadHistory = async () => {
    try {
        const response = await axios.get('/resources/history', {
            params: { range: selectedRange.value }
        })
        if (response.data.success) {
            historySummary.value = response.data.summary || {}
            incidents.value = response.data.incidents || []
            await nextTick()
            renderHistoryCharts(response.data.points || [])
        }
    } catch (error) {
        console.error('Failed to load resource history:', error)
    }
}

const changeRange = async (range) => {
    if (selectedRange.value === range) return
    selectedRange.value = range
    await loadHistory()
}

const destroyHistoryCharts = () => {
    if (historyCpuChart) {
        historyCpuChart.destroy()
        historyCpuChart = null
    }
    if (historyMemoryChart) {
        historyMemoryChart.destroy()
        historyMemoryChart = null
    }
}

const renderHistoryCharts = (points) => {
    destroyHistoryCharts()
    if (!points || points.length === 0) return

    const labels = points.map(p => p.time)
    const cpuData = points.map(p => p.cpu)
    const loadData = points.map(p => p.load)
    const memData = points.map(p => p.memory)

    const ctxCpu = document.getElementById('chart-history-cpu')
    if (ctxCpu) {
        historyCpuChart = new Chart(ctxCpu, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'CPU Usage (%)',
                        data: cpuData,
                        borderColor: '#e53e3e',
                        backgroundColor: 'rgba(229, 62, 62, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: points.length > 50 ? 0 : 2,
                        yAxisID: 'y',
                    },
                    {
                        label: '1m Load Average',
                        data: loadData,
                        borderColor: '#3182ce',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        tension: 0.3,
                        pointRadius: 0,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            title: (items) => points[items[0].dataIndex]?.full_time || items[0].label,
                            label: (item) => `${item.dataset.label}: ${item.raw}${item.datasetIndex === 0 ? '%' : ''}`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { min: 0, max: 100, title: { display: true, text: 'CPU %' } },
                    y1: { min: 0, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Load Avg' } }
                }
            }
        })
    }

    const ctxMem = document.getElementById('chart-history-memory')
    if (ctxMem) {
        historyMemoryChart = new Chart(ctxMem, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Memory Usage (%)',
                        data: memData,
                        borderColor: '#dd6b20',
                        backgroundColor: 'rgba(221, 107, 32, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: points.length > 50 ? 0 : 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            title: (items) => points[items[0].dataIndex]?.full_time || items[0].label,
                            label: (item) => {
                                const pt = points[item.dataIndex]
                                return `Memory: ${item.raw}% (${pt?.memory_used_mb || 0} MB used of ${pt?.memory_total_mb || 0} MB)`
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { min: 0, max: 100, title: { display: true, text: 'Memory %' } }
                }
            }
        })
    }
}
</script>


