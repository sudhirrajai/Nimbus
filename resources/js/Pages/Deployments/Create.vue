<template>
  <MainLayout>
    <div class="container-fluid py-4">

      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex align-items-center">
            <button class="btn btn-link text-dark p-0 me-3 mb-0" @click="goBack">
              <i class="material-symbols-rounded">arrow_back</i>
            </button>
            <div>
              <h4 class="font-weight-bolder mb-0">New Deployment</h4>
              <p class="mb-0 text-sm">Configure a Git-based deployment for your domain</p>
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

      <!-- Step Progress -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-body py-3">
              <div class="d-flex justify-content-between align-items-center">
                <div v-for="(s, index) in steps" :key="index"
                  class="step-item d-flex align-items-center"
                  :class="{ 'flex-grow-1': index < steps.length - 1 }"
                >
                  <div class="d-flex align-items-center">
                    <div class="step-circle"
                      :class="{
                        'active': currentStep === index,
                        'completed': currentStep > index,
                        'pending': currentStep < index
                      }"
                    >
                      <i v-if="currentStep > index" class="material-symbols-rounded text-sm">check</i>
                      <span v-else>{{ index + 1 }}</span>
                    </div>
                    <span class="step-label ms-2 d-none d-md-inline text-sm"
                      :class="{ 'font-weight-bold': currentStep === index }"
                    >{{ s.label }}</span>
                  </div>
                  <div v-if="index < steps.length - 1" class="step-line flex-grow-1 mx-3"
                    :class="{ 'completed': currentStep > index }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Step Content -->
      <div class="row">
        <div class="col-12 col-lg-8 mx-auto">
          <div class="card">
            <div class="card-body p-4">

              <!-- Step 1: Select Domain -->
              <div v-if="currentStep === 0">
                <h5 class="font-weight-bolder mb-1">Select Domain</h5>
                <p class="text-sm text-secondary mb-4">Choose the domain you want to deploy to</p>

                <div v-if="loadingDomains" class="text-center py-4">
                  <div class="spinner-border text-dark" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>

                <div v-else-if="availableDomains.length === 0" class="text-center py-4">
                  <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">language</i>
                  <p class="text-secondary mt-2">No domains available. Please add a domain first.</p>
                  <button class="btn btn-sm bg-gradient-dark" @click="router.visit('/domains')">
                    Go to Domains
                  </button>
                </div>

                <div v-else class="list-group">
                  <label v-for="domain in availableDomains" :key="domain.name"
                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center border-0 border-bottom"
                    :class="{
                      'active-domain': form.domain === domain.name,
                      'opacity-50': domain.has_deployment
                    }"
                    style="cursor: pointer;"
                  >
                    <div class="d-flex align-items-center">
                      <input type="radio" :value="domain.name" v-model="form.domain"
                        :disabled="domain.has_deployment"
                        class="form-check-input me-3"
                      >
                      <div>
                        <span class="font-weight-bold">{{ domain.name }}</span>
                        <br>
                        <small class="text-secondary">/var/www/{{ domain.name }}</small>
                      </div>
                    </div>
                    <span v-if="domain.has_deployment" class="badge bg-gradient-warning">
                      Has deployment
                    </span>
                  </label>
                </div>
              </div>

              <!-- Step 2: Repository Type -->
              <div v-if="currentStep === 1">
                <h5 class="font-weight-bolder mb-1">Repository Type</h5>
                <p class="text-sm text-secondary mb-4">Is your repository public or private?</p>

                <div class="row g-3">
                  <div class="col-6">
                    <div class="card h-100 cursor-pointer repo-type-card"
                      :class="{ 'border-dark border-2': form.repo_type === 'public' }"
                      @click="form.repo_type = 'public'"
                    >
                      <div class="card-body text-center py-4">
                        <i class="material-symbols-rounded mb-2" style="font-size: 48px;">public</i>
                        <h6 class="mb-1">Public</h6>
                        <p class="text-sm text-secondary mb-0">Anyone can see this repository</p>
                      </div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="card h-100 cursor-pointer repo-type-card"
                      :class="{ 'border-dark border-2': form.repo_type === 'private' }"
                      @click="form.repo_type = 'private'"
                    >
                      <div class="card-body text-center py-4">
                        <i class="material-symbols-rounded mb-2" style="font-size: 48px;">lock</i>
                        <h6 class="mb-1">Private</h6>
                        <p class="text-sm text-secondary mb-0">Requires authentication token</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Token input for private repos -->
                <div v-if="form.repo_type === 'private'" class="mt-4">
                  <div class="form-group">
                    <label class="form-control-label font-weight-bold">
                      <i class="material-symbols-rounded text-sm me-1">key</i>
                      GitHub Personal Access Token
                    </label>
                    <div class="input-group input-group-outline">
                      <input
                        :type="showToken ? 'text' : 'password'"
                        v-model="form.access_token"
                        class="form-control"
                        placeholder="ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                      >
                      <button class="btn btn-outline-dark mb-0" @click="showToken = !showToken" type="button">
                        <i class="material-symbols-rounded text-sm">{{ showToken ? 'visibility_off' : 'visibility' }}</i>
                      </button>
                    </div>
                    <small class="text-muted d-block mt-1">
                      Generate a token at
                      <a href="https://github.com/settings/tokens" target="_blank" class="text-dark">github.com/settings/tokens</a>
                      with <code>repo</code> scope.
                    </small>
                  </div>
                </div>
              </div>

              <!-- Step 3: Repository URL -->
              <div v-if="currentStep === 2">
                <h5 class="font-weight-bolder mb-1">Repository URL</h5>
                <p class="text-sm text-secondary mb-4">Enter the Git repository URL</p>

                <!-- URL Type Toggle -->
                <div class="d-flex gap-2 mb-3">
                  <button class="btn btn-sm mb-0" :class="form.url_type === 'https' ? 'bg-gradient-dark' : 'btn-outline-dark'"
                    @click="form.url_type = 'https'">
                    HTTPS
                  </button>
                  <button class="btn btn-sm mb-0" :class="form.url_type === 'ssh' ? 'bg-gradient-dark' : 'btn-outline-dark'"
                    @click="form.url_type = 'ssh'">
                    SSH
                  </button>
                </div>

                <div class="form-group">
                  <label class="form-control-label">Repository URL</label>
                  <div class="input-group input-group-outline">
                    <input
                      type="text"
                      v-model="form.repo_url"
                      class="form-control"
                      :placeholder="form.url_type === 'https' ? 'https://github.com/user/repo.git' : 'git@github.com:user/repo.git'"
                    >
                    <button class="btn btn-outline-dark mb-0" @click="validateRepository" :disabled="validatingRepo || !form.repo_url.trim()" type="button">
                      <span v-if="validatingRepo" class="spinner-border spinner-border-sm me-1" role="status"></span>
                      <i v-else class="material-symbols-rounded text-sm me-1">verified</i>
                      Validate
                    </button>
                  </div>
                  <!-- Validation result -->
                  <div v-if="repoValidation" class="mt-2">
                    <small :class="repoValidation.valid ? 'text-success' : 'text-danger'">
                      <i class="material-symbols-rounded text-sm me-1">{{ repoValidation.valid ? 'check_circle' : 'error' }}</i>
                      {{ repoValidation.message }}
                    </small>
                  </div>
                </div>
              </div>

              <!-- Step 4: Branch Selection -->
              <div v-if="currentStep === 3">
                <h5 class="font-weight-bolder mb-1">Select Branch</h5>
                <p class="text-sm text-secondary mb-4">Choose the branch to deploy from</p>

                <div class="d-flex justify-content-end mb-3">
                  <button class="btn btn-sm btn-outline-dark mb-0" @click="fetchBranches" :disabled="fetchingBranches">
                    <span v-if="fetchingBranches" class="spinner-border spinner-border-sm me-1" role="status"></span>
                    <i v-else class="material-symbols-rounded text-sm me-1">refresh</i>
                    Fetch Branches
                  </button>
                </div>

                <!-- Fetched branches -->
                <div v-if="branches.length > 0" class="mb-3">
                  <label class="form-control-label mb-2">Available Branches</label>
                  <div class="list-group">
                    <label v-for="branch in branches" :key="branch"
                      class="list-group-item list-group-item-action d-flex align-items-center border-0 border-bottom"
                      :class="{ 'active-domain': form.branch === branch }"
                      style="cursor: pointer;"
                    >
                      <input type="radio" :value="branch" v-model="form.branch" class="form-check-input me-3">
                      <div>
                        <i class="material-symbols-rounded text-sm me-1 opacity-5">fork_right</i>
                        <span>{{ branch }}</span>
                        <span v-if="branch === 'main' || branch === 'master'" class="badge badge-sm bg-gradient-dark ms-2">default</span>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- Manual branch input -->
                <div class="form-group mt-3">
                  <label class="form-control-label">Or type branch name manually</label>
                  <div class="input-group input-group-outline">
                    <input
                      type="text"
                      v-model="form.branch"
                      class="form-control"
                      placeholder="main"
                    >
                  </div>
                </div>
              </div>

              <!-- Step 5: Review & Deploy -->
              <div v-if="currentStep === 4">
                <h5 class="font-weight-bolder mb-1">Review & Create</h5>
                <p class="text-sm text-secondary mb-4">Confirm your deployment configuration</p>

                <div class="card bg-gray-100">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="text-xs text-uppercase text-secondary font-weight-bolder">Domain</label>
                        <p class="mb-0 font-weight-bold">{{ form.domain }}</p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="text-xs text-uppercase text-secondary font-weight-bolder">Repository Type</label>
                        <p class="mb-0">
                          <i class="material-symbols-rounded text-sm me-1">{{ form.repo_type === 'private' ? 'lock' : 'public' }}</i>
                          {{ form.repo_type }}
                        </p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="text-xs text-uppercase text-secondary font-weight-bolder">URL Type</label>
                        <p class="mb-0">{{ form.url_type.toUpperCase() }}</p>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="text-xs text-uppercase text-secondary font-weight-bolder">Branch</label>
                        <p class="mb-0">
                          <span class="badge bg-gradient-dark">{{ form.branch }}</span>
                        </p>
                      </div>
                      <div class="col-12 mb-3">
                        <label class="text-xs text-uppercase text-secondary font-weight-bolder">Repository URL</label>
                        <p class="mb-0 text-sm" style="word-break: break-all;">{{ form.repo_url }}</p>
                      </div>
                      <div class="col-12" v-if="form.repo_type === 'private'">
                        <label class="text-xs text-uppercase text-secondary font-weight-bolder">Access Token</label>
                        <p class="mb-0 text-sm">•••••••••••••••••••</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="alert alert-info mt-3 mb-0 py-2" role="alert">
                  <small>
                    <i class="material-symbols-rounded text-sm me-1">info</i>
                    This will create the deployment configuration. You can then trigger the deployment from the dashboard.
                    If your repository contains a <code>nimbus.yaml</code> file, it will be used for automated setup.
                  </small>
                </div>
              </div>

            </div>

            <!-- Navigation buttons -->
            <div class="card-footer d-flex justify-content-between">
              <button v-if="currentStep > 0" class="btn btn-outline-secondary mb-0" @click="prevStep">
                <i class="material-symbols-rounded text-sm me-1">arrow_back</i>
                Back
              </button>
              <div v-else></div>

              <button v-if="currentStep < steps.length - 1" class="btn bg-gradient-dark mb-0"
                @click="nextStep" :disabled="!canProceed">
                Next
                <i class="material-symbols-rounded text-sm ms-1">arrow_forward</i>
              </button>
              <button v-else class="btn bg-gradient-dark mb-0"
                @click="createDeployment" :disabled="submitting || !canProceed">
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">rocket_launch</i>
                Create Deployment
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
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const currentStep = ref(0)
const submitting = ref(false)
const loadingDomains = ref(false)
const validatingRepo = ref(false)
const fetchingBranches = ref(false)
const showToken = ref(false)

const availableDomains = ref([])
const branches = ref([])
const repoValidation = ref(null)

const steps = [
  { label: 'Domain' },
  { label: 'Repo Type' },
  { label: 'Repo URL' },
  { label: 'Branch' },
  { label: 'Review' },
]

const form = ref({
  domain: '',
  repo_type: 'public',
  url_type: 'https',
  repo_url: '',
  access_token: '',
  branch: 'main',
})

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
  setTimeout(() => { alert.value.show = false }, 5000)
}

const canProceed = computed(() => {
  switch (currentStep.value) {
    case 0: return !!form.value.domain
    case 1:
      if (form.value.repo_type === 'private' && form.value.url_type === 'https') {
        return !!form.value.access_token.trim()
      }
      return true
    case 2: return !!form.value.repo_url.trim()
    case 3: return !!form.value.branch.trim()
    case 4: return true
    default: return false
  }
})

const loadDomains = async () => {
  try {
    loadingDomains.value = true
    const res = await axios.get('/deployments/domains')
    availableDomains.value = res.data
  } catch (error) {
    showAlert('danger', 'Failed to load domains')
  } finally {
    loadingDomains.value = false
  }
}

const nextStep = () => {
  if (currentStep.value < steps.length - 1) {
    currentStep.value++

    // Auto-fetch branches when entering branch step
    if (currentStep.value === 3 && branches.value.length === 0) {
      fetchBranches()
    }
  }
}

const prevStep = () => {
  if (currentStep.value > 0) currentStep.value--
}

const validateRepository = async () => {
  try {
    validatingRepo.value = true
    repoValidation.value = null
    const res = await axios.post('/deployments/validate-repo', {
      repo_url: form.value.repo_url,
      repo_type: form.value.repo_type,
      url_type: form.value.url_type,
      access_token: form.value.access_token,
    })
    repoValidation.value = res.data
  } catch (error) {
    repoValidation.value = { valid: false, message: 'Validation failed: ' + (error.response?.data?.message || error.message) }
  } finally {
    validatingRepo.value = false
  }
}

const fetchBranches = async () => {
  try {
    fetchingBranches.value = true
    const res = await axios.post('/deployments/branches', {
      repo_url: form.value.repo_url,
      repo_type: form.value.repo_type,
      url_type: form.value.url_type,
      access_token: form.value.access_token,
    })
    if (res.data.success) {
      branches.value = res.data.branches
      // Auto-select main or master if available
      if (branches.value.includes('main')) {
        form.value.branch = 'main'
      } else if (branches.value.includes('master')) {
        form.value.branch = 'master'
      }
    } else {
      showAlert('danger', 'Failed to fetch branches: ' + (res.data.error || 'Unknown error'))
    }
  } catch (error) {
    showAlert('danger', 'Failed to fetch branches')
  } finally {
    fetchingBranches.value = false
  }
}

const createDeployment = async () => {
  try {
    submitting.value = true
    const res = await axios.post('/deployments', {
      domain: form.value.domain,
      repo_url: form.value.repo_url,
      repo_type: form.value.repo_type,
      url_type: form.value.url_type,
      access_token: form.value.repo_type === 'private' ? form.value.access_token : null,
      branch: form.value.branch,
    })
    showAlert('success', res.data.message)
    // Redirect to deployments dashboard
    setTimeout(() => router.visit('/deployments'), 1000)
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to create deployment')
  } finally {
    submitting.value = false
  }
}

const goBack = () => {
  router.visit('/deployments')
}
</script>

<style scoped>
.step-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: bold;
  transition: all 0.3s ease;
}

.step-circle.active {
  background: #344767;
  color: white;
  box-shadow: 0 4px 12px rgba(52, 71, 103, 0.3);
}

.step-circle.completed {
  background: #2dce89;
  color: white;
}

.step-circle.pending {
  background: #e9ecef;
  color: #8898aa;
}

.step-line {
  height: 2px;
  background: #e9ecef;
  transition: background 0.3s ease;
}

.step-line.completed {
  background: #2dce89;
}

.step-item {
  white-space: nowrap;
}

.repo-type-card {
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.repo-type-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.active-domain {
  background-color: rgba(52, 71, 103, 0.05) !important;
  border-left: 3px solid #344767 !important;
}

.list-group-item {
  transition: all 0.2s ease;
}

.list-group-item:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.cursor-pointer {
  cursor: pointer;
}

code {
  background: rgba(0, 0, 0, 0.06);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.8rem;
}

.bg-gray-100 {
  background-color: #f8f9fa;
}
</style>
