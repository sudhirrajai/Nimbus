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
                                            Task Scheduler</p>
                                        <h5 class="text-white font-weight-bolder mb-0">
                                            Cron Jobs
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-white shadow text-center rounded-circle">
                                        <i class="material-symbols-rounded text-dark text-lg opacity-10">schedule</i>
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

            <!-- Main Content -->
            <template v-else>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Scheduled Tasks (www-data user)</h6>
                                <button class="btn btn-sm bg-gradient-primary" @click="openCreateModal">
                                    <i class="material-symbols-rounded text-sm me-1">add</i>
                                    New Cron Job
                                </button>
                            </div>
                            <div class="card-body">
                                <div v-if="jobs.length === 0" class="text-center py-4 text-muted">
                                    <i class="material-symbols-rounded mb-2" style="font-size: 48px;">schedule</i>
                                    <p>No cron jobs configured. Create one to get started.</p>
                                </div>

                                <div v-else class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    Schedule</th>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    Command</th>
                                                <th
                                                    class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                    Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="job in jobs" :key="job.id">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div
                                                            class="avatar avatar-sm bg-gradient-info rounded-circle me-2">
                                                            <i
                                                                class="material-symbols-rounded text-white text-sm">schedule</i>
                                                        </div>
                                                        <div>
                                                            <code
                                                                class="text-sm font-weight-bold">{{ job.schedule }}</code>
                                                            <p class="text-xs text-muted mb-0">{{
                                                                getScheduleDescription(job) }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code class="text-sm"
                                                        style="max-width: 400px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            {{ job.command }}
                          </code>
                                                </td>
                                                <td>
                                                    <button class="btn btn-link text-success p-1" title="Run Now"
                                                        @click="runNow(job)">
                                                        <i class="material-symbols-rounded">play_arrow</i>
                                                    </button>
                                                    <button class="btn btn-link text-primary p-1" title="Edit"
                                                        @click="editJob(job)">
                                                        <i class="material-symbols-rounded">edit</i>
                                                    </button>
                                                    <button class="btn btn-link text-danger p-1" title="Delete"
                                                        @click="confirmDelete(job)">
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

                <!-- Quick Reference -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header pb-0">
                                <h6 class="mb-0">Cron Schedule Reference</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="text-sm mb-2"><strong>Format:</strong>
                                            <code>minute hour day month weekday</code>
                                        </p>
                                        <ul class="text-sm">
                                            <li><code>*</code> - every value</li>
                                            <li><code>*/5</code> - every 5 units</li>
                                            <li><code>1,15</code> - at 1 and 15</li>
                                            <li><code>1-5</code> - from 1 to 5</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="text-sm mb-2"><strong>Common Examples:</strong></p>
                                        <ul class="text-sm">
                                            <li><code>* * * * *</code> - Every minute</li>
                                            <li><code>0 * * * *</code> - Every hour</li>
                                            <li><code>0 0 * * *</code> - Every day at midnight</li>
                                            <li><code>0 0 * * 0</code> - Every Sunday at midnight</li>
                                            <li><code>0 0 1 * *</code> - First day of every month</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Create/Edit Modal -->
            <div class="modal fade" :class="{ show: showModal }" :style="showModal ? 'display: block;' : ''"
                tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-primary">
                            <h5 class="modal-title text-white">
                                <i class="material-symbols-rounded me-2">{{ isEditing ? 'edit' : 'add_circle' }}</i>
                                {{ isEditing ? 'Edit Cron Job' : 'Create Cron Job' }}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showModal = false"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Quick Presets -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Quick Presets</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-primary" @click="setPreset('* * * * *')">Every
                                        Minute</button>
                                    <button class="btn btn-sm btn-outline-primary"
                                        @click="setPreset('*/5 * * * *')">Every 5
                                        Minutes</button>
                                    <button class="btn btn-sm btn-outline-primary"
                                        @click="setPreset('0 * * * *')">Hourly</button>
                                    <button class="btn btn-sm btn-outline-primary"
                                        @click="setPreset('0 0 * * *')">Daily</button>
                                    <button class="btn btn-sm btn-outline-primary"
                                        @click="setPreset('0 0 * * 0')">Weekly</button>
                                    <button class="btn btn-sm btn-outline-primary"
                                        @click="setPreset('0 0 1 * *')">Monthly</button>
                                </div>
                            </div>

                            <!-- Schedule Builder -->
                            <div class="row mb-3">
                                <div class="col">
                                    <label class="form-label fw-bold">Minute</label>
                                    <input type="text" class="form-control" v-model="form.minute" placeholder="*">
                                    <small class="text-muted">0-59</small>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-bold">Hour</label>
                                    <input type="text" class="form-control" v-model="form.hour" placeholder="*">
                                    <small class="text-muted">0-23</small>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-bold">Day</label>
                                    <input type="text" class="form-control" v-model="form.day" placeholder="*">
                                    <small class="text-muted">1-31</small>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-bold">Month</label>
                                    <input type="text" class="form-control" v-model="form.month" placeholder="*">
                                    <small class="text-muted">1-12</small>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-bold">Weekday</label>
                                    <input type="text" class="form-control" v-model="form.weekday" placeholder="*">
                                    <small class="text-muted">0-6 (Sun-Sat)</small>
                                </div>
                            </div>

                            <!-- Schedule Preview -->
                            <div class="alert alert-info py-2 mb-4">
                                <i class="material-symbols-rounded text-sm me-1">schedule</i>
                                <strong>Schedule:</strong> <code>{{ form.minute }} {{ form.hour }} {{ form.day }} {{ form.month }}
                        {{ form.weekday }}</code>
                            </div>

                            <!-- Command -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Command</label>
                                <input type="text" class="form-control" v-model="form.command"
                                    placeholder="php /var/www/yoursite/artisan schedule:run">
                                <small class="text-muted">Full path to command or script</small>
                            </div>

                            <!-- Common Commands -->
                            <div class="mb-3">
                                <label class="form-label">Common Commands</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-outline-secondary"
                                        @click="form.command = 'php /var/www/yoursite/artisan schedule:run >> /dev/null 2>&1'">
                                        Laravel Scheduler
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary"
                                        @click="form.command = 'php /var/www/yoursite/artisan queue:work --stop-when-empty >> /dev/null 2>&1'">
                                        Queue Worker
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary"
                                        @click="form.command = 'curl -s https://example.com/cron >> /dev/null 2>&1'">
                                        HTTP Request
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showModal = false">Cancel</button>
                            <button type="button" class="btn bg-gradient-primary" @click="saveJob" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ saving ? 'Saving...' : (isEditing ? 'Update Job' : 'Create Job') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showModal" class="modal-backdrop fade show"></div>

            <!-- Output Modal -->
            <div class="modal fade" :class="{ show: showOutputModal }" :style="showOutputModal ? 'display: block;' : ''"
                tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title">
                                <i class="material-symbols-rounded me-2">terminal</i>
                                Job Output
                            </h5>
                            <button type="button" class="btn-close btn-close-white"
                                @click="showOutputModal = false"></button>
                        </div>
                        <div class="modal-body p-0">
                            <pre class="terminal-output">{{ jobOutput }}</pre>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                @click="showOutputModal = false">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showOutputModal" class="modal-backdrop fade show"></div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(true)
const saving = ref(false)
const jobs = ref([])

// Modals
const showModal = ref(false)
const showOutputModal = ref(false)
const isEditing = ref(false)
const editingJob = ref(null)
const jobOutput = ref('')

// Form
const form = ref({
    minute: '*',
    hour: '*',
    day: '*',
    month: '*',
    weekday: '*',
    command: ''
})

onMounted(async () => {
    await loadJobs()
    loading.value = false
})

const loadJobs = async () => {
    try {
        const response = await axios.get('/cron/jobs')
        jobs.value = response.data.jobs || []
    } catch (error) {
        console.error('Failed to load jobs:', error)
    }
}

const openCreateModal = () => {
    isEditing.value = false
    editingJob.value = null
    form.value = { minute: '*', hour: '*', day: '*', month: '*', weekday: '*', command: '' }
    showModal.value = true
}

const editJob = (job) => {
    isEditing.value = true
    editingJob.value = job
    form.value = {
        minute: job.minute,
        hour: job.hour,
        day: job.day,
        month: job.month,
        weekday: job.weekday,
        command: job.command
    }
    showModal.value = true
}

const setPreset = (schedule) => {
    const parts = schedule.split(' ')
    form.value.minute = parts[0]
    form.value.hour = parts[1]
    form.value.day = parts[2]
    form.value.month = parts[3]
    form.value.weekday = parts[4]
}

const saveJob = async () => {
    if (!form.value.command) {
        alert('Command is required')
        return
    }

    saving.value = true
    try {
        if (isEditing.value) {
            await axios.post('/cron/update', {
                old_command: editingJob.value.command,
                ...form.value
            })
        } else {
            await axios.post('/cron/create', form.value)
        }
        showModal.value = false
        await loadJobs()
    } catch (error) {
        alert('Failed: ' + (error.response?.data?.error || error.message))
    } finally {
        saving.value = false
    }
}

const confirmDelete = async (job) => {
    if (!confirm('Delete this cron job?')) return
    try {
        await axios.post('/cron/delete', { command: job.command })
        await loadJobs()
    } catch (error) {
        alert('Failed: ' + (error.response?.data?.error || error.message))
    }
}

const runNow = async (job) => {
    try {
        jobOutput.value = 'Running...'
        showOutputModal.value = true
        const response = await axios.post('/cron/run', { command: job.command })
        jobOutput.value = response.data.output || 'Job completed with no output'
    } catch (error) {
        jobOutput.value = 'Error: ' + (error.response?.data?.error || error.message)
    }
}

const getScheduleDescription = (job) => {
    const { minute, hour, day, month, weekday } = job

    if (minute === '*' && hour === '*' && day === '*' && month === '*' && weekday === '*') {
        return 'Every minute'
    }
    if (minute === '0' && hour === '*' && day === '*' && month === '*' && weekday === '*') {
        return 'Every hour'
    }
    if (minute === '0' && hour === '0' && day === '*' && month === '*' && weekday === '*') {
        return 'Every day at midnight'
    }
    if (minute === '0' && hour === '0' && day === '*' && month === '*' && weekday === '0') {
        return 'Every Sunday at midnight'
    }
    if (minute === '0' && hour === '0' && day === '1' && month === '*' && weekday === '*') {
        return 'First day of every month'
    }
    if (minute.startsWith('*/')) {
        return `Every ${minute.substring(2)} minutes`
    }

    return ''
}
</script>

<style scoped>
.avatar {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.terminal-output {
    background-color: #1e1e2e;
    color: #a6e3a1;
    font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
    font-size: 13px;
    line-height: 1.5;
    padding: 16px;
    margin: 0;
    height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
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

.form-label {
    color: #344767;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}
</style>
