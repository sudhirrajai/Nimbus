<template>
  <MainLayout>
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">Database Management</h4>
              <p class="mb-0 text-sm">Manage MySQL databases, users, and phpMyAdmin</p>
            </div>
            <div class="d-flex gap-2" v-if="status.phpMyAdminInstalled">
              <button class="btn btn-outline-warning mb-0" @click="reinstallPhpMyAdmin" :disabled="reinstalling">
                <span v-if="reinstalling" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">refresh</i>
                {{ reinstalling ? 'Reinstalling...' : 'Reinstall' }}
              </button>
              <button class="btn btn-outline-secondary mb-0" @click="loadData" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                Refresh
              </button>
              <a href="/phpmyadmin" target="_blank" class="btn bg-gradient-info mb-0">
                <i class="material-symbols-rounded text-sm me-1">open_in_new</i>
                phpMyAdmin
              </a>
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
      <div class="row" v-if="loading && !status.checked">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-secondary mt-2">Loading database information...</p>
        </div>
      </div>

      <!-- phpMyAdmin Not Installed -->
      <div class="row" v-if="status.checked && !status.phpMyAdminInstalled">
        <div class="col-12">
          <div class="card">
            <div class="card-body text-center py-5" v-if="!installing">
              <i class="material-symbols-rounded text-warning" style="font-size: 4rem;">database</i>
              <h4 class="mt-3">phpMyAdmin Not Installed</h4>
              <p class="text-secondary mb-4">Install phpMyAdmin to manage your MySQL databases</p>
              <button class="btn bg-gradient-primary btn-lg" @click="installPhpMyAdmin" :disabled="installing">
                <i class="material-symbols-rounded text-sm me-1">download</i>
                Install phpMyAdmin
              </button>
            </div>
            <!-- Terminal view during installation -->
            <div class="card-body" v-else>
              <h5 class="mb-3">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Installing phpMyAdmin...
              </h5>
              <div class="terminal-output bg-dark text-white p-3 rounded"
                style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                {{ installLog || 'Starting installation...' }}</div>
              <div class="mt-3 text-secondary text-sm">
                <i class="material-symbols-rounded text-sm align-middle">info</i>
                This may take a few minutes. Please wait...
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Credentials Display (after fresh install) -->
      <div class="row mb-4" v-if="showCredentials && credentials">
        <div class="col-12">
          <div class="card bg-gradient-success">
            <div class="card-body text-white">
              <h5 class="text-white mb-3">
                <i class="material-symbols-rounded me-2">check_circle</i>
                phpMyAdmin Installed Successfully!
              </h5>
              <p class="mb-3">Save these credentials securely. They will only be shown once.</p>
              <div class="bg-white text-dark p-3 rounded mb-3">
                <div class="row">
                  <div class="col-md-4">
                    <label class="text-xs text-uppercase text-secondary">URL</label>
                    <p class="mb-0 font-weight-bold">/phpmyadmin</p>
                  </div>
                  <div class="col-md-4">
                    <label class="text-xs text-uppercase text-secondary">Username</label>
                    <p class="mb-0 font-weight-bold">{{ credentials.username }}</p>
                  </div>
                  <div class="col-md-4">
                    <label class="text-xs text-uppercase text-secondary">Password</label>
                    <p class="mb-0 font-weight-bold font-monospace">{{ credentials.password }}</p>
                  </div>
                </div>
              </div>
              <a :href="'/database/credentials/download'" class="btn btn-white" download>
                <i class="material-symbols-rounded text-sm me-1">download</i>
                Download Credentials
              </a>
              <button class="btn btn-outline-white ms-2" @click="showCredentials = false">
                Got it, continue
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Database Management (when phpMyAdmin is installed) -->
      <template v-if="status.phpMyAdminInstalled && !showCredentials">
        <!-- Create Forms Row -->
        <div class="row mb-4">
          <!-- Create Database -->
          <div class="col-lg-4 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Create Database</h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label text-xs text-uppercase">Database Name</label>
                  <input type="text" class="form-control" v-model="newDatabase.name" placeholder="my_database"
                    pattern="[a-zA-Z][a-zA-Z0-9_]*">
                </div>
                <button class="btn bg-gradient-primary w-100" @click="createDatabase"
                  :disabled="!newDatabase.name || creatingDb">
                  <span v-if="creatingDb" class="spinner-border spinner-border-sm me-1"></span>
                  Create Database
                </button>
              </div>
            </div>
          </div>

          <!-- Create User -->
          <div class="col-lg-4 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Create User</h6>
              </div>
              <div class="card-body">
                <div class="mb-2">
                  <label class="form-label text-xs text-uppercase">Username</label>
                  <input type="text" class="form-control" v-model="newUser.username" placeholder="db_user">
                </div>
                <div class="mb-3">
                  <label class="form-label text-xs text-uppercase">Password</label>
                  <input type="password" class="form-control" v-model="newUser.password" placeholder="********">
                </div>
                <button class="btn bg-gradient-success w-100" @click="createUser"
                  :disabled="!newUser.username || !newUser.password || creatingUser">
                  <span v-if="creatingUser" class="spinner-border spinner-border-sm me-1"></span>
                  Create User
                </button>
              </div>
            </div>
          </div>

          <!-- Assign User -->
          <div class="col-lg-4 mb-4">
            <div class="card h-100">
              <div class="card-header pb-0">
                <h6 class="mb-0">Assign User to Database</h6>
              </div>
              <div class="card-body">
                <div class="mb-2">
                  <label class="form-label text-xs text-uppercase">Database</label>
                  <select class="form-control" v-model="assignment.database">
                    <option value="">Select database...</option>
                    <option v-for="db in databases" :key="db.name" :value="db.name">{{ db.name }}</option>
                  </select>
                </div>
                <div class="mb-2">
                  <label class="form-label text-xs text-uppercase">User</label>
                  <select class="form-control" v-model="assignment.username">
                    <option value="">Select user...</option>
                    <option v-for="user in users" :key="user.username" :value="user.username">
                      {{ user.username }}@{{ user.host }}
                    </option>
                  </select>
                </div>
                <button class="btn bg-gradient-info w-100" @click="showAssignModal = true"
                  :disabled="!assignment.database || !assignment.username">
                  Select Permissions
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Databases List -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header pb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">Databases</h6>
                  <span class="badge bg-gradient-primary">{{ databases.length }} databases</span>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table align-items-center mb-0">
                    <thead>
                      <tr>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Database</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Size</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Users</th>
                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                          Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="db in databases" :key="db.name">
                        <td>
                          <div class="d-flex align-items-center">
                            <i class="material-symbols-rounded text-info me-2">database</i>
                            <h6 class="mb-0 text-sm">{{ db.name }}</h6>
                          </div>
                        </td>
                        <td>
                          <span class="text-sm">{{ db.size }}</span>
                        </td>
                        <td>
                          <div v-if="db.users.length > 0">
                            <span v-for="(user, index) in db.users" :key="user.username"
                              class="badge bg-gradient-secondary me-1 mb-1">
                              {{ user.username }}
                            </span>
                          </div>
                          <span v-else class="text-xs text-secondary">No users assigned</span>
                        </td>
                        <td class="text-center">
                          <button class="btn btn-link text-info mb-0 px-2" @click="openPhpMyAdmin(db)"
                            title="Open in phpMyAdmin">
                            <i class="material-symbols-rounded text-sm">open_in_new</i>
                          </button>
                          <button class="btn btn-link text-primary mb-0 px-2" @click="manageDatabase(db)"
                            title="Manage">
                            <i class="material-symbols-rounded text-sm">settings</i>
                          </button>
                          <button class="btn btn-link text-danger mb-0 px-2" @click="confirmDeleteDb(db)"
                            title="Delete">
                            <i class="material-symbols-rounded text-sm">delete</i>
                          </button>
                        </td>
                      </tr>
                      <tr v-if="databases.length === 0">
                        <td colspan="4" class="text-center py-4 text-secondary">
                          No databases found. Create one above.
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

      <!-- Assign Permissions Modal -->
      <div class="modal-backdrop fade show" v-if="showAssignModal" @click="showAssignModal = false"></div>
      <div class="modal fade show d-block" v-if="showAssignModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Assign Permissions</h5>
              <button type="button" class="btn-close" @click="showAssignModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm">
                Assign <strong>{{ assignment.username }}</strong> to database <strong>{{ assignment.database }}</strong>
              </p>
              <div class="row">
                <div class="col-6" v-for="priv in availablePrivileges" :key="priv">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" :value="priv" v-model="assignment.privileges"
                      :id="'priv-' + priv">
                    <label class="form-check-label text-sm" :for="'priv-' + priv">{{ priv }}</label>
                  </div>
                </div>
              </div>
              <div class="mt-3">
                <button class="btn btn-link text-sm p-0" @click="selectAllPrivileges">Select All</button>
                <span class="mx-2">|</span>
                <button class="btn btn-link text-sm p-0" @click="selectBasicPrivileges">Basic (CRUD)</button>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showAssignModal = false">Cancel</button>
              <button class="btn bg-gradient-success" @click="assignUser"
                :disabled="assignment.privileges.length === 0 || assigning">
                <span v-if="assigning" class="spinner-border spinner-border-sm me-1"></span>
                Assign Permissions
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Manage Database Modal -->
      <div class="modal-backdrop fade show" v-if="showManageModal" @click="showManageModal = false"></div>
      <div class="modal fade show d-block" v-if="showManageModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-info me-2">database</i>
                Manage: {{ managingDb?.name }}
              </h5>
              <button type="button" class="btn-close" @click="showManageModal = false"></button>
            </div>
            <div class="modal-body">
              <h6>Database Users</h6>
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Privileges</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="user in managingDb?.users" :key="user.username">
                      <td>{{ user.username }}@{{ user.host }}</td>
                      <td>
                        <span v-for="priv in user.privileges?.slice(0, 3)" :key="priv"
                          class="badge bg-secondary me-1">{{ priv
                          }}</span>
                        <span v-if="user.privileges?.length > 3" class="text-xs text-secondary">+{{
                          user.privileges.length - 3
                        }} more</span>
                      </td>
                      <td>
                        <button class="btn btn-link text-primary p-0 me-2" @click="editUserPermissions(user)"
                          title="Edit permissions">
                          <i class="material-symbols-rounded text-sm">edit</i>
                        </button>
                        <button class="btn btn-link text-warning p-0 me-2" @click="changeUserPassword(user)"
                          title="Change password">
                          <i class="material-symbols-rounded text-sm">key</i>
                        </button>
                        <button class="btn btn-link text-danger p-0" @click="removeUserAccess(user)"
                          title="Remove access">
                          <i class="material-symbols-rounded text-sm">person_remove</i>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="managingDb?.users?.length === 0">
                      <td colspan="3" class="text-center text-secondary">No users assigned</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showManageModal = false">Close</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Change Password Modal -->
      <div class="modal-backdrop fade show" v-if="showPasswordModal" @click="showPasswordModal = false"></div>
      <div class="modal fade show d-block" v-if="showPasswordModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Change Password</h5>
              <button type="button" class="btn-close" @click="showPasswordModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm">Change password for <strong>{{ editingUser?.username }}</strong></p>
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" v-model="newPassword" placeholder="Enter new password">
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showPasswordModal = false">Cancel</button>
              <button class="btn bg-gradient-warning" @click="updatePassword"
                :disabled="!newPassword || updatingPassword">
                <span v-if="updatingPassword" class="spinner-border spinner-border-sm me-1"></span>
                Update Password
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Database Modal -->
      <div class="modal-backdrop fade show" v-if="showDeleteModal" @click="showDeleteModal = false"></div>
      <div class="modal fade show d-block" v-if="showDeleteModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title text-danger">
                <i class="material-symbols-rounded me-2">warning</i>
                Delete Database
              </h5>
              <button type="button" class="btn-close" @click="showDeleteModal = false"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to delete database <strong>{{ dbToDelete?.name }}</strong>?</p>
              <p class="text-danger text-sm mb-0">This action cannot be undone. All data will be permanently lost.</p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showDeleteModal = false">Cancel</button>
              <button class="btn bg-gradient-danger" @click="deleteDatabase" :disabled="deletingDb">
                <span v-if="deletingDb" class="spinner-border spinner-border-sm me-1"></span>
                Delete Database
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- phpMyAdmin Access Modal -->
      <div class="modal-backdrop fade show" v-if="showPmaModal" @click="showPmaModal = false"></div>
      <div class="modal fade show d-block" v-if="showPmaModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-info me-2">open_in_new</i>
                Open phpMyAdmin
              </h5>
              <button type="button" class="btn-close" @click="showPmaModal = false"></button>
            </div>
            <div class="modal-body">
              <p>Opening phpMyAdmin for database <strong>{{ pmaAccess?.database }}</strong></p>
              <div class="alert alert-info">
                <p class="mb-1"><strong>Login with:</strong></p>
                <p class="mb-0">Username: <code>{{ pmaAccess?.username }}</code></p>
                <p class="mb-0 text-sm text-secondary">Enter your database user password</p>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showPmaModal = false">Cancel</button>
              <a :href="pmaAccess?.url" target="_blank" class="btn bg-gradient-info" @click="showPmaModal = false">
                <i class="material-symbols-rounded text-sm me-1">open_in_new</i>
                Open phpMyAdmin
              </a>
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
const installing = ref(false)
const reinstalling = ref(false)
const creatingDb = ref(false)
const creatingUser = ref(false)
const assigning = ref(false)
const deletingDb = ref(false)
const updatingPassword = ref(false)

const status = ref({ checked: false, phpMyAdminInstalled: false })
const credentials = ref(null)
const showCredentials = ref(false)
const installLog = ref('')
const databases = ref([])
const users = ref([])

const newDatabase = ref({ name: '' })
const newUser = ref({ username: '', password: '' })
const assignment = ref({ database: '', username: '', privileges: [] })

const showAssignModal = ref(false)
const showManageModal = ref(false)
const showPasswordModal = ref(false)
const showDeleteModal = ref(false)
const showPmaModal = ref(false)

const managingDb = ref(null)
const editingUser = ref(null)
const dbToDelete = ref(null)
const pmaAccess = ref(null)
const newPassword = ref('')

const availablePrivileges = [
  'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
  'ALTER', 'INDEX', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
  'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
  'ALTER ROUTINE', 'EVENT', 'TRIGGER'
]

const alert = ref({ show: false, type: 'success', message: '' })

onMounted(() => {
  checkStatus()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const getAlertIcon = (type) => {
  const icons = { success: 'check_circle', danger: 'error', warning: 'warning', info: 'info' }
  return icons[type] || 'info'
}

const checkStatus = async () => {
  try {
    loading.value = true
    const response = await axios.get('/database/status')
    status.value = { ...response.data, checked: true }

    if (status.value.phpMyAdminInstalled) {
      await loadData()
    }
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to check status')
    status.value.checked = true
  } finally {
    loading.value = false
  }
}

const loadData = async () => {
  try {
    loading.value = true
    const [dbResponse, userResponse] = await Promise.all([
      axios.get('/database/list'),
      axios.get('/database/users')
    ])
    databases.value = dbResponse.data.databases
    users.value = userResponse.data.users
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load data')
  } finally {
    loading.value = false
  }
}

const installPhpMyAdmin = async () => {
  try {
    installing.value = true
    installLog.value = '' // Reset log
    showAlert('info', 'Installing phpMyAdmin... This may take a few minutes.')

    const response = await axios.post('/database/install-phpmyadmin')

    // Start polling for status if polling mode
    if (response.data.polling) {
      // Save credentials for later display
      credentials.value = response.data.credentials
      pollInstallStatus()
    } else {
      // Synchronous mode - installation completed immediately
      credentials.value = response.data.credentials
      showCredentials.value = true
      status.value.phpMyAdminInstalled = true
      installing.value = false
      showAlert('success', response.data.message)
    }
  } catch (error) {
    const errMsg = error.response?.data?.error || 'Failed to install phpMyAdmin'
    const details = error.response?.data?.details || ''
    showAlert('danger', errMsg + (details ? '\n\nDetails: ' + details : ''))
    console.error('phpMyAdmin install error:', error.response?.data)
    installing.value = false
  }
}

// Poll for installation status
const pollInstallStatus = async () => {
  try {
    const response = await axios.get('/database/install-status')
    installLog.value = response.data.log

    if (response.data.status === 'done') {
      installing.value = false
      status.value.phpMyAdminInstalled = true
      showCredentials.value = true
      showAlert('success', 'phpMyAdmin installed successfully!')
    } else if (response.data.status === 'error') {
      installing.value = false
      showAlert('danger', 'Installation failed. Check the log for details.')
    } else {
      // Still running, poll again in 2 seconds
      setTimeout(pollInstallStatus, 2000)
    }
  } catch (error) {
    console.error('Poll error:', error)
    setTimeout(pollInstallStatus, 2000)
  }
}

const reinstallPhpMyAdmin = async () => {
  if (!confirm('Are you sure you want to reinstall phpMyAdmin? This will remove and reinstall it with new credentials.')) {
    return
  }

  try {
    reinstalling.value = true
    showAlert('info', 'Reinstalling phpMyAdmin... This may take a few minutes.')

    const response = await axios.post('/database/reinstall-phpmyadmin')

    credentials.value = response.data.credentials
    showCredentials.value = true

    showAlert('success', response.data.message)
  } catch (error) {
    const errMsg = error.response?.data?.error || 'Failed to reinstall phpMyAdmin'
    const details = error.response?.data?.details || ''
    showAlert('danger', errMsg + (details ? '\n\nDetails: ' + details : ''))
    console.error('phpMyAdmin reinstall error:', error.response?.data)
  } finally {
    reinstalling.value = false
  }
}

const createDatabase = async () => {
  try {
    creatingDb.value = true
    await axios.post('/database/create', { name: newDatabase.value.name })
    showAlert('success', `Database '${newDatabase.value.name}' created successfully`)
    newDatabase.value.name = ''
    await loadData()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to create database')
  } finally {
    creatingDb.value = false
  }
}

const createUser = async () => {
  try {
    creatingUser.value = true
    await axios.post('/database/user/create', newUser.value)
    showAlert('success', `User '${newUser.value.username}' created successfully`)
    newUser.value = { username: '', password: '' }
    await loadData()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to create user')
  } finally {
    creatingUser.value = false
  }
}

const selectAllPrivileges = () => {
  assignment.value.privileges = [...availablePrivileges]
}

const selectBasicPrivileges = () => {
  assignment.value.privileges = ['SELECT', 'INSERT', 'UPDATE', 'DELETE']
}

const assignUser = async () => {
  try {
    assigning.value = true
    await axios.post('/database/user/assign', assignment.value)
    showAlert('success', `User assigned to database successfully`)
    showAssignModal.value = false
    assignment.value = { database: '', username: '', privileges: [] }
    await loadData()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to assign user')
  } finally {
    assigning.value = false
  }
}

const manageDatabase = (db) => {
  managingDb.value = db
  showManageModal.value = true
}

const editUserPermissions = (user) => {
  assignment.value = {
    database: managingDb.value.name,
    username: user.username,
    privileges: user.privileges || []
  }
  showManageModal.value = false
  showAssignModal.value = true
}

const changeUserPassword = (user) => {
  editingUser.value = user
  newPassword.value = ''
  showPasswordModal.value = true
}

const updatePassword = async () => {
  try {
    updatingPassword.value = true
    await axios.post('/database/user/password', {
      username: editingUser.value.username,
      host: editingUser.value.host,
      password: newPassword.value
    })
    showAlert('success', 'Password updated successfully')
    showPasswordModal.value = false
    newPassword.value = ''
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to update password')
  } finally {
    updatingPassword.value = false
  }
}

const removeUserAccess = async (user) => {
  try {
    await axios.post('/database/user/permissions', {
      database: managingDb.value.name,
      username: user.username,
      host: user.host,
      privileges: []
    })
    showAlert('success', 'User access removed')
    await loadData()
    // Update managing db
    managingDb.value = databases.value.find(d => d.name === managingDb.value.name)
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to remove user access')
  }
}

const confirmDeleteDb = (db) => {
  dbToDelete.value = db
  showDeleteModal.value = true
}

const deleteDatabase = async () => {
  try {
    deletingDb.value = true
    await axios.post('/database/delete', { name: dbToDelete.value.name })
    showAlert('success', `Database '${dbToDelete.value.name}' deleted`)
    showDeleteModal.value = false
    await loadData()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to delete database')
  } finally {
    deletingDb.value = false
  }
}

const openPhpMyAdmin = async (db) => {
  if (db.users.length === 0) {
    showAlert('warning', 'No users assigned to this database. Assign a user first.')
    return
  }

  try {
    showAlert('info', 'Opening phpMyAdmin...')
    const response = await axios.post('/database/phpmyadmin/access', {
      database: db.name,
      username: db.users[0].username
    })
    // Open directly in new tab
    window.open(response.data.url, '_blank')
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to get phpMyAdmin access')
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

.gap-2 {
  gap: 0.5rem;
}
</style>
