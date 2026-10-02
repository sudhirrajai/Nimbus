<template>
  <MainLayout>
    <Head title="Databases" />
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <h4 class="font-weight-bolder mb-0">Database Management</h4>
              <p class="mb-0 text-sm">Manage MySQL databases, users, and tables</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="loadData" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin-animation': loading }">refresh</i>
                Refresh
              </button>
              <button class="btn bg-gradient-info mb-0" @click="handleOpenWorkspace" :disabled="openingWorkspace">
                <span v-if="openingWorkspace" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">table_chart</i>
                Database Workspace
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

      <!-- Loading State -->
      <div class="row" v-if="loading && databases.length === 0">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-secondary mt-2">Loading database information...</p>
        </div>
      </div>
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
                <div class="mb-2">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label text-xs text-uppercase font-weight-bolder text-secondary mb-0">Password</label>
                    <button 
                      type="button" 
                      class="btn-auto-gen" 
                      @click="generateStrongPassword('create')"
                      title="Generate strong password and copy to clipboard"
                    >
                      <i class="material-symbols-rounded text-xs me-1">auto_awesome</i>
                      Auto-Generate & Copy
                    </button>
                  </div>
                  <div class="password-box-unified">
                    <input 
                      :type="showCreatePassword ? 'text' : 'password'" 
                      v-model="newUser.password" 
                      placeholder="Enter or generate password"
                      class="font-monospace"
                    >
                    <div class="d-flex align-items-center gap-1">
                      <button 
                        type="button" 
                        class="icon-action-btn"
                        @click="showCreatePassword = !showCreatePassword"
                        :title="showCreatePassword ? 'Hide password' : 'Show password'"
                      >
                        <i class="material-symbols-rounded text-sm">{{ showCreatePassword ? 'visibility_off' : 'visibility' }}</i>
                      </button>
                      <button 
                        type="button" 
                        class="icon-action-btn"
                        :class="{ 'text-success': copiedField === 'create' }"
                        @click="copyToClipboard(newUser.password, 'create')"
                        :disabled="!newUser.password"
                        title="Copy password to clipboard"
                      >
                        <i class="material-symbols-rounded text-sm">{{ copiedField === 'create' ? 'check' : 'content_copy' }}</i>
                      </button>
                    </div>
                  </div>
                  <transition name="fade">
                    <span v-if="copiedField === 'create'" class="text-xxs text-success font-weight-bold mt-1 d-inline-flex align-items-center">
                      <i class="material-symbols-rounded text-xs me-1">check_circle</i> Copied to clipboard!
                    </span>
                  </transition>
                </div>
                <div class="mb-3">
                  <label class="form-label text-xs text-uppercase">Host Access</label>
                  <select class="form-select form-control" v-model="newUser.host">
                    <option value="localhost">Localhost (localhost)</option>
                    <option value="%">Any Host (%) - Remote Access</option>
                  </select>
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
                  <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0">Your Databases</h6>
                    <div class="d-flex align-items-center gap-3">
                      <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                        <input v-model="dbSearchQuery" type="text" class="form-control" placeholder="Search databases...">
                      </div>
                      <span class="badge bg-gradient-primary">{{ filteredDatabases.length }} databases</span>
                    </div>
                  </div>
                </div>
              <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive p-0">
                  <table class="table align-items-center mb-0">
                    <thead>
                      <tr>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Database</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Size</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Users</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Associated Projects</th>
                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                          Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                    <tr v-for="db in paginatedDatabases" :key="db.name" class="domain-row">
                      <td>
                        <div class="d-flex align-items-center px-3 py-2">
                          <div class="icon-box-db me-3">
                            <i class="material-symbols-rounded text-info">database</i>
                          </div>
                          <div>
                            <h6 class="mb-0 text-sm font-weight-bold">{{ db.name }}</h6>
                            <div class="text-xxs text-secondary mt-1">
                              <span v-if="db.created_by">Created by: {{ db.created_by }} <span v-if="db.created_at">on {{ db.created_at }}</span></span>
                              <span v-else>Created by: System</span>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="material-symbols-rounded text-secondary text-sm me-1">analytics</i>
                          <span class="text-sm font-weight-bold">{{ db.size }}</span>
                        </div>
                      </td>
                      <td>
                        <div v-if="db.users.length > 0" class="d-flex flex-wrap gap-1">
                          <span v-for="user in db.users" :key="user.username"
                            class="badge-db-user">
                            <i class="material-symbols-rounded text-xxs me-1">person</i>
                            {{ user.username }}
                          </span>
                        </div>
                        <span v-else class="text-xs text-secondary opacity-7">No users assigned</span>
                      </td>
                      <td>
                        <div v-if="db.projects && db.projects.length > 0" class="d-flex flex-wrap gap-1">
                          <span v-for="p in db.projects" :key="p.project" class="badge bg-light text-dark text-xs border py-1 px-2 rounded">
                            <i class="material-symbols-rounded text-xxs me-1 align-middle">folder</i>
                            {{ p.project }} <span class="text-secondary text-xxs">({{ p.type }})</span>
                          </span>
                        </div>
                        <span v-else class="text-xs text-secondary opacity-7">None</span>
                      </td>
                      <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                          <button class="action-btn btn-view" @click="openNativeManager(db.name)" :disabled="openingManager[db.name]" title="Open Database Workspace">
                            <span v-if="openingManager[db.name]" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="material-symbols-rounded">table_chart</i>
                          </button>
                          <button class="action-btn btn-backup" @click="quickBackupDatabase(db.name)" :disabled="backingUpDb[db.name]" title="Quick Backup Database">
                            <span v-if="backingUpDb[db.name]" class="spinner-border spinner-border-sm text-success"></span>
                            <i v-else class="material-symbols-rounded">archive</i>
                          </button>
                          <button class="action-btn btn-link-proj" @click="openLinkProjectModal(db)"
                            title="Link Project / Domain">
                            <i class="material-symbols-rounded">link</i>
                          </button>
                          <button class="action-btn btn-edit" @click="manageDatabase(db)"
                            title="Manage">
                            <i class="material-symbols-rounded">settings</i>
                          </button>
                          <button class="action-btn btn-delete" @click="confirmDeleteDb(db)"
                            title="Delete">
                            <i class="material-symbols-rounded">delete</i>
                          </button>
                        </div>
                      </td>
                    </tr>
                      <tr v-if="filteredDatabases.length === 0">
                        <td colspan="5" class="text-center py-5 text-secondary">
                          <div class="empty-state">
                            <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">database</i>
                            <p class="mt-3">No databases found matching your search.</p>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="filteredDatabases.length > itemsPerPage" class="d-flex justify-content-between align-items-center p-3 border-top">
                  <div class="text-xs text-secondary">
                    Showing {{ paginationStart + 1 }} to {{ Math.min(paginationEnd, filteredDatabases.length) }} of {{ filteredDatabases.length }} entries
                  </div>
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: dbCurrentPage === 1 }">
                      <button class="page-link" @click="dbCurrentPage--" aria-label="Previous">
                        <i class="material-symbols-rounded text-xs">chevron_left</i>
                      </button>
                    </li>
                    <li v-for="page in totalPages" :key="page" class="page-item" :class="{ active: dbCurrentPage === page }">
                      <button class="page-link" @click="dbCurrentPage = page">{{ page }}</button>
                    </li>
                    <li class="page-item" :class="{ disabled: dbCurrentPage === totalPages }">
                      <button class="page-link" @click="dbCurrentPage++" aria-label="Next">
                        <i class="material-symbols-rounded text-xs">chevron_right</i>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

      <!-- Select Database Modal (for opening Workspace from header) -->
      <div class="modal-backdrop fade show" v-if="showWorkspaceModal" @click="showWorkspaceModal = false"></div>
      <div class="modal fade show d-block" v-if="showWorkspaceModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title d-flex align-items-center gap-2">
                <i class="material-symbols-rounded text-info">table_chart</i>
                Open Database Workspace
              </h5>
              <button type="button" class="btn-close" @click="showWorkspaceModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm text-secondary mb-3">
                Select a database to launch the native Nimbus Database Workspace:
              </p>
              <div class="list-group">
                <button
                  v-for="db in databases"
                  :key="db.name"
                  type="button"
                  class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 border-radius-lg mb-2"
                  @click="openNativeManager(db.name); showWorkspaceModal = false"
                >
                  <div class="d-flex align-items-center">
                    <i class="material-symbols-rounded text-info me-2">database</i>
                    <span class="font-weight-bold">{{ db.name }}</span>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark text-xxs border">{{ db.size }}</span>
                    <i class="material-symbols-rounded text-secondary text-sm">chevron_right</i>
                  </div>
                </button>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showWorkspaceModal = false">Close</button>
            </div>
          </div>
        </div>
      </div>

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
                    <tr v-for="user in managingDb?.users" :key="user.username + '@' + user.host">
                      <td>
                        <div class="d-flex align-items-center">
                          <span class="font-weight-bold text-sm">{{ user.username }}</span>
                          <span class="badge ms-2" :class="user.host === '%' ? 'bg-gradient-warning' : (user.host === 'localhost' ? 'bg-light text-dark border' : 'bg-gradient-info')">
                            {{ user.host === '%' ? 'Remote (%)' : user.host }}
                          </span>
                        </div>
                      </td>
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
                        <button class="btn btn-link text-info p-0 me-2" @click="openHostModal(user)"
                          title="Change Host / Remote Access">
                          <i class="material-symbols-rounded text-sm">lan</i>
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
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label text-xs text-uppercase font-weight-bolder text-secondary mb-0">New Password</label>
                  <button 
                    type="button" 
                    class="btn-auto-gen" 
                    @click="generateStrongPassword('change')"
                    title="Generate strong password and copy to clipboard"
                  >
                    <i class="material-symbols-rounded text-xs me-1">auto_awesome</i>
                    Auto-Generate & Copy
                  </button>
                </div>
                <div class="password-box-unified">
                  <input 
                    :type="showChangePassword ? 'text' : 'password'" 
                    v-model="newPassword" 
                    placeholder="Enter or generate new password"
                    class="font-monospace"
                  >
                  <div class="d-flex align-items-center gap-1">
                    <button 
                      type="button" 
                      class="icon-action-btn"
                      @click="showChangePassword = !showChangePassword"
                      :title="showChangePassword ? 'Hide password' : 'Show password'"
                    >
                      <i class="material-symbols-rounded text-sm">{{ showChangePassword ? 'visibility_off' : 'visibility' }}</i>
                    </button>
                    <button 
                      type="button" 
                      class="icon-action-btn"
                      :class="{ 'text-success': copiedField === 'change' }"
                      @click="copyToClipboard(newPassword, 'change')"
                      :disabled="!newPassword"
                      title="Copy password to clipboard"
                    >
                      <i class="material-symbols-rounded text-sm">{{ copiedField === 'change' ? 'check' : 'content_copy' }}</i>
                    </button>
                  </div>
                </div>
                <transition name="fade">
                  <span v-if="copiedField === 'change'" class="text-xxs text-success font-weight-bold mt-1 d-inline-flex align-items-center">
                    <i class="material-symbols-rounded text-xs me-1">check_circle</i> Copied to clipboard!
                  </span>
                </transition>
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

      <!-- Change Host Modal -->
      <div class="modal-backdrop fade show" v-if="showHostModal" @click="showHostModal = false"></div>
      <div class="modal fade show d-block" v-if="showHostModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title d-flex align-items-center">
                <i class="material-symbols-rounded text-info me-2">lan</i>
                User Host & Remote Access
              </h5>
              <button type="button" class="btn-close" @click="showHostModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm">
                Configuring allowed host for user <strong>{{ hostTargetUser?.username }}</strong>
                (currently <code>{{ hostTargetUser?.host }}</code>).
              </p>

              <div class="form-group mb-3">
                <label class="form-control-label text-xs text-uppercase font-weight-bold">Allowed Connection Host</label>
                <select class="form-select form-control" v-model="selectedHostType">
                  <option value="localhost">Localhost only (localhost) — For sites on this server</option>
                  <option value="%">Any Host (%) — Remote connection from external clients</option>
                  <option value="custom">Specific IP / Subnet</option>
                </select>
              </div>

              <div class="form-group mb-3" v-if="selectedHostType === 'custom'">
                <label class="form-control-label text-xs text-uppercase font-weight-bold">Custom Client IP or CIDR</label>
                <input type="text" class="form-control" v-model="customHostInput" placeholder="e.g. 192.168.1.50 or 203.0.113.10">
              </div>

              <div class="alert alert-warning text-white text-xs mb-0" v-if="selectedHostType === '%'">
                <div class="d-flex align-items-start">
                  <i class="material-symbols-rounded me-2" style="font-size: 1.2rem;">warning</i>
                  <div>
                    <strong>Security Notice:</strong> Any host (<code>%</code>) allows connections from external tools (Navicat, DBeaver, external servers). Ensure the user has a strong password and firewall allows port 3306 only from trusted sources.
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showHostModal = false">Cancel</button>
              <button class="btn bg-gradient-info" @click="submitHostChange" :disabled="updatingHost || (selectedHostType === 'custom' && !customHostInput.trim())">
                <span v-if="updatingHost" class="spinner-border spinner-border-sm me-1"></span>
                Save Host Setting
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

      <!-- Link Project Modal -->
      <div class="modal-backdrop fade show" v-if="showLinkModal" @click="showLinkModal = false"></div>
      <div class="modal fade show d-block" v-if="showLinkModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-primary me-2">link</i>
                Link Database to Project: {{ linkingDb?.name }}
              </h5>
              <button type="button" class="btn-close" @click="showLinkModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm text-secondary mb-3">
                Manually link this database to a hosted domain (project). Users with access to the domain will be able to manage this database.
              </p>
              <div class="form-group mb-3">
                <label class="form-control-label">Select Domain / Project</label>
                <select class="form-select form-control" v-model="selectedProjectDomain" style="padding: 0.5rem 0.75rem;">
                  <option value="">-- No Project / Unlink --</option>
                  <option v-for="dom in availableDomains" :key="dom.name" :value="dom.name">
                    {{ dom.name }}
                  </option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showLinkModal = false">Cancel</button>
              <button class="btn bg-gradient-primary mb-0" @click="saveProjectLink" :disabled="assigningProject">
                <span v-if="assigningProject" class="spinner-border spinner-border-sm me-1"></span>
                Save Assignment
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Native Database Manager Workspace Modal -->
      <DatabaseManagerModal :show="showNativeManager" :database-name="selectedDbForManager" @close="showNativeManager = false" />

    </div>
  </MainLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import DatabaseManagerModal from '@/Components/DatabaseManagerModal.vue'
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const openingManager = ref({})
const backingUpDb = ref({})

const openNativeManager = async (dbName) => {
  try {
    openingManager.value[dbName] = true
    const response = await axios.post('/database/manager/token', { database: dbName })
    if (response.data?.url) {
      window.open(response.data.url, '_blank')
    }
  } catch (err) {
    alert(err.response?.data?.error || 'Failed to generate database session token')
  } finally {
    openingManager.value[dbName] = false
  }
}

const quickBackupDatabase = async (dbName) => {
  try {
    backingUpDb.value[dbName] = true
    const response = await axios.post('/backups', {
      database_name: dbName,
      type: 'database'
    })
    const msg = response.data?.message || `Backup for "${dbName}" created successfully!`
    showAlert('success', msg)
  } catch (err) {
    const errorMsg = err.response?.data?.error || err.response?.data?.message || 'Failed to create database backup'
    showAlert('danger', errorMsg)
  } finally {
    backingUpDb.value[dbName] = false
  }
}

const loading = ref(false)
const openingWorkspace = ref(false)
const showWorkspaceModal = ref(false)
const creatingDb = ref(false)
const creatingUser = ref(false)
const assigning = ref(false)
const deletingDb = ref(false)
const updatingPassword = ref(false)

const databases = ref([])
const users = ref([])

const dbSearchQuery = ref('')
const dbCurrentPage = ref(1)
const itemsPerPage = ref(10)

const newDatabase = ref({ name: '' })
const newUser = ref({ username: '', password: '', host: 'localhost' })
const assignment = ref({ database: '', username: '', privileges: [] })

const showCreatePassword = ref(false)
const showChangePassword = ref(false)
const copiedField = ref(null)

const generateStrongPassword = (target = 'create') => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()_+-=[]{}|'
  let password = ''
  const uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ'
  const lowers = 'abcdefghijkmnopqrstuvwxyz'
  const numbers = '23456789'
  const specials = '!@#$%^&*()_+-='

  password += uppers[Math.floor(Math.random() * uppers.length)]
  password += lowers[Math.floor(Math.random() * lowers.length)]
  password += numbers[Math.floor(Math.random() * numbers.length)]
  password += specials[Math.floor(Math.random() * specials.length)]

  for (let i = 0; i < 12; i++) {
    password += chars[Math.floor(Math.random() * chars.length)]
  }

  password = password.split('').sort(() => 0.5 - Math.random()).join('')

  if (target === 'create') {
    newUser.value.password = password
    showCreatePassword.value = true
  } else if (target === 'change') {
    newPassword.value = password
    showChangePassword.value = true
  }

  copyToClipboard(password, target)
}

const copyToClipboard = async (text, target = 'create') => {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    copiedField.value = target
    setTimeout(() => {
      if (copiedField.value === target) copiedField.value = null
    }, 3000)
  } catch (err) {
    const el = document.createElement('textarea')
    el.value = text
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    copiedField.value = target
    setTimeout(() => {
      if (copiedField.value === target) copiedField.value = null
    }, 3000)
  }
}

const showAssignModal = ref(false)
const showManageModal = ref(false)
const showPasswordModal = ref(false)
const showHostModal = ref(false)
const showDeleteModal = ref(false)
const showLinkModal = ref(false)

const managingDb = ref(null)
const editingUser = ref(null)
const hostTargetUser = ref(null)
const selectedHostType = ref('localhost')
const customHostInput = ref('')
const updatingHost = ref(false)
const dbToDelete = ref(null)
const newPassword = ref('')
const linkingDb = ref(null)
const selectedProjectDomain = ref('')
const availableDomains = ref([])
const assigningProject = ref(false)

const availablePrivileges = [
  'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
  'ALTER', 'INDEX', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
  'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
  'ALTER ROUTINE', 'EVENT', 'TRIGGER', 'REFERENCES'
]

const alert = ref({ show: false, type: 'success', message: '' })

onMounted(() => {
  loadData()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const getAlertIcon = (type) => {
  const icons = { success: 'check_circle', danger: 'error', warning: 'warning', info: 'info' }
  return icons[type] || 'info'
}

const handleOpenWorkspace = () => {
  if (databases.value.length === 0) {
    showAlert('info', 'No databases found. Please create a database first.')
    return
  }
  if (databases.value.length === 1) {
    openNativeManager(databases.value[0].name)
    return
  }
  showWorkspaceModal.value = true
}

const filteredDatabases = computed(() => {
  if (!dbSearchQuery.value) return databases.value
  const q = dbSearchQuery.value.toLowerCase()
  return databases.value.filter(db => db.name.toLowerCase().includes(q))
})

const totalPages = computed(() => Math.ceil(filteredDatabases.value.length / itemsPerPage.value))
const paginationStart = computed(() => (dbCurrentPage.value - 1) * itemsPerPage.value)
const paginationEnd = computed(() => dbCurrentPage.value * itemsPerPage.value)

const paginatedDatabases = computed(() => {
  return filteredDatabases.value.slice(paginationStart.value, paginationEnd.value)
})

const loadData = async () => {
  try {
    loading.value = true
    const [dbResponse, userResponse] = await Promise.all([
      axios.get('/database/list'),
      axios.get('/database/users')
    ])
    databases.value = dbResponse.data.databases || []
    users.value = userResponse.data.users || []
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load data')
  } finally {
    loading.value = false
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
    showAlert('success', `User '${newUser.value.username}'@'${newUser.value.host}' created successfully`)
    newUser.value = { username: '', password: '', host: 'localhost' }
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

const openHostModal = (user) => {
  hostTargetUser.value = user
  if (user.host === 'localhost') {
    selectedHostType.value = 'localhost'
    customHostInput.value = ''
  } else if (user.host === '%') {
    selectedHostType.value = '%'
    customHostInput.value = ''
  } else {
    selectedHostType.value = 'custom'
    customHostInput.value = user.host
  }
  showHostModal.value = true
}

const submitHostChange = async () => {
  if (!hostTargetUser.value) return
  let targetHost = selectedHostType.value
  if (targetHost === 'custom') {
    targetHost = customHostInput.value.trim()
    if (!targetHost) {
      showAlert('danger', 'Please provide a valid host or IP address')
      return
    }
  }

  try {
    updatingHost.value = true
    const response = await axios.post('/database/user/update-host', {
      username: hostTargetUser.value.username,
      current_host: hostTargetUser.value.host,
      new_host: targetHost
    })

    showAlert('success', response.data?.message || 'User host updated successfully')
    showHostModal.value = false

    if (managingDb.value && managingDb.value.users) {
      const u = managingDb.value.users.find(u => u.username === hostTargetUser.value.username && u.host === hostTargetUser.value.host)
      if (u) {
        u.host = targetHost
      }
    }

    await loadData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || err.response?.data?.message || 'Failed to update user host')
  } finally {
    updatingHost.value = false
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

const openLinkProjectModal = async (db) => {
  linkingDb.value = db
  selectedProjectDomain.value = db.domain || (db.projects && db.projects.length > 0 ? db.projects[0].project : '')
  showLinkModal.value = true
  if (availableDomains.value.length === 0) {
    try {
      const res = await axios.get('/domains/api')
      availableDomains.value = res.data.domains || []
    } catch (e) {
      console.error('Failed to load available domains', e)
    }
  }
}

const saveProjectLink = async () => {
  if (!linkingDb.value) return
  try {
    assigningProject.value = true
    const res = await axios.post('/database/assign-project', {
      name: linkingDb.value.name,
      domain: selectedProjectDomain.value
    })
    showAlert('success', res.data.message || 'Project linked successfully')
    showLinkModal.value = false
    await loadData()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to link project')
  } finally {
    assigningProject.value = false
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

.icon-box-db {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e9f2ff;
  box-shadow: 0 4px 6px -1px rgba(17, 113, 239, 0.1);
}

.badge-db-user {
  background: #f8f9fa;
  color: #344767;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  border: 1px solid #e9ecef;
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

.btn-sso:hover { background: #1171ef; color: #fff; }
.btn-settings:hover { background: #5e72e4; color: #fff; }
.btn-delete:hover { background: #f5365c; color: #fff; }
.btn-backup:hover { background: #2dce89; color: #fff; }

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.terminal-output::-webkit-scrollbar {
  width: 8px;
}
.terminal-output::-webkit-scrollbar-track {
  background: #1e1e1e;
}
.terminal-output::-webkit-scrollbar-thumb {
  background: #333;
  border-radius: 4px;
}
.terminal-output::-webkit-scrollbar-thumb:hover {
  background: #444;
}

.password-box-unified {
  display: flex;
  align-items: center;
  border: 1px solid #d2d6da;
  border-radius: 0.5rem;
  background: #fff;
  padding: 0 0.5rem 0 0.75rem;
  transition: all 0.2s ease;
}
.password-box-unified:focus-within {
  border-color: #5e72e4;
  box-shadow: 0 0 0 2px rgba(94, 114, 228, 0.2);
}
.password-box-unified input {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0.45rem 0.25rem 0.45rem 0;
  font-size: 0.875rem;
  color: #495057;
  width: 100%;
}
.icon-action-btn {
  background: transparent;
  border: none;
  color: #8392ab;
  padding: 4px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}
.icon-action-btn:hover {
  background: #f1f5f9;
  color: #344767;
}
.btn-auto-gen {
  background: #f0fdf4;
  color: #16a34a;
  border: 1px solid #bbf7d0;
  border-radius: 50rem;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 8px;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s ease;
  line-height: 1.2;
}
.btn-auto-gen:hover {
  background: #dcfce7;
  color: #15803d;
  border-color: #86efac;
  transform: translateY(-1px);
}
</style>


