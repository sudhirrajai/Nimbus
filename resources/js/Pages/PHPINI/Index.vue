<template>
  <MainLayout>
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">PHP Configuration</h4>
              <p class="mb-0 text-sm">Manage PHP settings and php.ini files</p>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="loadInfo" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                Refresh
              </button>
              <button class="btn bg-gradient-warning mb-0" @click="confirmRestart" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">restart_alt</i>
                Restart PHP-FPM
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Alert Messages -->
      <div class="row" v-if="alert.show">
        <div class="col-12">
          <div :class="`alert alert-${alert.type} alert-dismissible fade show`" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">{{ alert.type === 'success' ? 'check_circle' : 'error' }}</i></span>
            <span class="alert-text">{{ alert.message }}</span>
            <button type="button" class="btn-close" @click="alert.show = false"></button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div class="row" v-if="loading && !phpInfo">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-secondary mt-2">Loading PHP configuration...</p>
        </div>
      </div>

      <template v-if="phpInfo">
        <!-- PHP Info Card -->
        <div class="row mb-4">
          <div class="col-lg-4 col-md-6 mb-4">
            <div class="card">
              <div class="card-body p-3">
                <div class="d-flex align-items-center">
                  <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                    <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">code</i>
                  </div>
                  <div class="ms-3">
                    <p class="text-sm mb-0 text-capitalize">PHP Version</p>
                    <h4 class="mb-0">{{ phpInfo.version }}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-4 col-md-6 mb-4">
            <div class="card">
              <div class="card-body p-3">
                <div class="d-flex align-items-center">
                  <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                    <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">memory</i>
                  </div>
                  <div class="ms-3">
                    <p class="text-sm mb-0 text-capitalize">Memory Limit</p>
                    <h4 class="mb-0">{{ currentSettings.memory_limit || 'N/A' }}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-4 col-md-6 mb-4">
            <div class="card">
              <div class="card-body p-3">
                <div class="d-flex align-items-center">
                  <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                    <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">upload</i>
                  </div>
                  <div class="ms-3">
                    <p class="text-sm mb-0 text-capitalize">Upload Max Size</p>
                    <h4 class="mb-0">{{ currentSettings.upload_max_filesize || 'N/A' }}</h4>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Settings -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="card">
              <div class="card-header pb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">Quick Settings</h6>
                  <span class="badge bg-gradient-info">Common Settings</span>
                </div>
                <p class="text-sm text-secondary mb-0">Quickly modify common PHP settings</p>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 col-lg-4 mb-3" v-for="(value, key) in currentSettings" :key="key">
                    <div class="quick-setting-item">
                      <label class="form-label text-xs text-uppercase fw-bold text-secondary mb-1">
                        {{ formatSettingName(key) }}
                      </label>
                      <div class="input-group input-group-sm">
                        <input 
                          type="text" 
                          class="form-control" 
                          :value="value"
                          @blur="updateQuickSetting(key, $event.target.value)"
                          @keyup.enter="updateQuickSetting(key, $event.target.value)"
                        />
                        <button 
                          class="btn btn-sm bg-gradient-primary mb-0" 
                          @click="updateQuickSetting(key, $event.target.previousElementSibling.value)"
                          title="Save"
                        >
                          <i class="material-symbols-rounded text-xs">save</i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- INI Files -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="card">
              <div class="card-header pb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">PHP Configuration Files</h6>
                </div>
                <p class="text-sm text-secondary mb-0">Edit php.ini files directly</p>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table align-items-center mb-0">
                    <thead>
                      <tr>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">File</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Type</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="ini in iniFiles" :key="ini.path">
                        <td>
                          <div class="d-flex align-items-center">
                            <i class="material-symbols-rounded text-info me-2">description</i>
                            <div>
                              <h6 class="mb-0 text-sm">{{ ini.path }}</h6>
                            </div>
                          </div>
                        </td>
                        <td>
                          <span class="badge badge-sm" :class="getLabelClass(ini.label)">
                            {{ ini.label }}
                          </span>
                        </td>
                        <td>
                          <span class="badge badge-sm bg-gradient-success" v-if="ini.exists">
                            <i class="material-symbols-rounded text-xs me-1">check</i> Exists
                          </span>
                          <span class="badge badge-sm bg-gradient-danger" v-else>Missing</span>
                        </td>
                        <td class="text-center">
                          <button 
                            class="btn btn-link text-primary mb-0 px-2" 
                            @click="openEditor(ini)"
                            title="Edit file"
                            :disabled="!ini.exists"
                          >
                            <i class="material-symbols-rounded text-sm">edit</i>
                          </button>
                        </td>
                      </tr>
                      <tr v-if="iniFiles.length === 0">
                        <td colspan="4" class="text-center py-4 text-secondary">
                          No PHP configuration files found
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

      <!-- Editor Modal -->
      <div class="modal-backdrop fade show" v-if="showEditorModal" @click="closeEditor"></div>
      <div class="modal fade show d-block" v-if="showEditorModal">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90vw;">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h5 class="modal-title mb-0">Edit: {{ editingFile?.path }}</h5>
                <p class="text-sm text-secondary mb-0">{{ editingFile?.label }}</p>
              </div>
              <button type="button" class="btn-close" @click="closeEditor"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-warning py-2 mb-3">
                <i class="material-symbols-rounded text-sm me-1">warning</i>
                <small>Be careful when editing php.ini. Invalid settings may cause PHP to fail. A backup will be created before saving.</small>
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
              <button class="btn bg-gradient-success" @click="saveIniFile" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                Save Changes
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Restart Confirmation Modal -->
      <div class="modal-backdrop fade show" v-if="showRestartModal" @click="showRestartModal = false"></div>
      <div class="modal fade show d-block" v-if="showRestartModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-warning me-2">restart_alt</i>
                Restart PHP-FPM
              </h5>
              <button type="button" class="btn-close" @click="showRestartModal = false"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to restart PHP-FPM?</p>
              <p class="text-sm text-secondary mb-0">
                This will apply any configuration changes and briefly interrupt PHP processing.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showRestartModal = false">Cancel</button>
              <button class="btn bg-gradient-warning" @click="restartPhp" :disabled="restarting">
                <span v-if="restarting" class="spinner-border spinner-border-sm me-2"></span>
                Restart Now
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
const restarting = ref(false)

const phpInfo = ref(null)
const iniFiles = ref([])
const currentSettings = ref({})

const showEditorModal = ref(false)
const showRestartModal = ref(false)
const editingFile = ref(null)
const editorContent = ref('')

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

onMounted(() => {
  loadInfo()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const loadInfo = async () => {
  try {
    loading.value = true
    const response = await axios.get('/php/info')
    phpInfo.value = response.data.php
    iniFiles.value = response.data.iniFiles
    currentSettings.value = response.data.currentSettings
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load PHP info')
  } finally {
    loading.value = false
  }
}

const formatSettingName = (key) => {
  return key.replace(/_/g, ' ').replace(/\./g, ' ')
}

const getLabelClass = (label) => {
  if (label.includes('FPM') || label.includes('Web')) return 'bg-gradient-success'
  if (label.includes('CLI')) return 'bg-gradient-secondary'
  if (label.includes('Apache')) return 'bg-gradient-info'
  return 'bg-gradient-dark'
}

const openEditor = async (ini) => {
  try {
    loading.value = true
    const response = await axios.post('/php/read', { path: ini.path })
    editorContent.value = response.data.content
    editingFile.value = ini
    showEditorModal.value = true
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to read file')
  } finally {
    loading.value = false
  }
}

const closeEditor = () => {
  showEditorModal.value = false
  editingFile.value = null
  editorContent.value = ''
}

const saveIniFile = async () => {
  try {
    saving.value = true
    await axios.post('/php/save', {
      path: editingFile.value.path,
      content: editorContent.value
    })
    showAlert('success', 'PHP configuration saved successfully. Remember to restart PHP-FPM to apply changes.')
    closeEditor()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to save file')
  } finally {
    saving.value = false
  }
}

const updateQuickSetting = async (setting, value) => {
  if (!value.trim()) return
  
  // Find the FPM ini file (primary)
  const fpmIni = iniFiles.value.find(ini => ini.label.includes('FPM'))
  if (!fpmIni) {
    showAlert('danger', 'PHP-FPM configuration file not found')
    return
  }

  try {
    loading.value = true
    await axios.post('/php/update-setting', {
      path: fpmIni.path,
      setting: setting,
      value: value.trim()
    })
    showAlert('success', `Setting '${setting}' updated. Restart PHP-FPM to apply.`)
    // Reload current settings
    loadInfo()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to update setting')
    loading.value = false
  }
}

const confirmRestart = () => {
  showRestartModal.value = true
}

const restartPhp = async () => {
  try {
    restarting.value = true
    const version = phpInfo.value?.version?.split('.').slice(0, 2).join('.') || '8.2'
    await axios.post('/php/restart', { version })
    showAlert('success', 'PHP-FPM restarted successfully')
    showRestartModal.value = false
    // Reload info to show updated settings
    loadInfo()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to restart PHP-FPM')
  } finally {
    restarting.value = false
  }
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

.icon-shape {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.quick-setting-item {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 12px;
}

.quick-setting-item .form-control {
  font-family: monospace;
  font-size: 14px;
}

textarea.font-monospace {
  font-family: 'Courier New', monospace;
  line-height: 1.5;
}

.gap-2 {
  gap: 0.5rem;
}
</style>
