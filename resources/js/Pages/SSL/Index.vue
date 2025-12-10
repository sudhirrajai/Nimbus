<template>
  <MainLayout>
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">SSL Certificates</h4>
              <p class="mb-0 text-sm">Manage SSL certificates for your domains (Let's Encrypt)</p>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="loadDomains" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                Refresh
              </button>
              <button class="btn bg-gradient-success mb-0" @click="renewAllCerts" :disabled="loading || renewingAll">
                <span v-if="renewingAll" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">autorenew</i>
                Renew All
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

      <!-- Stats Cards -->
      <div class="row mb-4" v-if="domains.length > 0">
        <div class="col-lg-3 col-md-6 mb-4">
          <div class="card">
            <div class="card-body p-3">
              <div class="d-flex align-items-center">
                <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                  <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">language</i>
                </div>
                <div class="ms-3">
                  <p class="text-sm mb-0 text-capitalize">Total Domains</p>
                  <h4 class="mb-0">{{ domains.length }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
          <div class="card">
            <div class="card-body p-3">
              <div class="d-flex align-items-center">
                <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                  <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">verified</i>
                </div>
                <div class="ms-3">
                  <p class="text-sm mb-0 text-capitalize">Secured</p>
                  <h4 class="mb-0">{{ securedCount }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
          <div class="card">
            <div class="card-body p-3">
              <div class="d-flex align-items-center">
                <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                  <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">schedule</i>
                </div>
                <div class="ms-3">
                  <p class="text-sm mb-0 text-capitalize">Expiring Soon</p>
                  <h4 class="mb-0">{{ expiringSoonCount }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
          <div class="card">
            <div class="card-body p-3">
              <div class="d-flex align-items-center">
                <div class="icon icon-shape bg-gradient-danger shadow text-center border-radius-md">
                  <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">gpp_maybe</i>
                </div>
                <div class="ms-3">
                  <p class="text-sm mb-0 text-capitalize">No SSL</p>
                  <h4 class="mb-0">{{ noSslCount }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div class="row" v-if="loading && domains.length === 0">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-secondary mt-2">Loading SSL information...</p>
        </div>
      </div>

      <!-- Domains List -->
      <div class="row" v-if="domains.length > 0">
        <div class="col-12">
          <div class="card">
            <div class="card-header pb-0">
              <h6 class="mb-0">Domain SSL Status</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Issuer</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Expiry</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="domain in domains" :key="domain.domain">
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="material-symbols-rounded me-2" :class="getStatusIconClass(domain.status)">
                            {{ getStatusIcon(domain.status) }}
                          </i>
                          <div>
                            <h6 class="mb-0 text-sm">{{ domain.domain }}</h6>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="badge badge-sm" :class="getStatusBadgeClass(domain.status)">
                          {{ getStatusLabel(domain.status) }}
                        </span>
                        <span v-if="domain.autoRenew" class="badge badge-sm bg-gradient-info ms-1" title="Auto-renew enabled">
                          <i class="material-symbols-rounded text-xs">autorenew</i>
                        </span>
                      </td>
                      <td>
                        <span class="text-sm text-secondary">{{ domain.issuer || '-' }}</span>
                      </td>
                      <td>
                        <div v-if="domain.hasSsl">
                          <span class="text-sm" :class="getExpiryClass(domain.daysRemaining)">
                            {{ formatDate(domain.validTo) }}
                          </span>
                          <br>
                          <small :class="getExpiryClass(domain.daysRemaining)">
                            {{ getDaysRemainingText(domain.daysRemaining) }}
                          </small>
                        </div>
                        <span v-else class="text-sm text-secondary">-</span>
                      </td>
                      <td class="text-center">
                        <!-- Install SSL button (for domains without SSL) -->
                        <button 
                          v-if="!domain.hasSsl"
                          class="btn btn-sm bg-gradient-success mb-0"
                          @click="installSsl(domain)"
                          :disabled="installing === domain.domain"
                        >
                          <span v-if="installing === domain.domain" class="spinner-border spinner-border-sm me-1"></span>
                          <i v-else class="material-symbols-rounded text-xs me-1">add_circle</i>
                          Install SSL
                        </button>
                        
                        <!-- Actions for domains with SSL -->
                        <template v-else>
                          <button 
                            class="btn btn-link text-info mb-0 px-2"
                            @click="renewSsl(domain)"
                            :disabled="renewing === domain.domain"
                            title="Renew certificate"
                          >
                            <span v-if="renewing === domain.domain" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="material-symbols-rounded text-sm">autorenew</i>
                          </button>
                          <button 
                            class="btn btn-link text-primary mb-0 px-2"
                            @click="viewDetails(domain)"
                            title="View details"
                          >
                            <i class="material-symbols-rounded text-sm">info</i>
                          </button>
                          <button 
                            class="btn btn-link text-danger mb-0 px-2"
                            @click="confirmRemove(domain)"
                            title="Remove certificate"
                          >
                            <i class="material-symbols-rounded text-sm">delete</i>
                          </button>
                        </template>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Details Modal -->
      <div class="modal-backdrop fade show" v-if="showDetailsModal" @click="showDetailsModal = false"></div>
      <div class="modal fade show d-block" v-if="showDetailsModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-success me-2">verified</i>
                SSL Certificate Details
              </h5>
              <button type="button" class="btn-close" @click="showDetailsModal = false"></button>
            </div>
            <div class="modal-body" v-if="selectedDomain">
              <div class="mb-3">
                <label class="text-xs text-uppercase text-secondary">Domain</label>
                <p class="mb-0 font-weight-bold">{{ selectedDomain.domain }}</p>
              </div>
              <div class="mb-3">
                <label class="text-xs text-uppercase text-secondary">Issuer</label>
                <p class="mb-0">{{ selectedDomain.issuer }}</p>
              </div>
              <div class="mb-3">
                <label class="text-xs text-uppercase text-secondary">Valid From</label>
                <p class="mb-0">{{ formatDate(selectedDomain.validFrom) }}</p>
              </div>
              <div class="mb-3">
                <label class="text-xs text-uppercase text-secondary">Valid Until</label>
                <p class="mb-0" :class="getExpiryClass(selectedDomain.daysRemaining)">
                  {{ formatDate(selectedDomain.validTo) }}
                  ({{ getDaysRemainingText(selectedDomain.daysRemaining) }})
                </p>
              </div>
              <div class="mb-3">
                <label class="text-xs text-uppercase text-secondary">Auto Renewal</label>
                <p class="mb-0">
                  <span v-if="selectedDomain.autoRenew" class="text-success">
                    <i class="material-symbols-rounded text-sm me-1">check_circle</i> Enabled
                  </span>
                  <span v-else class="text-secondary">
                    <i class="material-symbols-rounded text-sm me-1">cancel</i> Disabled
                  </span>
                </p>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showDetailsModal = false">Close</button>
              <button class="btn bg-gradient-info" @click="renewFromDetails">
                <i class="material-symbols-rounded text-xs me-1">autorenew</i>
                Renew Now
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Remove Confirmation Modal -->
      <div class="modal-backdrop fade show" v-if="showRemoveModal" @click="showRemoveModal = false"></div>
      <div class="modal fade show d-block" v-if="showRemoveModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-danger me-2">warning</i>
                Remove SSL Certificate
              </h5>
              <button type="button" class="btn-close" @click="showRemoveModal = false"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to remove the SSL certificate for <strong>{{ domainToRemove?.domain }}</strong>?</p>
              <p class="text-sm text-secondary mb-0">
                This will delete the certificate and your site will no longer be accessible via HTTPS.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showRemoveModal = false">Cancel</button>
              <button class="btn bg-gradient-danger" @click="removeSsl" :disabled="removing">
                <span v-if="removing" class="spinner-border spinner-border-sm me-2"></span>
                Remove Certificate
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Output Modal -->
      <div class="modal-backdrop fade show" v-if="showOutputModal" @click="showOutputModal = false"></div>
      <div class="modal fade show d-block" v-if="showOutputModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded me-2" :class="outputSuccess ? 'text-success' : 'text-danger'">
                  {{ outputSuccess ? 'check_circle' : 'error' }}
                </i>
                {{ outputTitle }}
              </h5>
              <button type="button" class="btn-close" @click="showOutputModal = false"></button>
            </div>
            <div class="modal-body">
              <pre class="bg-dark text-light p-3 rounded" style="font-size: 12px; max-height: 400px; overflow: auto;">{{ outputContent }}</pre>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showOutputModal = false">Close</button>
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

const loading = ref(false)
const installing = ref(null)
const renewing = ref(null)
const renewingAll = ref(false)
const removing = ref(false)

const domains = ref([])

const showDetailsModal = ref(false)
const showRemoveModal = ref(false)
const showOutputModal = ref(false)

const selectedDomain = ref(null)
const domainToRemove = ref(null)
const outputTitle = ref('')
const outputContent = ref('')
const outputSuccess = ref(true)

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

// Computed stats
const securedCount = computed(() => domains.value.filter(d => d.hasSsl && d.status === 'valid').length)
const expiringSoonCount = computed(() => domains.value.filter(d => d.status === 'expiring_soon' || d.status === 'expired').length)
const noSslCount = computed(() => domains.value.filter(d => !d.hasSsl).length)

onMounted(() => {
  loadDomains()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const getAlertIcon = (type) => {
  const icons = { success: 'check_circle', danger: 'error', warning: 'warning', info: 'info' }
  return icons[type] || 'info'
}

const loadDomains = async () => {
  try {
    loading.value = true
    const response = await axios.get('/ssl/domains')
    domains.value = response.data.domains
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load domains')
  } finally {
    loading.value = false
  }
}

const getStatusIcon = (status) => {
  const icons = {
    valid: 'lock',
    expiring_soon: 'schedule',
    expired: 'lock_open',
    no_ssl: 'no_encryption'
  }
  return icons[status] || 'help'
}

const getStatusIconClass = (status) => {
  const classes = {
    valid: 'text-success',
    expiring_soon: 'text-warning',
    expired: 'text-danger',
    no_ssl: 'text-secondary'
  }
  return classes[status] || 'text-secondary'
}

const getStatusBadgeClass = (status) => {
  const classes = {
    valid: 'bg-gradient-success',
    expiring_soon: 'bg-gradient-warning',
    expired: 'bg-gradient-danger',
    no_ssl: 'bg-gradient-secondary'
  }
  return classes[status] || 'bg-gradient-secondary'
}

const getStatusLabel = (status) => {
  const labels = {
    valid: 'Secured',
    expiring_soon: 'Expiring Soon',
    expired: 'Expired',
    no_ssl: 'No SSL'
  }
  return labels[status] || 'Unknown'
}

const getExpiryClass = (daysRemaining) => {
  if (daysRemaining === null) return 'text-secondary'
  if (daysRemaining < 0) return 'text-danger font-weight-bold'
  if (daysRemaining <= 30) return 'text-warning'
  return 'text-success'
}

const getDaysRemainingText = (daysRemaining) => {
  if (daysRemaining === null) return ''
  if (daysRemaining < 0) return `Expired ${Math.abs(daysRemaining)} days ago`
  if (daysRemaining === 0) return 'Expires today'
  if (daysRemaining === 1) return 'Expires tomorrow'
  return `${daysRemaining} days remaining`
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const installSsl = async (domain) => {
  try {
    installing.value = domain.domain
    showAlert('info', `Installing SSL certificate for ${domain.domain}... This may take a minute.`)
    
    const response = await axios.post('/ssl/install', { domain: domain.domain })
    
    showAlert('success', response.data.message)
    outputTitle.value = 'SSL Installation Complete'
    outputContent.value = response.data.details || 'Certificate installed successfully'
    outputSuccess.value = true
    showOutputModal.value = true
    
    await loadDomains()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to install SSL')
    if (error.response?.data?.details) {
      outputTitle.value = 'SSL Installation Failed'
      outputContent.value = error.response.data.details
      outputSuccess.value = false
      showOutputModal.value = true
    }
  } finally {
    installing.value = null
  }
}

const renewSsl = async (domain) => {
  try {
    renewing.value = domain.domain
    showAlert('info', `Renewing SSL certificate for ${domain.domain}...`)
    
    const response = await axios.post('/ssl/renew', { domain: domain.domain })
    
    showAlert('success', response.data.message)
    await loadDomains()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to renew SSL')
    if (error.response?.data?.details) {
      outputTitle.value = 'SSL Renewal Failed'
      outputContent.value = error.response.data.details
      outputSuccess.value = false
      showOutputModal.value = true
    }
  } finally {
    renewing.value = null
  }
}

const renewAllCerts = async () => {
  try {
    renewingAll.value = true
    showAlert('info', 'Renewing all SSL certificates...')
    
    const response = await axios.post('/ssl/renew-all')
    
    showAlert('success', response.data.message)
    outputTitle.value = 'Renew All Certificates'
    outputContent.value = response.data.details || 'Process completed'
    outputSuccess.value = response.data.success
    showOutputModal.value = true
    
    await loadDomains()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to renew certificates')
  } finally {
    renewingAll.value = false
  }
}

const viewDetails = (domain) => {
  selectedDomain.value = domain
  showDetailsModal.value = true
}

const renewFromDetails = async () => {
  showDetailsModal.value = false
  await renewSsl(selectedDomain.value)
}

const confirmRemove = (domain) => {
  domainToRemove.value = domain
  showRemoveModal.value = true
}

const removeSsl = async () => {
  try {
    removing.value = true
    const response = await axios.post('/ssl/remove', { domain: domainToRemove.value.domain })
    
    showAlert('success', response.data.message)
    showRemoveModal.value = false
    await loadDomains()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to remove SSL')
  } finally {
    removing.value = false
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

.gap-2 {
  gap: 0.5rem;
}

pre {
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
