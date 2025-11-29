<template>
  <MainLayout>
    <div class="row">
      <div class="ms-3">
        <h3 class="mb-0 h4 font-weight-bolder">Server Dashboard</h3>
        <p class="mb-4">Real-time server monitoring and statistics</p>
      </div>

      <!-- Server Stats Cards -->
      <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
          <div class="card-header p-2 ps-3">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-sm mb-0 text-capitalize">CPU Usage</p>
                <h4 class="mb-0">{{ liveStats.cpu.usage }}%</h4>
                <p class="text-xs mb-0 mt-1">{{ liveStats.cpu.cores }} Cores</p>
              </div>
              <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary shadow text-center border-radius-lg">
                <i class="material-symbols-rounded opacity-10">memory</i>
              </div>
            </div>
          </div>
          <hr class="dark horizontal my-0">
          <div class="card-footer p-2 ps-3">
            <p class="mb-0 text-sm">
              <span class="text-muted">Load: {{ liveStats.cpu.load_1min }}</span>
            </p>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
          <div class="card-header p-2 ps-3">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-sm mb-0 text-capitalize">RAM Usage</p>
                <h4 class="mb-0">{{ liveStats.memory.usage_percent }}%</h4>
                <p class="text-xs mb-0 mt-1">
                  {{ liveStats.memory.used }} / {{ liveStats.memory.total }}
                </p>
              </div>
              <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                <i class="material-symbols-rounded opacity-10">storage</i>
              </div>
            </div>
          </div>
          <hr class="dark horizontal my-0">
          <div class="card-footer p-2 ps-3">
            <p class="mb-0 text-sm">
              <span class="text-muted">Free: {{ liveStats.memory.free }}</span>
            </p>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
        <div class="card">
          <div class="card-header p-2 ps-3">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-sm mb-0 text-capitalize">Disk Usage</p>
                <h4 class="mb-0">{{ liveStats.disk.usage_percent }}%</h4>
                <p class="text-xs mb-0 mt-1">
                  {{ liveStats.disk.used }} / {{ liveStats.disk.total }}
                </p>
              </div>
              <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                <i class="material-symbols-rounded opacity-10">hard_drive</i>
              </div>
            </div>
          </div>
          <hr class="dark horizontal my-0">
          <div class="card-footer p-2 ps-3">
            <p class="mb-0 text-sm">
              <span class="text-muted">Free: {{ liveStats.disk.free }}</span>
            </p>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-sm-6">
        <div class="card">
          <div class="card-header p-2 ps-3">
            <div class="d-flex justify-content-between">
              <div>
                <p class="text-sm mb-0 text-capitalize">Server Uptime</p>
                <h4 class="mb-0 text-xs">{{ liveStats.uptime.formatted }}</h4>
                <p class="text-xs mb-0 mt-1">{{ liveStats.processes }} processes</p>
              </div>
              <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                <i class="material-symbols-rounded opacity-10">schedule</i>
              </div>
            </div>
          </div>
          <hr class="dark horizontal my-0">
          <div class="card-footer p-2 ps-3">
            <p class="mb-0 text-sm">
              <span class="text-success font-weight-bolder">
                <i class="material-symbols-rounded text-xs">check_circle</i> Online
              </span>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
      <div class="col-lg-4 col-md-6 mt-4 mb-4">
        <div class="card">
          <div class="card-body">
            <h6 class="mb-0">CPU Usage History</h6>
            <p class="text-sm">Last hour performance</p>
            <div class="pe-2">
              <div class="chart">
                <canvas id="chart-cpu" class="chart-canvas" height="170"></canvas>
              </div>
            </div>
            <hr class="dark horizontal">
            <div class="d-flex">
              <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
              <p class="mb-0 text-sm">Real-time monitoring</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-md-6 mt-4 mb-4">
        <div class="card">
          <div class="card-body">
            <h6 class="mb-0">Memory Usage</h6>
            <p class="text-sm">RAM consumption over time</p>
            <div class="pe-2">
              <div class="chart">
                <canvas id="chart-memory" class="chart-canvas" height="170"></canvas>
              </div>
            </div>
            <hr class="dark horizontal">
            <div class="d-flex">
              <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
              <p class="mb-0 text-sm">Updated every 5 seconds</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 mt-4 mb-3">
        <div class="card">
          <div class="card-body">
            <h6 class="mb-0">Load Average</h6>
            <p class="text-sm">System load distribution</p>
            <div class="pe-2">
              <div class="chart">
                <canvas id="chart-load" class="chart-canvas" height="170"></canvas>
              </div>
            </div>
            <hr class="dark horizontal">
            <div class="d-flex">
              <i class="material-symbols-rounded text-sm my-auto me-1">schedule</i>
              <p class="mb-0 text-sm">{{ liveStats.cpu.cores }} CPU cores</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Resource Overview Row -->
    <div class="row mb-4">
      <div class="col-lg-8 col-md-6 mb-md-0 mb-4">
        <div class="card">
          <div class="card-header pb-0">
            <div class="row">
              <div class="col-lg-6 col-7">
                <h6>System Resources</h6>
                <p class="text-sm mb-0">
                  <i class="fa fa-check text-info" aria-hidden="true"></i>
                  <span class="font-weight-bold ms-1">All systems operational</span>
                </p>
              </div>
              <div class="col-lg-6 col-5 my-auto text-end">
                <button
                  @click="refreshStats"
                  class="btn btn-sm btn-outline-primary mb-0"
                  :disabled="isRefreshing"
                >
                  <i class="material-symbols-rounded text-xs">refresh</i>
                  {{ isRefreshing ? 'Refreshing...' : 'Refresh' }}
                </button>
              </div>
            </div>
          </div>
          <div class="card-body px-0 pb-2">
            <div class="table-responsive">
              <table class="table align-items-center mb-0">
                <thead>
                  <tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Resource</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Current</th>
                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Usage</th>
                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <div class="d-flex px-2 py-1">
                        <div class="icon icon-sm icon-shape bg-gradient-primary shadow text-center border-radius-md me-3">
                          <i class="material-symbols-rounded opacity-10 text-white text-xs">memory</i>
                        </div>
                        <div class="d-flex flex-column justify-content-center">
                          <h6 class="mb-0 text-sm">CPU</h6>
                          <p class="text-xs text-secondary mb-0">{{ liveStats.cpu.cores }} cores</p>
                        </div>
                      </div>
                    </td>
                    <td>
                      <p class="text-xs font-weight-bold mb-0">{{ liveStats.cpu.usage }}%</p>
                      <p class="text-xs text-secondary mb-0">Load: {{ liveStats.cpu.load_1min }}</p>
                    </td>
                    <td class="align-middle">
                      <div class="progress-wrapper w-75 mx-auto">
                        <div class="progress-info">
                          <div class="progress-percentage">
                            <span class="text-xs font-weight-bold">{{ liveStats.cpu.usage }}%</span>
                          </div>
                        </div>
                        <div class="progress">
                          <div
                            class="progress-bar"
                            :class="getProgressClass(liveStats.cpu.usage)"
                            :style="`width: ${Math.min(liveStats.cpu.usage, 100)}%`"
                            role="progressbar"
                          ></div>
                        </div>
                      </div>
                    </td>
                    <td class="align-middle text-center">
                      <span class="badge badge-sm" :class="getStatusBadge(liveStats.cpu.usage)">
                        {{ getStatusText(liveStats.cpu.usage) }}
                      </span>
                    </td>
                  </tr>

                  <tr>
                    <td>
                      <div class="d-flex px-2 py-1">
                        <div class="icon icon-sm icon-shape bg-gradient-success shadow text-center border-radius-md me-3">
                          <i class="material-symbols-rounded opacity-10 text-white text-xs">storage</i>
                        </div>
                        <div class="d-flex flex-column justify-content-center">
                          <h6 class="mb-0 text-sm">RAM</h6>
                          <p class="text-xs text-secondary mb-0">{{ liveStats.memory.total }}</p>
                        </div>
                      </div>
                    </td>
                    <td>
                      <p class="text-xs font-weight-bold mb-0">{{ liveStats.memory.used }}</p>
                      <p class="text-xs text-secondary mb-0">Free: {{ liveStats.memory.free }}</p>
                    </td>
                    <td class="align-middle">
                      <div class="progress-wrapper w-75 mx-auto">
                        <div class="progress-info">
                          <div class="progress-percentage">
                            <span class="text-xs font-weight-bold">{{ liveStats.memory.usage_percent }}%</span>
                          </div>
                        </div>
                        <div class="progress">
                          <div
                            class="progress-bar"
                            :class="getProgressClass(liveStats.memory.usage_percent)"
                            :style="`width: ${liveStats.memory.usage_percent}%`"
                            role="progressbar"
                          ></div>
                        </div>
                      </div>
                    </td>
                    <td class="align-middle text-center">
                      <span class="badge badge-sm" :class="getStatusBadge(liveStats.memory.usage_percent)">
                        {{ getStatusText(liveStats.memory.usage_percent) }}
                      </span>
                    </td>
                  </tr>

                  <tr>
                    <td>
                      <div class="d-flex px-2 py-1">
                        <div class="icon icon-sm icon-shape bg-gradient-info shadow text-center border-radius-md me-3">
                          <i class="material-symbols-rounded opacity-10 text-white text-xs">hard_drive</i>
                        </div>
                        <div class="d-flex flex-column justify-content-center">
                          <h6 class="mb-0 text-sm">Disk</h6>
                          <p class="text-xs text-secondary mb-0">{{ liveStats.disk.total }}</p>
                        </div>
                      </div>
                    </td>
                    <td>
                      <p class="text-xs font-weight-bold mb-0">{{ liveStats.disk.used }}</p>
                      <p class="text-xs text-secondary mb-0">Free: {{ liveStats.disk.free }}</p>
                    </td>
                    <td class="align-middle">
                      <div class="progress-wrapper w-75 mx-auto">
                        <div class="progress-info">
                          <div class="progress-percentage">
                            <span class="text-xs font-weight-bold">{{ liveStats.disk.usage_percent }}%</span>
                          </div>
                        </div>
                        <div class="progress">
                          <div
                            class="progress-bar"
                            :class="getProgressClass(liveStats.disk.usage_percent)"
                            :style="`width: ${liveStats.disk.usage_percent}%`"
                            role="progressbar"
                          ></div>
                        </div>
                      </div>
                    </td>
                    <td class="align-middle text-center">
                      <span class="badge badge-sm" :class="getStatusBadge(liveStats.disk.usage_percent)">
                        {{ getStatusText(liveStats.disk.usage_percent) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 col-md-6">
        <div class="card h-100">
          <div class="card-header pb-0">
            <h6>System Information</h6>
            <p class="text-sm">
              <i class="fa fa-server text-info" aria-hidden="true"></i>
              <span class="font-weight-bold">Server Status</span>
            </p>
          </div>
          <div class="card-body p-3">
            <div class="timeline timeline-one-side">
              <div class="timeline-block mb-3">
                <span class="timeline-step">
                  <i class="material-symbols-rounded text-success text-gradient">schedule</i>
                </span>
                <div class="timeline-content">
                  <h6 class="text-dark text-sm font-weight-bold mb-0">Uptime</h6>
                  <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">
                    {{ liveStats.uptime.formatted }}
                  </p>
                </div>
              </div>

              <div class="timeline-block mb-3">
                <span class="timeline-step">
                  <i class="material-symbols-rounded text-info text-gradient">apps</i>
                </span>
                <div class="timeline-content">
                  <h6 class="text-dark text-sm font-weight-bold mb-0">Active Processes</h6>
                  <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">
                    {{ liveStats.processes }} running
                  </p>
                </div>
              </div>

              <div class="timeline-block mb-3">
                <span class="timeline-step">
                  <i class="material-symbols-rounded text-warning text-gradient">speed</i>
                </span>
                <div class="timeline-content">
                  <h6 class="text-dark text-sm font-weight-bold mb-0">Load Average</h6>
                  <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">
                    1m: {{ liveStats.load['1min'] }} |
                    5m: {{ liveStats.load['5min'] }} |
                    15m: {{ liveStats.load['15min'] }}
                  </p>
                </div>
              </div>

              <div class="timeline-block">
                <span class="timeline-step">
                  <i class="material-symbols-rounded text-primary text-gradient">memory</i>
                </span>
                <div class="timeline-content">
                  <h6 class="text-dark text-sm font-weight-bold mb-0">CPU Cores</h6>
                  <p class="text-secondary font-weight-bold text-xs mt-1 mb-0">
                    {{ liveStats.cpu.cores }} cores available
                  </p>
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
import { ref, onMounted, onUnmounted } from 'vue'
import MainLayout from '@/Layouts/MainLayout.vue'
import axios from 'axios'

const props = defineProps({
  serverStats: {
    type: Object,
    required: true
  }
})

const liveStats = ref({ ...props.serverStats })
const isRefreshing = ref(false)
const chartsReady = ref(false)

let cpuChart = null
let memoryChart = null
let loadChart = null
let refreshInterval = null

const cpuHistory = ref([])
const memoryHistory = ref([])
const loadHistory = ref([])
const timeLabels = ref([])

onMounted(() => {
  console.log('Component mounted, loading Chart.js...')
  loadChartJs()
})

onUnmounted(() => {
  console.log('Component unmounting, cleaning up...')
  if (refreshInterval) {
    clearInterval(refreshInterval)
    refreshInterval = null
  }
  destroyCharts()
})

const destroyCharts = () => {
  if (cpuChart) {
    cpuChart.destroy()
    cpuChart = null
  }
  if (memoryChart) {
    memoryChart.destroy()
    memoryChart = null
  }
  if (loadChart) {
    loadChart.destroy()
    loadChart = null
  }
}

const loadChartJs = () => {
  if (typeof Chart !== 'undefined') {
    console.log('Chart.js already loaded')
    initializeApp()
    return
  }

  const script = document.createElement('script')
  script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
  script.async = true
  
  script.onload = () => {
    console.log('Chart.js loaded successfully')
    initializeApp()
  }
  
  script.onerror = () => {
    console.error('Failed to load Chart.js')
  }
  
  document.head.appendChild(script)
}

const initializeApp = () => {
  setTimeout(() => {
    if (typeof Chart !== 'undefined') {
      console.log('Initializing charts and auto-refresh...')
      initializeHistoryData()
      initializeCharts()
      startAutoRefresh()
      chartsReady.value = true
    } else {
      console.error('Chart.js is still not available')
    }
  }, 300)
}

const initializeHistoryData = () => {
  const now = new Date()
  for (let i = 11; i >= 0; i--) {
    const time = new Date(now - i * 5000)
    timeLabels.value.push(formatTime(time))
    cpuHistory.value.push(liveStats.value.cpu.usage)
    memoryHistory.value.push(liveStats.value.memory.usage_percent)
    loadHistory.value.push(parseFloat(liveStats.value.cpu.load_1min))
  }
  console.log('History data initialized:', {
    cpu: cpuHistory.value,
    memory: memoryHistory.value,
    load: loadHistory.value
  })
}

const initializeCharts = () => {
  if (typeof Chart === 'undefined') {
    console.error('Cannot initialize charts: Chart.js not loaded')
    return
  }

  const ctxCpu = document.getElementById('chart-cpu')
  if (ctxCpu) {
    cpuChart = new Chart(ctxCpu, {
      type: 'line',
      data: {
        labels: [...timeLabels.value],
        datasets: [{
          label: 'CPU %',
          tension: 0.4,
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#5e72e4',
          pointBorderColor: 'transparent',
          borderColor: '#5e72e4',
          backgroundColor: 'rgba(94, 114, 228, 0.1)',
          fill: true,
          data: [...cpuHistory.value]
        }]
      },
      options: getChartOptions(100)
    })
    console.log('CPU chart initialized')
  }

  const ctxMemory = document.getElementById('chart-memory')
  if (ctxMemory) {
    memoryChart = new Chart(ctxMemory, {
      type: 'line',
      data: {
        labels: [...timeLabels.value],
        datasets: [{
          label: 'Memory %',
          tension: 0.4,
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#2dce89',
          pointBorderColor: 'transparent',
          borderColor: '#2dce89',
          backgroundColor: 'rgba(45, 206, 137, 0.1)',
          fill: true,
          data: [...memoryHistory.value]
        }]
      },
      options: getChartOptions(100)
    })
    console.log('Memory chart initialized')
  }

  const ctxLoad = document.getElementById('chart-load')
  if (ctxLoad) {
    loadChart = new Chart(ctxLoad, {
      type: 'bar',
      data: {
        labels: ['1 min', '5 min', '15 min'],
        datasets: [{
          label: 'Load',
          backgroundColor: ['#5e72e4', '#11cdef', '#2dce89'],
          data: [
            parseFloat(liveStats.value.cpu.load_1min),
            parseFloat(liveStats.value.cpu.load_5min),
            parseFloat(liveStats.value.cpu.load_15min)
          ],
          barThickness: 40
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { enabled: true }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              color: '#737373',
              font: { size: 12 }
            },
            grid: {
              color: '#e5e5e5',
              borderDash: [5, 5]
            }
          },
          x: {
            ticks: {
              color: '#737373',
              font: { size: 12 }
            },
            grid: { display: false }
          }
        }
      }
    })
    console.log('Load chart initialized')
  }
}

const getChartOptions = (maxValue) => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true }
  },
  interaction: {
    intersect: false,
    mode: 'index'
  },
  scales: {
    y: {
      beginAtZero: true,
      max: maxValue,
      ticks: {
        color: '#737373',
        font: { size: 12 },
        callback: (value) => value + '%'
      },
      grid: {
        color: '#e5e5e5',
        borderDash: [4, 4],
        drawBorder: false
      }
    },
    x: {
      ticks: {
        color: '#737373',
        font: { size: 10 },
        maxRotation: 0
      },
      grid: {
        display: false,
        drawBorder: false
      }
    }
  }
})

const updateCharts = () => {
  if (!chartsReady.value) {
    console.warn('Charts not ready yet')
    return
  }

  if (cpuChart && cpuChart.data) {
    cpuChart.data.labels = [...timeLabels.value]
    cpuChart.data.datasets[0].data = [...cpuHistory.value]
    cpuChart.update('none')
  }

  if (memoryChart && memoryChart.data) {
    memoryChart.data.labels = [...timeLabels.value]
    memoryChart.data.datasets[0].data = [...memoryHistory.value]
    memoryChart.update('none')
  }

  if (loadChart && loadChart.data) {
    loadChart.data.datasets[0].data = [
      parseFloat(liveStats.value.cpu.load_1min),
      parseFloat(liveStats.value.cpu.load_5min),
      parseFloat(liveStats.value.cpu.load_15min)
    ]
    loadChart.update('none')
  }
}

const startAutoRefresh = () => {
  console.log('Starting auto-refresh every 5 seconds')
  refreshInterval = setInterval(() => {
    refreshStats()
  }, 5000)
}

const refreshStats = async () => {
  if (isRefreshing.value) {
    console.log('Already refreshing, skipping...')
    return
  }
  
  isRefreshing.value = true
  console.log('Refreshing stats...')
  
  try {
    const response = await axios.get('/dashboard/stats')
    console.log('Stats received:', response.data)

    liveStats.value = response.data

    timeLabels.value.shift()
    timeLabels.value.push(formatTime(new Date()))

    cpuHistory.value.shift()
    cpuHistory.value.push(response.data.cpu.usage)

    memoryHistory.value.shift()
    memoryHistory.value.push(response.data.memory.usage_percent)

    loadHistory.value.shift()
    loadHistory.value.push(parseFloat(response.data.cpu.load_1min))

    updateCharts()
    console.log('Charts updated successfully')
  } catch (error) {
    console.error('Failed to refresh stats:', error)
    if (error.response) {
      console.error('Response error:', error.response.status, error.response.data)
    }
  } finally {
    isRefreshing.value = false
  }
}

const formatTime = (date) => {
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

const getProgressClass = (percentage) => {
  if (percentage < 60) return 'bg-gradient-success'
  if (percentage < 80) return 'bg-gradient-warning'
  return 'bg-gradient-danger'
}

const getStatusBadge = (percentage) => {
  if (percentage < 60) return 'bg-gradient-success'
  if (percentage < 80) return 'bg-gradient-warning'
  return 'bg-gradient-danger'
}

const getStatusText = (percentage) => {
  if (percentage < 60) return 'Good'
  if (percentage < 80) return 'Warning'
  return 'Critical'
}
</script>