<template>
    <MainLayout>
        <div class="container-fluid py-2">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-dark">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-white text-sm mb-0 text-uppercase font-weight-bold opacity-7">
                                            Process Manager</p>
                                        <h5 class="text-white font-weight-bolder mb-0">
                                            Supervisor
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-white shadow text-center rounded-circle">
                                        <i class="material-symbols-rounded text-dark text-lg opacity-10">memory</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Not Installed State -->
            <div v-else-if="!status.installed" class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="material-symbols-rounded text-secondary mb-3" style="font-size: 64px;">memory</i>
                            <h4>Supervisor Not Installed</h4>
                            <p class="text-muted mb-4">
                                Install Supervisor to manage long-running processes like queue workers.
                            </p>
                            <button class="btn bg-gradient-primary" @click="installSupervisor" :disabled="installing">
                                <span v-if="installing" class="spinner-border spinner-border-sm me-2"></span>
                                {{ installing ? 'Installing...' : 'Install Supervisor' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Installed State -->
            <template v-else>
                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Total Processes</p>
                                        <h4 class="mb-0">{{ processes.length }}</h4>
                                    </div>
                                    <div
                                        class="icon icon-md icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                        <i class="material-symbols-rounded opacity-10">memory</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Running</p>
                                        <h4 class="mb-0 text-success">{{ runningCount }}</h4>
                                    </div>
                                    <div
                                        class="icon icon-md icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                        <i class="material-symbols-rounded opacity-10">play_circle</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-sm-6">
                        <div class="card">
                            <div class="card-header p-2 ps-3">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-sm mb-0 text-capitalize">Stopped</p>
                                        <h4 class="mb-0 text-warning">{{ stoppedCount }}</h4>
                                    </div>
                                    <div
                                        class="icon icon-md icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                        <i class="material-symbols-rounded opacity-10">stop_circle</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Processes Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Supervisor Processes</h6>
                                <div class="d-flex gap-2">
                                    <!-- Bulk Actions -->
                                    <div class="btn-group" v-if="processes.length > 0">
                                        <button class="btn btn-sm btn-outline-success" @click="startAll"
                                            title="Start All">
                                            <i class="material-symbols-rounded text-sm">play_arrow</i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" @click="stopAll"
                                            title="Stop All">
                                            <i class="material-symbols-rounded text-sm">stop</i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" @click="restartAll"
                                            title="Restart All">
                                            <i class="material-symbols-rounded text-sm">refresh</i>
                                        </button>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" @click="reloadConfig">
                                        <i class="material-symbols-rounded text-sm me-1">sync</i>
                                        Reload
                                    </button>
                                    <button class="btn btn-sm bg-gradient-primary" @click="openCreateModal">
                                        <i class="material-symbols-rounded text-sm me-1">add</i>
                                        New Process
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div v-if="processes.length === 0" class="text-center py-4 text-muted">
                                    <i class="material-symbols-rounded mb-2" style="font-size: 48px;">memory</i>
                                    <p>No processes configured. Create one to get started.</p>
                                </div>

                                <div v-else class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    Process</th>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    Status</th>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    PID</th>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    Uptime</th>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="process in processes" :key="process.name">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div
                                                            class="avatar avatar-sm bg-gradient-dark rounded-circle me-2">
                                                            <i
                                                                class="material-symbols-rounded text-white text-sm">terminal</i>
                                                        </div>
                                                        <h6 class="mb-0 text-sm">{{ process.name }}</h6>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span :class="getStatusBadge(process.status)">{{ process.status
                                                        }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-sm">{{ process.pid || '-' }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-sm">{{ process.uptime || '-' }}</span>
                                                </td>
                                                <td>
                                                    <button v-if="process.status !== 'RUNNING'"
                                                        class="btn btn-link text-success p-1" title="Start"
                                                        @click="startProcess(process.name)">
                                                        <i class="material-symbols-rounded">play_arrow</i>
                                                    </button>
                                                    <button v-if="process.status === 'RUNNING'"
                                                        class="btn btn-link text-warning p-1" title="Stop"
                                                        @click="stopProcess(process.name)">
                                                        <i class="material-symbols-rounded">stop</i>
                                                    </button>
                                                    <button class="btn btn-link text-info p-1" title="Restart"
                                                        @click="restartProcess(process.name)">
                                                        <i class="material-symbols-rounded">refresh</i>
                                                    </button>
                                                    <button class="btn btn-link text-primary p-1" title="View Logs"
                                                        @click="openLogs(process.name)">
                                                        <i class="material-symbols-rounded">description</i>
                                                    </button>
                                                    <button class="btn btn-link text-secondary p-1" title="Edit"
                                                        @click="editProcess(process.name)">
                                                        <i class="material-symbols-rounded">edit</i>
                                                    </button>
                                                    <button class="btn btn-link text-danger p-1" title="Delete"
                                                        @click="confirmDelete(process.name)">
                                                        <i class="material-symbols-rounded">delete</i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Create Process Modal -->
            <div class="modal fade" :class="{ show: showCreateModal }" :style="showCreateModal ? 'display: block;' : ''"
                tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-primary">
                            <h5 class="modal-title text-white">
                                <i class="material-symbols-rounded me-2">{{ isEditing ? 'edit' : 'add_circle' }}</i>
                                {{ isEditing ? 'Edit Process' : 'Create Supervisor Process' }}
                            </h5>
                            <button type="button" class="btn-close btn-close-white"
                                @click="showCreateModal = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Worker Name</label>
                                    <input type="text" class="form-control" v-model="newProcess.name"
                                        placeholder="mysite-worker" :readonly="isEditing">
                                    <small v-if="isEditing" class="text-muted">Name cannot be changed</small>
                                    <small v-else class="text-muted">Unique identifier for this worker</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Project Folder</label>
                                    <div class="input-group">
                                        <span class="input-group-text">/var/www/</span>
                                        <input type="text" class="form-control" v-model="newProcess.project"
                                            placeholder="mysite.com">
                                    </div>
                                    <small class="text-muted">Project directory name</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">Workers</label>
                                    <input type="number" class="form-control" v-model="newProcess.numprocs" min="1"
                                        max="10">
                                    <small class="text-muted">Number of workers</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">Sleep (sec)</label>
                                    <input type="number" class="form-control" v-model="newProcess.sleep" min="1"
                                        max="60">
                                    <small class="text-muted">Wait between jobs</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">Tries</label>
                                    <input type="number" class="form-control" v-model="newProcess.tries" min="1"
                                        max="10">
                                    <small class="text-muted">Max retry attempts</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label fw-bold">Timeout (sec)</label>
                                    <input type="number" class="form-control" v-model="newProcess.timeout" min="30"
                                        max="3600">
                                    <small class="text-muted">Job timeout</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Log File</label>
                                <div class="input-group">
                                    <span class="input-group-text">/var/www/{{ newProcess.project || 'mysite.com'
                                    }}/</span>
                                    <input type="text" class="form-control" v-model="newProcess.logfile"
                                        placeholder="worker.log">
                                </div>
                                <small class="text-muted">Where to save worker logs</small>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" v-model="newProcess.autostart"
                                            id="autostart">
                                        <label class="form-check-label" for="autostart">
                                            <strong>Auto Start</strong><br>
                                            <small class="text-muted">Start on system boot</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" v-model="newProcess.autorestart"
                                            id="autorestart">
                                        <label class="form-check-label" for="autorestart">
                                            <strong>Auto Restart</strong><br>
                                            <small class="text-muted">Restart on crash</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Command Preview -->
                            <div class="alert alert-secondary py-2 mb-0">
                                <small class="fw-bold">Command Preview:</small><br>
                                <code class="text-xs">/usr/bin/php /var/www/{{ newProcess.project || 'mysite.com' }}/artisan
                        queue:work --sleep={{ newProcess.sleep }} --tries={{ newProcess.tries }} --timeout={{
                            newProcess.timeout }}</code>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                @click="showCreateModal = false">Cancel</button>
                            <button type="button" class="btn bg-gradient-primary" @click="saveProcess"
                                :disabled="creating">
                                <span v-if="creating" class="spinner-border spinner-border-sm me-2"></span>
                                {{ creating ? 'Saving...' : (isEditing ? 'Update Worker' : 'Create Worker') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showCreateModal" class="modal-backdrop fade show"></div>

            <!-- Toast Notification -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                <div class="toast align-items-center border-0"
                    :class="toastType === 'success' ? 'bg-success' : 'bg-danger'"
                    :style="showToast ? 'display: block;' : 'display: none;'" role="alert">
                    <div class="d-flex">
                        <div class="toast-body text-white">
                            <i class="material-symbols-rounded me-2 text-sm">{{ toastType === 'success' ? 'check_circle'
                                : 'error'
                            }}</i>
                            {{ toastMessage }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            @click="showToast = false"></button>
                    </div>
                </div>
            </div>

            <!-- Logs Modal -->
            <div class="modal fade" :class="{ show: showLogsModal }" :style="showLogsModal ? 'display: block;' : ''"
                tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title">
                                <i class="material-symbols-rounded me-2">description</i>
                                Logs: {{ selectedProcess }}
                            </h5>
                            <button type="button" class="btn-close btn-close-white"
                                @click="showLogsModal = false"></button>
                        </div>
                        <div class="modal-body p-0">
                            <ul class="nav nav-tabs px-3 pt-2">
                                <li class="nav-item">
                                    <a class="nav-link" :class="{ active: logType === 'stdout' }" href="#"
                                        @click.prevent="logType = 'stdout'; loadLogs()">Stdout</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" :class="{ active: logType === 'stderr' }" href="#"
                                        @click.prevent="logType = 'stderr'; loadLogs()">Stderr</a>
                                </li>
                            </ul>
                            <pre class="terminal-output">{{ logContent }}</pre>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-primary" @click="loadLogs">
                                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                                Refresh
                            </button>
                            <button type="button" class="btn btn-secondary"
                                @click="showLogsModal = false">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showLogsModal" class="modal-backdrop fade show"></div>

            <!-- Terminal Modal for Install -->
            <div class="modal fade" :class="{ show: showTerminalModal }"
                :style="showTerminalModal ? 'display: block;' : ''" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title">
                                <i class="material-symbols-rounded me-2">terminal</i>
                                Installing Supervisor
                            </h5>
                            <span v-if="terminalStatus === 'running'" class="badge bg-warning ms-2">Running</span>
                            <span v-else-if="terminalStatus === 'complete'"
                                class="badge bg-success ms-2">Complete</span>
                        </div>
                        <div class="modal-body p-0">
                            <pre class="terminal-output">{{ terminalLog }}</pre>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeTerminal"
                                :disabled="terminalStatus === 'running'">
                                {{ terminalStatus === 'running' ? 'Please wait...' : 'Close' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showTerminalModal" class="modal-backdrop fade show"></div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const loading = ref(true)
const installing = ref(false)
const creating = ref(false)
const isEditing = ref(false)
const status = ref({ installed: false, running: false })
const processes = ref([])

// Modals
const showCreateModal = ref(false)
const showLogsModal = ref(false)
const showTerminalModal = ref(false)

// Terminal
const terminalLog = ref('')
const terminalStatus = ref('idle')
let pollInterval = null

// Logs
const selectedProcess = ref('')
const logType = ref('stdout')
const logContent = ref('')

// Toast messages
const toastMessage = ref('')
const toastType = ref('success')
const showToast = ref(false)

// Form
const newProcess = ref({
    name: '',
    project: '',
    numprocs: 1,
    autostart: true,
    autorestart: true,
    sleep: 3,
    tries: 3,
    timeout: 120,
    stopwaitsecs: 3600,
    logfile: ''
})

// System users for dropdown - not needed, always www-data
const systemUsers = ref([])

const runningCount = computed(() => processes.value.filter(p => p.status === 'RUNNING').length)
const stoppedCount = computed(() => processes.value.filter(p => p.status !== 'RUNNING').length)

onMounted(async () => {
    await loadStatus()
    await loadSystemUsers()
    if (status.value.installed) {
        await loadProcesses()
    }
    loading.value = false
})

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval)
})

const loadStatus = async () => {
    try {
        const response = await axios.get('/supervisor/status')
        status.value = response.data
    } catch (error) {
        console.error('Failed to load status:', error)
    }
}

const loadSystemUsers = async () => {
    try {
        const response = await axios.get('/supervisor/users')
        systemUsers.value = response.data.users || []
    } catch (error) {
        console.error('Failed to load users:', error)
    }
}

const loadProcesses = async () => {
    try {
        const response = await axios.get('/supervisor/processes')
        processes.value = response.data.processes || []
    } catch (error) {
        console.error('Failed to load processes:', error)
    }
}

const installSupervisor = async () => {
    installing.value = true
    showTerminalModal.value = true
    terminalLog.value = 'Starting installation...\n'
    terminalStatus.value = 'running'

    try {
        await axios.post('/supervisor/install')
        startLogPolling()
    } catch (error) {
        terminalLog.value += '\nError: ' + (error.response?.data?.error || error.message)
        terminalStatus.value = 'failed'
        installing.value = false
    }
}

const startLogPolling = () => {
    if (pollInterval) clearInterval(pollInterval)

    pollInterval = setInterval(async () => {
        try {
            const response = await axios.get('/supervisor/install-log')
            terminalLog.value = response.data.log

            if (response.data.isComplete) {
                terminalStatus.value = 'complete'
                clearInterval(pollInterval)
                installing.value = false
                await loadStatus()
                if (status.value.installed) await loadProcesses()
            } else if (response.data.isFailed) {
                terminalStatus.value = 'failed'
                clearInterval(pollInterval)
                installing.value = false
            }
        } catch (error) {
            console.error('Failed to poll log:', error)
        }
    }, 1000)
}

const closeTerminal = async () => {
    if (pollInterval) clearInterval(pollInterval)
    showTerminalModal.value = false
}

const startProcess = async (name) => {
    try {
        await axios.post('/supervisor/start', { name })
        await loadProcesses()
        showNotification(`Started ${name}`, 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const stopProcess = async (name) => {
    try {
        await axios.post('/supervisor/stop', { name })
        await loadProcesses()
        showNotification(`Stopped ${name}`, 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const restartProcess = async (name) => {
    try {
        await axios.post('/supervisor/restart', { name })
        await loadProcesses()
        showNotification(`Restarted ${name}`, 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const confirmDelete = async (name) => {
    if (!confirm(`Delete worker ${name}? This will remove the configuration.`)) return
    try {
        await axios.post('/supervisor/delete', { name })
        await loadProcesses()
        showNotification(`Deleted ${name}`, 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const openLogs = (name) => {
    selectedProcess.value = name
    logContent.value = 'Loading...'
    showLogsModal.value = true
    loadLogs()
}

const loadLogs = async () => {
    try {
        const response = await axios.get('/supervisor/logs', {
            params: { name: selectedProcess.value, type: logType.value }
        })
        logContent.value = response.data.log || 'No logs available'
    } catch (error) {
        logContent.value = 'Error loading logs: ' + error.message
    }
}

const reloadConfig = async () => {
    try {
        await axios.post('/supervisor/reload')
        await loadProcesses()
        showNotification('Configuration reloaded', 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const openCreateModal = () => {
    isEditing.value = false
    resetForm()
    showCreateModal.value = true
}

const editProcess = async (name) => {
    try {
        const response = await axios.get('/supervisor/config', { params: { name } })
        if (response.data.success) {
            newProcess.value = response.data.config
            isEditing.value = true
            showCreateModal.value = true
        }
    } catch (error) {
        showNotification('Failed to load config: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const saveProcess = async () => {
    if (!newProcess.value.name || !newProcess.value.project) {
        showNotification('Worker Name and Project are required', 'error')
        return
    }
    creating.value = true
    try {
        if (isEditing.value) {
            await axios.post('/supervisor/update', newProcess.value)
            showNotification('Worker updated successfully', 'success')
        } else {
            await axios.post('/supervisor/create', newProcess.value)
            showNotification('Worker created successfully', 'success')
        }
        showCreateModal.value = false
        resetForm()
        isEditing.value = false
        await loadProcesses()
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    } finally {
        creating.value = false
    }
}

const resetForm = () => {
    newProcess.value = {
        name: '',
        project: '',
        numprocs: 1,
        autostart: true,
        autorestart: true,
        sleep: 3,
        tries: 3,
        timeout: 120,
        logfile: 'worker.log'
    }
}

const startAll = async () => {
    try {
        await axios.post('/supervisor/start-all')
        await loadProcesses()
        showNotification('All workers started', 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const stopAll = async () => {
    if (!confirm('Stop all workers?')) return
    try {
        await axios.post('/supervisor/stop-all')
        await loadProcesses()
        showNotification('All workers stopped', 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const restartAll = async () => {
    try {
        await axios.post('/supervisor/restart-all')
        await loadProcesses()
        showNotification('All workers restarted', 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const showNotification = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => {
        showToast.value = false
    }, 4000)
}

const getStatusBadge = (status) => {
    const badges = {
        'RUNNING': 'badge bg-gradient-success',
        'STOPPED': 'badge bg-gradient-secondary',
        'STARTING': 'badge bg-gradient-info',
        'STOPPING': 'badge bg-gradient-warning',
        'EXITED': 'badge bg-gradient-danger',
        'FATAL': 'badge bg-gradient-danger',
        'BACKOFF': 'badge bg-gradient-warning'
    }
    return badges[status] || 'badge bg-gradient-secondary'
}
</script>

<style scoped>
.terminal-output {
    background-color: #1e1e2e;
    color: #a6e3a1;
    font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
    font-size: 13px;
    line-height: 1.5;
    padding: 16px;
    margin: 0;
    height: 400px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.avatar {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Form input styling */
.form-control {
    background-color: #fff !important;
    border: 1px solid #d2d6da !important;
    border-radius: 0.5rem !important;
    padding: 0.625rem 0.75rem !important;
    font-size: 0.875rem !important;
    color: #495057 !important;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
}

.form-control:focus {
    border-color: #e91e63 !important;
    box-shadow: 0 0 0 2px rgba(233, 30, 99, 0.1) !important;
    outline: none !important;
}

.form-control::placeholder {
    color: #adb5bd !important;
    opacity: 1 !important;
}

.form-check-input {
    width: 1.25rem;
    height: 1.25rem;
    border: 1px solid #d2d6da;
}

.form-check-input:checked {
    background-color: #e91e63;
    border-color: #e91e63;
}

.form-label {
    color: #344767;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}
</style>
