<template>
  <MainLayout>
    <Head title="Node.js Version Manager" />
    <div class="container-fluid py-3">

      <!-- Header Card -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card bg-gradient-dark shadow-xl border-0 overflow-hidden">
            <div class="card-body p-4 position-relative">
              <div class="row align-items-center">
                <div class="col-12 col-md-8">
                  <div class="d-flex align-items-center gap-3">
                    <div class="icon-shape bg-white text-dark shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                      <i class="material-symbols-rounded text-success text-2xl">javascript</i>
                    </div>
                    <div>
                      <h4 class="text-white font-weight-bolder mb-1 d-flex align-items-center gap-2">
                        Node.js Version Manager
                        <span v-if="status.active_version" class="badge bg-success text-xxs font-monospace">
                          Active: v{{ status.active_version }}
                        </span>
                      </h4>
                      <p class="text-white text-xs mb-0 opacity-8">
                        Manage multiple Node.js runtimes system-wide. Switch global versions, install LTS releases, and isolate project environments.
                      </p>
                    </div>
                  </div>
                </div>

                <div class="col-12 col-md-4 text-md-end text-start mt-3 mt-md-0">
                  <button class="btn btn-sm btn-white text-dark mb-0 shadow-sm d-inline-flex align-items-center gap-1" @click="fetchStatus" :disabled="loading">
                    <i class="material-symbols-rounded text-sm" :class="{ 'spin-animation': loading }">refresh</i>
                    <span>Refresh</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Toast Alerts -->
      <div v-if="toast.show" class="position-fixed top-3 end-3 z-index-3" style="z-index: 9999; max-width: 420px;">
        <div class="alert alert-dismissible text-white fade show shadow-lg border-0 d-flex align-items-center gap-2 mb-0"
          :class="toast.type === 'danger' ? 'bg-gradient-danger' : (toast.type === 'warning' ? 'bg-gradient-warning' : 'bg-gradient-success')">
          <i class="material-symbols-rounded text-md">
            {{ toast.type === 'danger' ? 'error' : (toast.type === 'warning' ? 'warning' : 'check_circle') }}
          </i>
          <span class="text-xs flex-grow-1">{{ toast.message }}</span>
          <button type="button" class="btn-close text-lg py-3 opacity-10" @click="toast.show = false">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>

      <!-- Metrics Row -->
      <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
          <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-xxs text-uppercase font-weight-bold text-secondary mb-0">Active Default</p>
                  <h4 class="font-weight-bolder mb-0 text-success">
                    {{ status.active_version ? 'v' + status.active_version : 'None' }}
                  </h4>
                  <span class="text-xxs text-secondary">Global CLI runtime</span>
                </div>
                <div class="icon-shape bg-gradient-success text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                  <i class="material-symbols-rounded">verified</i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
          <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-xxs text-uppercase font-weight-bold text-secondary mb-0">Installed Versions</p>
                  <h4 class="font-weight-bolder mb-0 text-dark">
                    {{ status.installed_versions?.length || 0 }}
                  </h4>
                  <span class="text-xxs text-secondary">Available locally</span>
                </div>
                <div class="icon-shape bg-gradient-dark text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                  <i class="material-symbols-rounded">folder_zip</i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
          <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-xxs text-uppercase font-weight-bold text-secondary mb-0">Package Manager</p>
                  <h4 class="font-weight-bolder mb-0 text-dark">
                    {{ status.npm_version ? 'npm v' + status.npm_version : 'N/A' }}
                  </h4>
                  <span class="text-xxs text-secondary">Global package manager</span>
                </div>
                <div class="icon-shape bg-gradient-danger text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                  <i class="material-symbols-rounded">package_2</i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
          <div class="card h-100 shadow-sm border">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-xxs text-uppercase font-weight-bold text-secondary mb-0">Version Engine</p>
                  <h4 class="font-weight-bolder mb-0 text-primary">n CLI</h4>
                  <span class="text-xxs text-success">System-wide active</span>
                </div>
                <div class="icon-shape bg-gradient-primary text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                  <i class="material-symbols-rounded">terminal</i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Installation Progress Card (When Installing) -->
      <div v-if="installing" class="row mb-4">
        <div class="col-12">
          <div class="card border border-warning shadow-sm bg-gray-50">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                  <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                  <h6 class="font-weight-bold text-dark mb-0">{{ installMessage || 'Installing Node.js version...' }}</h6>
                </div>
                <span class="badge bg-warning text-dark text-xxs font-weight-bold">Background Job Active</span>
              </div>
              <pre class="bg-dark text-white p-2 border-radius-lg font-monospace text-xxs mb-0" style="max-height: 140px; overflow-y: auto;">{{ installLogs || 'Downloading pre-compiled binary package from nodejs.org...' }}</pre>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Install LTS Cards -->
      <div class="row mb-4">
        <div class="col-12 mb-2">
          <h6 class="font-weight-bolder text-dark mb-1 d-flex align-items-center gap-2">
            <i class="material-symbols-rounded text-primary text-sm">stars</i>
            Recommended Official LTS Releases
          </h6>
          <p class="text-xs text-secondary mb-0">One-click install official verified Long Term Support (LTS) releases.</p>
        </div>

        <div v-for="lts in status.available_lts" :key="lts.major" class="col-md-4 col-12 mb-3">
          <div class="card h-100 shadow-sm border" :class="lts.installed ? 'border-success' : ''">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-dark text-white text-xs font-monospace">Node {{ lts.major }}</span>
                    <span class="text-xxs text-secondary">Codename: {{ lts.codename }}</span>
                  </div>
                  <span v-if="lts.installed" class="badge bg-gradient-success text-xxs d-inline-flex align-items-center gap-1">
                    <i class="material-symbols-rounded text-xxs">check_circle</i> Installed
                  </span>
                  <span v-else class="badge bg-gray-100 text-secondary text-xxs">Not Installed</span>
                </div>
                <h6 class="font-weight-bold text-dark mb-1">{{ lts.name }}</h6>
                <p class="text-xxs text-secondary mb-3">{{ lts.description }}</p>
              </div>

              <div>
                <button v-if="!lts.installed" class="btn btn-sm bg-gradient-primary w-100 mb-0 d-inline-flex align-items-center justify-content-center gap-1"
                  @click="triggerInstall(lts.major)" :disabled="installing">
                  <i class="material-symbols-rounded text-sm">download</i>
                  <span>1-Click Install Node {{ lts.major }}</span>
                </button>
                <button v-else-if="status.active_version?.startsWith(lts.major)" class="btn btn-sm btn-outline-success w-100 mb-0 disabled">
                  <i class="material-symbols-rounded text-sm me-1">check</i> Currently Active
                </button>
                <button v-else class="btn btn-sm btn-outline-dark w-100 mb-0 d-inline-flex align-items-center justify-content-center gap-1"
                  @click="triggerSwitch(lts.major)" :disabled="actionLoading">
                  <i class="material-symbols-rounded text-sm">swap_horiz</i>
                  <span>Switch Global to v{{ lts.major }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Installed Versions Table -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card shadow-sm border">
            <div class="card-header bg-white border-bottom p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <h6 class="font-weight-bolder text-dark mb-0">Installed Node.js Runtimes</h6>
                <p class="text-xxs text-secondary mb-0">All Node.js binaries available on this system</p>
              </div>

              <!-- Custom Version Install Input -->
              <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="max-width: 220px;">
                  <span class="input-group-text bg-gray-100 border-end-0 text-xxs font-monospace">Node</span>
                  <input type="text" v-model="customVersion" class="form-control text-xs border-start-0" placeholder="e.g. 22.12.0" :disabled="installing">
                </div>
                <button class="btn btn-sm bg-gradient-dark mb-0 d-inline-flex align-items-center gap-1 shadow-sm"
                  @click="triggerInstall(customVersion)" :disabled="installing || !customVersion.trim()">
                  <i class="material-symbols-rounded text-sm">add</i>
                  <span>Install</span>
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table align-items-center mb-0">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="ps-3 py-2 text-uppercase text-secondary text-xxs font-weight-bolder">Version</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Channel</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Binary Location</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Status</th>
                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder pe-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ver in status.installed_versions" :key="ver.version" class="hover-bg-gray-50">
                    <td class="ps-3 py-2">
                      <div class="d-flex align-items-center gap-2">
                        <i class="material-symbols-rounded text-success text-lg">javascript</i>
                        <span class="font-weight-bold text-dark font-monospace text-sm">v{{ ver.version }}</span>
                      </div>
                    </td>
                    <td>
                      <span v-if="ver.is_lts" class="badge bg-info text-xxs">LTS Release</span>
                      <span v-else class="badge bg-secondary text-xxs">Current</span>
                    </td>
                    <td>
                      <span class="text-xxs font-monospace text-secondary">{{ ver.path }}</span>
                    </td>
                    <td>
                      <span v-if="ver.is_active" class="badge bg-gradient-success text-xxs d-inline-flex align-items-center gap-1">
                        <i class="material-symbols-rounded text-xxs">check_circle</i> Active Global Default
                      </span>
                      <span v-else class="badge bg-gray-100 text-secondary text-xxs">
                        Standby
                      </span>
                    </td>
                    <td class="text-center pe-3">
                      <div class="d-flex align-items-center justify-content-center gap-1">
                        <button v-if="!ver.is_active" class="btn btn-xs btn-outline-primary mb-0 d-inline-flex align-items-center gap-1"
                          @click="triggerSwitch(ver.version)" :disabled="actionLoading" title="Set this version as system default">
                          <i class="material-symbols-rounded text-xs">swap_horiz</i>
                          <span>Set Default</span>
                        </button>
                        <button v-if="!ver.is_active" class="btn btn-xs btn-outline-danger mb-0 d-inline-flex align-items-center gap-1"
                          @click="triggerUninstall(ver.version)" :disabled="actionLoading" title="Uninstall this version">
                          <i class="material-symbols-rounded text-xs">delete</i>
                        </button>
                        <span v-if="ver.is_active" class="text-xxs text-muted fst-italic">Active System Default</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Card Footer: CLI Usage Guide -->
            <div class="card-footer bg-gray-50 border-top p-3">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="text-xxs text-secondary">
                  <i class="material-symbols-rounded text-xs me-1 align-middle text-primary">info</i>
                  In SSH or Web Terminal, you can also switch versions anytime using: 
                  <code class="text-dark bg-white px-2 py-0 border-radius-sm border font-weight-bold">n 22</code> or 
                  <code class="text-dark bg-white px-2 py-0 border-radius-sm border font-weight-bold">n lts</code>
                </span>
                <span class="text-xxs text-muted">
                  Storage location: <code class="text-secondary">/usr/local/n/versions/node/</code>
                </span>
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
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import axios from 'axios'

const loading = ref(false)
const actionLoading = ref(false)
const installing = ref(false)
const installMessage = ref('')
const installLogs = ref('')
const customVersion = ref('')
let pollTimer = null

const status = ref({
  node_installed: false,
  active_version: '',
  active_path: '',
  npm_version: '',
  n_installed: false,
  installed_versions: [],
  available_lts: []
})

const toast = ref({
  show: false,
  type: 'success',
  message: ''
})

const showToast = (type, message) => {
  toast.value = { show: true, type, message }
  setTimeout(() => { toast.value.show = false }, 5000)
}

const fetchStatus = async () => {
  try {
    loading.value = true
    const res = await axios.get('/node/status')
    status.value = res.data
  } catch (err) {
    showToast('danger', err.response?.data?.error || 'Failed to fetch Node.js status')
  } finally {
    loading.value = false
  }
}

const triggerInstall = async (version) => {
  if (!version) return
  try {
    installing.value = true
    installLogs.value = ''
    installMessage.value = `Starting installation of Node.js ${version}...`
    const res = await axios.post('/node/install', { version })
    showToast('success', res.data.message || `Node.js ${version} install initiated.`)
    customVersion.value = ''
    startPolling()
  } catch (err) {
    installing.value = false
    showToast('danger', err.response?.data?.error || 'Failed to start installation')
  }
}

const pollInstallStatus = async () => {
  try {
    const res = await axios.get('/node/install-status')
    installLogs.value = res.data.logs || ''
    installMessage.value = res.data.message || ''

    if (res.data.status === 'completed') {
      stopPolling()
      installing.value = false
      showToast('success', 'Node.js installed successfully!')
      await fetchStatus()
    } else if (res.data.status === 'failed') {
      stopPolling()
      installing.value = false
      showToast('danger', res.data.message || 'Node.js installation failed. Check logs.')
      await fetchStatus()
    } else if (res.data.status === 'idle') {
      stopPolling()
      installing.value = false
      await fetchStatus()
    }
  } catch (err) {
    stopPolling()
    installing.value = false
  }
}

const startPolling = () => {
  stopPolling()
  pollTimer = setInterval(pollInstallStatus, 2000)
}

const stopPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

const triggerSwitch = async (version) => {
  try {
    actionLoading.value = true
    const res = await axios.post('/node/switch', { version })
    showToast('success', res.data.message || `Switched to Node.js ${version}`)
    await fetchStatus()
  } catch (err) {
    showToast('danger', err.response?.data?.error || 'Failed to switch version')
  } finally {
    actionLoading.value = false
  }
}

const triggerUninstall = async (version) => {
  if (!confirm(`Are you sure you want to remove Node.js ${version}?`)) return
  try {
    actionLoading.value = true
    const res = await axios.post('/node/uninstall', { version })
    showToast('success', res.data.message || `Node.js ${version} removed`)
    await fetchStatus()
  } catch (err) {
    showToast('danger', err.response?.data?.error || 'Failed to remove version')
  } finally {
    actionLoading.value = false
  }
}

onMounted(() => {
  fetchStatus()
  pollInstallStatus()
})

onUnmounted(() => {
  stopPolling()
})
</script>

<style scoped>
.spin-animation {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.hover-bg-gray-50:hover {
  background-color: #f8f9fa;
}
</style>
