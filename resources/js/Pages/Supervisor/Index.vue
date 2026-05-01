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
                                        <h4 class="mb-0">{{ totalProcessesCount }}</h4>
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
                                <h6 class="mb-0">Supervisor Configurations</h6>
                                <div class="d-flex gap-2">
                                    <div class="btn-group" v-if="groups.length > 0">
                                        <button class="btn btn-sm btn-outline-success" @click="startAll" title="Start All">
                                            <i class="material-symbols-rounded text-sm">play_arrow</i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" @click="stopAll" title="Stop All">
                                            <i class="material-symbols-rounded text-sm">stop</i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" @click="restartAll" title="Restart All">
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
                                <div v-if="groups.length === 0" class="text-center py-4 text-muted">
                                    <i class="material-symbols-rounded mb-2" style="font-size: 48px;">memory</i>
                                    <p>No processes configured. Create one to get started.</p>
                                </div>

                                <div v-else class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2" width="40"></th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Configuration Group</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Workers</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2 text-end pe-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template v-for="group in groups" :key="group.name">
                                                <!-- Group Row -->
                                                <tr class="bg-light-gray">
                                                    <td>
                                                        <button class="btn btn-link btn-icon-only btn-sm text-dark mb-0" @click="toggleGroup(group.name)">
                                                            <i class="material-symbols-rounded text-sm transform-transition" :style="expandedGroups.includes(group.name) ? 'transform: rotate(90deg)' : ''">chevron_right</i>
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar avatar-sm bg-gradient-info rounded-circle me-2">
                                                                <i class="material-symbols-rounded text-white text-sm">settings_suggest</i>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-0 text-sm">{{ group.name }}</h6>
                                                                <small class="text-xs text-muted">{{ group.name }}.conf</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span :class="getStatusBadge(group.status)">{{ group.status }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-sm bg-gradient-secondary">{{ group.count }} processes</span>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <button class="btn btn-link text-success p-1" title="Start All in Group" @click="startProcess(group.name + ':*')">
                                                            <i class="material-symbols-rounded">play_arrow</i>
                                                        </button>
                                                        <button class="btn btn-link text-warning p-1" title="Stop All in Group" @click="stopProcess(group.name + ':*')">
                                                            <i class="material-symbols-rounded">stop</i>
                                                        </button>
                                                        <button class="btn btn-link text-info p-1" title="Restart All in Group" @click="restartProcess(group.name + ':*')">
                                                            <i class="material-symbols-rounded">refresh</i>
                                                        </button>
                                                        <div class="d-inline-block ms-2 border-start ps-2">
                                                            <button class="btn btn-link text-primary p-1" title="Edit Config" @click="editProcess(group.name)">
                                                                <i class="material-symbols-rounded">edit</i>
                                                            </button>
                                                            <button class="btn btn-link text-danger p-1" title="Delete Config" @click="confirmDelete(group.name)">
                                                                <i class="material-symbols-rounded">delete</i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- Child Processes (Expanded) -->
                                                <template v-if="expandedGroups.includes(group.name)">
                                                    <tr v-for="process in group.processes" :key="process.fullName" class="bg-white">
                                                        <td></td>
                                                        <td class="ps-4">
                                                            <div class="d-flex align-items-center">
                                                                <i class="material-symbols-rounded text-xs text-secondary me-2">subdirectory_arrow_right</i>
                                                                <span class="text-xs font-weight-bold">{{ process.name }}</span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span :class="getStatusBadge(process.status, true)">{{ process.status }}</span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="text-xxs">PID: {{ process.pid || '-' }}</span>
                                                                <span class="text-xxs">UP: {{ process.uptime || '-' }}</span>
                                                            </div>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <button v-if="process.status !== 'RUNNING'" class="btn btn-link text-success p-1" title="Start" @click="startProcess(process.fullName)">
                                                                <i class="material-symbols-rounded text-sm">play_arrow</i>
                                                            </button>
                                                            <button v-if="process.status === 'RUNNING'" class="btn btn-link text-warning p-1" title="Stop" @click="stopProcess(process.fullName)">
                                                                <i class="material-symbols-rounded text-sm">stop</i>
                                                            </button>
                                                            <button class="btn btn-link text-info p-1" title="Restart" @click="restartProcess(process.fullName)">
                                                                <i class="material-symbols-rounded text-sm">refresh</i>
                                                            </button>
                                                            <button class="btn btn-link text-primary p-1" title="View Logs" @click="openLogs(process.fullName)">
                                                                <i class="material-symbols-rounded text-sm">description</i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Create Process Modal -->
            <Teleport to="body">
            <div class="modal fade" :class="{ show: showCreateModal }" :style="{ display: showCreateModal ? 'block' : 'none' }"
                tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-primary">
                            <h5 class="modal-title text-white">
                                <i class="material-symbols-rounded me-2">{{ isEditing ? 'edit' : 'add_circle' }}</i>
                                {{ isEditing ? 'Edit Queue Worker' : 'Create Queue Worker' }}
                            </h5>
                            <button type="button" class="btn-close btn-close-white"
                                @click="showCreateModal = false"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Info Alert -->
                            <div class="alert alert-info py-2 mb-3">
                                <i class="material-symbols-rounded me-1 text-sm">info</i>
                                <strong>Note:</strong> Projects are located in <code>/var/www/</code>. Select your
                                project from the
                                dropdown or enter a custom path.
                            </div>

                            <!-- Mode Tabs -->
                            <ul class="nav nav-tabs mb-3">
                                <li class="nav-item">
                                    <a class="nav-link" :class="{ active: !manualMode }" href="#"
                                        @click.prevent="manualMode = false">
                                        <i class="material-symbols-rounded text-sm me-1">build</i> Builder
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" :class="{ active: manualMode }" href="#"
                                        @click.prevent="manualMode = true">
                                        <i class="material-symbols-rounded text-sm me-1">code</i> Manual Config
                                    </a>
                                </li>
                            </ul>

                            <!-- Builder Mode -->
                            <div v-if="!manualMode" class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Worker Name</label>
                                        <input type="text" class="form-control" v-model="newProcess.name"
                                            placeholder="mysite-worker" :readonly="isEditing">
                                        <small v-if="isEditing" class="text-muted">Name cannot be changed</small>
                                        <small v-else class="text-muted">Unique identifier (e.g., mysite-worker)</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Project</label>
                                        <select class="form-control form-select" v-model="newProcess.project">
                                            <option value="">-- Select a project --</option>
                                            <option v-for="proj in projects" :key="proj.name" :value="proj.name">
                                                {{ proj.name }} {{ proj.isLaravel ? '(Laravel)' : '' }}
                                            </option>
                                        </select>
                                        <small class="text-muted">Or type a custom path:</small>
                                        <input type="text" class="form-control mt-1" v-model="newProcess.project"
                                            placeholder="mysite.com">
                                    </div>

                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label fw-bold">Workers</label>
                                            <input type="number" class="form-control" v-model="newProcess.numprocs"
                                                min="1" max="10">
                                            <small class="text-muted">Parallel workers</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label fw-bold">Sleep (sec)</label>
                                            <input type="number" class="form-control" v-model="newProcess.sleep" min="1"
                                                max="60">
                                            <small class="text-muted">Wait between jobs</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label fw-bold">Tries</label>
                                            <input type="number" class="form-control" v-model="newProcess.tries" min="1"
                                                max="10">
                                            <small class="text-muted">Max retry attempts</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label fw-bold">Timeout (sec)</label>
                                            <input type="number" class="form-control" v-model="newProcess.timeout"
                                                min="30" max="3600">
                                            <small class="text-muted">Job timeout</small>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Log File</label>
                                        <input type="text" class="form-control" v-model="newProcess.logfile"
                                            placeholder="worker.log">
                                        <small class="text-muted">Saved to /var/www/{{ newProcess.project || 'project'
                                            }}/{{
                                                newProcess.logfile || 'worker.log' }}</small>
                                    </div>

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    v-model="newProcess.autostart" id="autostart">
                                                <label class="form-check-label" for="autostart">
                                                    <strong>Auto Start</strong><br>
                                                    <small class="text-muted">On boot</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    v-model="newProcess.autorestart" id="autorestart">
                                                <label class="form-check-label" for="autorestart">
                                                    <strong>Auto Restart</strong><br>
                                                    <small class="text-muted">On crash</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Config Preview -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="material-symbols-rounded text-sm me-1">preview</i>
                                        Generated Config Preview
                                    </label>
                                    <pre class="config-preview">{{ generatedConfig }}</pre>
                                    <small class="text-muted">This file will be saved to: <code>/etc/supervisor/conf.d/{{
                                        newProcess.name || 'worker' }}.conf</code></small>
                                </div>
                            </div>

                            <!-- Manual Mode -->
                            <div v-else>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Worker Name</label>
                                    <input type="text" class="form-control" v-model="newProcess.name"
                                        placeholder="mysite-worker" :readonly="isEditing">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="material-symbols-rounded text-sm me-1">code</i>
                                        Custom Supervisor Config
                                    </label>
                                    <textarea class="form-control config-editor" v-model="newProcess.rawConfig"
                                        rows="15" placeholder="[program:myworker]
command=/usr/bin/php /var/www/mysite/artisan queue:work
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/mysite/worker.log"></textarea>
                                    <small class="text-muted">Write your complete supervisor configuration. The
                                        [program:name]
                                        section will use the Worker Name above.</small>
                                </div>
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
            </Teleport>

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
            <Teleport to="body">
            <div class="modal fade" :class="{ show: showLogsModal }" :style="{ display: showLogsModal ? 'block' : 'none' }"
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
            </Teleport>

            <!-- Terminal Modal for Install -->
            <Teleport to="body">
            <div class="modal fade" :class="{ show: showTerminalModal }"
                :style="{ display: showTerminalModal ? 'block' : 'none' }" tabindex="-1">
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
            </Teleport>
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
const groups = ref([])
const expandedGroups = ref([])

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

// Projects dropdown
const projects = ref([])
const manualMode = ref(false)

// Form
const newProcess = ref({
    name: '',
    project: '',
    command: '',
    user: 'www-data',
    numprocs: 1,
    autostart: true,
    autorestart: true,
    sleep: 3,
    tries: 3,
    timeout: 120,
    logfile: 'worker.log',
    rawConfig: ''
})

// System users for dropdown - not needed, always www-data
const systemUsers = ref([])

const totalProcessesCount = computed(() => {
    return groups.value.reduce((total, group) => total + group.processes.length, 0)
})

const runningCount = computed(() => {
    let count = 0
    groups.value.forEach(group => {
        count += group.processes.filter(p => p.status === 'RUNNING').length
    })
    return count
})

const stoppedCount = computed(() => {
    let count = 0
    groups.value.forEach(group => {
        count += group.processes.filter(p => p.status !== 'RUNNING').length
    })
    return count
})

// Live config preview
const generatedConfig = computed(() => {
    const p = newProcess.value
    const name = p.name || 'process'
    const command = p.command || (p.project ? `/usr/bin/php /var/www/${p.project}/artisan queue:work` : '')
    const user = p.user || 'www-data'
    const logfile = p.project ? `/var/www/${p.project}/${p.logfile || 'worker.log'}` : `/var/log/supervisor/${name}.log`

    return `[program:${name}]
process_name=%(program_name)s_%(process_num)02d
command=${command}
autostart=${p.autostart ? 'true' : 'false'}
autorestart=${p.autorestart ? 'true' : 'false'}
user=${user}
numprocs=${p.numprocs}
redirect_stderr=true
stdout_logfile=${logfile}
stopwaitsecs=3600`
})

onMounted(async () => {
    await loadStatus()
    await loadProjects()
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

const loadProjects = async () => {
    try {
        const response = await axios.get('/supervisor/projects')
        projects.value = response.data.projects || []
    } catch (error) {
        console.error('Failed to load projects:', error)
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
        groups.value = response.data.groups || []
    } catch (error) {
        console.error('Failed to load processes:', error)
    }
}

const toggleGroup = (groupName) => {
    if (expandedGroups.value.includes(groupName)) {
        expandedGroups.value = expandedGroups.value.filter(g => g !== groupName)
    } else {
        expandedGroups.value.push(groupName)
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
    if (name.toLowerCase().includes('nimbus') && !confirm('Warning: Stopping the Nimbus process will make the control panel unreachable. Are you absolutely sure?')) return
    try {
        await axios.post('/supervisor/stop', { name })
        await loadProcesses()
        showNotification(`Stopped ${name}`, 'success')
    } catch (error) {
        showNotification('Failed: ' + (error.response?.data?.error || error.message), 'error')
    }
}

const restartProcess = async (name) => {
    if (name.toLowerCase().includes('nimbus') && !confirm('Warning: Restarting the Nimbus process may temporarily disconnect your session. Continue?')) return
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
        logfile: 'worker.log',
        rawConfig: ''
    }
    manualMode.value = false
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

const getStatusBadge = (status, isSmall = false) => {
    const base = isSmall ? 'badge badge-sm border' : 'badge';
    const badges = {
        'RUNNING': isSmall ? 'border-success text-success' : 'bg-gradient-success',
        'STOPPED': isSmall ? 'border-secondary text-secondary' : 'bg-gradient-secondary',
        'STARTING': isSmall ? 'border-info text-info' : 'bg-gradient-info',
        'STOPPING': isSmall ? 'border-warning text-warning' : 'bg-gradient-warning',
        'EXITED': isSmall ? 'border-danger text-danger' : 'bg-gradient-danger',
        'FATAL': isSmall ? 'border-danger text-danger' : 'bg-gradient-danger',
        'BACKOFF': isSmall ? 'border-warning text-warning' : 'bg-gradient-warning'
    }
    return `${base} ${badges[status] || (isSmall ? 'border-secondary text-secondary' : 'bg-gradient-secondary')}`
}
</script>

<style scoped>
.transform-transition {
    transition: transform 0.2s ease-in-out;
}
.bg-light-gray {
    background-color: rgba(0, 0, 0, 0.02) !important;
}
.terminal-output {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    font-family: 'Fira Code', 'Roboto Mono', monospace;
    font-size: 13px;
    line-height: 1.5;
    max-height: 500px;
    overflow-y: auto;
    border-radius: 0 0 4px 4px;
    margin: 0;
}
.config-preview, .config-editor {
    font-family: 'Fira Code', 'Roboto Mono', monospace;
    font-size: 12px;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}
.config-editor {
    width: 100%;
}
</style>


