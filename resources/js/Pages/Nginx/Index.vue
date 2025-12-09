<template>
  <MainLayout>
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
                <h6 class="mb-0">Domain Configurations</h6>
                <span class="badge bg-gradient-primary">{{ domains.length }} domains</span>
              </div>
              <p class="text-sm text-secondary mb-0">Edit nginx configuration for each domain</p>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Config File</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="domain in domains" :key="domain.domain">
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="material-symbols-rounded text-info me-2">language</i>
                          <div>
                            <h6 class="mb-0 text-sm">{{ domain.domain }}</h6>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="text-xs font-monospace text-secondary">{{ domain.configPath }}</span>
                      </td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <span class="badge badge-sm bg-gradient-success" v-if="domain.hasConfig">
                            <i class="material-symbols-rounded text-xs me-1">check</i> Config exists
                          </span>
                          <span class="badge badge-sm bg-gradient-danger" v-else>
                            <i class="material-symbols-rounded text-xs me-1">close</i> No config
                          </span>
                          <span class="badge badge-sm bg-gradient-info" v-if="domain.isEnabled">
                            Enabled
                          </span>
                          <span class="badge badge-sm bg-gradient-secondary" v-else>
                            Disabled
                          </span>
                        </div>
                      </td>
                      <td class="text-center">
                        <button 
                          class="btn btn-link text-primary mb-0 px-2" 
                          @click="openEditor(domain)"
                          title="Edit configuration"
                          :disabled="!domain.hasConfig"
                        >
                          <i class="material-symbols-rounded text-sm">edit</i>
                        </button>
                        <button 
                          class="btn btn-link mb-0 px-2"
                          :class="domain.isEnabled ? 'text-warning' : 'text-success'"
                          @click="toggleDomain(domain)"
                          :title="domain.isEnabled ? 'Disable domain' : 'Enable domain'"
                          :disabled="!domain.hasConfig || toggling === domain.domain"
                        >
                          <span v-if="toggling === domain.domain" class="spinner-border spinner-border-sm"></span>
                          <i v-else class="material-symbols-rounded text-sm">
                            {{ domain.isEnabled ? 'toggle_on' : 'toggle_off' }}
                          </i>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="domains.length === 0">
                      <td colspan="4" class="text-center py-4 text-secondary">
                        No domains found
                      </td>
                    </tr>
                  </tbody>
                </table>
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
              <div class="alert alert-warning py-2 mb-3">
                <i class="material-symbols-rounded text-sm me-1">warning</i>
                <small>Be careful when editing nginx configuration. Invalid settings will be automatically reverted. A backup is created before saving.</small>
              </div>
              <textarea 
                v-model="editorContent" 
                class="form-control font-monospace" 
                rows="25"
                style="font-size: 13px;"
              ></textarea>
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
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const saving = ref(false)
const reloading = ref(false)
const toggling = ref(null)

const domains = ref([])

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

const openEditor = async (domain) => {
  try {
    loading.value = true
    const response = await axios.post('/nginx/config/read', { domain: domain.domain })
    editorContent.value = response.data.content
    editingDomain.value = domain
    showEditorModal.value = true
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to read configuration')
  } finally {
    loading.value = false
  }
}

const closeEditor = () => {
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
.modal {
  background: rgba(0, 0, 0, 0.5);
  position: fixed;
  z-index: 20050;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.modal-backdrop {
  position: fixed;
  z-index: 20040;
}

.modal-content {
  border: none;
  border-radius: 1rem;
  z-index: 20060;
}

.gap-2 {
  gap: 0.5rem;
}

textarea.font-monospace {
  font-family: 'Courier New', monospace;
  line-height: 1.5;
}

pre {
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
