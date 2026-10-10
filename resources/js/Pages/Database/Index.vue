<template>
  <MainLayout>
    <Head title="Databases" />
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-3">
        <div class="col-12">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <h4 class="font-weight-bolder mb-0">Database Management</h4>
              <p class="mb-0 text-sm text-secondary">Manage relational databases, users, permissions, and native workspaces</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="handleRefresh" :disabled="loading || loadingPostgres">
                <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin-animation': loading || loadingPostgres }">refresh</i>
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

      <!-- Engine Switcher Tab Bar -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card p-2 shadow-sm border-0 bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
              <div class="d-flex align-items-center gap-2">
                <button 
                  type="button" 
                  class="engine-switch-btn" 
                  :class="{ active: activeEngine === 'mysql' }"
                  @click="switchEngine('mysql')"
                >
                  <span class="engine-icon-pill mysql-pill">
                    <i class="material-symbols-rounded">database</i>
                  </span>
                  <div class="text-start">
                    <div class="fw-bold text-sm line-height-1">MySQL</div>
                    <div class="text-xxs opacity-7">Port 3306</div>
                  </div>
                  <span class="badge bg-light text-dark border ms-2">{{ databases.length }}</span>
                </button>

                <button 
                  type="button" 
                  class="engine-switch-btn" 
                  :class="{ active: activeEngine === 'postgres' }"
                  @click="switchEngine('postgres')"
                >
                  <span class="engine-icon-pill pg-pill">
                    <i class="material-symbols-rounded">deployed_code</i>
                  </span>
                  <div class="text-start">
                    <div class="fw-bold text-sm line-height-1">PostgreSQL</div>
                    <div class="text-xxs opacity-7">Port 5432</div>
                  </div>
                  <span v-if="postgresStatus.installed" class="badge bg-light text-dark border ms-2">
                    {{ postgresDatabases.length }}
                  </span>
                  <span v-else class="badge bg-gradient-warning text-xxs ms-2">
                    Not Installed
                  </span>
                </button>
              </div>

              <!-- Quick Engine Status Info -->
              <div class="d-flex align-items-center gap-2 px-2 text-xs text-secondary">
                <span v-if="activeEngine === 'mysql'" class="d-flex align-items-center gap-2">
                  <span class="badge-dot-live bg-success"></span>
                  <span class="font-weight-bold text-dark">MySQL Service Running</span>
                  <span class="text-secondary">&bull; Standard Web Engine</span>
                </span>
                <span v-else class="d-flex align-items-center gap-2">
                  <span class="badge-dot-live" :class="postgresStatus.active ? 'bg-success' : (postgresStatus.installed ? 'bg-warning' : 'bg-secondary')"></span>
                  <span class="font-weight-bold text-dark">
                    {{ postgresStatus.installed ? (postgresStatus.active ? 'PostgreSQL Active' : 'PostgreSQL Stopped') : 'PostgreSQL Not Installed' }}
                  </span>
                  <span v-if="postgresStatus.installed" class="text-secondary">&bull; {{ postgresStatus.version ? 'v' + postgresStatus.version : 'Port 5432' }}</span>
                </span>
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

      <!-- ========================================== -->
      <!-- SECTION A: MYSQL MANAGEMENT                -->
      <!-- ========================================== -->
      <div v-if="activeEngine === 'mysql'">
        <!-- Loading State -->
        <div class="row" v-if="loading && databases.length === 0">
          <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-secondary mt-2">Loading MySQL databases...</p>
          </div>
        </div>

        <template v-else>
          <!-- Create Forms Row -->
          <div class="row mb-4">
            <!-- Create Database -->
            <div class="col-lg-4 mb-4">
              <div class="card h-100 shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <h6 class="mb-0 d-flex align-items-center gap-2">
                    <i class="material-symbols-rounded text-primary text-sm">add_box</i>
                    Create Database
                  </h6>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Database Name</label>
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
              <div class="card h-100 shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <h6 class="mb-0 d-flex align-items-center gap-2">
                    <i class="material-symbols-rounded text-success text-sm">person_add</i>
                    Create User
                  </h6>
                </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Username</label>
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
                    <label class="form-label text-xs text-uppercase font-weight-bold">Host Access</label>
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
              <div class="card h-100 shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <h6 class="mb-0 d-flex align-items-center gap-2">
                    <i class="material-symbols-rounded text-info text-sm">link</i>
                    Assign User to Database
                  </h6>
                </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Database</label>
                    <select class="form-control" v-model="assignment.database">
                      <option value="">Select database...</option>
                      <option v-for="db in databases" :key="db.name" :value="db.name">{{ db.name }}</option>
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label text-xs text-uppercase font-weight-bold">User</label>
                    <select class="form-control" v-model="assignment.username">
                      <option value="">Select user...</option>
                      <option v-for="user in users" :key="user.username + '@' + user.host" :value="user.username">
                        {{ user.username }}@{{ user.host }}
                      </option>
                    </select>
                  </div>
                  <button class="btn bg-gradient-info w-100 mt-3" @click="showAssignModal = true"
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
              <div class="card shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 font-weight-bold">MySQL Databases</h6>
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
                          <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
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
                            <div v-if="db.users && db.users.length > 0" class="d-flex flex-wrap gap-1">
                              <span v-for="user in db.users" :key="user.username" class="badge-db-user">
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
                              <button class="action-btn btn-view" @click="openNativeManager(db.name, 'mysql')" :disabled="openingManager[db.name]" title="Open Database Workspace">
                                <span v-if="openingManager[db.name]" class="spinner-border spinner-border-sm"></span>
                                <i v-else class="material-symbols-rounded">table_chart</i>
                              </button>
                              <button class="action-btn btn-backup" @click="quickBackupDatabase(db.name)" :disabled="backingUpDb[db.name]" title="Quick Backup Database">
                                <span v-if="backingUpDb[db.name]" class="spinner-border spinner-border-sm text-success"></span>
                                <i v-else class="material-symbols-rounded">archive</i>
                              </button>
                              <button class="action-btn btn-link-proj" @click="openLinkProjectModal(db)" title="Link Project / Domain">
                                <i class="material-symbols-rounded">link</i>
                              </button>
                              <button class="action-btn btn-edit" @click="manageDatabase(db)" title="Manage">
                                <i class="material-symbols-rounded">settings</i>
                              </button>
                              <button class="action-btn btn-delete" @click="confirmDeleteDb(db)" title="Delete">
                                <i class="material-symbols-rounded">delete</i>
                              </button>
                            </div>
                          </td>
                        </tr>
                        <tr v-if="filteredDatabases.length === 0">
                          <td colspan="5" class="text-center py-5 text-secondary">
                            <div class="empty-state">
                              <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">database</i>
                              <p class="mt-3">No MySQL databases found matching your search.</p>
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
        </template>
      </div>

      <!-- ========================================== -->
      <!-- SECTION B: POSTGRESQL MANAGEMENT           -->
      <!-- ========================================== -->
      <div v-else-if="activeEngine === 'postgres'">

        <!-- CASE B1: POSTGRES NOT INSTALLED -->
        <div v-if="!postgresStatus.installed" class="row">
          <div class="col-12">
            <div class="card shadow-lg border-0 overflow-hidden mb-4">
              <div class="card-body p-4 p-md-5">
                <div class="row align-items-center">
                  <div class="col-lg-7">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 bg-gradient-info text-white text-xs font-weight-bold rounded-pill mb-3">
                      <i class="material-symbols-rounded text-xs">deployed_code</i>
                      Enterprise Relational Engine
                    </div>
                    <h3 class="font-weight-bolder text-dark mb-2">Install PostgreSQL Server</h3>
                    <p class="text-secondary text-sm mb-4">
                      PostgreSQL is the world's most advanced open-source relational database. Perfect for complex queries, high concurrency, JSONB documents, and enterprise workloads.
                    </p>

                    <div class="row g-3 mb-4">
                      <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-light">
                          <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="material-symbols-rounded text-success">verified</i>
                            <span class="font-weight-bold text-xs text-dark">ACID & Fault-Tolerant</span>
                          </div>
                          <p class="text-xxs text-secondary mb-0">Write-Ahead Logging (WAL) and multi-version concurrency (MVCC).</p>
                        </div>
                      </div>
                      <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-light">
                          <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="material-symbols-rounded text-primary">data_object</i>
                            <span class="font-weight-bold text-xs text-dark">Native JSONB Support</span>
                          </div>
                          <p class="text-xxs text-secondary mb-0">High-performance indexed document storage alongside relational tables.</p>
                        </div>
                      </div>
                      <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-light">
                          <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="material-symbols-rounded text-info">table_chart</i>
                            <span class="font-weight-bold text-xs text-dark">Native Nimbus Workspace</span>
                          </div>
                          <p class="text-xxs text-secondary mb-0">Seamless 1-click table browsing, SQL query console, and ER diagram visualizer.</p>
                        </div>
                      </div>
                      <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-light">
                          <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="material-symbols-rounded text-warning">security</i>
                            <span class="font-weight-bold text-xs text-dark">Automated Provisioning</span>
                          </div>
                          <p class="text-xxs text-secondary mb-0">Configures <code>nimbus_admin</code> superuser, SCRAM auth, and installs <code>php-pgsql</code>.</p>
                        </div>
                      </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-3">
                      <button 
                        class="btn bg-gradient-primary btn-lg mb-0 px-4" 
                        @click="startPostgresInstall" 
                        :disabled="installingPostgres || installLogStatus === 'running'"
                      >
                        <span v-if="installingPostgres || installLogStatus === 'running'" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="material-symbols-rounded text-sm me-2">download</i>
                        {{ (installingPostgres || installLogStatus === 'running') ? 'Installing PostgreSQL...' : 'Install PostgreSQL 16' }}
                      </button>
                      <span class="text-xs text-secondary">
                        <i class="material-symbols-rounded text-xs me-1 align-middle">info</i>
                        Requires ~1-2 minutes to install packages and reload PHP.
                      </span>
                    </div>
                  </div>

                  <div class="col-lg-5 text-center d-none d-lg-block">
                    <div class="pg-hero-visual p-4 text-center">
                      <div class="pg-elephant-circle mx-auto mb-3">
                        <i class="material-symbols-rounded" style="font-size: 64px; color: #336791;">deployed_code</i>
                      </div>
                      <h5 class="font-weight-bold text-dark mb-1">PostgreSQL on Nimbus</h5>
                      <span class="badge bg-light text-dark border text-xxs">TCP Port 5432 &bull; Localhost</span>
                    </div>
                  </div>
                </div>

                <!-- Live Installation Logs Box -->
                <div v-if="installingPostgres || installLog || installLogStatus === 'running'" class="mt-4 pt-3 border-top">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="spinner-grow spinner-grow-sm text-primary" v-if="installLogStatus === 'running'"></span>
                      <i class="material-symbols-rounded text-success text-sm" v-else-if="installLogStatus === 'completed'">check_circle</i>
                      <i class="material-symbols-rounded text-danger text-sm" v-else-if="installLogStatus === 'error'">error</i>
                      <span class="text-xs font-weight-bold text-dark">Installation Terminal Log</span>
                      <span class="badge" :class="installLogStatus === 'running' ? 'bg-gradient-warning' : (installLogStatus === 'completed' ? 'bg-gradient-success' : 'bg-gradient-danger')">
                        {{ installLogStatus.toUpperCase() }}
                      </span>
                    </div>
                    <button class="btn btn-link text-xs p-0 text-secondary mb-0" @click="installLog = ''" v-if="installLogStatus !== 'running'">
                      Clear Log
                    </button>
                  </div>
                  <pre class="terminal-output bg-dark text-white p-3 rounded-3 text-xxs font-monospace mb-0" style="max-height: 260px; overflow-y: auto;">{{ installLog || 'Initializing package manager and downloading binaries...' }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- CASE B2: POSTGRES INSTALLED & ACTIVE -->
        <div v-else>
          <!-- PostgreSQL Status & Service Bar -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="card shadow-sm border-0">
                <div class="card-body p-3">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                      <div class="icon-box-pg">
                        <i class="material-symbols-rounded text-primary">deployed_code</i>
                      </div>
                      <div>
                        <div class="d-flex align-items-center gap-2">
                          <h6 class="mb-0 font-weight-bold">PostgreSQL Service</h6>
                          <span class="badge" :class="postgresStatus.active ? 'bg-gradient-success' : 'bg-gradient-danger'">
                            {{ postgresStatus.active ? 'Active (Running)' : 'Stopped' }}
                          </span>
                          <span class="badge bg-light text-dark border text-xxs font-weight-bold">
                            {{ postgresStatus.version ? 'v' + postgresStatus.version : 'Installed' }}
                          </span>
                        </div>
                        <div class="text-xxs text-secondary mt-1">
                          Host: <code>127.0.0.1</code> &bull; Port: <code>5432</code> &bull; Superuser: <code>nimbus_admin</code>
                        </div>
                      </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <button 
                        class="btn btn-sm btn-outline-success mb-0" 
                        v-if="!postgresStatus.active"
                        @click="postgresServiceControl('start')"
                        :disabled="controllingPgService"
                      >
                        <i class="material-symbols-rounded text-xs me-1">play_arrow</i> Start
                      </button>
                      <button 
                        class="btn btn-sm btn-outline-danger mb-0" 
                        v-if="postgresStatus.active"
                        @click="postgresServiceControl('stop')"
                        :disabled="controllingPgService"
                      >
                        <i class="material-symbols-rounded text-xs me-1">stop</i> Stop
                      </button>
                      <button 
                        class="btn btn-sm btn-outline-primary mb-0" 
                        @click="postgresServiceControl('restart')"
                        :disabled="controllingPgService"
                      >
                        <i class="material-symbols-rounded text-xs me-1" :class="{ 'spin-animation': controllingPgService }">restart_alt</i> Restart
                      </button>
                      <button 
                        class="btn btn-sm btn-outline-secondary mb-0" 
                        @click="postgresServiceControl('reload')"
                        :disabled="controllingPgService"
                      >
                        <i class="material-symbols-rounded text-xs me-1">refresh</i> Reload
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- PostgreSQL Create Forms Row -->
          <div class="row mb-4">
            <!-- Create PostgreSQL Database -->
            <div class="col-lg-4 mb-4">
              <div class="card h-100 shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <h6 class="mb-0 d-flex align-items-center gap-2">
                    <i class="material-symbols-rounded text-primary text-sm">add_box</i>
                    Create PostgreSQL Database
                  </h6>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Database Name</label>
                    <input type="text" class="form-control" v-model="newPostgresDb.name" placeholder="pg_database" pattern="[a-zA-Z][a-zA-Z0-9_]*">
                  </div>
                  <div class="mb-3">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Owner Role</label>
                    <select class="form-select form-control" v-model="newPostgresDb.owner">
                      <option value="nimbus_admin">nimbus_admin (Default Admin)</option>
                      <option v-for="user in postgresUsers" :key="user.username" :value="user.username">
                        {{ user.username }}
                      </option>
                    </select>
                  </div>
                  <button class="btn bg-gradient-primary w-100 mt-2" @click="createPostgresDatabase" :disabled="!newPostgresDb.name || creatingPgDb">
                    <span v-if="creatingPgDb" class="spinner-border spinner-border-sm me-1"></span>
                    Create Database
                  </button>
                </div>
              </div>
            </div>

            <!-- Create PostgreSQL Role / User -->
            <div class="col-lg-4 mb-4">
              <div class="card h-100 shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <h6 class="mb-0 d-flex align-items-center gap-2">
                    <i class="material-symbols-rounded text-success text-sm">person_add</i>
                    Create PostgreSQL Role
                  </h6>
                </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Role Name / Username</label>
                    <input type="text" class="form-control" v-model="newPgUser.username" placeholder="app_user">
                  </div>
                  <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <label class="form-label text-xs text-uppercase font-weight-bolder text-secondary mb-0">Password</label>
                      <button 
                        type="button" 
                        class="btn-auto-gen" 
                        @click="generateStrongPassword('create-pg')"
                        title="Generate strong password and copy to clipboard"
                      >
                        <i class="material-symbols-rounded text-xs me-1">auto_awesome</i>
                        Auto-Generate & Copy
                      </button>
                    </div>
                    <div class="password-box-unified">
                      <input 
                        :type="showCreatePgPassword ? 'text' : 'password'" 
                        v-model="newPgUser.password" 
                        placeholder="Enter or generate password"
                        class="font-monospace"
                      >
                      <div class="d-flex align-items-center gap-1">
                        <button 
                          type="button" 
                          class="icon-action-btn"
                          @click="showCreatePgPassword = !showCreatePgPassword"
                        >
                          <i class="material-symbols-rounded text-sm">{{ showCreatePgPassword ? 'visibility_off' : 'visibility' }}</i>
                        </button>
                        <button 
                          type="button" 
                          class="icon-action-btn"
                          :class="{ 'text-success': copiedField === 'create-pg' }"
                          @click="copyToClipboard(newPgUser.password, 'create-pg')"
                          :disabled="!newPgUser.password"
                        >
                          <i class="material-symbols-rounded text-sm">{{ copiedField === 'create-pg' ? 'check' : 'content_copy' }}</i>
                        </button>
                      </div>
                    </div>
                    <transition name="fade">
                      <span v-if="copiedField === 'create-pg'" class="text-xxs text-success font-weight-bold mt-1 d-inline-flex align-items-center">
                        <i class="material-symbols-rounded text-xs me-1">check_circle</i> Copied to clipboard!
                      </span>
                    </transition>
                  </div>
                  <div class="form-check form-check-inline mt-1 mb-2">
                    <input class="form-check-input" type="checkbox" id="pgCreateDbCheck" v-model="newPgUser.createdb">
                    <label class="form-check-label text-xs font-weight-bold" for="pgCreateDbCheck">Can Create DBs (CREATEDB)</label>
                  </div>
                  <button class="btn bg-gradient-success w-100 mt-2" @click="createPostgresUser" :disabled="!newPgUser.username || !newPgUser.password || creatingPgUser">
                    <span v-if="creatingPgUser" class="spinner-border spinner-border-sm me-1"></span>
                    Create Role
                  </button>
                </div>
              </div>
            </div>

            <!-- Assign Role to PostgreSQL Database -->
            <div class="col-lg-4 mb-4">
              <div class="card h-100 shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <h6 class="mb-0 d-flex align-items-center gap-2">
                    <i class="material-symbols-rounded text-info text-sm">link</i>
                    Grant Privileges
                  </h6>
                </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Database</label>
                    <select class="form-control" v-model="pgAssignment.database">
                      <option value="">Select database...</option>
                      <option v-for="db in postgresDatabases" :key="db.name" :value="db.name">{{ db.name }}</option>
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Role / User</label>
                    <select class="form-control" v-model="pgAssignment.username">
                      <option value="">Select role...</option>
                      <option v-for="user in postgresUsers" :key="user.username" :value="user.username">{{ user.username }}</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label text-xs text-uppercase font-weight-bold">Privilege Scope</label>
                    <select class="form-select form-control" v-model="pgAssignment.privileges">
                      <option value="ALL">ALL PRIVILEGES (Full Control)</option>
                      <option value="CONNECT">CONNECT ONLY</option>
                      <option value="READ_WRITE">READ & WRITE (SELECT, INSERT, UPDATE, DELETE)</option>
                      <option value="READ_ONLY">READ ONLY (SELECT)</option>
                    </select>
                  </div>
                  <button class="btn bg-gradient-info w-100" @click="assignPostgresUser" :disabled="!pgAssignment.database || !pgAssignment.username || assigningPgUser">
                    <span v-if="assigningPgUser" class="spinner-border spinner-border-sm me-1"></span>
                    Grant Privileges
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- PostgreSQL Databases Table -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="card shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 font-weight-bold">PostgreSQL Databases</h6>
                    <div class="d-flex align-items-center gap-3">
                      <div class="input-group input-group-sm" style="width: 250px;">
                        <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                        <input v-model="pgSearchQuery" type="text" class="form-control" placeholder="Search PostgreSQL DBs...">
                      </div>
                      <span class="badge bg-gradient-primary">{{ filteredPgDatabases.length }} databases</span>
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
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Owner</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Encoding</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Associated Projects</th>
                          <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="db in filteredPgDatabases" :key="db.name" class="domain-row">
                          <td>
                            <div class="d-flex align-items-center px-3 py-2">
                              <div class="icon-box-pg me-3">
                                <i class="material-symbols-rounded text-primary">deployed_code</i>
                              </div>
                              <div>
                                <h6 class="mb-0 text-sm font-weight-bold">{{ db.name }}</h6>
                                <div class="text-xxs text-secondary mt-1">
                                  <span>Created by: {{ db.created_by || db.owner || 'nimbus_admin' }}</span>
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
                            <span class="badge bg-light text-dark border text-xs">
                              <i class="material-symbols-rounded text-xxs me-1">account_circle</i>
                              {{ db.owner }}
                            </span>
                          </td>
                          <td>
                            <span class="text-xs text-secondary">{{ db.encoding || 'UTF8' }}</span>
                          </td>
                          <td>
                            <div v-if="db.projects && db.projects.length > 0" class="d-flex flex-wrap gap-1">
                              <span v-for="p in db.projects" :key="p.project" class="badge bg-light text-dark text-xs border py-1 px-2 rounded">
                                <i class="material-symbols-rounded text-xxs me-1 align-middle">folder</i>
                                {{ p.project }}
                              </span>
                            </div>
                            <span v-else class="text-xs text-secondary opacity-7">None</span>
                          </td>
                          <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                              <button class="action-btn btn-view" @click="openNativeManager(db.name, 'postgres')" :disabled="openingManager[db.name]" title="Open PostgreSQL Workspace">
                                <span v-if="openingManager[db.name]" class="spinner-border spinner-border-sm"></span>
                                <i v-else class="material-symbols-rounded">table_chart</i>
                              </button>
                              <button class="action-btn btn-link-proj" @click="openLinkProjectModal(db)" title="Link Project / Domain">
                                <i class="material-symbols-rounded">link</i>
                              </button>
                              <button class="action-btn btn-delete" @click="deletePostgresDatabase(db.name)" title="Drop Database">
                                <i class="material-symbols-rounded">delete</i>
                              </button>
                            </div>
                          </td>
                        </tr>
                        <tr v-if="filteredPgDatabases.length === 0">
                          <td colspan="6" class="text-center py-5 text-secondary">
                            <div class="empty-state">
                              <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">deployed_code</i>
                              <p class="mt-3">No PostgreSQL databases created yet.</p>
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

          <!-- PostgreSQL Roles / Users Table -->
          <div class="row">
            <div class="col-12">
              <div class="card shadow-sm border-0">
                <div class="card-header pb-0 bg-transparent">
                  <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 font-weight-bold">PostgreSQL Roles & Users</h6>
                    <span class="badge bg-gradient-info">{{ postgresUsers.length }} roles</span>
                  </div>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                  <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                      <thead>
                        <tr>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Role Name</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Role Attributes</th>
                          <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="user in postgresUsers" :key="user.username" class="domain-row">
                          <td>
                            <div class="d-flex align-items-center px-3 py-2">
                              <i class="material-symbols-rounded text-info me-2">person</i>
                              <span class="font-weight-bold text-sm">{{ user.username }}</span>
                            </div>
                          </td>
                          <td>
                            <div class="d-flex flex-wrap gap-1">
                              <span v-if="user.superuser" class="badge bg-gradient-warning text-xxs">SUPERUSER</span>
                              <span v-if="user.createdb" class="badge bg-gradient-success text-xxs">CREATEDB</span>
                              <span v-if="user.can_login" class="badge bg-light text-dark border text-xxs">CAN LOGIN</span>
                            </div>
                          </td>
                          <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                              <button class="action-btn" @click="changeUserPassword(user)" title="Change Password">
                                <i class="material-symbols-rounded text-warning">key</i>
                              </button>
                              <button 
                                class="action-btn btn-delete" 
                                v-if="user.username !== 'nimbus_admin' && user.username !== 'postgres'" 
                                @click="deletePostgresUser(user.username)" 
                                title="Delete Role"
                              >
                                <i class="material-symbols-rounded">delete</i>
                              </button>
                            </div>
                          </td>
                        </tr>
                        <tr v-if="postgresUsers.length === 0">
                          <td colspan="3" class="text-center py-4 text-secondary">
                            No custom roles created.
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- ========================================== -->
      <!-- MODALS                                     -->
      <!-- ========================================== -->

      <!-- Select Database Modal (for opening Workspace from header) -->
      <div class="modal-backdrop fade show" v-if="showWorkspaceModal" @click="showWorkspaceModal = false"></div>
      <div class="modal fade show d-block" v-if="showWorkspaceModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title d-flex align-items-center gap-2">
                <i class="material-symbols-rounded text-info">table_chart</i>
                Open {{ activeEngine === 'postgres' ? 'PostgreSQL' : 'MySQL' }} Workspace
              </h5>
              <button type="button" class="btn-close" @click="showWorkspaceModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm text-secondary mb-3">
                Select a database to launch the native Nimbus Database Workspace:
              </p>
              <div class="list-group">
                <button
                  v-for="db in (activeEngine === 'postgres' ? postgresDatabases : databases)"
                  :key="db.name"
                  type="button"
                  class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 border-radius-lg mb-2"
                  @click="openNativeManager(db.name, activeEngine); showWorkspaceModal = false"
                >
                  <div class="d-flex align-items-center">
                    <i class="material-symbols-rounded me-2" :class="activeEngine === 'postgres' ? 'text-primary' : 'text-info'">
                      {{ activeEngine === 'postgres' ? 'deployed_code' : 'database' }}
                    </i>
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

      <!-- Assign Permissions Modal (MySQL) -->
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

      <!-- Manage Database Modal (MySQL) -->
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
                          class="badge bg-secondary me-1">{{ priv }}</span>
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

      <!-- Change Password Modal (Supports both MySQL & PostgreSQL) -->
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

      <!-- Change Host Modal (MySQL) -->
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

      <!-- Delete Database Modal (MySQL) -->
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
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Link Project / Domain Modal -->
      <div class="modal-backdrop fade show" v-if="showLinkModal" @click="showLinkModal = false"></div>
      <div class="modal fade show d-block" v-if="showLinkModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title d-flex align-items-center">
                <i class="material-symbols-rounded text-info me-2">link</i>
                Link Project to {{ linkingDb?.name }}
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

    </div>
  </MainLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import axios from 'axios'

// Engine Selection: 'mysql' | 'postgres'
const activeEngine = ref('mysql')

const switchEngine = async (engine) => {
  activeEngine.value = engine
  if (engine === 'postgres') {
    await loadPostgresStatus()
    if (postgresStatus.value.installed) {
      await loadPostgresData()
    }
  } else {
    await loadData()
  }
}

const handleRefresh = async () => {
  if (activeEngine.value === 'postgres') {
    await loadPostgresStatus()
    if (postgresStatus.value.installed) {
      await loadPostgresData()
    }
  } else {
    await loadData()
  }
}

// -------------------------------------------------------------
// MySQL State
// -------------------------------------------------------------
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

const openingManager = ref({})
const backingUpDb = ref({})

const availablePrivileges = [
  'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
  'ALTER', 'INDEX', 'CREATE TEMPORARY TABLES', 'LOCK TABLES',
  'EXECUTE', 'CREATE VIEW', 'SHOW VIEW', 'CREATE ROUTINE',
  'ALTER ROUTINE', 'EVENT', 'TRIGGER', 'REFERENCES'
]

const alert = ref({ show: false, type: 'success', message: '' })

// -------------------------------------------------------------
// PostgreSQL State
// -------------------------------------------------------------
const postgresStatus = ref({
  installed: false,
  active: false,
  version: '',
  port: 5432,
  database_count: 0,
  user_count: 0,
  install_status: 'idle'
})
const postgresDatabases = ref([])
const postgresUsers = ref([])
const loadingPostgres = ref(false)
const pgSearchQuery = ref('')

const installingPostgres = ref(false)
const installLog = ref('')
const installLogStatus = ref('idle')
let installPollTimer = null

const controllingPgService = ref(false)

const newPostgresDb = ref({ name: '', owner: 'nimbus_admin' })
const creatingPgDb = ref(false)

const newPgUser = ref({ username: '', password: '', superuser: false, createdb: true })
const creatingPgUser = ref(false)
const showCreatePgPassword = ref(false)

const pgAssignment = ref({ database: '', username: '', privileges: 'ALL' })
const assigningPgUser = ref(false)

// -------------------------------------------------------------
// Lifecycle & Initial Load
// -------------------------------------------------------------
onMounted(async () => {
  loadData()
  loadPostgresStatus()
})

onUnmounted(() => {
  if (installPollTimer) clearInterval(installPollTimer)
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const getAlertIcon = (type) => {
  const icons = { success: 'check_circle', danger: 'error', warning: 'warning', info: 'info' }
  return icons[type] || 'info'
}

// -------------------------------------------------------------
// Password Helpers
// -------------------------------------------------------------
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
  } else if (target === 'create-pg') {
    newPgUser.value.password = password
    showCreatePgPassword.value = true
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

// -------------------------------------------------------------
// Workspace Handling (Native ManagerPage.vue)
// -------------------------------------------------------------
const openNativeManager = async (dbName, engine = null) => {
  const selectedEngine = engine || activeEngine.value
  try {
    openingManager.value[dbName] = true
    const url = selectedEngine === 'postgres' ? '/database/postgres/manager/token' : '/database/manager/token'
    const response = await axios.post(url, { database: dbName })
    if (response.data?.url) {
      window.open(response.data.url, '_blank')
    }
  } catch (err) {
    alert(err.response?.data?.error || 'Failed to generate database session token')
  } finally {
    openingManager.value[dbName] = false
  }
}

const handleOpenWorkspace = () => {
  const currentDbs = activeEngine.value === 'postgres' ? postgresDatabases.value : databases.value
  if (currentDbs.length === 0) {
    showAlert('info', `No ${activeEngine.value === 'postgres' ? 'PostgreSQL' : 'MySQL'} databases found. Please create one first.`)
    return
  }
  if (currentDbs.length === 1) {
    openNativeManager(currentDbs[0].name, activeEngine.value)
    return
  }
  showWorkspaceModal.value = true
}

// -------------------------------------------------------------
// MySQL Operations
// -------------------------------------------------------------
const filteredDatabases = computed(() => {
  if (!dbSearchQuery.value) return databases.value
  const q = dbSearchQuery.value.toLowerCase()
  return databases.value.filter(db => db.name.toLowerCase().includes(q))
})

const totalPages = computed(() => Math.ceil(filteredDatabases.value.length / itemsPerPage.value) || 1)
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
    showAlert('danger', error.response?.data?.error || 'Failed to load MySQL data')
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
    if (activeEngine.value === 'postgres') {
      await axios.post('/database/postgres/user/password', {
        username: editingUser.value.username,
        password: newPassword.value
      })
    } else {
      await axios.post('/database/user/password', {
        username: editingUser.value.username,
        host: editingUser.value.host,
        password: newPassword.value
      })
    }
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

const quickBackupDatabase = async (dbName) => {
  try {
    backingUpDb.value[dbName] = true
    const response = await axios.post('/backups', {
      database_name: dbName,
      type: 'database'
    })
    showAlert('success', response.data?.message || `Backup for "${dbName}" created successfully!`)
  } catch (err) {
    showAlert('danger', err.response?.data?.error || err.response?.data?.message || 'Failed to create database backup')
  } finally {
    backingUpDb.value[dbName] = false
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
    const url = activeEngine.value === 'postgres' ? '/database/postgres/assign-project' : '/database/assign-project'
    const res = await axios.post(url, {
      name: linkingDb.value.name,
      domain: selectedProjectDomain.value
    })
    showAlert('success', res.data.message || 'Project linked successfully')
    showLinkModal.value = false
    if (activeEngine.value === 'postgres') {
      await loadPostgresData()
    } else {
      await loadData()
    }
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to link project')
  } finally {
    assigningProject.value = false
  }
}

// -------------------------------------------------------------
// PostgreSQL Operations
// -------------------------------------------------------------
const filteredPgDatabases = computed(() => {
  if (!pgSearchQuery.value) return postgresDatabases.value
  const q = pgSearchQuery.value.toLowerCase()
  return postgresDatabases.value.filter(db => db.name.toLowerCase().includes(q))
})

const loadPostgresStatus = async () => {
  try {
    const res = await axios.get('/database/postgres/status')
    postgresStatus.value = res.data
    if (res.data.install_status === 'running') {
      startInstallPolling()
    }
  } catch (err) {
    console.error('Failed to load PostgreSQL status', err)
  }
}

const loadPostgresData = async () => {
  try {
    loadingPostgres.value = true
    const [dbRes, userRes] = await Promise.all([
      axios.get('/database/postgres/list'),
      axios.get('/database/postgres/users')
    ])
    postgresDatabases.value = dbRes.data.databases || []
    postgresUsers.value = userRes.data.users || []
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to load PostgreSQL data')
  } finally {
    loadingPostgres.value = false
  }
}

const startPostgresInstall = async () => {
  try {
    installingPostgres.value = true
    installLogStatus.value = 'running'
    installLog.value = 'Initiating PostgreSQL background installation...'
    const res = await axios.post('/database/postgres/install')
    showAlert('info', res.data.message || 'PostgreSQL installation initiated')
    startInstallPolling()
  } catch (err) {
    installingPostgres.value = false
    showAlert('danger', err.response?.data?.error || 'Failed to start installation')
  }
}

const startInstallPolling = () => {
  if (installPollTimer) clearInterval(installPollTimer)
  installingPostgres.value = true
  installPollTimer = setInterval(async () => {
    try {
      const res = await axios.get('/database/postgres/install-status')
      installLog.value = res.data.log || ''
      installLogStatus.value = res.data.status
      if (res.data.status === 'completed') {
        clearInterval(installPollTimer)
        installPollTimer = null
        installingPostgres.value = false
        showAlert('success', 'PostgreSQL installed and configured successfully!')
        await loadPostgresStatus()
        await loadPostgresData()
      } else if (res.data.status === 'error') {
        clearInterval(installPollTimer)
        installPollTimer = null
        installingPostgres.value = false
        showAlert('danger', 'PostgreSQL installation encountered an error. Check logs below.')
      }
    } catch (err) {
      // ignore transient polling errors
    }
  }, 2000)
}

const postgresServiceControl = async (action) => {
  try {
    controllingPgService.value = true
    const res = await axios.post('/database/postgres/service', { action })
    showAlert('success', res.data.message || `Service ${action} succeeded`)
    await loadPostgresStatus()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || `Service ${action} failed`)
  } finally {
    controllingPgService.value = false
  }
}

const createPostgresDatabase = async () => {
  try {
    creatingPgDb.value = true
    await axios.post('/database/postgres/create', newPostgresDb.value)
    showAlert('success', `PostgreSQL database '${newPostgresDb.value.name}' created`)
    newPostgresDb.value.name = ''
    await loadPostgresData()
    await loadPostgresStatus()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to create PostgreSQL database')
  } finally {
    creatingPgDb.value = false
  }
}

const deletePostgresDatabase = async (name) => {
  if (!confirm(`Are you sure you want to drop PostgreSQL database '${name}'? All data will be permanently deleted!`)) return
  try {
    await axios.post('/database/postgres/delete', { name })
    showAlert('success', `PostgreSQL database '${name}' dropped`)
    await loadPostgresData()
    await loadPostgresStatus()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to drop PostgreSQL database')
  }
}

const createPostgresUser = async () => {
  try {
    creatingPgUser.value = true
    await axios.post('/database/postgres/user/create', newPgUser.value)
    showAlert('success', `PostgreSQL role '${newPgUser.value.username}' created successfully`)
    newPgUser.value = { username: '', password: '', superuser: false, createdb: true }
    await loadPostgresData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to create PostgreSQL role')
  } finally {
    creatingPgUser.value = false
  }
}

const deletePostgresUser = async (username) => {
  if (!confirm(`Are you sure you want to delete role '${username}'?`)) return
  try {
    await axios.post('/database/postgres/user/delete', { username })
    showAlert('success', `Role '${username}' deleted`)
    await loadPostgresData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to delete role')
  }
}

const assignPostgresUser = async () => {
  try {
    assigningPgUser.value = true
    await axios.post('/database/postgres/user/assign', pgAssignment.value)
    showAlert('success', `Privileges granted to ${pgAssignment.value.username} on ${pgAssignment.value.database}`)
    pgAssignment.value = { database: '', username: '', privileges: 'ALL' }
    await loadPostgresData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to assign role privileges')
  } finally {
    assigningPgUser.value = false
  }
}
</script>

<style scoped>
.engine-switch-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.5rem 1rem;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.engine-switch-btn:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
  transform: translateY(-1px);
}

.engine-switch-btn.active {
  background: #ffffff;
  border-color: #6366f1;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
  color: #1e293b;
}

.engine-icon-pill {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.mysql-pill {
  background: #e0f2fe;
  color: #0284c7;
}

.pg-pill {
  background: #ede9fe;
  color: #7c3aed;
}

.badge-dot-live {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

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

.icon-box-pg {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ede9fe;
  box-shadow: 0 4px 6px -1px rgba(124, 58, 237, 0.15);
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

.pg-hero-visual {
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  border-radius: 1.5rem;
  border: 1px solid rgba(0,0,0,0.06);
}

.pg-elephant-circle {
  width: 110px;
  height: 110px;
  border-radius: 50%;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10px 25px -5px rgba(51, 103, 145, 0.25);
}
</style>
