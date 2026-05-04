<template>
<MainLayout>
<Head title="WordPress" />
<div class="container-fluid py-4">
  <!-- Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card bg-gradient-dark">
        <div class="card-body p-3">
          <div class="row align-items-center">
            <div class="col-8">
              <h4 class="text-white mb-0"><i class="material-symbols-rounded me-2">web</i>WordPress Manager</h4>
              <p class="text-white text-sm mb-0 opacity-8">Install, manage, and secure your WordPress sites</p>
            </div>
            <div class="col-4 text-end">
              <button class="btn btn-sm btn-white mb-0 me-2" @click="scanSites" :disabled="scanning">
                <span v-if="scanning" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">search</i>
                {{ scanning ? 'Scanning...' : 'Scan Server' }}
              </button>
              <button class="btn btn-sm bg-gradient-success mb-0" @click="showInstallModal = true">
                <i class="material-symbols-rounded text-sm me-1">add</i> New Install
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="row mb-4">
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4" v-for="stat in statsCards" :key="stat.label">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">{{ stat.label }}</p>
              <h4 class="mb-0">{{ stat.value }}</h4>
            </div>
            <div :class="'icon icon-md icon-shape shadow text-center border-radius-lg bg-gradient-' + stat.color">
              <i class="material-symbols-rounded opacity-10">{{ stat.icon }}</i>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sites Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="material-symbols-rounded text-sm me-1">language</i> WordPress Sites</h6>
        </div>
        <div class="card-body px-0 pb-2">
          <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
          <div v-else-if="sites.length === 0" class="text-center py-5">
            <i class="material-symbols-rounded" style="font-size:48px;opacity:0.3">web</i>
            <p class="text-secondary mt-2">No WordPress sites found. Click "Scan Server" or install a new site.</p>
          </div>
          <div v-else class="table-responsive">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Site</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Version</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Database</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="site in sites" :key="site.id">
                  <td>
                    <div class="d-flex px-2 py-1">
                      <div class="icon icon-sm icon-shape bg-gradient-info shadow text-center border-radius-md me-3">
                        <i class="material-symbols-rounded opacity-10 text-white text-xs">web</i>
                      </div>
                      <div class="d-flex flex-column justify-content-center">
                        <h6 class="mb-0 text-sm">{{ site.site_title || site.domain }}</h6>
                        <p class="text-xs text-secondary mb-0">
                          <a :href="(site.ssl_enabled ? 'https://' : 'http://') + site.domain" target="_blank" class="text-primary">{{ site.domain }}</a>
                        </p>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge bg-gradient-secondary">WP {{ site.wp_version || '?' }}</span></td>
                  <td><span class="text-xs">{{ site.db_name || '-' }}</span></td>
                  <td class="align-middle text-center">
                    <span class="badge badge-sm" :class="site.status === 'active' ? 'bg-gradient-success' : site.status === 'installing' ? 'bg-gradient-warning' : 'bg-gradient-danger'">
                      {{ site.status }}
                    </span>
                  </td>
                  <td class="align-middle text-center">
                    <button class="btn btn-link text-info p-1" title="Details" @click="loadDetails(site)"><i class="material-symbols-rounded text-sm">info</i></button>
                    <button class="btn btn-link text-warning p-1" title="Change Password" @click="openChangePassword(site)"><i class="material-symbols-rounded text-sm">key</i></button>
                    <button class="btn btn-link text-success p-1" title="Update Core" @click="updateCore(site)"><i class="material-symbols-rounded text-sm">system_update</i></button>
                    <a :href="'/file-manager/' + site.domain" class="btn btn-link text-primary p-1" title="File Manager"><i class="material-symbols-rounded text-sm">folder</i></a>
                    <button class="btn btn-link text-danger p-1" title="Delete" @click="confirmDelete(site)"><i class="material-symbols-rounded text-sm">delete</i></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Install Modal -->
  <div v-if="showInstallModal">
    <div class="modal-backdrop fade show" @click="showInstallModal = false"></div>
    <div class="modal fade show" style="display:block">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-gradient-success border-0">
            <h5 class="modal-title text-white"><i class="material-symbols-rounded me-2">download</i>Install WordPress</h5>
            <button class="btn-close btn-close-white" @click="showInstallModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Domain *</label>
                <select class="form-control form-select" v-model="installForm.domain">
                  <option value="">Select domain...</option>
                  <option v-for="d in availableDomains" :key="d" :value="d">{{ d }}</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Site Title *</label>
                <input type="text" class="form-control" v-model="installForm.site_title" placeholder="My Website">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Admin Username *</label>
                <input type="text" class="form-control" v-model="installForm.admin_user" placeholder="admin">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Admin Password *</label>
                <input type="password" class="form-control" v-model="installForm.admin_password" placeholder="Min 8 chars">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Admin Email *</label>
                <input type="email" class="form-control" v-model="installForm.admin_email" placeholder="admin@example.com">
              </div>
              <div class="col-12"><hr><h6 class="text-sm">Database Configuration</h6></div>
              <div class="col-md-4 mb-3">
                <label class="form-label">DB Name *</label>
                <input type="text" class="form-control" v-model="installForm.db_name" placeholder="wp_mysite">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">DB User *</label>
                <input type="text" class="form-control" v-model="installForm.db_user" placeholder="wp_user">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">DB Password *</label>
                <input type="password" class="form-control" v-model="installForm.db_password" placeholder="Min 6 chars">
              </div>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button class="btn btn-outline-secondary" @click="showInstallModal = false">Cancel</button>
            <button class="btn bg-gradient-success" @click="installWordPress" :disabled="installing">
              <span v-if="installing" class="spinner-border spinner-border-sm me-1"></span>
              {{ installing ? 'Installing...' : 'Install WordPress' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Change Password Modal -->
  <div v-if="showPasswordModal">
    <div class="modal-backdrop fade show" @click="showPasswordModal = false"></div>
    <div class="modal fade show" style="display:block">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-gradient-warning border-0">
            <h5 class="modal-title text-white"><i class="material-symbols-rounded me-2">key</i>Change Admin Password</h5>
            <button class="btn-close btn-close-white" @click="showPasswordModal = false"></button>
          </div>
          <div class="modal-body">
            <p class="text-sm text-secondary">Changing password for <strong>{{ selectedSite?.domain }}</strong></p>
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" v-model="passwordForm.username">
            </div>
            <div class="mb-3">
              <label class="form-label">New Password (min 8 chars)</label>
              <input type="password" class="form-control" v-model="passwordForm.new_password">
            </div>
          </div>
          <div class="modal-footer border-0">
            <button class="btn btn-outline-secondary" @click="showPasswordModal = false">Cancel</button>
            <button class="btn bg-gradient-warning" @click="changePassword" :disabled="changingPassword">
              <span v-if="changingPassword" class="spinner-border spinner-border-sm me-1"></span>
              Change Password
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Details Modal -->
  <div v-if="showDetailsModal">
    <div class="modal-backdrop fade show" @click="showDetailsModal = false"></div>
    <div class="modal fade show" style="display:block">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-gradient-info border-0">
            <h5 class="modal-title text-white"><i class="material-symbols-rounded me-2">info</i>{{ selectedSite?.domain }}</h5>
            <button class="btn-close btn-close-white" @click="showDetailsModal = false"></button>
          </div>
          <div class="modal-body">
            <div v-if="loadingDetails" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            <template v-else>
              <!-- Core Update -->
              <div v-if="siteDetails.core_update" class="alert alert-warning d-flex align-items-center mb-3">
                <i class="material-symbols-rounded me-2">system_update</i>
                Update available: WordPress {{ siteDetails.core_update.version }}
                <button class="btn btn-sm btn-warning ms-auto" @click="updateCore(selectedSite)">Update Now</button>
              </div>
              <!-- Users -->
              <h6 class="text-sm mb-2"><i class="material-symbols-rounded text-sm me-1">group</i>Users ({{ siteDetails.users.length }})</h6>
              <div class="table-responsive mb-3">
                <table class="table table-sm mb-0">
                  <thead><tr><th class="text-xs">Username</th><th class="text-xs">Email</th><th class="text-xs">Role</th></tr></thead>
                  <tbody>
                    <tr v-for="u in siteDetails.users" :key="u.ID">
                      <td class="text-xs">{{ u.user_login }}</td>
                      <td class="text-xs">{{ u.user_email }}</td>
                      <td class="text-xs"><span class="badge bg-gradient-dark">{{ u.roles }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Plugins -->
              <h6 class="text-sm mb-2"><i class="material-symbols-rounded text-sm me-1">extension</i>Plugins ({{ siteDetails.plugins.length }})
                <button class="btn btn-link btn-sm p-0 ms-2" @click="updatePlugins">Update All</button>
              </h6>
              <div class="table-responsive mb-3">
                <table class="table table-sm mb-0">
                  <thead><tr><th class="text-xs">Plugin</th><th class="text-xs">Version</th><th class="text-xs">Status</th><th class="text-xs">Action</th></tr></thead>
                  <tbody>
                    <tr v-for="p in siteDetails.plugins" :key="p.name">
                      <td class="text-xs">{{ p.name }}</td>
                      <td class="text-xs">{{ p.version }}</td>
                      <td class="text-xs"><span class="badge" :class="p.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ p.status }}</span></td>
                      <td class="text-xs">
                        <button class="btn btn-link btn-sm p-0" @click="togglePlugin(p)">{{ p.status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Themes -->
              <h6 class="text-sm mb-2"><i class="material-symbols-rounded text-sm me-1">palette</i>Themes ({{ siteDetails.themes.length }})</h6>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th class="text-xs">Theme</th><th class="text-xs">Version</th><th class="text-xs">Status</th></tr></thead>
                  <tbody>
                    <tr v-for="t in siteDetails.themes" :key="t.name">
                      <td class="text-xs">{{ t.name }}</td>
                      <td class="text-xs">{{ t.version }}</td>
                      <td class="text-xs"><span class="badge" :class="t.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ t.status }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Modal -->
  <div v-if="showDeleteModal">
    <div class="modal-backdrop fade show" @click="showDeleteModal = false"></div>
    <div class="modal fade show" style="display:block">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header border-0 pb-0">
            <div class="d-flex align-items-center">
              <div style="width:42px;height:42px;border-radius:0.75rem;display:flex;align-items:center;justify-content:center" class="bg-gradient-danger text-white">
                <i class="material-symbols-rounded">delete_forever</i>
              </div>
              <div class="ms-3">
                <h5 class="mb-0">Delete WordPress Site</h5>
                <p class="text-sm text-secondary mb-0">{{ selectedSite?.domain }}</p>
              </div>
            </div>
            <button class="btn-close" @click="showDeleteModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" v-model="deleteOptions.delete_files" id="delFiles">
              <label class="form-check-label text-sm" for="delFiles">Delete all WordPress files</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" v-model="deleteOptions.delete_database" id="delDb">
              <label class="form-check-label text-sm" for="delDb">Drop database ({{ selectedSite?.db_name }})</label>
            </div>
            <div class="alert alert-danger mt-3 mb-0 py-2"><small><strong>Warning:</strong> This cannot be undone.</small></div>
          </div>
          <div class="modal-footer border-0">
            <button class="btn btn-outline-secondary" @click="showDeleteModal = false">Cancel</button>
            <button class="btn bg-gradient-danger" @click="deleteSite" :disabled="deleting">
              <span v-if="deleting" class="spinner-border spinner-border-sm me-1"></span>Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index:11">
    <div class="toast align-items-center border-0" :class="toastType === 'success' ? 'bg-success' : 'bg-danger'" :style="showToast ? 'display:block' : 'display:none'" role="alert">
      <div class="d-flex">
        <div class="toast-body text-white">{{ toastMessage }}</div>
        <button class="btn-close btn-close-white me-2 m-auto" @click="showToast = false"></button>
      </div>
    </div>
  </div>
</div>
</MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const sites = ref([])
const loading = ref(true)
const scanning = ref(false)
const installing = ref(false)
const changingPassword = ref(false)
const deleting = ref(false)
const loadingDetails = ref(false)
const availableDomains = ref([])

const showInstallModal = ref(false)
const showPasswordModal = ref(false)
const showDetailsModal = ref(false)
const showDeleteModal = ref(false)
const selectedSite = ref(null)
const siteDetails = ref({ plugins: [], themes: [], users: [], core_update: null })

const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const installForm = ref({ domain: '', site_title: '', admin_user: 'admin', admin_password: '', admin_email: '', db_name: '', db_user: '', db_password: '' })
const passwordForm = ref({ username: '', new_password: '' })
const deleteOptions = ref({ delete_files: false, delete_database: false })

const statsCards = computed(() => [
  { label: 'Total Sites', value: sites.value.length, icon: 'language', color: 'primary' },
  { label: 'Active', value: sites.value.filter(s => s.status === 'active').length, icon: 'check_circle', color: 'success' },
  { label: 'SSL Enabled', value: sites.value.filter(s => s.ssl_enabled).length, icon: 'lock', color: 'info' },
  { label: 'Updates Available', value: 0, icon: 'system_update', color: 'warning' },
])

const notify = (msg, type = 'success') => { toastMessage.value = msg; toastType.value = type; showToast.value = true; setTimeout(() => showToast.value = false, 4000) }

onMounted(async () => {
  await loadSites()
  await loadDomains()
})

const loadSites = async () => {
  loading.value = true
  try { const r = await axios.get('/wordpress/list'); sites.value = r.data.sites || [] } catch (e) { console.error(e) } finally { loading.value = false }
}

const loadDomains = async () => {
  try {
    const r = await axios.get('/domains/list')
    const existing = sites.value.map(s => s.domain)
    availableDomains.value = (r.data.domains || []).map(d => d.domain || d).filter(d => !existing.includes(d))
  } catch (e) { console.error(e) }
}

const scanSites = async () => {
  scanning.value = true
  try { const r = await axios.post('/wordpress/scan'); sites.value = r.data.sites || []; notify(`Scan complete. Found ${r.data.scanned} WordPress site(s).`) } catch (e) { notify(e.response?.data?.error || 'Scan failed', 'error') } finally { scanning.value = false }
}

const installWordPress = async () => {
  installing.value = true
  try { await axios.post('/wordpress/install', installForm.value); notify('WordPress installed!'); showInstallModal.value = false; await loadSites(); await loadDomains() } catch (e) { notify(e.response?.data?.error || 'Install failed', 'error') } finally { installing.value = false }
}

const openChangePassword = (site) => { selectedSite.value = site; passwordForm.value = { username: site.admin_user || 'admin', new_password: '' }; showPasswordModal.value = true }

const changePassword = async () => {
  changingPassword.value = true
  try { await axios.post(`/wordpress/${selectedSite.value.id}/password`, passwordForm.value); notify('Password changed!'); showPasswordModal.value = false } catch (e) { notify(e.response?.data?.error || 'Failed', 'error') } finally { changingPassword.value = false }
}

const loadDetails = async (site) => {
  selectedSite.value = site; showDetailsModal.value = true; loadingDetails.value = true
  try { const r = await axios.get(`/wordpress/${site.id}/details`); siteDetails.value = r.data.details } catch (e) { notify('Failed to load details', 'error') } finally { loadingDetails.value = false }
}

const updateCore = async (site) => {
  try { notify('Updating WordPress core...'); const r = await axios.post(`/wordpress/${site.id}/update-core`); notify(r.data.message); await loadSites() } catch (e) { notify(e.response?.data?.error || 'Update failed', 'error') }
}

const updatePlugins = async () => {
  try { notify('Updating plugins...'); await axios.post(`/wordpress/${selectedSite.value.id}/update-plugins`); notify('Plugins updated!'); await loadDetails(selectedSite.value) } catch (e) { notify('Failed', 'error') }
}

const togglePlugin = async (plugin) => {
  const action = plugin.status === 'active' ? 'deactivate' : 'activate'
  try { await axios.post(`/wordpress/${selectedSite.value.id}/toggle-plugin`, { plugin: plugin.name, action }); notify(`Plugin ${action}d`); await loadDetails(selectedSite.value) } catch (e) { notify('Failed', 'error') }
}

const confirmDelete = (site) => { selectedSite.value = site; deleteOptions.value = { delete_files: false, delete_database: false }; showDeleteModal.value = true }

const deleteSite = async () => {
  deleting.value = true
  try { await axios.delete(`/wordpress/${selectedSite.value.id}`, { data: deleteOptions.value }); notify('Site removed'); showDeleteModal.value = false; await loadSites() } catch (e) { notify('Failed', 'error') } finally { deleting.value = false }
}
</script>
