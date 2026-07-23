<template>
  <MainLayout>
    <Head title="Activity Log" />
    <div class="container-fluid py-4">
      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card bg-gradient-dark">
            <div class="card-body p-3">
              <div class="row align-items-center">
                <div class="col-8">
                  <h4 class="text-white mb-0">
                    <i class="material-symbols-rounded me-2">history</i>
                    System Activity Log
                  </h4>
                  <p class="text-white text-sm mb-0 opacity-8">
                    Audit trail monitoring who is doing what, at what time, and across which services
                  </p>
                </div>
                <div class="col-4 text-end d-flex align-items-center justify-content-end gap-2">
                  <button class="btn btn-outline-light btn-sm mb-0" @click="showSettingsModal = true">
                    <i class="material-symbols-rounded text-sm me-1">auto_delete</i> Auto Cleanup
                  </button>
                  <button class="btn btn-link text-white p-0 mb-0" @click="fetchActivities" :disabled="loading">
                    <i class="material-symbols-rounded" :class="{ 'spin': loading }">refresh</i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-body p-3">
              <div class="row g-2">
                <!-- Search -->
                <div class="col-md-4">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                    <input v-model="filters.search" type="text" class="form-control" placeholder="Search by email, IP, description..." @input="debouncedFetch">
                  </div>
                </div>

                <!-- Service Filter -->
                <div class="col-md-2">
                  <select v-model="filters.service" class="form-select form-select-sm" @change="fetchActivities">
                    <option value="">All Services</option>
                    <option v-for="svc in uniqueServices" :key="svc" :value="svc">
                      {{ formatName(svc) }}
                    </option>
                  </select>
                </div>

                <!-- Action Filter -->
                <div class="col-md-2">
                  <select v-model="filters.action" class="form-select form-select-sm" @change="fetchActivities">
                    <option value="">All Actions</option>
                    <option v-for="act in uniqueActions" :key="act" :value="act">
                      {{ formatName(act) }}
                    </option>
                  </select>
                </div>

                <!-- Date Filter -->
                <div class="col-md-2">
                  <select v-model="filters.date_range" class="form-select form-select-sm" @change="fetchActivities">
                    <option value="">All Time</option>
                    <option value="24h">Last 24 Hours</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                  </select>
                </div>

                <!-- Clear Filters Button -->
                <div class="col-md-2 text-end">
                  <button class="btn btn-outline-secondary btn-sm mb-0 w-100" @click="resetFilters">
                    Clear Filters
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table Card -->
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header pb-0">
              <h6 class="mb-0"><i class="material-symbols-rounded text-sm me-1">list</i> Audit Records</h6>
            </div>
            <div class="card-body px-0 pb-2">
              <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
              </div>
              <div v-else-if="activities.length === 0" class="text-center py-5">
                <div class="empty-state">
                  <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">history_toggle_off</i>
                  <p class="text-secondary mt-3">No activity records found matching filters.</p>
                </div>
              </div>
              <div v-else class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 50px;"></th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">User</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Action</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Service</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Description</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">IP Address</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Time</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Details</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="log in activities" :key="log.id">
                      <tr class="activity-row">
                        <td class="text-center">
                          <div class="icon icon-sm icon-shape shadow text-center border-radius-md d-flex align-items-center justify-content-center"
                               :class="'bg-gradient-' + getServiceColor(log.service)">
                            <i class="material-symbols-rounded text-white text-xs">{{ getServiceIcon(log.service) }}</i>
                          </div>
                        </td>
                        <td>
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm" v-if="log.user">{{ log.user.name }}</h6>
                            <h6 class="mb-0 text-sm text-secondary" v-else>System / Unauth</h6>
                            <p class="text-xs text-secondary mb-0">{{ log.email || '-' }}</p>
                          </div>
                        </td>
                        <td>
                          <span class="badge badge-sm" :class="'bg-gradient-' + getActionColor(log.action)">
                            {{ log.action }}
                          </span>
                        </td>
                        <td>
                          <span class="text-xs font-weight-bold">{{ formatName(log.service) }}</span>
                        </td>
                        <td>
                          <span class="text-xs text-wrap text-dark" style="max-width: 350px; display: inline-block;">{{ log.description }}</span>
                        </td>
                        <td>
                          <code class="text-xs">{{ log.ip_address || '-' }}</code>
                        </td>
                        <td>
                          <span class="text-xs text-secondary">{{ formatDateTime(log.created_at) }}</span>
                        </td>
                        <td class="align-middle text-center">
                          <button class="btn btn-link text-secondary p-1 mb-0" @click="toggleDetails(log.id)">
                            <i class="material-symbols-rounded text-sm">
                              {{ expandedLogs.includes(log.id) ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}
                            </i>
                          </button>
                        </td>
                      </tr>
                      <!-- Expanded User Agent Details -->
                      <tr v-if="expandedLogs.includes(log.id)">
                        <td colspan="8" class="bg-gray-100 p-3">
                          <div class="row text-xs">
                            <div class="col-md-2 text-secondary font-weight-bold">User Agent:</div>
                            <div class="col-md-10 text-muted">{{ log.user_agent || 'Unknown User Agent' }}</div>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <div v-if="pagination.total > pagination.per_page" class="d-flex justify-content-between align-items-center p-3 border-top">
                <div class="text-xs text-secondary">
                  Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }} to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }} records
                </div>
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                    <button class="page-link" @click="changePage(pagination.current_page - 1)" aria-label="Previous">
                      <i class="material-symbols-rounded text-xs">chevron_left</i>
                    </button>
                  </li>
                  <li v-for="page in totalPages" :key="page" class="page-item" :class="{ active: pagination.current_page === page }">
                    <button class="page-link" @click="changePage(page)">{{ page }}</button>
                  </li>
                  <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                    <button class="page-link" @click="changePage(pagination.current_page + 1)" aria-label="Next">
                      <i class="material-symbols-rounded text-xs">chevron_right</i>
                    </button>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Auto-Cleanup Settings Modal -->
    <div v-if="showSettingsModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title d-flex align-items-center">
              <i class="material-symbols-rounded me-2 text-primary">auto_delete</i> Activity Log Auto-Cleanup
            </h5>
            <button type="button" class="btn-close text-dark" @click="showSettingsModal = false" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div v-if="feedbackMessage" :class="['alert', 'alert-' + feedbackType, 'text-white', 'py-2', 'px-3', 'mb-3', 'text-sm']">
              {{ feedbackMessage }}
            </div>

            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="autoCleanSwitch" v-model="cleanupSettings.auto_clean">
              <label class="form-check-label font-weight-bold" for="autoCleanSwitch">
                Enable Daily Automatic Cleanup Cron
              </label>
              <small class="d-block text-muted">Automatically prunes activity logs daily at 00:00 UTC based on retention threshold.</small>
            </div>

            <div class="mb-3">
              <label class="form-label font-weight-bold">Log Retention Period (Days)</label>
              <select v-model="cleanupSettings.retention_days" class="form-select">
                <option :value="1">1 Day</option>
                <option :value="3">3 Days</option>
                <option :value="7">7 Days</option>
                <option :value="30">30 Days (1 Month)</option>
                <option :value="60">60 Days (2 Months)</option>
                <option :value="90">90 Days (3 Months)</option>
                <option :value="0">Keep Forever (Disabled)</option>
              </select>
              <small class="text-muted d-block mt-1">Logs older than the selected retention period will be deleted.</small>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-danger btn-sm" @click="runCleanNow" :disabled="cleaningNow || cleanupSettings.retention_days <= 0">
                <span v-if="cleaningNow" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">delete_sweep</i>
                Clean Up Old
              </button>
              <button type="button" class="btn btn-danger btn-sm" @click="runClearAll" :disabled="clearingAll">
                <span v-if="clearingAll" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">delete_forever</i>
                Delete All Logs
              </button>
            </div>

            <div>
              <button type="button" class="btn btn-secondary btn-sm me-2" @click="showSettingsModal = false">Cancel</button>
              <button type="button" class="btn btn-primary btn-sm" @click="saveSettings" :disabled="savingSettings">
                <span v-if="savingSettings" class="spinner-border spinner-border-sm me-1"></span>
                Save Settings
              </button>
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
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const loading = ref(false)
const activities = ref([])
const uniqueServices = ref([])
const uniqueActions = ref([])
const expandedLogs = ref([])

const showSettingsModal = ref(false)
const savingSettings = ref(false)
const cleaningNow = ref(false)
const clearingAll = ref(false)
const cleanupSettings = ref({
  retention_days: 30,
  auto_clean: true
})
const feedbackMessage = ref('')
const feedbackType = ref('success')

const filters = ref({
  search: '',
  service: '',
  action: '',
  date_range: '',
  page: 1
})

const pagination = ref({
  total: 0,
  per_page: 25,
  current_page: 1,
  last_page: 1
})

const totalPages = computed(() => {
  const pages = []
  for (let i = 1; i <= pagination.value.last_page; i++) {
    pages.push(i)
  }
  return pages
})

const fetchSettings = async () => {
  try {
    const response = await axios.get('/activities/settings')
    if (response.data.success) {
      cleanupSettings.value = {
        retention_days: response.data.retention_days,
        auto_clean: response.data.auto_clean
      }
    }
  } catch (error) {
    console.error('Failed to fetch activity cleanup settings:', error)
  }
}

const saveSettings = async () => {
  savingSettings.value = true
  feedbackMessage.value = ''
  try {
    const response = await axios.post('/activities/settings', cleanupSettings.value)
    if (response.data.success) {
      feedbackType.value = 'success'
      feedbackMessage.value = response.data.message
      setTimeout(() => {
        showSettingsModal.value = false
        feedbackMessage.value = ''
      }, 1500)
    }
  } catch (error) {
    feedbackType.value = 'danger'
    feedbackMessage.value = error.response?.data?.error || 'Failed to update settings.'
  } finally {
    savingSettings.value = false
  }
}

const runCleanNow = async () => {
  if (!confirm(`Are you sure you want to delete activity logs older than ${cleanupSettings.value.retention_days} days? This action cannot be undone.`)) {
    return
  }
  cleaningNow.value = true
  feedbackMessage.value = ''
  try {
    const response = await axios.post('/activities/clean', { days: cleanupSettings.value.retention_days })
    if (response.data.success) {
      feedbackType.value = 'success'
      feedbackMessage.value = response.data.message
      fetchActivities()
    }
  } catch (error) {
    feedbackType.value = 'danger'
    feedbackMessage.value = error.response?.data?.error || error.response?.data?.message || 'Failed to perform cleanup.'
  } finally {
    cleaningNow.value = false
  }
}

const runClearAll = async () => {
  if (!confirm('WARNING: Are you sure you want to delete ALL activity logs? This action is permanent and cannot be undone!')) {
    return
  }
  clearingAll.value = true
  feedbackMessage.value = ''
  try {
    const response = await axios.post('/activities/clear-all')
    if (response.data.success) {
      feedbackType.value = 'success'
      feedbackMessage.value = response.data.message
      fetchActivities()
    }
  } catch (error) {
    feedbackType.value = 'danger'
    feedbackMessage.value = error.response?.data?.error || error.response?.data?.message || 'Failed to clear all logs.'
  } finally {
    clearingAll.value = false
  }
}

onMounted(() => {
  fetchActivities()
  fetchSettings()
})

let debounceTimeout = null
const debouncedFetch = () => {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => {
    filters.value.page = 1
    fetchActivities()
  }, 300)
}

const fetchActivities = async () => {
  loading.value = true
  try {
    const params = {
      search: filters.value.search,
      service: filters.value.service,
      action: filters.value.action,
      date_range: filters.value.date_range,
      page: filters.value.page
    }

    const response = await axios.get('/activities/list', { params })
    if (response.data.success) {
      activities.value = response.data.activities.data || []
      pagination.value = {
        total: response.data.activities.total,
        per_page: response.data.activities.per_page,
        current_page: response.data.activities.current_page,
        last_page: response.data.activities.last_page
      }
      uniqueServices.value = response.data.services || []
      uniqueActions.value = response.data.actions || []
    }
  } catch (error) {
    console.error("Failed to fetch activity logs:", error)
  } finally {
    loading.value = false
  }
}

const changePage = (page) => {
  if (page < 1 || page > pagination.value.last_page) return
  filters.value.page = page
  fetchActivities()
}

const resetFilters = () => {
  filters.value = {
    search: '',
    service: '',
    action: '',
    date_range: '',
    page: 1
  }
  fetchActivities()
}

const toggleDetails = (logId) => {
  const index = expandedLogs.value.indexOf(logId)
  if (index > -1) {
    expandedLogs.value.splice(index, 1)
  } else {
    expandedLogs.value.push(logId)
  }
}

const getServiceIcon = (service) => {
  const icons = {
    database: 'storage',
    domains: 'language',
    nginx: 'settings_ethernet',
    supervisor: 'memory',
    ssl: 'lock',
    cron: 'schedule',
    email: 'email',
    'file-manager': 'folder',
    auth: 'shield',
    shield: 'admin_panel_settings',
    dns: 'dns',
    updates: 'system_update',
    settings: 'settings',
    profile: 'person'
  }
  return icons[service] || 'history'
}

const getServiceColor = (service) => {
  const colors = {
    database: 'info',
    domains: 'primary',
    nginx: 'secondary',
    supervisor: 'warning',
    ssl: 'success',
    cron: 'info',
    email: 'warning',
    'file-manager': 'success',
    auth: 'danger',
    shield: 'danger',
    dns: 'primary',
    updates: 'success',
    settings: 'dark',
    profile: 'info'
  }
  return colors[service] || 'secondary'
}

const getActionColor = (action) => {
  const colors = {
    create: 'success',
    update: 'info',
    delete: 'danger',
    view: 'secondary',
    login: 'success',
    logout: 'secondary',
    failed_login: 'danger',
    setup: 'primary'
  }
  return colors[action] || 'secondary'
}

const formatName = (str) => {
  if (!str) return ''
  return str.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}
</script>

<style scoped>
.activity-row {
  transition: background-color 0.2s ease;
}
.activity-row:hover {
  background-color: rgba(0, 0, 0, 0.02);
}
</style>
