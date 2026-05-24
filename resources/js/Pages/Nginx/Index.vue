<template>
  <MainLayout>
    <Head title="Nginx Configuration" />
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">Nginx Configuration</h4>
              <p class="mb-0 text-sm">Manage nginx configuration files for your domains</p>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="loadDomains" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                Refresh
              </button>
              <button class="btn bg-gradient-info mb-0" @click="testNginxConfig" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">rule</i>
                Test Config
              </button>
              <button class="btn bg-gradient-warning mb-0" @click="confirmReload" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">restart_alt</i>
                Reload Nginx
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Alert Messages -->
      <div class="row" v-if="alert.show">
        <div class="col-12">
          <div :class="`alert alert-${alert.type} alert-dismissible fade show`" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">{{ getAlertIcon(alert.type) }}</i></span>
            <span class="alert-text">{{ alert.message }}</span>
            <button type="button" class="btn-close" @click="alert.show = false"></button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div class="row" v-if="loading && domains.length === 0">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-secondary mt-2">Loading domains...</p>
        </div>
      </div>

      <!-- Domains List -->
      <div class="row" v-if="domains.length > 0">
        <div class="col-12">
          <div class="card">
            <div class="card-header pb-0">
              <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                    <input v-model="searchQuery" type="text" class="form-control" placeholder="Search domains...">
                  </div>
                  <span class="badge bg-gradient-primary">{{ filteredDomains.length }} domains</span>
                </div>
              </div>
              <p class="text-sm text-secondary mb-0">Edit nginx configuration for each domain</p>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Config File</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="domain in paginatedDomains" :key="domain.domain" class="domain-row">
                      <td>
                        <div class="d-flex align-items-center px-3 py-2">
                          <div class="icon-box-nginx me-3">
                            <i class="material-symbols-rounded text-info">language</i>
                          </div>
                          <div>
                            <h6 class="mb-0 text-sm font-weight-bold">{{ domain.domain }}</h6>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="material-symbols-rounded text-secondary text-xs me-1">description</i>
                          <span class="text-xs font-monospace text-secondary">{{ domain.configPath }}</span>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex flex-column gap-1">
                          <div v-if="domain.hasConfig" class="status-pill status-active">
                            <span class="pill-dot"></span>
                            Config Exists
                          </div>
                          <div v-else class="status-pill status-error">
                            <span class="pill-dot"></span>
                            No Config
                          </div>
                          
                          <div v-if="domain.isEnabled" class="status-pill status-info" style="font-size: 9px; padding: 2px 8px;">
                            Enabled
                          </div>
                          <div v-else class="status-pill status-secondary" style="font-size: 9px; padding: 2px 8px;">
                            Disabled
                          </div>
                        </div>
                      </td>
                      <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                          <button 
                            class="action-btn btn-edit" 
                            @click="openEditor(domain)"
                            title="Edit configuration"
                            :disabled="!domain.hasConfig"
                          >
                            <i class="material-symbols-rounded">edit</i>
                          </button>
                          <button 
                            class="action-btn"
                            :class="domain.isEnabled ? 'btn-toggle-on' : 'btn-toggle-off'"
                            @click="toggleDomain(domain)"
                            :title="domain.isEnabled ? 'Disable domain' : 'Enable domain'"
                            :disabled="!domain.hasConfig || toggling === domain.domain"
                          >
                            <span v-if="toggling === domain.domain" class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>
                            <i v-else class="material-symbols-rounded">
                              {{ domain.isEnabled ? 'toggle_on' : 'toggle_off' }}
                            </i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="filteredDomains.length === 0">
                        <td colspan="4" class="text-center py-5 text-secondary">
                          <div class="empty-state">
                            <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">folder_off</i>
                            <p class="mt-3">No domains found matching your search.</p>
                          </div>
                        </td>
                      </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Pagination -->
              <div v-if="filteredDomains.length > itemsPerPage" class="d-flex justify-content-between align-items-center p-3 border-top">
                <div class="text-xs text-secondary">
                  Showing {{ paginationStart + 1 }} to {{ Math.min(paginationEnd, filteredDomains.length) }} of {{ filteredDomains.length }} entries
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

      <!-- Editor Modal -->
      <div class="modal-backdrop fade show" v-if="showEditorModal" @click="closeEditor"></div>
      <div class="modal fade show d-block" v-if="showEditorModal">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90vw;">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title mb-0">Nginx Config: {{ editingDomain?.domain }}</h5>
                <p class="text-sm text-secondary mb-0">{{ editingDomain?.configPath }}</p>
              </div>
              <button type="button" class="btn-close" @click="closeEditor"></button>
            </div>
            <div class="modal-body">
              <transition name="fade">
                <div v-if="alert.show" :class="`alert alert-${alert.type} alert-dismissible fade show text-white mb-3`" role="alert">
                  <span class="alert-icon"><i class="material-symbols-rounded">{{ getAlertIcon(alert.type) }}</i></span>
                  <span class="alert-text ms-2 font-weight-bold text-white">{{ alert.message }}</span>
                  <button type="button" class="btn-close text-white" @click="alert.show = false" style="filter: invert(1);"></button>
                </div>
              </transition>
              <div class="alert alert-warning py-2 mb-3">
                <i class="material-symbols-rounded text-sm me-1">warning</i>
                <small>Be careful when editing nginx configuration. Invalid settings will be automatically reverted. A backup is created before saving.</small>
              </div>
              <div id="nginx-editor-container" class="nginx-editor-box border shadow-inner"></div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="closeEditor">Cancel</button>
              <button class="btn bg-gradient-info" @click="saveAndTest" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                Save & Test
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Reload Confirmation Modal -->
      <div class="modal-backdrop fade show" v-if="showReloadModal" @click="showReloadModal = false"></div>
      <div class="modal fade show d-block" v-if="showReloadModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-warning me-2">restart_alt</i>
                Reload Nginx
              </h5>
              <button type="button" class="btn-close" @click="showReloadModal = false"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to reload Nginx?</p>
              <p class="text-sm text-secondary mb-0">
                This will apply all configuration changes. Active connections may be briefly interrupted.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showReloadModal = false">Cancel</button>
              <button class="btn bg-gradient-warning" @click="reloadNginx" :disabled="reloading">
                <span v-if="reloading" class="spinner-border spinner-border-sm me-2"></span>
                Reload Now
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Test Config Modal -->
      <div class="modal-backdrop fade show" v-if="showTestModal" @click="showTestModal = false"></div>
      <div class="modal fade show d-block" v-if="showTestModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded me-2" :class="testResult?.success ? 'text-success' : 'text-danger'">
                  {{ testResult?.success ? 'check_circle' : 'error' }}
                </i>
                Configuration Test
              </h5>
              <button type="button" class="btn-close" @click="showTestModal = false"></button>
            </div>
            <div class="modal-body">
              <div :class="`alert alert-${testResult?.success ? 'success' : 'danger'} mb-3`">
                {{ testResult?.message }}
              </div>
              <pre class="bg-dark text-light p-3 rounded" style="font-size: 12px; max-height: 300px; overflow: auto;">{{ testResult?.output }}</pre>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showTestModal = false">Close</button>
              <button v-if="testResult?.success" class="btn bg-gradient-success" @click="reloadAfterTest">
                <i class="material-symbols-rounded text-sm me-1">restart_alt</i>
                Reload Nginx
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
import { ref, onMounted, computed, nextTick } from 'vue'
import axios from 'axios'
import ace from 'ace-builds'

// Import Ace components
import 'ace-builds/src-noconflict/mode-nginx'
import 'ace-builds/src-noconflict/theme-monokai'
import 'ace-builds/src-noconflict/ext-language_tools'
import 'ace-builds/src-noconflict/ext-searchbox'

const loading = ref(false)
const saving = ref(false)
const reloading = ref(false)
const toggling = ref(null)

const domains = ref([])
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

const showEditorModal = ref(false)
const showReloadModal = ref(false)
const showTestModal = ref(false)

const editingDomain = ref(null)
const editorContent = ref('')
const testResult = ref(null)

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

onMounted(() => {
  loadDomains()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const getAlertIcon = (type) => {
  const icons = {
    success: 'check_circle',
    danger: 'error',
    warning: 'warning',
    info: 'info'
  }
  return icons[type] || 'info'
}

const loadDomains = async () => {
  try {
    loading.value = true
    const response = await axios.get('/nginx/domains')
    domains.value = response.data.domains
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load domains')
  } finally {
    loading.value = false
  }
}

const filteredDomains = computed(() => {
  if (!searchQuery.value) return domains.value
  const q = searchQuery.value.toLowerCase()
  return domains.value.filter(d => d.domain.toLowerCase().includes(q))
})

const totalPages = computed(() => Math.ceil(filteredDomains.value.length / itemsPerPage.value))
const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage.value)
const paginationEnd = computed(() => currentPage.value * itemsPerPage.value)

const paginatedDomains = computed(() => {
  return filteredDomains.value.slice(paginationStart.value, paginationEnd.value)
})

let aceEditor = null

const initNginxEditor = () => {
  if (aceEditor) aceEditor.destroy()
  
  aceEditor = ace.edit("nginx-editor-container")
  aceEditor.setTheme("ace/theme/monokai")
  aceEditor.session.setMode("ace/mode/nginx")
  aceEditor.setValue(editorContent.value, -1)
  
  aceEditor.setOptions({
    fontSize: "14px",
    enableBasicAutocompletion: true,
    enableLiveAutocompletion: true,
    showPrintMargin: false,
    scrollPastEnd: 0.5,
    wrap: true
  })

  aceEditor.on('change', () => {
    editorContent.value = aceEditor.getValue()
  })
}

const openEditor = async (domain) => {
  try {
    loading.value = true
    const response = await axios.post('/nginx/config/read', { domain: domain.domain })
    editorContent.value = response.data.content
    editingDomain.value = domain
    showEditorModal.value = true
    
    nextTick(() => {
      initNginxEditor()
    })
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to read configuration')
  } finally {
    loading.value = false
  }
}

const closeEditor = () => {
  if (aceEditor) {
    aceEditor.destroy()
    aceEditor = null
  }
  showEditorModal.value = false
  editingDomain.value = null
  editorContent.value = ''
}

const saveAndTest = async () => {
  try {
    saving.value = true
    await axios.post('/nginx/config/save', {
      domain: editingDomain.value.domain,
      content: editorContent.value
    })
    showAlert('success', 'Configuration saved and tested successfully')
    closeEditor()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to save configuration')
  } finally {
    saving.value = false
  }
}

const toggleDomain = async (domain) => {
  try {
    toggling.value = domain.domain
    const response = await axios.post('/nginx/toggle', {
      domain: domain.domain,
      enabled: !domain.isEnabled
    })
    domain.isEnabled = !domain.isEnabled
    showAlert('success', response.data.message)
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to toggle domain')
  } finally {
    toggling.value = null
  }
}

const testNginxConfig = async () => {
  try {
    loading.value = true
    const response = await axios.post('/nginx/test')
    testResult.value = response.data
    showTestModal.value = true
  } catch (error) {
    testResult.value = {
      success: false,
      message: error.response?.data?.message || 'Configuration test failed',
      output: error.response?.data?.output || error.message
    }
    showTestModal.value = true
  } finally {
    loading.value = false
  }
}

const confirmReload = () => {
  showReloadModal.value = true
}

const reloadNginx = async () => {
  try {
    reloading.value = true
    await axios.post('/nginx/reload')
    showAlert('info', 'Nginx reload scheduled. Service will reload in 1 second.')
    showReloadModal.value = false
    
    // Wait and then show success
    setTimeout(() => {
      showAlert('success', 'Nginx reloaded successfully!')
    }, 2000)
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to reload Nginx')
  } finally {
    reloading.value = false
  }
}

const reloadAfterTest = async () => {
  showTestModal.value = false
  await reloadNginx()
}
</script>

<style scoped>
.domain-row {
  transition: all 0.2s ease;
}
.domain-row:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.icon-box-nginx {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f9fa;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 50px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.pill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 8px;
  position: relative;
}

.status-active {
  background: #e6f6ec;
  color: #0c6b36;
}
.status-active .pill-dot {
  background: #2dce89;
  box-shadow: 0 0 0 2px rgba(45, 206, 137, 0.2);
}

.status-info {
  background: #e9f2ff;
  color: #1171ef;
}

.status-error {
  background: #feeef2;
  color: #9d174d;
}
.status-error .pill-dot {
  background: #f5365c;
  box-shadow: 0 0 0 2px rgba(245, 54, 92, 0.2);
}

.status-secondary {
  background: #f1f5f9;
  color: #475569;
}

.action-btn {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  border: none;
  background: #fff;
  color: #67748e;
  transition: all 0.2s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.action-btn i {
  font-size: 1.25rem;
}

.action-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.btn-edit:hover { background: #1171ef; color: #fff; }
.btn-toggle-on { color: #2dce89; }
.btn-toggle-on:hover { background: #2dce89; color: #fff; }
.btn-toggle-off { color: #f5365c; }
.btn-toggle-off:hover { background: #f5365c; color: #fff; }

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.nginx-editor-box {
  width: 100%;
  height: 550px;
  border-radius: 12px;
  overflow: hidden;
  font-family: 'Fira Code', monospace;
}
</style>


