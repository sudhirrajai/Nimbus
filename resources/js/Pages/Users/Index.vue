<template>
<MainLayout>
<Head title="User Management" />
<div class="container-fluid py-4">
  <!-- Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card bg-gradient-dark">
        <div class="card-body p-3">
          <div class="row align-items-center">
            <div class="col-8">
              <h4 class="text-white mb-0"><i class="material-symbols-rounded me-2">group</i>User Management</h4>
              <p class="text-white text-sm mb-0 opacity-8">Manage panel users, roles, and website access</p>
            </div>
            <div class="col-4 text-end">
              <button class="btn btn-sm bg-gradient-success mb-0" @click="openCreateModal">
                <i class="material-symbols-rounded text-sm me-1">person_add</i> Add User
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

  <!-- Users Table -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header pb-0">
          <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="material-symbols-rounded text-sm me-1">people</i> Panel Users</h6>
            <div class="d-flex align-items-center gap-3">
              <div class="input-group input-group-sm" style="width: 250px;">
                <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                <input v-model="searchQuery" type="text" class="form-control" placeholder="Search users by name or email...">
              </div>
              <button class="btn btn-link text-dark p-0 mb-0" @click="loadUsers" :disabled="loading">
                <i class="material-symbols-rounded" :class="{ 'spin': loading }">refresh</i>
              </button>
            </div>
          </div>
        </div>
        <div class="card-body px-0 pb-2">
          <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>
          <div v-else-if="filteredUsers.length === 0" class="text-center py-5">
            <div class="empty-state">
              <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">person_off</i>
              <p class="text-secondary mt-3">No users found matching your search.</p>
            </div>
          </div>
          <div v-else class="table-responsive">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">User</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Role</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Linux User</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Websites</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Last Login</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in paginatedUsers" :key="user.id" class="user-row">
                  <td>
                    <div class="d-flex px-2 py-1">
                      <div class="icon icon-sm icon-shape shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center"
                           :class="user.role === 'root' ? 'bg-gradient-danger' : user.role === 'admin' ? 'bg-gradient-warning' : 'bg-gradient-info'">
                        <i class="material-symbols-rounded text-white text-xs">{{ user.role === 'root' ? 'shield_person' : user.role === 'admin' ? 'admin_panel_settings' : 'person' }}</i>
                      </div>
                      <div class="d-flex flex-column justify-content-center">
                        <h6 class="mb-0 text-sm">{{ user.name }}</h6>
                        <p class="text-xs text-secondary mb-0">{{ user.email }}</p>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-sm" :class="user.role === 'root' ? 'bg-gradient-danger' : user.role === 'admin' ? 'bg-gradient-warning' : 'bg-gradient-info'">
                      {{ user.role }}
                    </span>
                  </td>
                  <td><code class="text-xs">{{ user.linux_user || '-' }}</code></td>
                  <td>
                    <span class="badge bg-gradient-secondary badge-sm">{{ user.website_count }} site{{ user.website_count !== 1 ? 's' : '' }}</span>
                  </td>
                  <td class="align-middle text-center">
                    <span class="badge badge-sm" :class="user.status === 'active' ? 'bg-gradient-success' : 'bg-gradient-danger'">
                      {{ user.status }}
                    </span>
                  </td>
                  <td><span class="text-xs text-secondary">{{ user.last_login_at || 'Never' }}</span></td>
                  <td class="align-middle text-center">
                    <button class="btn btn-link text-primary p-1" title="Assign Websites" @click="openWebsitesModal(user)">
                      <i class="material-symbols-rounded text-sm">language</i>
                    </button>
                    <button class="btn btn-link text-info p-1" title="Edit User" @click="openEditModal(user)" :disabled="user.is_protected">
                      <i class="material-symbols-rounded text-sm">edit</i>
                    </button>
                    <button v-if="!user.is_protected" class="btn btn-link p-1" :class="user.status === 'active' ? 'text-warning' : 'text-success'"
                            :title="user.status === 'active' ? 'Suspend' : 'Activate'" @click="toggleStatus(user)">
                      <i class="material-symbols-rounded text-sm">{{ user.status === 'active' ? 'block' : 'check_circle' }}</i>
                    </button>
                    <button v-if="!user.is_protected" class="btn btn-link text-danger p-1" title="Delete" @click="confirmDelete(user)">
                      <i class="material-symbols-rounded text-sm">delete</i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="filteredUsers.length > itemsPerPage" class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="text-xs text-secondary">
              Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }} of {{ filteredUsers.length }} users
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

  <!-- Create/Edit User Modal -->
  <div v-if="showUserModal">
    <div class="modal-backdrop fade show" @click="showUserModal = false"></div>
    <div class="modal fade show" style="display:block">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header border-0" :class="editingUser ? 'bg-gradient-info' : 'bg-gradient-success'">
            <h5 class="modal-title text-white">
              <i class="material-symbols-rounded me-2">{{ editingUser ? 'edit' : 'person_add' }}</i>
              {{ editingUser ? 'Edit User' : 'Create User' }}
            </h5>
            <button class="btn-close btn-close-white" @click="showUserModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Full Name *</label>
              <input type="text" class="form-control" v-model="userForm.name" placeholder="John Doe">
            </div>
            <div class="mb-3">
              <label class="form-label">Email *</label>
              <input type="email" class="form-control" v-model="userForm.email" placeholder="john@example.com">
            </div>
            <div class="mb-3">
              <label class="form-label">{{ editingUser ? 'New Password (leave blank to keep)' : 'Password *' }}</label>
              <input type="password" class="form-control" v-model="userForm.password" placeholder="Min 8 characters">
            </div>
            <div class="mb-3">
              <label class="form-label">Role *</label>
              <select class="form-control form-select" v-model="userForm.role">
                <option value="user">User — Access assigned websites only</option>
                <option value="admin">Admin — Manage assigned websites + view stats</option>
              </select>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button class="btn btn-outline-secondary" @click="showUserModal = false">Cancel</button>
            <button class="btn" :class="editingUser ? 'bg-gradient-info' : 'bg-gradient-success'" @click="saveUser" :disabled="saving">
              <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
              {{ editingUser ? 'Update' : 'Create User' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Assign Websites Modal -->
  <div v-if="showWebsitesModal">
    <div class="modal-backdrop fade show" @click="showWebsitesModal = false"></div>
    <div class="modal fade show" style="display:block">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-gradient-primary border-0">
            <h5 class="modal-title text-white">
              <i class="material-symbols-rounded me-2">language</i>
              Website Access — {{ selectedUser?.name }}
            </h5>
            <button class="btn-close btn-close-white" @click="showWebsitesModal = false"></button>
          </div>
          <div class="modal-body">
            <p class="text-sm text-secondary mb-3">Select which websites this user can access and what they can do:</p>
            <div v-if="availableDomains.length === 0" class="text-center py-3">
              <p class="text-secondary">No domains available on the server.</p>
            </div>
            <div v-else>
              <div v-for="domain in availableDomains" :key="domain" class="border rounded-3 p-3 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" :id="'domain-' + domain"
                           :checked="isDomainAssigned(domain)" @change="toggleDomain(domain)">
                    <label class="form-check-label fw-bold" :for="'domain-' + domain">
                      <i class="material-symbols-rounded text-sm me-1 text-primary">language</i>{{ domain }}
                    </label>
                  </div>
                </div>
                <div v-if="isDomainAssigned(domain)" class="mt-2 ms-4 d-flex flex-wrap gap-2">
                  <span v-for="perm in allPermissions" :key="perm.key"
                        class="badge cursor-pointer" style="cursor:pointer"
                        :class="hasPerm(domain, perm.key) ? 'bg-gradient-success' : 'bg-light text-dark border'"
                        @click="togglePerm(domain, perm.key)">
                    <i class="material-symbols-rounded text-xs me-1">{{ perm.icon }}</i>{{ perm.label }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button class="btn btn-outline-secondary" @click="showWebsitesModal = false">Cancel</button>
            <button class="btn bg-gradient-primary" @click="saveWebsites" :disabled="savingWebsites">
              <span v-if="savingWebsites" class="spinner-border spinner-border-sm me-1"></span>
              Save Access
            </button>
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
                <i class="material-symbols-rounded">person_remove</i>
              </div>
              <div class="ms-3">
                <h5 class="mb-0">Delete User</h5>
                <p class="text-sm text-secondary mb-0">{{ userToDelete?.name }} ({{ userToDelete?.email }})</p>
              </div>
            </div>
            <button class="btn-close" @click="showDeleteModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-danger mb-0 py-2">
              <small>
                <strong>Warning:</strong> This will permanently delete the user account
                <span v-if="userToDelete?.linux_user">, remove the Linux user <code>{{ userToDelete.linux_user }}</code>,</span>
                and revoke all website access. This cannot be undone.
              </small>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button class="btn btn-outline-secondary" @click="showDeleteModal = false">Cancel</button>
            <button class="btn bg-gradient-danger" @click="deleteUser" :disabled="deleting">
              <span v-if="deleting" class="spinner-border spinner-border-sm me-1"></span>
              Delete User
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index:11">
    <div class="toast align-items-center border-0" :class="toastType === 'success' ? 'bg-success' : 'bg-danger'"
         :style="showToast ? 'display:block' : 'display:none'" role="alert">
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

const users = ref([])
const availableDomains = ref([])
const loading = ref(true)
const saving = ref(false)
const savingWebsites = ref(false)
const deleting = ref(false)

const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

const showUserModal = ref(false)
const showWebsitesModal = ref(false)
const showDeleteModal = ref(false)

const editingUser = ref(null)
const selectedUser = ref(null)
const userToDelete = ref(null)

const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const userForm = ref({ name: '', email: '', password: '', role: 'user' })
const websiteAssignments = ref([]) // [{ domain, permissions: [] }]

const allPermissions = [
  { key: 'files', label: 'Files', icon: 'folder' },
  { key: 'deployments', label: 'Deployments', icon: 'rocket_launch' },
  { key: 'wordpress', label: 'WordPress', icon: 'web' },
  { key: 'database', label: 'Database', icon: 'storage' },
  { key: 'ssl', label: 'SSL', icon: 'lock' },
  { key: 'nginx', label: 'Nginx', icon: 'settings_input_component' },
  { key: 'supervisor', label: 'Supervisor', icon: 'bolt' },
  { key: 'cron', label: 'Cron', icon: 'schedule' },
]

const statsCards = computed(() => [
  { label: 'Total Users', value: users.value.length, icon: 'group', color: 'primary' },
  { label: 'Admins', value: users.value.filter(u => u.role === 'admin').length, icon: 'admin_panel_settings', color: 'warning' },
  { label: 'Active', value: users.value.filter(u => u.status === 'active').length, icon: 'check_circle', color: 'success' },
  { label: 'Suspended', value: users.value.filter(u => u.status === 'suspended').length, icon: 'block', color: 'danger' },
])

const filteredUsers = computed(() => {
  if (!searchQuery.value) return users.value
  const q = searchQuery.value.toLowerCase()
  return users.value.filter(user => 
    user.name.toLowerCase().includes(q) || 
    user.email.toLowerCase().includes(q) ||
    (user.linux_user && user.linux_user.toLowerCase().includes(q))
  )
})

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage.value))

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredUsers.value.slice(start, end)
})

const notify = (msg, type = 'success') => {
  toastMessage.value = msg; toastType.value = type; showToast.value = true
  setTimeout(() => showToast.value = false, 4000)
}

onMounted(async () => {
  await loadUsers()
  await loadDomains()
})

const loadUsers = async () => {
  loading.value = true
  try { const r = await axios.get('/users/list'); users.value = r.data.users || [] }
  catch (e) { console.error(e) }
  finally { loading.value = false }
}

const loadDomains = async () => {
  try { const r = await axios.get('/users/domains'); availableDomains.value = r.data.domains || [] }
  catch (e) { console.error(e) }
}

// ─── Create / Edit ──────────────────────────────────────────

const openCreateModal = () => {
  editingUser.value = null
  userForm.value = { name: '', email: '', password: '', role: 'user' }
  showUserModal.value = true
}

const openEditModal = (user) => {
  editingUser.value = user
  userForm.value = { name: user.name, email: user.email, password: '', role: user.role }
  showUserModal.value = true
}

const saveUser = async () => {
  saving.value = true
  try {
    if (editingUser.value) {
      const data = { ...userForm.value }
      if (!data.password) delete data.password
      await axios.put(`/users/${editingUser.value.id}`, data)
      notify('User updated!')
    } else {
      await axios.post('/users', userForm.value)
      notify('User created!')
    }
    showUserModal.value = false
    await loadUsers()
  } catch (e) {
    notify(e.response?.data?.message || e.response?.data?.error || 'Failed', 'error')
  } finally { saving.value = false }
}

// ─── Website Assignments ────────────────────────────────────

const openWebsitesModal = (user) => {
  selectedUser.value = user
  websiteAssignments.value = (user.websites || []).map(w => ({
    domain: w.domain,
    permissions: [...(w.permissions || ['files', 'deployments', 'wordpress'])]
  }))
  showWebsitesModal.value = true
}

const isDomainAssigned = (domain) => websiteAssignments.value.some(w => w.domain === domain)

const toggleDomain = (domain) => {
  const idx = websiteAssignments.value.findIndex(w => w.domain === domain)
  if (idx >= 0) {
    websiteAssignments.value.splice(idx, 1)
  } else {
    websiteAssignments.value.push({ domain, permissions: ['files', 'deployments', 'wordpress'] })
  }
}

const hasPerm = (domain, perm) => {
  const site = websiteAssignments.value.find(w => w.domain === domain)
  return site && site.permissions.includes(perm)
}

const togglePerm = (domain, perm) => {
  const site = websiteAssignments.value.find(w => w.domain === domain)
  if (!site) return
  const idx = site.permissions.indexOf(perm)
  if (idx >= 0) site.permissions.splice(idx, 1)
  else site.permissions.push(perm)
}

const saveWebsites = async () => {
  savingWebsites.value = true
  try {
    await axios.put(`/users/${selectedUser.value.id}/websites`, { websites: websiteAssignments.value })
    notify('Website access updated!')
    showWebsitesModal.value = false
    await loadUsers()
  } catch (e) {
    notify(e.response?.data?.error || 'Failed', 'error')
  } finally { savingWebsites.value = false }
}

// ─── Status Toggle ──────────────────────────────────────────

const toggleStatus = async (user) => {
  const newStatus = user.status === 'active' ? 'suspended' : 'active'
  try {
    await axios.put(`/users/${user.id}`, { status: newStatus })
    notify(`User ${newStatus === 'active' ? 'activated' : 'suspended'}`)
    await loadUsers()
  } catch (e) { notify('Failed', 'error') }
}

// ─── Delete ─────────────────────────────────────────────────

const confirmDelete = (user) => { userToDelete.value = user; showDeleteModal.value = true }

const deleteUser = async () => {
  deleting.value = true
  try {
    await axios.delete(`/users/${userToDelete.value.id}`)
    notify('User deleted')
    showDeleteModal.value = false
    await loadUsers()
  } catch (e) {
    notify(e.response?.data?.error || 'Failed', 'error')
  } finally { deleting.value = false }
}
</script>
