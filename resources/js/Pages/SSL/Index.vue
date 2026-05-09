<template>
  <MainLayout>
    <Head title="SSL Certificates" />
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
              <button class="btn bg-gradient-success mb-0" @click="renewAllCerts" :disabled="loading || renewingAll || !certbotInstalled">
                <span v-if="renewingAll" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">autorenew</i>
                Renew All
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Certbot Not Installed Banner -->
      <div class="row mb-4" v-if="certbotChecked && !certbotInstalled">
        <div class="col-12">
          <div class="card border border-warning">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                  <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md me-3">
                    <i class="material-symbols-rounded opacity-10" style="font-size: 1.5rem;">warning</i>
                  </div>
                  <div>
                    <h6 class="mb-0 text-sm">Certbot Not Installed</h6>
                    <p class="text-xs text-secondary mb-0">
                      Certbot is required to issue and manage Let's Encrypt SSL certificates.
                      Install it to enable SSL management.
                    </p>
                  </div>
                </div>
                <button 
                  class="btn bg-gradient-warning mb-0 ms-3" 
                  @click="installCertbot"
                  :disabled="installingCertbot"
                >
                  <span v-if="installingCertbot" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="material-symbols-rounded text-sm me-1">download</i>
                  Install Certbot
                </button>
              </div>
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
          <div class="card h-100">
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
          <div class="card h-100">
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
          <div class="card h-100">
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
          <div class="card h-100">
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
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Issuer</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Expiry</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="domain in domains" :key="domain.domain" class="domain-row">
                      <td>
                        <div class="d-flex align-items-center px-3 py-2">
                          <div class="icon-box-ssl me-3" :class="getStatusIconClass(domain.status)">
                            <i class="material-symbols-rounded">{{ getStatusIcon(domain.status) }}</i>
                          </div>
                          <div>
                            <h6 class="mb-0 text-sm font-weight-bold">{{ domain.domain }}</h6>
                            <span v-if="domain.sslSource" class="badge-source">
                              {{ getSourceLabel(domain.sslSource) }}
                            </span>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex flex-column gap-1">
                          <div v-if="!domain.is_active" class="status-pill status-error" style="font-size: 9px; padding: 2px 8px;">
                            <span class="pill-dot"></span>
                            Inactive DNS
                          </div>
                          <span v-if="domain.status === 'valid'" class="status-pill status-active">
                            <span class="pill-dot"></span>
                            Secured
                          </span>
                          <span v-else-if="domain.status === 'expiring_soon'" class="status-pill status-configuring">
                            <span class="pill-dot"></span>
                            Expiring Soon
                          </span>
                          <span v-else-if="domain.status === 'expired'" class="status-pill status-error">
                            <span class="pill-dot"></span>
                            Expired
                          </span>
                          <span v-else class="status-pill status-secondary">
                            <span class="pill-dot"></span>
                            No SSL
                          </span>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="material-symbols-rounded text-secondary text-sm me-1">verified_user</i>
                          <span class="text-xs font-weight-bold">{{ domain.issuer || '-' }}</span>
                        </div>
                      </td>
                      <td>
                        <div v-if="domain.hasSsl">
                          <div class="d-flex align-items-center mb-1">
                            <i class="material-symbols-rounded text-sm me-1" :class="getExpiryClass(domain.daysRemaining)">event</i>
                            <span class="text-xs font-weight-bold" :class="getExpiryClass(domain.daysRemaining)">
                              {{ formatDate(domain.validTo) }}
                            </span>
                          </div>
                          <span class="text-xxs opacity-7 d-block ps-4" :class="getExpiryClass(domain.daysRemaining)">
                            {{ getDaysRemainingText(domain.daysRemaining) }}
                          </span>
                        </div>
                        <span v-else class="text-xs text-secondary">-</span>
                      </td>
                      <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                          <!-- Install SSL button -->
                          <button 
                            v-if="!domain.hasSsl"
                            class="action-btn btn-install-ssl"
                            @click="installSsl(domain)"
                            :disabled="installing === domain.domain || !certbotInstalled || !domain.is_active"
                            :title="!domain.is_active ? `DNS not pointing to ${domain.server_ip}` : (!certbotInstalled ? 'Install Certbot first' : 'Install SSL certificate')"
                          >
                            <span v-if="installing === domain.domain" class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>
                            <i v-else class="material-symbols-rounded">add_moderator</i>
                          </button>
                          
                          <!-- Actions for domains with SSL -->
                          <template v-else>
                            <button 
                              class="action-btn btn-update"
                              @click="renewSsl(domain)"
                              :disabled="renewing === domain.domain || !certbotInstalled"
                              title="Renew certificate"
                            >
                              <span v-if="renewing === domain.domain" class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>
                              <i v-else class="material-symbols-rounded">autorenew</i>
                            </button>
                            <button 
                              class="action-btn btn-info"
                              @click="viewDetails(domain)"
                              title="View details"
                            >
                              <i class="material-symbols-rounded">info</i>
                            </button>
                            <button 
                              class="action-btn btn-delete"
                              @click="confirmRemove(domain)"
                              :disabled="!certbotInstalled"
                              title="Remove certificate"
                            >
                              <i class="material-symbols-rounded">delete</i>
                            </button>
                          </template>
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
                <label class="text-xs text-uppercase text-secondary">SSL Source</label>
                <p class="mb-0">
                  <span class="badge badge-sm" :class="getSourceBadgeClass(selectedDomain.sslSource)">
                    {{ getSourceLabel(selectedDomain.sslSource) }}
                  </span>
                </p>
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
              <button class="btn bg-gradient-info" @click="renewFromDetails" :disabled="!certbotInstalled">
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
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const installing = ref(null)
const renewing = ref(null)
const renewingAll = ref(false)
const removing = ref(false)
const installingCertbot = ref(false)

const domains = ref([])
const certbotInstalled = ref(true) // Assume true until checked
const certbotChecked = ref(false)

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
    
    // Update certbot status from the response
    if (response.data.certbotInstalled !== undefined) {
      certbotInstalled.value = response.data.certbotInstalled
      certbotChecked.value = true
    }
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load domains')
  } finally {
    loading.value = false
  }
}

const installCertbot = async () => {
  try {
    installingCertbot.value = true
    showAlert('info', 'Installing Certbot... This may take a minute.')
    
    const response = await axios.post('/ssl/install-certbot')
    
    showAlert('success', response.data.message || 'Certbot installed successfully')
    certbotInstalled.value = true
    
    // Reload to refresh status
    await loadDomains()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to install Certbot')
    if (error.response?.data?.error) {
      outputTitle.value = 'Certbot Installation Failed'
      outputContent.value = error.response.data.error
      outputSuccess.value = false
      showOutputModal.value = true
    }
  } finally {
    installingCertbot.value = false
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

const getSourceBadgeClass = (source) => {
  const classes = {
    letsencrypt: 'bg-gradient-success',
    nginx_custom: 'bg-gradient-info',
    detected_live: 'bg-gradient-dark',
  }
  return classes[source] || 'bg-gradient-secondary'
}

const getSourceLabel = (source) => {
  const labels = {
    letsencrypt: "Let's Encrypt",
    nginx_custom: 'Custom SSL',
    detected_live: 'Detected (Live)',
  }
  return labels[source] || source || 'Unknown'
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
.domain-row {
  transition: all 0.2s ease;
}
.domain-row:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.icon-box-ssl {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f9fa;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.icon-box-ssl.text-success { background: #e6f6ec; }
.icon-box-ssl.text-warning { background: #fff5e9; }
.icon-box-ssl.text-danger { background: #feeef2; }

.badge-source {
  background: #f0f2f5;
  color: #4b5563;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
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

.status-configuring {
  background: #fff5e9;
  color: #8a5a00;
}
.status-configuring .pill-dot {
  background: #fb923c;
  box-shadow: 0 0 0 2px rgba(251, 146, 60, 0.2);
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
.status-secondary .pill-dot {
  background: #94a3b8;
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

.btn-install-ssl:hover { background: #2dce89; color: #fff; }
.btn-update:hover { background: #1171ef; color: #fff; }
.btn-info:hover { background: #5e72e4; color: #fff; }
.btn-delete:hover { background: #f5365c; color: #fff; }

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}
</style>


