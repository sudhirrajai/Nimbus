<template>
  <MainLayout>
    <div class="container-fluid py-4">

      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">Git Deployments</h4>
              <p class="mb-0 text-sm">Deploy projects from GitHub repositories with automated setup</p>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-dark mb-0" @click="showBlacklistModal = true">
                <i class="material-symbols-rounded text-sm me-1">security</i>
                Blacklist
              </button>
              <button class="btn bg-gradient-dark mb-0" @click="goToCreate">
                <i class="material-symbols-rounded text-sm me-1">add</i>
                New Deployment
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
            <span class="alert-text"><strong>{{ alert.type === 'success' ? 'Success!' : 'Error!' }}</strong> {{ alert.message }}</span>
            <button type="button" class="btn-close" @click="alert.show = false"></button>
          </div>
        </div>
      </div>

      <!-- Deployments Table -->
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header pb-0">
              <div class="d-flex justify-content-between align-items-center">
                <h6>Your Deployments</h6>
                <button class="btn btn-link text-dark p-0 mb-0" @click="loadDeployments" :disabled="loading">
                  <i class="material-symbols-rounded" :class="{ 'spin': loading }">refresh</i>
                </button>
              </div>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Repository</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Branch</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Last Deploy</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="dep in deployments" :key="dep.id">
                      <td>
                        <div class="d-flex px-2 py-1">
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm">{{ dep.domain }}</h6>
                            <p class="text-xs text-secondary mb-0">
                              <i class="material-symbols-rounded text-xs me-1">{{ dep.repo_type === 'private' ? 'lock' : 'public' }}</i>
                              {{ dep.repo_type }}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <p class="text-xs font-weight-bold mb-0" :title="dep.repo_url">
                          {{ shortenRepoUrl(dep.repo_url) }}
                        </p>
                        <p class="text-xs text-secondary mb-0" v-if="dep.commit_hash">
                          <i class="material-symbols-rounded text-xs me-1">commit</i>{{ dep.commit_hash }}
                        </p>
                      </td>
                      <td>
                        <span class="badge badge-sm bg-gradient-dark">{{ dep.branch }}</span>
                      </td>
                      <td>
                        <span :class="`badge badge-sm bg-gradient-${dep.status_color}`">
                          {{ dep.status }}
                        </span>
                        <span v-if="dep.is_in_progress" class="ms-1">
                          <span class="spinner-border spinner-border-sm text-warning" role="status"></span>
                        </span>
                      </td>
                      <td>
                        <p class="text-xs text-secondary mb-0">{{ dep.last_deployed_at || 'Never' }}</p>
                      </td>
                      <td class="align-middle text-center">
                        <button
                          class="btn btn-link text-success mb-0 px-2"
                          @click="triggerDeploy(dep)"
                          :disabled="dep.is_in_progress"
                          :title="dep.is_in_progress ? 'Deploy in progress' : 'Deploy now'"
                        >
                          <i class="material-symbols-rounded text-sm">rocket_launch</i>
                        </button>
                        <button
                          class="btn btn-link text-info mb-0 px-2"
                          @click="viewLogs(dep)"
                          title="View logs"
                        >
                          <i class="material-symbols-rounded text-sm">terminal</i>
                        </button>
                        <button
                          class="btn btn-link text-danger mb-0 px-2"
                          @click="confirmDelete(dep)"
                          :disabled="dep.is_in_progress"
                          title="Delete deployment"
                        >
                          <i class="material-symbols-rounded text-sm">delete</i>
                        </button>
                      </td>
                    </tr>

                    <tr v-if="deployments.length === 0 && !loading">
                      <td colspan="6" class="text-center py-4">
                        <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">rocket_launch</i>
                        <p class="text-secondary mb-2">No deployments configured yet.</p>
                        <button class="btn btn-sm bg-gradient-dark" @click="goToCreate">
                          <i class="material-symbols-rounded text-sm me-1">add</i>
                          Create your first deployment
                        </button>
                      </td>
                    </tr>

                    <tr v-if="loading">
                      <td colspan="6" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                          <span class="visually-hidden">Loading...</span>
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

      <!-- Delete Confirmation Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showDeleteModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder text-danger">
                <i class="material-symbols-rounded me-1">warning</i>
                Delete Deployment
              </h5>
              <button type="button" class="btn-close" @click="showDeleteModal = false" :disabled="submitting"></button>
            </div>
            <div class="modal-body">
              <p class="mb-0">
                Are you sure you want to delete the deployment for <strong>{{ deploymentToDelete?.domain }}</strong>?
              </p>
              <p class="text-sm text-secondary mb-0 mt-2">
                <i class="material-symbols-rounded text-sm me-1">info</i>
                This will only remove the deployment configuration. Your domain files will not be affected.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showDeleteModal = false" :disabled="submitting">Cancel</button>
              <button class="btn bg-gradient-danger mb-0" @click="deleteDeployment" :disabled="submitting">
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Deploy Confirmation Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showDeployModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">
                <i class="material-symbols-rounded me-1">rocket_launch</i>
                Deploy Project
              </h5>
              <button type="button" class="btn-close" @click="showDeployModal = false" :disabled="deploying"></button>
            </div>
            <div class="modal-body">
              <div v-if="!deploying && !deployResult">
                <p class="mb-2">Deploy <strong>{{ deploymentToDeploy?.domain }}</strong>?</p>
                <div class="d-flex flex-column gap-1">
                  <small class="text-secondary">
                    <i class="material-symbols-rounded text-xs me-1">link</i>
                    {{ deploymentToDeploy?.repo_url }}
                  </small>
                  <small class="text-secondary">
                    <i class="material-symbols-rounded text-xs me-1">fork_right</i>
                    Branch: {{ deploymentToDeploy?.branch }}
                  </small>
                </div>
                <div class="alert alert-warning mt-3 mb-0 py-2" role="alert">
                  <small>
                    <i class="material-symbols-rounded text-sm me-1">info</i>
                    This will clone/pull the repository and run all install &amp; build commands defined in <code>nimbus.yaml</code>.
                    Existing files in the domain directory may be overwritten.
                  </small>
                </div>
              </div>

              <!-- Deploying state -->
              <div v-if="deploying" class="text-center py-3">
                <div class="spinner-border text-dark mb-3" role="status" style="width: 3rem; height: 3rem;">
                  <span class="visually-hidden">Deploying...</span>
                </div>
                <p class="font-weight-bold mb-1">Deploying...</p>
                <p class="text-sm text-secondary mb-0">This may take a few minutes. Please don't close this dialog.</p>
              </div>

              <!-- Deploy result -->
              <div v-if="deployResult" class="text-center py-3">
                <i class="material-symbols-rounded mb-2" :class="deployResult.success ? 'text-success' : 'text-danger'" style="font-size: 48px;">
                  {{ deployResult.success ? 'check_circle' : 'error' }}
                </i>
                <p class="font-weight-bold mb-1">{{ deployResult.message }}</p>
                <p class="text-sm text-danger mb-0" v-if="deployResult.error">{{ deployResult.error }}</p>
              </div>
            </div>
            <div class="modal-footer">
              <button v-if="!deploying && !deployResult" class="btn btn-outline-secondary mb-0" @click="showDeployModal = false">Cancel</button>
              <button v-if="!deploying && !deployResult" class="btn bg-gradient-dark mb-0" @click="executeDeploy">
                <i class="material-symbols-rounded text-sm me-1">rocket_launch</i>
                Deploy Now
              </button>
              <button v-if="deployResult" class="btn btn-outline-secondary mb-0" @click="viewLogsAfterDeploy">
                <i class="material-symbols-rounded text-sm me-1">terminal</i>
                View Logs
              </button>
              <button v-if="deployResult" class="btn bg-gradient-dark mb-0" @click="closeDeployModal">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Command Blacklist Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showBlacklistModal">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">
                <i class="material-symbols-rounded me-1">security</i>
                Command Blacklist
              </h5>
              <button type="button" class="btn-close" @click="showBlacklistModal = false"></button>
            </div>
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
              <p class="text-sm text-secondary mb-3">
                Commands matching these patterns will be blocked during deployment.
                This prevents <code>nimbus.yaml</code> files from executing dangerous commands.
              </p>

              <!-- Add new entry -->
              <div class="d-flex gap-2 mb-3">
                <div class="flex-grow-1">
                  <input type="text" v-model="newBlacklistPattern" class="form-control form-control-sm" placeholder="Pattern (e.g., rm -rf /)">
                </div>
                <select v-model="newBlacklistType" class="form-select form-select-sm" style="width: 120px;">
                  <option value="contains">Contains</option>
                  <option value="exact">Exact</option>
                  <option value="regex">Regex</option>
                </select>
                <div class="flex-grow-1">
                  <input type="text" v-model="newBlacklistDesc" class="form-control form-control-sm" placeholder="Description">
                </div>
                <button class="btn btn-sm bg-gradient-dark mb-0" @click="addBlacklistEntry" :disabled="!newBlacklistPattern.trim()">
                  <i class="material-symbols-rounded text-sm">add</i>
                </button>
              </div>

              <div class="table-responsive">
                <table class="table table-sm align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Pattern</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Type</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Description</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Active</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="entry in blacklistEntries" :key="entry.id">
                      <td>
                        <code class="text-xs">{{ entry.pattern }}</code>
                      </td>
                      <td>
                        <span class="badge badge-sm bg-gradient-secondary">{{ entry.type }}</span>
                      </td>
                      <td>
                        <span class="text-xs text-secondary">{{ entry.description || '—' }}</span>
                      </td>
                      <td>
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox" :checked="entry.is_active" @change="toggleBlacklistEntry(entry)">
                        </div>
                      </td>
                      <td class="text-center">
                        <button class="btn btn-link text-danger mb-0 px-1" @click="deleteBlacklistEntry(entry)" title="Delete">
                          <i class="material-symbols-rounded text-sm">close</i>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="blacklistEntries.length === 0">
                      <td colspan="5" class="text-center py-3 text-secondary text-sm">
                        No blacklist entries. Add patterns to block dangerous commands.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn bg-gradient-dark mb-0" @click="showBlacklistModal = false">Close</button>
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
import { router } from '@inertiajs/vue3'

const deployments = ref([])
const loading = ref(false)
const submitting = ref(false)
const deploying = ref(false)
const deployResult = ref(null)

const showDeleteModal = ref(false)
const showDeployModal = ref(false)
const showBlacklistModal = ref(false)

const deploymentToDelete = ref(null)
const deploymentToDeploy = ref(null)

// Blacklist
const blacklistEntries = ref([])
const newBlacklistPattern = ref('')
const newBlacklistType = ref('contains')
const newBlacklistDesc = ref('')

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

onMounted(() => {
  loadDeployments()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => { alert.value.show = false }, 5000)
}

const loadDeployments = async () => {
  try {
    loading.value = true
    const res = await axios.get('/deployments/list')
    deployments.value = res.data
  } catch (error) {
    showAlert('danger', 'Failed to load deployments')
    console.error(error)
  } finally {
    loading.value = false
  }
}

const goToCreate = () => {
  router.visit('/deployments/create')
}

const shortenRepoUrl = (url) => {
  if (!url) return ''
  // Extract owner/repo from GitHub URLs
  const match = url.match(/github\.com[:/](.+?)(?:\.git)?$/)
  if (match) return match[1]
  // Fallback: show last part
  return url.length > 40 ? '...' + url.slice(-37) : url
}

// Deploy
const triggerDeploy = (dep) => {
  deploymentToDeploy.value = dep
  deployResult.value = null
  deploying.value = false
  showDeployModal.value = true
}

const executeDeploy = async () => {
  try {
    deploying.value = true
    deployResult.value = null
    const res = await axios.post(`/deployments/${deploymentToDeploy.value.id}/deploy`)
    deployResult.value = res.data
    loadDeployments()
  } catch (error) {
    deployResult.value = {
      success: false,
      message: 'Deployment failed',
      error: error.response?.data?.error || error.message
    }
  } finally {
    deploying.value = false
  }
}

const viewLogsAfterDeploy = () => {
  router.visit(`/deployments/${deploymentToDeploy.value.id}/view-logs`)
}

const closeDeployModal = () => {
  showDeployModal.value = false
  deployResult.value = null
  deploying.value = false
}

// Logs
const viewLogs = (dep) => {
  router.visit(`/deployments/${dep.id}/view-logs`)
}

// Delete
const confirmDelete = (dep) => {
  deploymentToDelete.value = dep
  showDeleteModal.value = true
}

const deleteDeployment = async () => {
  try {
    submitting.value = true
    await axios.delete(`/deployments/${deploymentToDelete.value.id}`)
    showAlert('success', `Deployment for "${deploymentToDelete.value.domain}" has been deleted`)
    showDeleteModal.value = false
    loadDeployments()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to delete deployment')
  } finally {
    submitting.value = false
  }
}

// Blacklist
const loadBlacklist = async () => {
  try {
    const res = await axios.get('/deployments/blacklist')
    blacklistEntries.value = res.data
  } catch (error) {
    console.error('Failed to load blacklist:', error)
  }
}

const addBlacklistEntry = async () => {
  try {
    await axios.post('/deployments/blacklist', {
      action: 'add',
      pattern: newBlacklistPattern.value,
      type: newBlacklistType.value,
      description: newBlacklistDesc.value,
    })
    newBlacklistPattern.value = ''
    newBlacklistDesc.value = ''
    loadBlacklist()
  } catch (error) {
    showAlert('danger', 'Failed to add blacklist entry')
  }
}

const toggleBlacklistEntry = async (entry) => {
  try {
    await axios.post('/deployments/blacklist', { action: 'toggle', id: entry.id })
    loadBlacklist()
  } catch (error) {
    showAlert('danger', 'Failed to toggle blacklist entry')
  }
}

const deleteBlacklistEntry = async (entry) => {
  try {
    await axios.post('/deployments/blacklist', { action: 'delete', id: entry.id })
    loadBlacklist()
  } catch (error) {
    showAlert('danger', 'Failed to delete blacklist entry')
  }
}

// Watch for blacklist modal open
import { watch } from 'vue'
watch(showBlacklistModal, (val) => {
  if (val) loadBlacklist()
})
</script>

<style scoped>
.modal {
  background: rgba(0, 0, 0, 0.5);
}

.modal-content {
  border: none;
  border-radius: 1rem;
}

.btn-link {
  text-decoration: none;
}

.btn-link:hover i {
  transform: scale(1.1);
  transition: transform 0.2s;
}

code {
  background: rgba(0, 0, 0, 0.06);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.8rem;
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
