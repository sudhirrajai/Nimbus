<template>
  <MainLayout>
    <div class="container-fluid py-2">
      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card bg-gradient-dark">
            <div class="card-body p-3">
              <div class="row">
                <div class="col-8">
                  <div class="numbers">
                    <p class="text-white text-sm mb-0 text-uppercase font-weight-bold opacity-7">Email Management</p>
                    <h5 class="text-white font-weight-bolder mb-0">
                      Manage email accounts for your domains
                    </h5>
                  </div>
                </div>
                <div class="col-4 text-end">
                  <div class="icon icon-shape bg-white shadow text-center rounded-circle">
                    <i class="material-symbols-rounded text-dark text-lg opacity-10">email</i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>

      <!-- Not Installed State -->
      <div v-else-if="!status.installed" class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body text-center py-5">
              <i class="material-symbols-rounded text-secondary mb-3" style="font-size: 64px;">mail_lock</i>
              <h4>Mail Server Not Installed</h4>
              <p class="text-muted mb-4">
                Install Postfix, Dovecot, and Roundcube to enable email management.
              </p>
              <div class="mb-3">
                <label class="form-label">Mail Server Hostname</label>
                <input 
                  type="text" 
                  class="form-control mx-auto" 
                  style="max-width: 400px;"
                  v-model="hostname"
                  placeholder="mail.yourdomain.com"
                >
              </div>
              <button class="btn bg-gradient-primary" @click="installMailServer" :disabled="installing">
                <span v-if="installing" class="spinner-border spinner-border-sm me-2"></span>
                {{ installing ? 'Installing...' : 'Install Mail Server' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Installed State -->
      <template v-else>
        <!-- Status Cards -->
        <div class="row mb-4">
          <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
              <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                  <div>
                    <p class="text-sm mb-0 text-capitalize">Email Domains</p>
                    <h4 class="mb-0">{{ status.stats?.domains || 0 }}</h4>
                  </div>
                  <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                    <i class="material-symbols-rounded opacity-10">dns</i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
              <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                  <div>
                    <p class="text-sm mb-0 text-capitalize">Email Accounts</p>
                    <h4 class="mb-0">{{ status.stats?.accounts || 0 }}</h4>
                  </div>
                  <div class="icon icon-md icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                    <i class="material-symbols-rounded opacity-10">person</i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
              <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                  <div>
                    <p class="text-sm mb-0 text-capitalize">Forwarders</p>
                    <h4 class="mb-0">{{ status.stats?.aliases || 0 }}</h4>
                  </div>
                  <div class="icon icon-md icon-shape bg-gradient-info shadow-info text-center rounded-circle">
                    <i class="material-symbols-rounded opacity-10">forward_to_inbox</i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-sm-6">
            <div class="card">
              <div class="card-header p-2 ps-3">
                <div class="d-flex justify-content-between">
                  <div>
                    <p class="text-sm mb-0 text-capitalize">Mail Server</p>
                    <h4 class="mb-0 text-success">
                      <i class="material-symbols-rounded text-sm">check_circle</i> Running
                    </h4>
                  </div>
                  <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                    <i class="material-symbols-rounded opacity-10">dns</i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header pb-0">
                <ul class="nav nav-tabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link" :class="{ active: activeTab === 'accounts' }" href="#" @click.prevent="activeTab = 'accounts'">
                      <i class="material-symbols-rounded text-sm me-1">person</i> Email Accounts
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" :class="{ active: activeTab === 'domains' }" href="#" @click.prevent="activeTab = 'domains'">
                      <i class="material-symbols-rounded text-sm me-1">dns</i> Domains
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" :class="{ active: activeTab === 'forwarders' }" href="#" @click.prevent="activeTab = 'forwarders'">
                      <i class="material-symbols-rounded text-sm me-1">forward_to_inbox</i> Forwarders
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" :class="{ active: activeTab === 'settings' }" href="#" @click.prevent="activeTab = 'settings'">
                      <i class="material-symbols-rounded text-sm me-1">settings</i> Client Settings
                    </a>
                  </li>
                </ul>
              </div>
              <div class="card-body">
                <!-- Email Accounts Tab -->
                <div v-if="activeTab === 'accounts'">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Email Accounts</h6>
                    <button class="btn btn-sm bg-gradient-primary mb-0" @click="showCreateAccountModal = true">
                      <i class="material-symbols-rounded text-sm me-1">add</i> Create Account
                    </button>
                  </div>
                  
                  <div v-if="accounts.length === 0" class="text-center py-4 text-muted">
                    <i class="material-symbols-rounded mb-2" style="font-size: 48px;">inbox</i>
                    <p>No email accounts yet. Create one to get started.</p>
                  </div>
                  
                  <div v-else class="table-responsive">
                    <table class="table align-items-center mb-0">
                      <thead>
                        <tr>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Email</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Usage</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="account in accounts" :key="account.id">
                          <td>
                            <div class="d-flex align-items-center">
                              <div class="avatar avatar-sm bg-gradient-primary rounded-circle me-2">
                                <i class="material-symbols-rounded text-white text-sm">mail</i>
                              </div>
                              <div>
                                <h6 class="mb-0 text-sm">{{ account.email }}</h6>
                                <p class="text-xs text-secondary mb-0">Created {{ formatDate(account.created_at) }}</p>
                              </div>
                            </div>
                          </td>
                          <td>
                            <div class="progress" style="height: 6px; width: 100px;">
                              <div class="progress-bar bg-gradient-info" role="progressbar" 
                                   :style="{ width: Math.min((account.used / account.quota) * 100, 100) + '%' }">
                              </div>
                            </div>
                            <span class="text-xs">{{ account.used || 0 }} MB / {{ account.quota }} MB</span>
                          </td>
                          <td>
                            <span class="badge bg-gradient-success">Active</span>
                          </td>
                          <td>
                            <button class="btn btn-link text-info p-1" title="Webmail" @click="openWebmail(account.email)">
                              <i class="material-symbols-rounded">open_in_new</i>
                            </button>
                            <button class="btn btn-link text-warning p-1" title="Change Password" @click="showPasswordModal(account)">
                              <i class="material-symbols-rounded">key</i>
                            </button>
                            <button class="btn btn-link text-danger p-1" title="Delete" @click="confirmDeleteAccount(account)">
                              <i class="material-symbols-rounded">delete</i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Domains Tab -->
                <div v-if="activeTab === 'domains'">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Email-Enabled Domains</h6>
                    <button class="btn btn-sm bg-gradient-primary mb-0" @click="showEnableDomainModal = true">
                      <i class="material-symbols-rounded text-sm me-1">add</i> Enable Domain
                    </button>
                  </div>
                  
                  <div v-if="domains.length === 0" class="text-center py-4 text-muted">
                    <i class="material-symbols-rounded mb-2" style="font-size: 48px;">dns</i>
                    <p>No domains enabled for email.</p>
                  </div>
                  
                  <div v-else class="table-responsive">
                    <table class="table align-items-center mb-0">
                      <thead>
                        <tr>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Accounts</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="domain in domains" :key="domain.id">
                          <td>
                            <h6 class="mb-0 text-sm">{{ domain.name }}</h6>
                          </td>
                          <td>{{ domain.account_count }} accounts</td>
                          <td>
                            <span class="badge bg-gradient-success">Active</span>
                          </td>
                          <td>
                            <button class="btn btn-link text-danger p-1" title="Disable" @click="confirmDisableDomain(domain)">
                              <i class="material-symbols-rounded">block</i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Forwarders Tab -->
                <div v-if="activeTab === 'forwarders'">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Email Forwarders</h6>
                    <button class="btn btn-sm bg-gradient-primary mb-0" @click="showCreateAliasModal = true">
                      <i class="material-symbols-rounded text-sm me-1">add</i> Create Forwarder
                    </button>
                  </div>
                  
                  <div v-if="aliases.length === 0" class="text-center py-4 text-muted">
                    <i class="material-symbols-rounded mb-2" style="font-size: 48px;">forward_to_inbox</i>
                    <p>No forwarders configured.</p>
                  </div>
                  
                  <div v-else class="table-responsive">
                    <table class="table align-items-center mb-0">
                      <thead>
                        <tr>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">From</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">To</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="alias in aliases" :key="alias.id">
                          <td><span class="text-sm">{{ alias.source }}</span></td>
                          <td><span class="text-sm">{{ alias.destination }}</span></td>
                          <td>
                            <button class="btn btn-link text-danger p-1" @click="deleteAlias(alias.id)">
                              <i class="material-symbols-rounded">delete</i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Client Settings Tab -->
                <div v-if="activeTab === 'settings'">
                  <h6 class="mb-3">Email Client Configuration</h6>
                  <p class="text-sm text-muted mb-4">Use these settings to configure your email client (Outlook, Thunderbird, etc.)</p>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="card bg-gray-100 mb-3">
                        <div class="card-body">
                          <h6 class="text-uppercase text-xs font-weight-bolder mb-3">Incoming Mail (IMAP)</h6>
                          <p class="mb-1"><strong>Server:</strong> {{ clientSettings.incoming?.imap?.server }}</p>
                          <p class="mb-1"><strong>Port:</strong> {{ clientSettings.incoming?.imap?.port }}</p>
                          <p class="mb-0"><strong>Security:</strong> {{ clientSettings.incoming?.imap?.security }}</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="card bg-gray-100 mb-3">
                        <div class="card-body">
                          <h6 class="text-uppercase text-xs font-weight-bolder mb-3">Outgoing Mail (SMTP)</h6>
                          <p class="mb-1"><strong>Server:</strong> {{ clientSettings.outgoing?.smtp?.server }}</p>
                          <p class="mb-1"><strong>Port:</strong> {{ clientSettings.outgoing?.smtp?.port }}</p>
                          <p class="mb-0"><strong>Security:</strong> {{ clientSettings.outgoing?.smtp?.security }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="alert alert-info text-sm">
                    <i class="material-symbols-rounded me-2">info</i>
                    Use your full email address as the username (e.g., user@yourdomain.com)
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Create Account Modal -->
      <div class="modal fade" :class="{ show: showCreateAccountModal }" :style="showCreateAccountModal ? 'display: block;' : ''" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Create Email Account</h5>
              <button type="button" class="btn-close" @click="showCreateAccountModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                  <input type="text" class="form-control" v-model="newAccount.username" placeholder="username">
                  <span class="input-group-text">@</span>
                  <select class="form-select" v-model="newAccount.domain">
                    <option value="">Select domain</option>
                    <option v-for="d in domains" :key="d.id" :value="d.name">{{ d.name }}</option>
                  </select>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" class="form-control" v-model="newAccount.password" placeholder="Minimum 8 characters">
              </div>
              <div class="mb-3">
                <label class="form-label">Quota (MB)</label>
                <input type="number" class="form-control" v-model="newAccount.quota" min="10" max="10240">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showCreateAccountModal = false">Cancel</button>
              <button type="button" class="btn bg-gradient-primary" @click="createAccount" :disabled="creatingAccount">
                {{ creatingAccount ? 'Creating...' : 'Create Account' }}
              </button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="showCreateAccountModal" class="modal-backdrop fade show"></div>

      <!-- Enable Domain Modal -->
      <div class="modal fade" :class="{ show: showEnableDomainModal }" :style="showEnableDomainModal ? 'display: block;' : ''" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Enable Domain for Email</h5>
              <button type="button" class="btn-close" @click="showEnableDomainModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Domain Name</label>
                <input type="text" class="form-control" v-model="newDomain" placeholder="example.com">
              </div>
              <div class="alert alert-warning text-sm">
                <i class="material-symbols-rounded me-2">warning</i>
                Make sure your domain's MX records point to this server.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showEnableDomainModal = false">Cancel</button>
              <button type="button" class="btn bg-gradient-primary" @click="enableDomain">Enable Domain</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="showEnableDomainModal" class="modal-backdrop fade show"></div>

      <!-- Create Alias Modal -->
      <div class="modal fade" :class="{ show: showCreateAliasModal }" :style="showCreateAliasModal ? 'display: block;' : ''" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Create Email Forwarder</h5>
              <button type="button" class="btn-close" @click="showCreateAliasModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Forward From</label>
                <input type="email" class="form-control" v-model="newAlias.source" placeholder="info@yourdomain.com">
              </div>
              <div class="mb-3">
                <label class="form-label">Forward To</label>
                <input type="email" class="form-control" v-model="newAlias.destination" placeholder="your@email.com">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showCreateAliasModal = false">Cancel</button>
              <button type="button" class="btn bg-gradient-primary" @click="createAlias">Create Forwarder</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="showCreateAliasModal" class="modal-backdrop fade show"></div>

      <!-- Change Password Modal -->
      <div class="modal fade" :class="{ show: showChangePasswordModal }" :style="showChangePasswordModal ? 'display: block;' : ''" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Change Password</h5>
              <button type="button" class="btn-close" @click="showChangePasswordModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm mb-3">Changing password for: <strong>{{ selectedAccount?.email }}</strong></p>
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" v-model="newPassword" placeholder="Minimum 8 characters">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="showChangePasswordModal = false">Cancel</button>
              <button type="button" class="btn bg-gradient-primary" @click="changePassword">Update Password</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="showChangePasswordModal" class="modal-backdrop fade show"></div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(true)
const installing = ref(false)
const creatingAccount = ref(false)
const activeTab = ref('accounts')
const hostname = ref('')

const status = ref({ installed: false })
const domains = ref([])
const accounts = ref([])
const aliases = ref([])
const clientSettings = ref({})

// Modals
const showCreateAccountModal = ref(false)
const showEnableDomainModal = ref(false)
const showCreateAliasModal = ref(false)
const showChangePasswordModal = ref(false)

// Form data
const newAccount = ref({ username: '', domain: '', password: '', quota: 1024 })
const newDomain = ref('')
const newAlias = ref({ source: '', destination: '' })
const selectedAccount = ref(null)
const newPassword = ref('')

onMounted(async () => {
  await loadStatus()
  if (status.value.installed) {
    await loadData()
  }
  loading.value = false
})

const loadStatus = async () => {
  try {
    const response = await axios.get('/email/status')
    status.value = response.data
  } catch (error) {
    console.error('Failed to load status:', error)
  }
}

const loadData = async () => {
  try {
    const [domainsRes, accountsRes, aliasesRes, settingsRes] = await Promise.all([
      axios.get('/email/domains'),
      axios.get('/email/accounts'),
      axios.get('/email/aliases'),
      axios.get('/email/client-settings')
    ])
    domains.value = domainsRes.data.domains || []
    accounts.value = accountsRes.data.accounts || []
    aliases.value = aliasesRes.data.aliases || []
    clientSettings.value = settingsRes.data
  } catch (error) {
    console.error('Failed to load data:', error)
  }
}

const installMailServer = async () => {
  if (!hostname.value) {
    alert('Please enter a hostname')
    return
  }
  installing.value = true
  try {
    await axios.post('/email/install', { hostname: hostname.value })
    alert('Mail server installed successfully!')
    await loadStatus()
  } catch (error) {
    alert('Installation failed: ' + (error.response?.data?.error || error.message))
  } finally {
    installing.value = false
  }
}

const enableDomain = async () => {
  if (!newDomain.value) return
  try {
    await axios.post('/email/domain/enable', { domain: newDomain.value })
    showEnableDomainModal.value = false
    newDomain.value = ''
    await loadData()
  } catch (error) {
    alert('Failed: ' + (error.response?.data?.error || error.message))
  }
}

const confirmDisableDomain = async (domain) => {
  if (!confirm(`Disable email for ${domain.name}? This will remove all email accounts for this domain.`)) return
  try {
    await axios.post('/email/domain/disable', { domain: domain.name })
    await loadData()
  } catch (error) {
    alert('Failed: ' + (error.response?.data?.error || error.message))
  }
}

const createAccount = async () => {
  if (!newAccount.value.username || !newAccount.value.domain || !newAccount.value.password) {
    alert('Please fill in all fields')
    return
  }
  creatingAccount.value = true
  try {
    await axios.post('/email/account/create', newAccount.value)
    showCreateAccountModal.value = false
    newAccount.value = { username: '', domain: '', password: '', quota: 1024 }
    await loadData()
  } catch (error) {
    alert('Failed: ' + (error.response?.data?.error || error.message))
  } finally {
    creatingAccount.value = false
  }
}

const confirmDeleteAccount = async (account) => {
  if (!confirm(`Delete email account ${account.email}? This cannot be undone.`)) return
  try {
    await axios.post('/email/account/delete', { email: account.email })
    await loadData()
  } catch (error) {
    alert('Failed: ' + (error.response?.data?.error || error.message))
  }
}

const showPasswordModal = (account) => {
  selectedAccount.value = account
  newPassword.value = ''
  showChangePasswordModal.value = true
}

const changePassword = async () => {
  if (!newPassword.value || newPassword.value.length < 8) {
    alert('Password must be at least 8 characters')
    return
  }
  try {
    await axios.post('/email/account/password', {
      email: selectedAccount.value.email,
      password: newPassword.value
    })
    showChangePasswordModal.value = false
    alert('Password updated successfully')
  } catch (error) {
    alert('Failed: ' + (error.response?.data?.error || error.message))
  }
}

const createAlias = async () => {
  if (!newAlias.value.source || !newAlias.value.destination) {
    alert('Please fill in all fields')
    return
  }
  try {
    await axios.post('/email/alias/create', newAlias.value)
    showCreateAliasModal.value = false
    newAlias.value = { source: '', destination: '' }
    await loadData()
  } catch (error) {
    alert('Failed: ' + (error.response?.data?.error || error.message))
  }
}

const deleteAlias = async (id) => {
  if (!confirm('Delete this forwarder?')) return
  try {
    await axios.post('/email/alias/delete', { id })
    await loadData()
  } catch (error) {
    alert('Failed: ' + (error.response?.data?.error || error.message))
  }
}

const openWebmail = (email) => {
  window.open('/roundcube', '_blank')
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString()
}
</script>

<style scoped>
.nav-link {
  cursor: pointer;
}
.nav-link.active {
  background-color: #f8f9fa;
  border-bottom: 2px solid #344767;
}
.avatar {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
