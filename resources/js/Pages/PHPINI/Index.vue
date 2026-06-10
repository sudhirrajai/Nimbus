<template>
  <MainLayout>
    <Head title="PHP Configuration" />
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
              <button class="btn btn-outline-primary mb-0" @click="syncNginxLimits" :disabled="syncing || loading">
                <i v-if="syncing" class="spinner-border spinner-border-sm me-1"></i>
                <i v-else class="material-symbols-rounded text-sm me-1">sync_alt</i>
                Sync with Nginx
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
            <span class="alert-icon"><i class="material-symbols-rounded">{{ alert.type === 'success' ? 'check_circle' :
                'error' }}</i></span>
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
        <!-- Tabs Navigation -->
        <div class="nav-wrapper position-relative end-0 mb-4">
          <ul class="nav nav-pills nav-pills-primary nav-fill p-1 flex-row" role="tablist" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
            <li class="nav-item">
              <a class="nav-link mb-0 px-0 py-2 d-flex align-items-center justify-content-center text-sm font-weight-bold" :class="{ active: activeTab === 'settings' }" @click="activeTab = 'settings'" href="javascript:;" role="tab" style="border-radius: 8px; transition: all 0.2s;">
                <i class="material-symbols-rounded text-sm me-2">tune</i>
                INI Settings
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link mb-0 px-0 py-2 d-flex align-items-center justify-content-center text-sm font-weight-bold" :class="{ active: activeTab === 'versions' }" @click="activeTab = 'versions'" href="javascript:;" role="tab" style="border-radius: 8px; transition: all 0.2s;">
                <i class="material-symbols-rounded text-sm me-2">dns</i>
                PHP Versions
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link mb-0 px-0 py-2 d-flex align-items-center justify-content-center text-sm font-weight-bold" :class="{ active: activeTab === 'extensions' }" @click="activeTab = 'extensions'" href="javascript:;" role="tab" style="border-radius: 8px; transition: all 0.2s;">
                <i class="material-symbols-rounded text-sm me-2">grid_view</i>
                Extensions
              </a>
            </li>
          </ul>
        </div>

        <!-- Settings Tab -->
        <template v-if="activeTab === 'settings'">
          <!-- PHP Info Card -->
          <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-4">
              <div class="card h-100">
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
              <div class="card h-100">
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
              <div class="card h-100">
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
                          <input type="text" class="form-control" :value="value" :ref="`input-${key}`"
                            @keyup.enter="updateQuickSetting(key, $event.target.value)" />
                          <button class="btn btn-sm bg-gradient-primary mb-0"
                            @click="updateQuickSetting(key, $refs[`input-${key}`][0].value)" title="Save"
                            :disabled="updatingSettings[key]">
                            <span v-if="updatingSettings[key]" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="material-symbols-rounded text-xs">save</i>
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
                          <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                            Actions</th>
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
                            <span v-if="ini.exists" class="status-pill status-active">
                              <span class="pill-dot"></span>
                              Exists
                            </span>

                            <span v-else class="status-pill status-error">
                              <span class="pill-dot"></span>
                              Missing
                            </span>
                          </td>
                          <td class="text-center">
                            <div class="d-flex justify-content-center">
                              <button class="action-btn btn-edit" @click="openEditor(ini)" title="Edit file"
                                :disabled="!ini.exists">
                                <i class="material-symbols-rounded">edit</i>
                              </button>
                            </div>
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

        <!-- PHP Versions Tab -->
        <div v-if="activeTab === 'versions'" class="row">
          <!-- Active Installation banner if installing -->
          <div v-if="versionInstalling" class="col-12 mb-4">
            <div class="card bg-gradient-dark text-white">
              <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                  <h5 class="text-white font-weight-bolder mb-1">PHP Installation in Progress</h5>
                  <p class="text-white opacity-8 text-sm mb-0">An installation script is currently running on the server. Please do not close this page.</p>
                </div>
                <button class="btn btn-outline-white mb-0 mt-2 mt-md-0" @click="openActiveLogViewer('version')">
                  <i class="material-symbols-rounded text-sm me-1">terminal</i>
                  View Live Terminal Output
                </button>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card">
              <div class="card-header pb-0">
                <h6>Available PHP Versions</h6>
                <p class="text-sm text-secondary mb-0">Install new versions or restart specific service pools.</p>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table align-items-center mb-0">
                    <thead>
                      <tr>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">PHP Version</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Service Name</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="ver in phpVersions" :key="ver.version">
                        <td>
                          <div class="d-flex align-items-center px-2 py-1">
                            <div class="icon icon-shape icon-sm bg-gradient-primary shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center">
                              <i class="material-symbols-rounded text-white opacity-10" style="font-size: 1rem;">code</i>
                            </div>
                            <h6 class="mb-0 text-sm font-weight-bold">PHP {{ ver.version }}</h6>
                          </div>
                        </td>
                        <td>
                          <span class="text-xs font-weight-bold text-secondary">{{ ver.service }}</span>
                        </td>
                        <td>
                          <span v-if="ver.installed && ver.active" class="status-pill status-active">
                            <span class="pill-dot"></span>
                            Active
                          </span>
                          <span v-else-if="ver.installed && !ver.active" class="status-pill status-error" title="Service stopped">
                            <span class="pill-dot"></span>
                            Stopped
                          </span>
                          <span v-else class="status-pill status-muted">
                            <span class="pill-dot"></span>
                            Not Installed
                          </span>
                        </td>
                        <td class="align-middle text-center">
                          <div class="d-flex justify-content-center gap-2">
                            <button 
                              v-if="ver.installed"
                              class="btn btn-xs btn-outline-warning mb-0" 
                              @click="restartSpecificPhp(ver.version)"
                              :disabled="restarting"
                            >
                              <i class="material-symbols-rounded text-xs me-1">restart_alt</i>
                              Restart
                            </button>
                            <button 
                              v-else
                              class="btn btn-xs btn-outline-primary mb-0" 
                              @click="installPhpVersion(ver.version)"
                              :disabled="versionInstalling"
                            >
                              <i class="material-symbols-rounded text-xs me-1">download</i>
                              Install PHP
                            </button>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PHP Extensions Tab -->
        <div v-if="activeTab === 'extensions'" class="row">
          <!-- Active Installation banner if extension is installing -->
          <div v-if="extInstalling" class="col-12 mb-4">
            <div class="card bg-gradient-dark text-white">
              <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                  <h5 class="text-white font-weight-bolder mb-1">Extension Installation in Progress</h5>
                  <p class="text-white opacity-8 text-sm mb-0">An extension install script is currently running on the server. Please wait.</p>
                </div>
                <button class="btn btn-outline-white mb-0 mt-2 mt-md-0" @click="openActiveLogViewer('extension')">
                  <i class="material-symbols-rounded text-sm me-1">terminal</i>
                  View Live Terminal Output
                </button>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="card">
              <div class="card-header pb-2 border-bottom">
                <div class="row align-items-center">
                  <div class="col-md-6">
                    <h6 class="mb-0">PHP Extensions Manager</h6>
                    <p class="text-sm text-secondary mb-0">Install common extensions for the selected PHP version.</p>
                  </div>
                  <div class="col-md-6 d-flex gap-2 justify-content-md-end mt-2 mt-md-0 align-items-center">
                    <span class="text-xs font-weight-bold text-secondary">PHP Version:</span>
                    <div class="input-group input-group-outline" style="max-width: 140px; margin-right: 10px;">
                      <select v-model="selectedExtensionVersion" @change="changeExtensionVersion" class="form-select form-control" style="padding: 0.35rem 0.5rem;">
                        <option v-for="ver in phpVersions.filter(v => v.installed)" :key="ver.version" :value="ver.version">
                          PHP {{ ver.version }}
                        </option>
                      </select>
                    </div>
                    <div class="input-group input-group-outline" style="max-width: 200px;">
                      <input v-model="extSearchQuery" type="text" class="form-control" placeholder="Search extensions..." style="padding: 0.35rem 0.5rem;" />
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-body">
                <div v-if="loadingExtensions" class="text-center py-5">
                  <div class="spinner-border text-primary" role="status"></div>
                  <p class="text-secondary text-sm mt-2">Reading PHP loaded modules...</p>
                </div>
                <div v-else class="row">
                  <div v-for="ext in filteredExtensions" :key="ext.name" class="col-md-6 col-lg-4 mb-4">
                    <div class="card shadow-none border" style="border-radius: 12px; transition: border-color 0.2s ease;">
                      <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                          <div style="flex: 1; margin-right: 10px;">
                            <span class="badge bg-light text-dark text-xxs font-weight-bold mb-2" style="border: 1px solid #e9ecef; padding: 2px 6px;">
                              {{ ext.package }}
                            </span>
                            <h6 class="mb-1 text-sm font-weight-bold">{{ ext.name }}</h6>
                            <p class="text-xs text-secondary mb-0" style="min-height: 36px; line-height: 1.3;">
                              {{ ext.description }}
                            </p>
                          </div>
                          <div>
                            <span v-if="ext.installed" class="badge bg-gradient-success text-xxs font-weight-bold d-flex align-items-center" style="padding: 4px 8px; border-radius: 100px;">
                              <i class="material-symbols-rounded text-xxs me-1">check_circle</i>
                              Active
                            </span>
                            <button 
                              v-else 
                              class="btn btn-xs bg-gradient-primary mb-0 d-flex align-items-center" 
                              @click="installExtension(ext.name)"
                              :disabled="extInstalling"
                              style="padding: 4px 10px;"
                            >
                              <i class="material-symbols-rounded text-xxs me-1">download</i>
                              Install
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="filteredExtensions.length === 0" class="col-12 text-center py-4 text-secondary text-sm">
                    No extensions match your search query.
                  </div>
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
                <small>Be careful when editing php.ini. Invalid settings may cause PHP to fail. A backup will be created
                  before saving.</small>
              </div>
              <textarea v-model="editorContent" class="form-control font-monospace" rows="25"
                style="font-size: 13px;"></textarea>
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

      <!-- Terminal Log Modal -->
      <div class="modal-backdrop fade show" v-if="showLogModal" @click="showLogModal = false"></div>
      <div class="modal fade show d-block" v-if="showLogModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content bg-dark text-white border-0">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title text-white font-weight-bolder d-flex align-items-center">
                <i class="material-symbols-rounded me-2">terminal</i>
                {{ currentInstallLogTitle }}
              </h5>
              <button type="button" class="btn-close btn-close-white" @click="showLogModal = false"></button>
            </div>
            <div class="modal-body pt-3">
              <div class="bg-black p-3 rounded font-monospace" style="height: 400px; overflow-y: auto; font-size: 12px; color: #10b981; border: 1px solid #334155; text-align: left;">
                <pre class="mb-0 text-wrap" style="color: #10b981; white-space: pre-wrap; font-family: monospace;">{{ currentActiveLoggingType === 'version' ? versionInstallLog : extInstallLog }}</pre>
                <!-- Show small blinking cursor if installing -->
                <div v-if="versionInstalling || extInstalling" class="d-inline-block bg-success ms-1" style="width: 8px; height: 14px; animation: blink 1s step-end infinite;"></div>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <span class="text-xs text-muted me-auto" v-if="versionInstalling || extInstalling">
                <span class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px; border-width: 1px;"></span>
                Running installation script on host...
              </span>
              <span class="text-xs text-success me-auto" v-else>
                <i class="material-symbols-rounded text-xs me-1">check_circle</i>
                Process completed.
              </span>
              <button class="btn btn-secondary mb-0" @click="showLogModal = false">Close</button>
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
import { ref, onMounted, reactive, computed } from 'vue'
import axios from 'axios'

const loading = ref(false)
const saving = ref(false)
const restarting = ref(false)
const syncing = ref(false)
const updatingSettings = reactive({})

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

const activeTab = ref('settings')

// PHP Versions variables
const phpVersions = ref([])
const loadingVersions = ref(false)
const versionInstalling = ref(false)
const versionInstallLog = ref('')
const versionInstallTimer = ref(null)

// PHP Extensions variables
const selectedExtensionVersion = ref('8.2')
const extensionsList = ref([])
const loadingExtensions = ref(false)
const extInstalling = ref(false)
const extInstallLog = ref('')
const extInstallTimer = ref(null)
const extSearchQuery = ref('')

const showLogModal = ref(false)
const currentActiveLoggingType = ref('') // 'version' or 'extension'
const currentInstallLogTitle = ref('')

onMounted(() => {
  loadInfo()
  loadPhpVersions()
  checkInstallStatusOnLoad()
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
  if (!value || !value.trim()) return

  // Find the FPM and CLI ini files
  const fpmIni = iniFiles.value.find(ini => ini.label.includes('FPM'))
  const cliIni = iniFiles.value.find(ini => ini.label.includes('CLI'))

  if (!fpmIni) {
    showAlert('danger', 'PHP-FPM configuration file not found')
    return
  }

  try {
    updatingSettings[setting] = true

    // Update the setting in FPM
    await axios.post('/php/update-setting', {
      path: fpmIni.path,
      setting: setting,
      value: value.trim()
    })

    // Also update in CLI if it exists
    if (cliIni) {
      try {
        await axios.post('/php/update-setting', {
          path: cliIni.path,
          setting: setting,
          value: value.trim()
        })
      } catch (cliError) {
        console.warn('Failed to update CLI ini:', cliError)
      }
    }

    // Update local display immediately (optimistic update)
    currentSettings.value[setting] = value.trim()

    showAlert('info', `Setting '${setting}' updated. Restarting PHP-FPM...`)

    // Auto-restart PHP-FPM
    await autoRestartPhp()

    // Wait longer for PHP-FPM to fully restart, then reload settings
    setTimeout(async () => {
      await loadInfo()
      showAlert('success', `PHP-FPM restarted! Setting '${setting}' is now active.`)
    }, 5000) // Increased to 5 seconds

  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to update setting')
    // Reload to show actual values if update failed
    await loadInfo()
  } finally {
    updatingSettings[setting] = false
  }
}

const autoRestartPhp = async () => {
  try {
    const version = phpInfo.value?.version?.split('.').slice(0, 2).join('.') || '8.2'
    await axios.post('/php/restart', { version })
  } catch (error) {
    console.error('Auto-restart failed:', error)
    throw error
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
    showAlert('info', 'PHP-FPM restart scheduled. Reloading settings in 5 seconds...')
    showRestartModal.value = false

    // Wait longer for PHP-FPM to restart, then reload info
    setTimeout(async () => {
      await loadInfo()
      showAlert('success', 'PHP-FPM restarted and settings reloaded successfully!')
    }, 5000) // Increased to 5 seconds
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to restart PHP-FPM')
  } finally {
    restarting.value = false
  }
}

const syncNginxLimits = async () => {
  try {
    syncing.value = true
    const response = await axios.post('/php/sync-nginx-limits')
    showAlert('success', response.data.message || 'Nginx limits synchronized with PHP successfully!')
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to synchronize Nginx limits')
  } finally {
    syncing.value = false
  }
}

// PHP Versions methods
const loadPhpVersions = async () => {
  try {
    loadingVersions.value = true
    const res = await axios.get('/php/versions')
    phpVersions.value = res.data.versions
    
    // Set default extension version to first installed version found
    const firstInstalled = res.data.versions.find(v => v.installed)
    if (firstInstalled) {
      selectedExtensionVersion.value = firstInstalled.version
      loadExtensions()
    }
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to load PHP versions')
  } finally {
    loadingVersions.value = false
  }
}

const restartSpecificPhp = async (version) => {
  try {
    restarting.value = true
    await axios.post('/php/restart', { version })
    showAlert('info', `PHP ${version} FPM restart scheduled. Service will reload in 5 seconds.`)
    setTimeout(async () => {
      await loadPhpVersions()
      showAlert('success', `PHP ${version} FPM has been restarted successfully.`)
    }, 5000)
  } catch (error) {
    showAlert('danger', error.response?.data?.error || `Failed to restart PHP ${version} FPM`)
  } finally {
    restarting.value = false
  }
}

const startVersionInstallPolling = () => {
  if (versionInstallTimer.value) return
  versionInstallTimer.value = setInterval(async () => {
    try {
      const res = await axios.get('/php/versions/install-status')
      versionInstallLog.value = res.data.log
      if (res.data.status === 'idle') {
        clearInterval(versionInstallTimer.value)
        versionInstallTimer.value = null
        versionInstalling.value = false
        showAlert('success', 'PHP installation process complete!')
        loadPhpVersions()
      }
    } catch (err) {
      console.error(err)
    }
  }, 2500)
}

const installPhpVersion = async (version) => {
  try {
    versionInstalling.value = true
    versionInstallLog.value = `Initializing installation of PHP ${version}...\n`
    currentInstallLogTitle.value = `Installing PHP ${version}`
    currentActiveLoggingType.value = 'version'
    showLogModal.value = true
    
    await axios.post('/php/versions/install', { version })
    showAlert('info', `Started installation of PHP ${version} in background.`)
    startVersionInstallPolling()
  } catch (err) {
    versionInstalling.value = false
    showAlert('danger', err.response?.data?.error || 'Failed to start PHP installation')
  }
}

// PHP Extensions methods
const loadExtensions = async () => {
  if (!selectedExtensionVersion.value) return
  try {
    loadingExtensions.value = true
    const res = await axios.get(`/php/versions/${selectedExtensionVersion.value}/extensions`)
    extensionsList.value = res.data.extensions
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to load extensions')
    extensionsList.value = []
  } finally {
    loadingExtensions.value = false
  }
}

const changeExtensionVersion = () => {
  loadExtensions()
}

const filteredExtensions = computed(() => {
  if (!extSearchQuery.value) return extensionsList.value
  const q = extSearchQuery.value.toLowerCase()
  return extensionsList.value.filter(ext => 
    ext.name.toLowerCase().includes(q) || 
    ext.description.toLowerCase().includes(q)
  )
})

const startExtInstallPolling = () => {
  if (extInstallTimer.value) return
  extInstallTimer.value = setInterval(async () => {
    try {
      const res = await axios.get(`/php/versions/${selectedExtensionVersion.value}/extensions/install-status`)
      extInstallLog.value = res.data.log
      if (res.data.status === 'idle') {
        clearInterval(extInstallTimer.value)
        extInstallTimer.value = null
        extInstalling.value = false
        showAlert('success', 'PHP Extension installation process complete!')
        loadExtensions()
      }
    } catch (err) {
      console.error(err)
    }
  }, 2500)
}

const installExtension = async (extensionName) => {
  try {
    extInstalling.value = true
    extInstallLog.value = `Initializing installation of PHP extension php${selectedExtensionVersion.value}-${extensionName}...\n`
    currentInstallLogTitle.value = `Installing php${selectedExtensionVersion.value}-${extensionName}`
    currentActiveLoggingType.value = 'extension'
    showLogModal.value = true
    
    await axios.post(`/php/versions/${selectedExtensionVersion.value}/extensions/install`, {
      extension: extensionName
    })
    showAlert('info', `Started installation of extension ${extensionName} in background.`)
    startExtInstallPolling()
  } catch (err) {
    extInstalling.value = false
    showAlert('danger', err.response?.data?.error || 'Failed to start extension installation')
  }
}

const checkInstallStatusOnLoad = async () => {
  try {
    const res = await axios.get('/php/versions/install-status')
    if (res.data.status === 'installing') {
      versionInstalling.value = true
      versionInstallLog.value = res.data.log
      startVersionInstallPolling()
    }
  } catch (err) {
    console.error(err)
  }
  
  if (selectedExtensionVersion.value) {
    try {
      const res = await axios.get(`/php/versions/${selectedExtensionVersion.value}/extensions/install-status`)
      if (res.data.status === 'installing') {
        extInstalling.value = true
        extInstallLog.value = res.data.log
        startExtInstallPolling()
      }
    } catch (err) {
      console.error(err)
    }
  }
}

const openActiveLogViewer = (type) => {
  if (type === 'version') {
    currentInstallLogTitle.value = `PHP Installation Logs`
    currentActiveLoggingType.value = 'version'
  } else {
    currentInstallLogTitle.value = `PHP Extension Installation Logs`
    currentActiveLoggingType.value = 'extension'
  }
  showLogModal.value = true
}
</script>

<style scoped>
.quick-setting-item {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 12px;
  border: 1px solid #f1f5f9;
  transition: all 0.2s ease;
}

.quick-setting-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  border-color: rgba(203, 12, 159, 0.15);
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

.status-error {
  background: #feeef2;
  color: #9d174d;
}
.status-error .pill-dot {
  background: #f5365c;
  box-shadow: 0 0 0 2px rgba(245, 54, 92, 0.2);
}

/* Action Buttons */
.action-btn {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  background: transparent;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  color: #67748e;
}

.action-btn i {
  font-size: 1.25rem !important;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-edit:hover {
  background-color: #f8fafc;
  color: #64748b;
}

.status-muted {
  background: #f1f5f9;
  color: #64748b;
}
.status-muted .pill-dot {
  background: #94a3b8;
  box-shadow: 0 0 0 2px rgba(148, 163, 184, 0.2);
}

@keyframes blink {
  from, to { background-color: transparent }
  50% { background-color: #10b981 }
}
</style>

