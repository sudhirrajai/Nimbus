<template>
    <MainLayout>
    <Head title="Cron Jobs" />
        <div class="container-fluid py-2">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-dark">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-12 col-md-8 mb-2 mb-md-0">
                                    <div class="numbers">
                                        <p class="text-white text-sm mb-0 text-uppercase font-weight-bold opacity-7">
                                             Task Scheduler</p>
                                        <h5 class="text-white font-weight-bolder mb-0">
                                             Cron Jobs
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4 text-md-end text-start">
                                    <div class="icon icon-shape bg-white shadow text-center rounded-circle d-inline-flex align-items-center justify-content-center">
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
                            <div class="card-header pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h6 class="mb-0">System Scheduled Tasks</h6>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="input-group input-group-sm" style="min-width: 180px; max-width: 250px;">
                                        <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                                        <input v-model="searchQuery" type="text" class="form-control" placeholder="Search cron jobs...">
                                    </div>
                                    <button class="btn btn-sm bg-gradient-primary mb-0" @click="openCreateModal">
                                        <i class="material-symbols-rounded text-sm me-1">add</i>
                                        New Cron Job
                                    </button>
                                </div>
                            </div>
                            <div class="card-body px-0 pt-0 pb-2">
                                <div v-if="filteredJobs.length === 0" class="text-center py-5 text-muted">
                                    <div class="empty-state">
                                        <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">schedule</i>
                                        <p class="mt-3">No cron jobs found matching your search.</p>
                                    </div>
                                </div>
                                <div v-else class="table-responsive p-0">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">User</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Schedule</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Command</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Last Execution</th>
                                                <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="job in paginatedJobs" :key="job.id" class="domain-row">
                                                <td class="ps-4">
                                                    <span :class="['badge badge-sm', job.user === 'root' ? 'bg-gradient-danger' : 'bg-gradient-info']">
                                                        {{ job.user }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm bg-gradient-light rounded-circle me-2">
                                                            <i class="material-symbols-rounded text-dark text-sm">schedule</i>
                                                        </div>
                                                        <div>
                                                            <code class="text-sm font-weight-bold">{{ job.schedule }}</code>
                                                            <p class="text-xs text-muted mb-0">{{ getScheduleDescription(job) }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code class="text-sm" style="max-width: 320px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        {{ job.command }}
                                                    </code>
                                                </td>
                                                <td>
                                                    <div v-if="job.last_run" class="d-flex flex-column">
                                                        <div class="d-flex align-items-center mb-1">
                                                            <span :class="['badge badge-sm me-2', job.last_run.status === 'success' ? 'bg-gradient-success' : 'bg-gradient-danger']">
                                                                {{ job.last_run.status === 'success' ? 'Success (exit 0)' : `Failed (exit ${job.last_run.exit_code})` }}
                                                            </span>
                                                            <span class="text-xxs text-secondary">{{ job.last_run.duration_ms }}ms</span>
                                                        </div>
                                                        <span class="text-xxs text-muted">{{ formatRelativeTime(job.last_run.executed_at) }}</span>
                                                    </div>
                                                    <span v-else class="text-xxs text-muted opacity-7">Never executed</span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button class="action-btn btn-view" title="Run Now" @click="runNow(job)" :disabled="runningJobs[job.id]">
                                                            <span v-if="runningJobs[job.id]" class="spinner-border spinner-border-sm"></span>
                                                            <i v-else class="material-symbols-rounded">play_arrow</i>
                                                        </button>
                                                        <button class="action-btn btn-view text-info" title="Execution History & Logs" @click="openHistoryModal(job)">
                                                            <i class="material-symbols-rounded">history</i>
                                                        </button>
                                                        <button class="action-btn btn-edit" title="Edit" @click="editJob(job)">
                                                            <i class="material-symbols-rounded">edit</i>
                                                        </button>
                                                        <button class="action-btn btn-delete" title="Delete" @click="confirmDelete(job)">
                                                            <i class="material-symbols-rounded">delete</i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <div v-if="filteredJobs.length > itemsPerPage" class="d-flex justify-content-between align-items-center p-3 border-top">
                                    <div class="text-xs text-secondary">
                                        Showing {{ paginationStart + 1 }} to {{ Math.min(paginationEnd, filteredJobs.length) }} of {{ filteredJobs.length }} entries
                                    </div>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                            <button class="page-link" @click="currentPage--" aria-label="Previous">
                                                <i class="material-symbols-rounded text-xs">chevron_left</i>
                                            </button>
                                        </li>
                                        <li v-for="page in totalPages" :key="page" class="page-item" :class="{ active: currentPage === page }">
                                            <button class="page-link" @click="currentPage = page">{{ page }}</button>
                                        </li>
                                        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                            <button class="page-link" @click="currentPage++" aria-label="Next">
                                                <i class="material-symbols-rounded text-xs">chevron_right</i>
                                            </button>
                                        </li>
                                    </ul>
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
                            <!-- User Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">System User</label>
                                <select class="form-control form-select" v-model="form.user" :disabled="isEditing">
                                    <option value="www-data">www-data (Standard Web User)</option>
                                    <option value="root">root (System Administrator)</option>
                                </select>
                                <small class="text-muted">Select which user's crontab to manage</small>
                            </div>

                            <!-- Task Templates -->
                            <div class="mb-4" v-if="!isEditing">
                                <label class="form-label fw-bold d-flex align-items-center">
                                    <i class="material-symbols-rounded text-sm me-1">auto_fix_high</i>
                                    Task Templates (Optional)
                                </label>
                                <div class="row g-2 mb-2">
                                    <div class="col-12 col-md-7">
                                        <select class="form-select form-select-sm" v-model="selectedTemplate" @change="onTemplateChange">
                                            <option value="">-- Choose a Predefined Task --</option>
                                            <option v-for="tpl in taskTemplates" :key="tpl.id" :value="tpl.id">
                                                {{ tpl.name }} ({{ tpl.frequency }})
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-5" v-if="currentTemplateObj?.needsDomain">
                                        <select class="form-select form-select-sm" v-model="selectedDomain" @change="onDomainChange">
                                            <option value="">-- Select Target Domain --</option>
                                            <option v-for="d in domains" :key="d" :value="d">{{ d }}</option>
                                        </select>
                                    </div>
                                </div>
                                <p class="text-xs text-muted mb-0" v-if="currentTemplateObj">
                                    <i class="material-symbols-rounded text-xs me-1 align-middle">info</i>
                                    {{ currentTemplateObj.description }}
                                </p>
                            </div>

                            <!-- Quick Presets -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Schedule Presets</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="setPreset('* * * * *')">Every Minute</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="setPreset('*/5 * * * *')">Every 5 Mins</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="setPreset('*/15 * * * *')">Every 15 Mins</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="setPreset('0 * * * *')">Hourly</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="setPreset('0 0 * * *')">Daily (Midnight)</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="setPreset('0 0 * * 0')">Weekly (Sunday)</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="setPreset('0 0 1 * *')">Monthly</button>
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
                            <div class="alert alert-info py-2 mb-4 text-white">
                                <i class="material-symbols-rounded text-sm me-1 align-middle">schedule</i>
                                <strong>Schedule:</strong> <code>{{ form.minute }} {{ form.hour }} {{ form.day }} {{ form.month }} {{ form.weekday }}</code>
                            </div>

                            <!-- Command -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Command</label>
                                <input type="text" class="form-control font-monospace" v-model="form.command"
                                    placeholder="php /var/www/yoursite/artisan schedule:run">
                                <small class="text-muted">Full executable command path</small>
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

            <!-- Execution History & Output Modal -->
            <div class="modal fade" :class="{ show: showHistoryModal }" :style="showHistoryModal ? 'display: block;' : ''" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-dark text-white p-3">
                            <div class="d-flex align-items-center">
                                <i class="material-symbols-rounded text-warning me-2" style="font-size: 1.5rem;">terminal</i>
                                <div>
                                    <h6 class="modal-title text-white mb-0 font-weight-bold">Cron Execution History & Output</h6>
                                    <code class="text-xs text-light font-monospace opacity-8 text-break">{{ activeJobForHistory?.command }}</code>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" @click="showHistoryModal = false"></button>
                        </div>
                        <div class="modal-body p-3">
                            <div v-if="loadingHistory" class="text-center py-5">
                                <div class="spinner-border text-dark" role="status"></div>
                                <p class="text-xs text-secondary mt-2">Loading execution history...</p>
                            </div>
                            <div v-else-if="jobHistoryList.length === 0" class="text-center py-5 text-muted">
                                <i class="material-symbols-rounded opacity-3" style="font-size: 48px;">history</i>
                                <p class="mt-2 text-sm">No execution runs recorded yet for this cron job.</p>
                                <button class="btn btn-sm bg-gradient-primary mt-2" @click="runNow(activeJobForHistory)">
                                    <i class="material-symbols-rounded text-xs me-1">play_arrow</i> Run Job Now
                                </button>
                            </div>
                            <div v-else>
                                <!-- Run selector tabs -->
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-3 border-bottom mb-3">
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <button 
                                            v-for="(run, idx) in jobHistoryList" 
                                            :key="idx"
                                            @click="selectedHistoryIdx = idx"
                                            :class="['btn btn-xs mb-0 d-flex align-items-center', selectedHistoryIdx === idx ? 'bg-gradient-dark text-white' : 'btn-outline-secondary']"
                                        >
                                            <span :class="['badge badge-dot me-2', run.status === 'success' ? 'bg-success' : 'bg-danger']"></span>
                                            {{ formatTimeOnly(run.executed_at) }}
                                            <span class="opacity-7 text-xxs ms-1">({{ run.duration_ms }}ms)</span>
                                        </button>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button class="btn btn-outline-danger btn-xs mb-0" @click="clearCurrentHistory" title="Clear History">
                                            <i class="material-symbols-rounded text-xxs me-1">delete_sweep</i> Clear History
                                        </button>
                                    </div>
                                </div>

                                <!-- Active Run Metadata -->
                                <div v-if="currentRun" class="row mb-3">
                                    <div class="col-md-3 col-6 mb-2">
                                        <div class="p-2 rounded bg-light border">
                                            <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Status</span>
                                            <span :class="['badge badge-sm', currentRun.status === 'success' ? 'bg-gradient-success' : 'bg-gradient-danger']">
                                                {{ currentRun.status === 'success' ? 'Success (Exit 0)' : `Failed (Exit ${currentRun.exit_code})` }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6 mb-2">
                                        <div class="p-2 rounded bg-light border">
                                            <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Executed At</span>
                                            <span class="text-xs font-weight-bold text-dark">{{ currentRun.executed_at }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6 mb-2">
                                        <div class="p-2 rounded bg-light border">
                                            <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Execution Duration</span>
                                            <span class="text-xs font-weight-bold text-dark">{{ currentRun.duration_ms }} ms</span>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6 mb-2">
                                        <div class="p-2 rounded bg-light border">
                                            <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Triggered By</span>
                                            <span class="text-xs font-weight-bold text-dark">{{ currentRun.executed_by || currentRun.user }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Terminal Output -->
                                <div v-if="currentRun">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-xxs text-uppercase font-weight-bold text-secondary">Terminal Output (stdout / stderr):</span>
                                        <button class="btn btn-link text-xs p-0 mb-0 d-flex align-items-center" @click="copyTerminalOutput">
                                            <i class="material-symbols-rounded text-xs me-1">{{ copiedOutput ? 'check' : 'content_copy' }}</i>
                                            {{ copiedOutput ? 'Copied!' : 'Copy Output' }}
                                        </button>
                                    </div>
                                    <pre class="terminal-output" style="max-height: 380px; overflow-y: auto; background: #0f172a; color: #f8fafc; padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.8rem; line-height: 1.5; white-space: pre-wrap;">{{ currentRun.output }}</pre>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer p-2">
                            <button type="button" class="btn btn-outline-secondary mb-0" @click="showHistoryModal = false">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showHistoryModal" class="modal-backdrop fade show" style="z-index: 1045;"></div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" :class="{ show: showDeleteModal }" :style="showDeleteModal ? 'display: block;' : ''" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-danger border-0">
                            <h5 class="modal-title text-white">
                                <i class="material-symbols-rounded me-2">warning</i>
                                Confirm Deletion
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showDeleteModal = false"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="mb-4">
                                <i class="material-symbols-rounded text-danger" style="font-size: 64px;">delete_forever</i>
                            </div>
                            <h5 class="mb-3">Are you sure?</h5>
                            <p class="text-secondary mb-0">You are about to permanently remove this cron job:</p>
                            <code class="d-block bg-light p-2 my-3 border-radius-md text-break text-danger">{{ jobToDelete?.command }}</code>
                            <p class="text-xs text-muted">
                                <i class="material-symbols-rounded text-xs me-1">info</i>
                                This will stop the task from running on your server immediately.
                            </p>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary mb-0 px-4" @click="showDeleteModal = false">Cancel</button>
                            <button type="button" class="btn btn-danger mb-0 px-4" @click="executeDelete" :disabled="deleting">
                                <span v-if="deleting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                {{ deleting ? 'Deleting...' : 'Delete Permanently' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="showDeleteModal" class="modal-backdrop fade show" style="z-index: 1040;"></div>
        </div>
    </MainLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { formatDate, formatTime } from '@/Utils/date'

const loading = ref(true)
const saving = ref(false)
const jobs = ref([])
const domains = ref([])
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

const selectedTemplate = ref('')
const selectedDomain = ref('')

const taskTemplates = [
    {
        id: 'laravel-scheduler',
        name: 'Laravel Task Scheduler',
        frequency: '* * * * *',
        description: 'Runs scheduled artisan commands and jobs every minute',
        schedule: '* * * * *',
        needsDomain: true,
        command: (d) => `php /var/www/${d || 'yourdomain.com'}/artisan schedule:run >> /dev/null 2>&1`
    },
    {
        id: 'laravel-queue',
        name: 'Laravel Queue Worker',
        frequency: '* * * * *',
        description: 'Processes pending background queue jobs',
        schedule: '* * * * *',
        needsDomain: true,
        command: (d) => `php /var/www/${d || 'yourdomain.com'}/artisan queue:work --stop-when-empty >> /dev/null 2>&1`
    },
    {
        id: 'wordpress-cron',
        name: 'WordPress WP-Cron',
        frequency: '*/15 * * * *',
        description: 'Executes WordPress scheduled actions every 15 minutes',
        schedule: '*/15 * * * *',
        needsDomain: true,
        command: (d) => `wget -q -O - https://${d || 'yourdomain.com'}/wp-cron.php?doing_wp_cron >/dev/null 2>&1`
    },
    {
        id: 'log-cleanup',
        name: 'Storage Log Truncation',
        frequency: '0 0 * * 0',
        description: 'Deletes Laravel storage logs older than 14 days weekly',
        schedule: '0 0 * * 0',
        needsDomain: true,
        command: (d) => `find /var/www/${d || 'yourdomain.com'}/storage/logs/ -name "*.log" -mtime +14 -delete`
    },
    {
        id: 'health-ping',
        name: 'URL / Health Monitor Ping',
        frequency: '*/5 * * * *',
        description: 'Pings a URL or webhook heartbeat endpoint every 5 minutes',
        schedule: '*/5 * * * *',
        needsDomain: true,
        command: (d) => `curl -fsS https://${d || 'yourdomain.com'}/health > /dev/null 2>&1`
    }
]

const currentTemplateObj = computed(() => taskTemplates.find(t => t.id === selectedTemplate.value))

const onTemplateChange = () => {
    if (!selectedTemplate.value) return
    const tpl = currentTemplateObj.value
    if (!tpl) return

    setPreset(tpl.schedule)

    if (tpl.needsDomain && !selectedDomain.value && domains.value.length > 0) {
        selectedDomain.value = domains.value[0]
    }

    form.value.command = tpl.command(selectedDomain.value)
}

const onDomainChange = () => {
    const tpl = currentTemplateObj.value
    if (tpl) {
        form.value.command = tpl.command(selectedDomain.value)
    }
}

// Modals
const showModal = ref(false)
const showOutputModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const editingJob = ref(null)
const jobToDelete = ref(null)
const jobOutput = ref('')
const deleting = ref(false)

// Computed
const filteredJobs = computed(() => {
    if (!searchQuery.value) return jobs.value
    const q = searchQuery.value.toLowerCase()
    return jobs.value.filter(job => 
        job.command.toLowerCase().includes(q) || 
        job.user.toLowerCase().includes(q) ||
        job.schedule.toLowerCase().includes(q)
    )
})

const totalPages = computed(() => Math.ceil(filteredJobs.value.length / itemsPerPage.value))
const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage.value)
const paginationEnd = computed(() => currentPage.value * itemsPerPage.value)

const paginatedJobs = computed(() => {
    return filteredJobs.value.slice(paginationStart.value, paginationEnd.value)
})

// Form
const form = ref({
    user: 'www-data',
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
        domains.value = response.data.domains || []
    } catch (error) {
        console.error('Failed to load jobs:', error)
    }
}

const openCreateModal = () => {
    isEditing.value = false
    editingJob.value = null
    selectedTemplate.value = ''
    selectedDomain.value = domains.value[0] || ''
    form.value = { user: 'www-data', minute: '*', hour: '*', day: '*', month: '*', weekday: '*', command: '' }
    showModal.value = true
}

const editJob = (job) => {
    isEditing.value = true
    editingJob.value = job
    form.value = {
        user: job.user,
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

const confirmDelete = (job) => {
    jobToDelete.value = job
    showDeleteModal.value = true
}

const executeDelete = async () => {
    if (!jobToDelete.value) return
    deleting.value = true
    try {
        await axios.post('/cron/delete', { 
            command: jobToDelete.value.command,
            user: jobToDelete.value.user
        })
        showDeleteModal.value = false
        await loadJobs()
    } catch (error) {
        alert('Failed: ' + (error.response?.data?.error || error.message))
    } finally {
        deleting.value = false
    }
}

const runningJobs = ref({})
const showHistoryModal = ref(false)
const activeJobForHistory = ref(null)
const jobHistoryList = ref([])
const selectedHistoryIdx = ref(0)
const loadingHistory = ref(false)
const copiedOutput = ref(false)

const currentRun = computed(() => jobHistoryList.value[selectedHistoryIdx.value] || null)

const openHistoryModal = async (job) => {
    if (!job) return
    activeJobForHistory.value = job
    selectedHistoryIdx.value = 0
    showHistoryModal.value = true
    loadingHistory.value = true
    try {
        const response = await axios.get('/cron/history', {
            params: { user: job.user, command: job.command }
        })
        jobHistoryList.value = response.data.history || []
    } catch (err) {
        console.error('Failed to load history:', err)
        jobHistoryList.value = []
    } finally {
        loadingHistory.value = false
    }
}

const clearCurrentHistory = async () => {
    if (!activeJobForHistory.value) return
    if (!confirm('Clear all recorded execution logs for this job?')) return
    try {
        await axios.post('/cron/clear-history', {
            user: activeJobForHistory.value.user,
            command: activeJobForHistory.value.command
        })
        jobHistoryList.value = []
        if (activeJobForHistory.value) {
            activeJobForHistory.value.last_run = null
        }
    } catch (err) {
        alert('Failed to clear history')
    }
}

const copyTerminalOutput = () => {
    if (!currentRun.value?.output) return
    navigator.clipboard.writeText(currentRun.value.output).then(() => {
        copiedOutput.value = true
        setTimeout(() => { copiedOutput.value = false }, 2000)
    })
}

const runNow = async (job) => {
    if (!job) return
    try {
        runningJobs.value[job.id] = true
        const response = await axios.post('/cron/run', { 
            command: job.command,
            user: job.user
        })
        if (response.data?.last_run) {
            job.last_run = response.data.last_run
        }
        // Open the history modal right away to show execution result
        await openHistoryModal(job)
    } catch (error) {
        alert('Execution failed: ' + (error.response?.data?.error || error.message))
    } finally {
        runningJobs.value[job.id] = false
    }
}

const formatRelativeTime = (dateStr) => {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    const now = new Date()
    const diffSec = Math.floor((now - d) / 1000)

    if (diffSec < 60) return 'Just now'
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`
    return formatDate(d)
}

const formatTimeOnly = (dateStr) => {
    if (!dateStr) return ''
    return formatTime(dateStr, { second: '2-digit' })
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


