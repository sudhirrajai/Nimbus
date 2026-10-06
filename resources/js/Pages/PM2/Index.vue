<template>
  <MainLayout>
    <Head title="PM2 Process Manager" />
    <div class="container-fluid py-2">
      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card bg-gradient-dark">
            <div class="card-body p-3">
              <div class="row align-items-center">
                <div class="col-12 col-md-8 mb-2 mb-md-0">
                  <div class="numbers">
                    <p class="text-white text-sm mb-0 text-uppercase font-weight-bold opacity-7">
                      Node.js Runtime & Process Manager
                    </p>
                    <h5 class="text-white font-weight-bolder mb-0 d-flex align-items-center gap-2">
                      PM2 Process Manager
                      <span v-if="status.pm2?.version" class="badge bg-white text-dark text-xxs font-monospace">
                        v{{ status.pm2.version }}
                      </span>
                    </h5>
                  </div>
                </div>
                <div class="col-12 col-md-4 text-md-end text-start">
                  <div class="icon icon-shape bg-white shadow text-center rounded-circle d-inline-flex align-items-center justify-content-center">
                    <i class="material-symbols-rounded text-dark text-lg opacity-10">rocket_launch</i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Toast Alert Notification -->
      <div 
        v-if="toast.show" 
        class="position-fixed top-3 end-3 z-index-3" 
        style="z-index: 9999; max-width: 400px;"
      >
        <div 
          class="alert alert-dismissible text-white fade show shadow-lg border-0 d-flex align-items-center gap-2 mb-0" 
          :class="toast.type === 'danger' ? 'bg-gradient-danger' : (toast.type === 'warning' ? 'bg-gradient-warning' : 'bg-gradient-success')"
          role="alert"
        >
          <i class="material-symbols-rounded text-md">
            {{ toast.type === 'danger' ? 'error' : (toast.type === 'warning' ? 'warning' : 'check_circle') }}
          </i>
          <span class="text-sm flex-grow-1">{{ toast.message }}</span>
          <button type="button" class="btn-close text-lg py-3 opacity-10" @click="toast.show = false">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading && !processes.length" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="text-sm text-secondary mt-2">Checking PM2 daemon & processes...</p>
      </div>

      <!-- Uninstalled PM2 Warning Banner -->
      <div v-else-if="!status.pm2?.installed" class="row mb-4">
        <div class="col-12">
          <div class="card bg-gradient-warning text-white border-0 shadow">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center">
                  <div class="avatar avatar-lg bg-white text-warning rounded-circle me-3 flex-shrink-0 d-flex align-items-center justify-content-center shadow-sm">
                    <i class="material-symbols-rounded text-lg">warning</i>
                  </div>
                  <div>
                    <h5 class="text-white mb-1">PM2 is Not Installed</h5>
                    <p class="text-white text-sm mb-0 opacity-9">
                      PM2 is required to manage Node.js, Next.js, and Express applications with daemon auto-restart and cluster load balancing.
                      <span v-if="status.node?.installed" class="badge bg-dark ms-2 text-white">Node.js {{ status.node.version }} Detected</span>
                      <span v-else class="badge bg-danger ms-2 text-white">Node.js Missing</span>
                    </p>
                  </div>
                </div>
                <div>
                  <button 
                    class="btn btn-white text-dark mb-0 font-weight-bold" 
                    @click="installPm2" 
                    :disabled="installing || !status.node?.installed"
                  >
                    <span v-if="installing" class="spinner-border spinner-border-sm me-2"></span>
                    <i v-else class="material-symbols-rounded text-sm me-1">download</i>
                    {{ installing ? 'Installing PM2...' : '1-Click Install PM2 Globally' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Dashboard View -->
      <template v-else>
        <!-- Metrics Row -->
        <div class="row mb-4">
          <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
            <div class="card h-100 shadow-sm border">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <p class="text-xs text-uppercase font-weight-bold text-secondary mb-0">Total Apps</p>
                    <h4 class="font-weight-bolder mb-0 text-dark">{{ stats.total || 0 }}</h4>
                    <span class="text-xxs text-secondary">Processes managed</span>
                  </div>
                  <div class="icon-shape bg-gradient-dark text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="material-symbols-rounded">apps</i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
            <div class="card h-100 shadow-sm border">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <p class="text-xs text-uppercase font-weight-bold text-secondary mb-0">Online / Running</p>
                    <h4 class="font-weight-bolder mb-0 text-success">{{ stats.online || 0 }}</h4>
                    <span class="text-xxs text-secondary">Healthy daemon status</span>
                  </div>
                  <div class="icon-shape bg-gradient-success text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="material-symbols-rounded">check_circle</i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
            <div class="card h-100 shadow-sm border">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <p class="text-xs text-uppercase font-weight-bold text-secondary mb-0">Node.js Memory</p>
                    <h4 class="font-weight-bolder mb-0 text-dark">{{ stats.total_memory_mb || 0 }} <span class="text-sm font-weight-normal text-secondary">MB</span></h4>
                    <span class="text-xxs text-secondary">Total RSS RAM</span>
                  </div>
                  <div class="icon-shape bg-gradient-info text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="material-symbols-rounded">memory</i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-sm-6 mb-xl-0 mb-3">
            <div class="card h-100 shadow-sm border">
              <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                  <div>
                    <p class="text-xs text-uppercase font-weight-bold text-secondary mb-0">Node.js CPU</p>
                    <h4 class="font-weight-bolder mb-0 text-dark">{{ stats.total_cpu || 0 }} <span class="text-sm font-weight-normal text-secondary">%</span></h4>
                    <span class="text-xxs text-secondary">Combined CPU load</span>
                  </div>
                  <div class="icon-shape bg-gradient-primary text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="material-symbols-rounded">speed</i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Card: Process List -->
        <div class="card shadow-sm border mb-4">
          <div class="card-header pb-0 pt-3 bg-transparent border-bottom">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-3">
              <div>
                <h6 class="font-weight-bolder text-dark mb-0">Managed Applications</h6>
                <p class="text-xs text-secondary mb-0">Live PM2 processes running on the server daemon</p>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="width: 220px;">
                  <span class="input-group-text"><i class="material-symbols-rounded text-xs">search</i></span>
                  <input type="text" class="form-control" placeholder="Search app..." v-model="searchQuery">
                </div>
                <button class="btn btn-sm btn-outline-dark mb-0 d-flex align-items-center gap-1" @click="loadProcesses" :disabled="loading">
                  <i class="material-symbols-rounded text-sm" :class="{ 'spin': loading }">refresh</i>
                  Refresh
                </button>
                <button class="btn btn-sm btn-outline-primary mb-0 d-flex align-items-center gap-1" @click="reloadAll" :disabled="actionRunning || !processes.length">
                  <i class="material-symbols-rounded text-sm">sync</i>
                  Zero-Downtime Reload All
                </button>
                <button class="btn btn-sm bg-gradient-dark mb-0 text-white d-flex align-items-center gap-1" @click="openCreateModal">
                  <i class="material-symbols-rounded text-sm">add</i>
                  New App
                </button>
              </div>
            </div>
          </div>

          <div class="card-body px-0 pt-0 pb-2">
            <div class="table-responsive p-0">
              <table class="table align-items-center mb-0">
                <thead>
                  <tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-3">App / ID</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Mode & Target</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">CPU</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Memory</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Uptime / Restarts</th>
                    <th class="text-end text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 pe-4">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="filteredProcesses.length === 0">
                    <td colspan="7" class="text-center py-5">
                      <div class="d-flex flex-column align-items-center justify-content-center">
                        <i class="material-symbols-rounded text-secondary mb-2" style="font-size: 40px;">terminal</i>
                        <h6 class="text-secondary font-weight-normal mb-1">No PM2 processes running</h6>
                        <p class="text-xs text-secondary mb-3">Launch a new Node.js / Next.js application or deploy via Git Deployments with <code>nimbus.yaml</code>.</p>
                        <button class="btn btn-sm bg-gradient-dark text-white mb-0" @click="openCreateModal">
                          <i class="material-symbols-rounded text-sm me-1">add</i> Launch First App
                        </button>
                      </div>
                    </td>
                  </tr>

                  <tr v-for="proc in filteredProcesses" :key="proc.id" class="border-bottom">
                    <!-- Name & ID -->
                    <td class="ps-3 py-3">
                      <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm bg-light text-dark rounded-circle me-3 d-flex align-items-center justify-content-center font-weight-bold font-monospace text-xs border">
                          {{ proc.id }}
                        </div>
                        <div>
                          <h6 class="mb-0 text-sm font-weight-bold text-dark">{{ proc.name }}</h6>
                          <span class="text-xxs text-secondary font-monospace">PID: {{ proc.pid || '—' }}</span>
                        </div>
                      </div>
                    </td>

                    <!-- Mode & Script -->
                    <td>
                      <div class="d-flex flex-column">
                        <div class="d-flex align-items-center gap-1 mb-1">
                          <span class="badge badge-sm" :class="proc.exec_mode === 'cluster' ? 'bg-gradient-info' : 'bg-gradient-secondary'">
                            {{ proc.exec_mode }}
                          </span>
                          <span v-if="proc.instances > 1" class="badge badge-sm bg-light text-dark border">
                            {{ proc.instances }} workers
                          </span>
                        </div>
                        <span class="text-xxs text-secondary text-truncate font-monospace" style="max-width: 200px;" :title="proc.full_script">
                          {{ proc.script }}
                        </span>
                      </div>
                    </td>

                    <!-- Status -->
                    <td>
                      <span class="badge badge-sm text-uppercase" :class="statusBadgeClass(proc.status)">
                        <span class="status-dot me-1" :class="statusDotClass(proc.status)"></span>
                        {{ proc.status }}
                      </span>
                    </td>

                    <!-- CPU -->
                    <td>
                      <div class="d-flex align-items-center">
                        <span class="text-xs font-weight-bold text-dark me-2">{{ proc.cpu }}%</span>
                        <div class="progress progress-xs w-50" style="height: 4px;">
                          <div 
                            class="progress-bar" 
                            :class="proc.cpu > 70 ? 'bg-danger' : (proc.cpu > 30 ? 'bg-warning' : 'bg-primary')" 
                            :style="{ width: Math.min(proc.cpu, 100) + '%' }"
                          ></div>
                        </div>
                      </div>
                    </td>

                    <!-- Memory -->
                    <td>
                      <span class="text-xs font-weight-bold text-dark">{{ proc.memory_mb }} MB</span>
                    </td>

                    <!-- Uptime / Restarts -->
                    <td>
                      <div class="d-flex flex-column">
                        <span class="text-xs font-weight-normal text-dark">{{ proc.uptime_human }}</span>
                        <span class="text-xxs" :class="proc.restarts > 5 ? 'text-danger font-weight-bold' : 'text-secondary'">
                          {{ proc.restarts }} restarts
                          <span v-if="proc.unstable_restarts > 0" class="badge bg-danger text-white text-xxs ms-1">
                            {{ proc.unstable_restarts }} unstable
                          </span>
                        </span>
                      </div>
                    </td>

                    <!-- Actions -->
                    <td class="text-end pe-4">
                      <div class="btn-group btn-group-sm" role="group">
                        <!-- Restart -->
                        <button 
                          class="btn btn-outline-dark btn-sm mb-0 px-2" 
                          @click="restartProc(proc.id, proc.name)" 
                          :disabled="actionRunning"
                          title="Restart Process"
                        >
                          <i class="material-symbols-rounded text-xs">restart_alt</i>
                        </button>

                        <!-- Start / Stop -->
                        <button 
                          v-if="proc.status === 'online'" 
                          class="btn btn-outline-warning btn-sm mb-0 px-2" 
                          @click="stopProc(proc.id, proc.name)" 
                          :disabled="actionRunning"
                          title="Stop Process"
                        >
                          <i class="material-symbols-rounded text-xs">stop</i>
                        </button>
                        <button 
                          v-else 
                          class="btn btn-outline-success btn-sm mb-0 px-2" 
                          @click="startProc(proc.id, proc.name)" 
                          :disabled="actionRunning"
                          title="Start Process"
                        >
                          <i class="material-symbols-rounded text-xs">play_arrow</i>
                        </button>

                        <!-- View Logs -->
                        <button 
                          class="btn btn-outline-info btn-sm mb-0 px-2" 
                          @click="viewLogs(proc)" 
                          title="View Process Logs"
                        >
                          <i class="material-symbols-rounded text-xs">subject</i>
                        </button>

                        <!-- Delete -->
                        <button 
                          class="btn btn-outline-danger btn-sm mb-0 px-2" 
                          @click="confirmDelete(proc)" 
                          :disabled="actionRunning"
                          title="Delete Process"
                        >
                          <i class="material-symbols-rounded text-xs">delete</i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>

      <!-- Delete Confirmation Modal -->
      <Teleport to="body">
        <div class="modal fade" :class="{ show: showDeleteModal }" :style="{ display: showDeleteModal ? 'block' : 'none' }" tabindex="-1" v-if="showDeleteModal">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
              <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center">
                  <div style="width:42px;height:42px;border-radius:0.75rem;display:flex;align-items:center;justify-content:center" class="bg-gradient-danger text-white">
                    <i class="material-symbols-rounded">delete_forever</i>
                  </div>
                  <div class="ms-3">
                    <h5 class="mb-0 text-dark">Delete PM2 Process</h5>
                    <p class="text-sm text-secondary mb-0">{{ procToDelete?.name }} (ID: {{ procToDelete?.id }})</p>
                  </div>
                </div>
                <button type="button" class="btn-close" @click="showDeleteModal = false"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-danger text-white mb-0 py-2 border-0">
                  <small><strong>Warning:</strong> This immediately stops the process and unregisters it from the PM2 daemon. This action cannot be undone.</small>
                </div>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary btn-sm mb-0" @click="showDeleteModal = false">Cancel</button>
                <button type="button" class="btn bg-gradient-danger btn-sm mb-0 text-white" @click="executeDelete" :disabled="actionRunning">
                  <span v-if="actionRunning" class="spinner-border spinner-border-sm me-1"></span>
                  Confirm Delete
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-if="showDeleteModal" class="modal-backdrop fade show" @click="showDeleteModal = false"></div>
      </Teleport>

      <!-- Create / Launch Modal -->
      <div class="modal-backdrop fade show" v-if="showCreateModal" @click="showCreateModal = false"></div>
      <div class="modal fade show d-block" v-if="showCreateModal">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center gap-2">
                <div class="icon-shape icon-sm bg-gradient-dark text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:34px;height:34px;">
                  <i class="material-symbols-rounded text-sm">rocket_launch</i>
                </div>
                <div>
                  <h5 class="modal-title font-weight-bolder text-dark mb-0">Launch Node.js Application</h5>
                  <p class="text-xs text-secondary mb-0">PM2 will daemonize and manage the application process</p>
                </div>
              </div>
              <button type="button" class="btn-close" @click="showCreateModal = false"></button>
            </div>

            <form @submit.prevent="submitCreate">
              <div class="modal-body py-3">
                <div class="row g-3">
                  <!-- App Name -->
                  <div class="col-md-6">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Process Name <span class="text-danger">*</span></label>
                    <input 
                      type="text" 
                      class="form-control form-control-sm" 
                      placeholder="e.g. my-node-api" 
                      v-model="createForm.name" 
                      required
                    >
                    <span class="text-xxs text-secondary">Alphanumeric, dashes, underscores</span>
                  </div>

                  <!-- Port -->
                  <div class="col-md-6">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Port (Optional)</label>
                    <input 
                      type="number" 
                      class="form-control form-control-sm" 
                      placeholder="e.g. 3000" 
                      v-model="createForm.port"
                    >
                    <span class="text-xxs text-secondary">Injected as PORT environment variable</span>
                  </div>

                  <!-- Directory (CWD) -->
                  <div class="col-12">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Working Directory (CWD) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                      <input 
                        type="text" 
                        class="form-control" 
                        placeholder="/var/www/example.com" 
                        v-model="createForm.cwd" 
                        required
                      >
                      <button 
                        v-if="domains.length" 
                        class="btn btn-outline-secondary dropdown-toggle mb-0" 
                        type="button" 
                        data-bs-toggle="dropdown"
                      >
                        Choose Domain
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="max-height: 200px; overflow-y: auto;">
                        <li v-for="d in domains" :key="d">
                          <a class="dropdown-item text-xs" href="javascript:;" @click="createForm.cwd = '/var/www/' + d">
                            /var/www/{{ d }}
                          </a>
                        </li>
                      </ul>
                    </div>
                  </div>

                  <!-- Entry Script -->
                  <div class="col-md-8">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Entry Script or Command <span class="text-danger">*</span></label>
                    <input 
                      type="text" 
                      class="form-control form-control-sm" 
                      placeholder="e.g. server.js or dist/index.js or npm -- start" 
                      v-model="createForm.script" 
                      required
                    >
                  </div>

                  <!-- Exec Mode -->
                  <div class="col-md-4">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Execution Mode</label>
                    <select class="form-select form-select-sm" v-model="createForm.exec_mode">
                      <option value="fork">Fork Mode</option>
                      <option value="cluster">Cluster Mode (Load Balanced)</option>
                    </select>
                  </div>

                  <!-- Instances -->
                  <div class="col-md-6">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Instances / Workers</label>
                    <select class="form-select form-select-sm" v-model="createForm.instances">
                      <option value="1">1 Instance</option>
                      <option value="2">2 Instances</option>
                      <option value="4">4 Instances</option>
                      <option value="max">Max (All Available CPU Cores)</option>
                    </select>
                  </div>

                  <!-- Max Memory Restart -->
                  <div class="col-md-6">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Max Memory Auto-Restart</label>
                    <input 
                      type="text" 
                      class="form-control form-control-sm" 
                      placeholder="e.g. 500M or 1G" 
                      v-model="createForm.max_memory_restart"
                    >
                  </div>

                  <!-- Script Args -->
                  <div class="col-12">
                    <label class="form-label text-xs font-weight-bold text-dark mb-1">Script Arguments (Optional)</label>
                    <input 
                      type="text" 
                      class="form-control form-control-sm" 
                      placeholder="e.g. --production --workers=2" 
                      v-model="createForm.args"
                    >
                  </div>
                </div>
              </div>

              <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm mb-0" @click="showCreateModal = false">
                  Cancel
                </button>
                <button type="submit" class="btn bg-gradient-dark btn-sm mb-0 text-white" :disabled="actionRunning">
                  <span v-if="actionRunning" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="material-symbols-rounded text-sm me-1">rocket_launch</i>
                  Launch Process
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Logs Modal -->
      <div class="modal-backdrop fade show" v-if="showLogsModal" @click="closeLogsModal"></div>
      <div class="modal fade show d-block" v-if="showLogsModal">
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content border-0 shadow-2xl bg-dark text-light">
            <div class="modal-header border-secondary pb-2 pt-3">
              <div class="d-flex align-items-center gap-2">
                <div class="icon-shape icon-sm bg-gradient-info text-white rounded-circle shadow text-center d-flex align-items-center justify-content-center" style="width:34px;height:34px;">
                  <i class="material-symbols-rounded text-sm">terminal</i>
                </div>
                <div>
                  <h6 class="modal-title font-weight-bolder text-white mb-0">Logs: {{ activeProc?.name }}</h6>
                  <p class="text-xxs text-secondary mb-0">Live PM2 output stream</p>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group">
                  <button 
                    class="btn btn-xs mb-0" 
                    :class="logType === 'all' ? 'btn-light' : 'btn-outline-light'" 
                    @click="setLogType('all')"
                  >
                    All
                  </button>
                  <button 
                    class="btn btn-xs mb-0" 
                    :class="logType === 'out' ? 'btn-light' : 'btn-outline-light'" 
                    @click="setLogType('out')"
                  >
                    Stdout
                  </button>
                  <button 
                    class="btn btn-xs mb-0" 
                    :class="logType === 'err' ? 'btn-danger' : 'btn-outline-danger'" 
                    @click="setLogType('err')"
                  >
                    Stderr
                  </button>
                </div>
                <button 
                  class="btn btn-xs btn-outline-secondary mb-0" 
                  @click="refreshLogs"
                  title="Refresh Logs"
                >
                  <i class="material-symbols-rounded text-xs">refresh</i>
                </button>
                <button type="button" class="btn-close btn-close-white" @click="closeLogsModal"></button>
              </div>
            </div>

            <div class="modal-body p-3">
              <div v-if="logsLoading" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                <span class="ms-2 text-xs text-secondary">Fetching logs...</span>
              </div>
              <pre 
                v-else 
                class="bg-black text-light p-3 rounded font-monospace text-xs mb-0" 
                style="max-height: 520px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; line-height: 1.45;"
              ><code>{{ currentLogsText || 'No logs recorded yet.' }}</code></pre>
            </div>

            <div class="modal-footer border-secondary pt-2 pb-2">
              <span class="text-xxs text-secondary me-auto">Auto-polling every 4 seconds</span>
              <button type="button" class="btn btn-outline-light btn-sm mb-0" @click="closeLogsModal">
                Close
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
import { Head } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const loading = ref(true)
const installing = ref(false)
const actionRunning = ref(false)
const searchQuery = ref('')

const toast = ref({
  show: false,
  message: '',
  type: 'success'
})

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

const status = ref({
  node: { installed: false, version: null },
  npm: { installed: false, version: null },
  pm2: { installed: false, version: null }
})

const processes = ref([])
const stats = ref({ total: 0, online: 0, total_memory_mb: 0, total_cpu: 0 })
const domains = ref([])

// Delete Modal
const showDeleteModal = ref(false)
const procToDelete = ref(null)

// Create Modal
const showCreateModal = ref(false)
const createForm = ref({
  name: '',
  cwd: '/var/www/',
  script: 'server.js',
  exec_mode: 'fork',
  instances: '1',
  port: '',
  max_memory_restart: '500M',
  args: ''
})

// Logs Modal
const showLogsModal = ref(false)
const logsLoading = ref(false)
const activeProc = ref(null)
const logType = ref('all')
const logsData = ref({ out: '', err: '', combined: '' })
let logTimer = null

const filteredProcesses = computed(() => {
  if (!searchQuery.value) return processes.value
  const q = searchQuery.value.toLowerCase()
  return processes.value.filter(p => 
    p.name.toLowerCase().includes(q) || 
    String(p.id).includes(q) ||
    String(p.pid).includes(q) ||
    (p.script && p.script.toLowerCase().includes(q))
  )
})

const currentLogsText = computed(() => {
  if (logType.value === 'out') return logsData.value.out
  if (logType.value === 'err') return logsData.value.err
  return logsData.value.combined || logsData.value.out
})

const loadStatus = async () => {
  try {
    const res = await axios.get('/pm2/status')
    status.value = res.data
  } catch (err) {
    console.error('Error fetching PM2 status:', err)
  }
}

const loadProcesses = async () => {
  loading.value = true
  try {
    const res = await axios.get('/pm2/processes')
    if (res.data.installed === false) {
      status.value.pm2.installed = false
    } else {
      processes.value = res.data.processes || []
      stats.value = res.data.stats || { total: 0, online: 0, total_memory_mb: 0, total_cpu: 0 }
      domains.value = res.data.domains || []
    }
  } catch (err) {
    console.error('Error loading processes:', err)
  } finally {
    loading.value = false
  }
}

const installPm2 = async () => {
  installing.value = true
  try {
    const res = await axios.post('/pm2/install')
    showToast(res.data.message || 'PM2 installed globally and configured with systemd.', 'success')
    await loadStatus()
    await loadProcesses()
  } catch (err) {
    showToast(err.response?.data?.error || err.response?.data?.details || 'Failed to install PM2', 'danger')
  } finally {
    installing.value = false
  }
}

const startProc = async (id, name) => {
  actionRunning.value = true
  try {
    await axios.post('/pm2/start', { id })
    showToast(`Started application ${name}`, 'success')
    await loadProcesses()
  } catch (err) {
    showToast(err.response?.data?.error || 'Failed to start', 'danger')
  } finally {
    actionRunning.value = false
  }
}

const stopProc = async (id, name) => {
  actionRunning.value = true
  try {
    await axios.post('/pm2/stop', { id })
    showToast(`Stopped application ${name}`, 'warning')
    await loadProcesses()
  } catch (err) {
    showToast(err.response?.data?.error || 'Failed to stop', 'danger')
  } finally {
    actionRunning.value = false
  }
}

const restartProc = async (id, name) => {
  actionRunning.value = true
  try {
    await axios.post('/pm2/restart', { id })
    showToast(`Restarted application ${name}`, 'success')
    await loadProcesses()
  } catch (err) {
    showToast(err.response?.data?.error || 'Failed to restart', 'danger')
  } finally {
    actionRunning.value = false
  }
}

const reloadAll = async () => {
  if (!confirm('Perform zero-downtime rolling reload on all managed PM2 processes?')) return

  actionRunning.value = true
  try {
    const res = await axios.post('/pm2/reload-all')
    showToast(res.data.message || 'Zero-downtime reload initiated for all processes', 'success')
    await loadProcesses()
  } catch (err) {
    showToast(err.response?.data?.error || 'Failed to reload', 'danger')
  } finally {
    actionRunning.value = false
  }
}

const confirmDelete = (proc) => {
  procToDelete.value = proc
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (!procToDelete.value) return
  actionRunning.value = true
  try {
    await axios.post('/pm2/delete', { id: procToDelete.value.id })
    showToast(`Process ${procToDelete.value.name} deleted from PM2`, 'success')
    showDeleteModal.value = false
    procToDelete.value = null
    await loadProcesses()
  } catch (err) {
    showToast(err.response?.data?.error || 'Failed to delete', 'danger')
  } finally {
    actionRunning.value = false
  }
}

const openCreateModal = () => {
  createForm.value = {
    name: '',
    cwd: domains.value.length ? `/var/www/${domains.value[0]}` : '/var/www/',
    script: 'server.js',
    exec_mode: 'fork',
    instances: '1',
    port: '',
    max_memory_restart: '500M',
    args: ''
  }
  showCreateModal.value = true
}

const submitCreate = async () => {
  actionRunning.value = true
  try {
    const res = await axios.post('/pm2/create', createForm.value)
    showCreateModal.value = false
    showToast(res.data.message || 'Process launched successfully in PM2', 'success')
    await loadProcesses()
  } catch (err) {
    showToast(err.response?.data?.error || err.response?.data?.details || 'Failed to launch process', 'danger')
  } finally {
    actionRunning.value = false
  }
}

// Logs
const viewLogs = async (proc) => {
  activeProc.value = proc
  showLogsModal.value = true
  await fetchLogs()
  startLogPolling()
}

const setLogType = (type) => {
  logType.value = type
}

const fetchLogs = async () => {
  if (!activeProc.value) return
  try {
    const res = await axios.get('/pm2/logs', {
      params: { id: activeProc.value.id, lines: 100 }
    })
    logsData.value = res.data
  } catch (err) {
    console.error('Failed to fetch logs:', err)
  }
}

const refreshLogs = async () => {
  logsLoading.value = true
  await fetchLogs()
  logsLoading.value = false
}

const startLogPolling = () => {
  stopLogPolling()
  logTimer = setInterval(() => {
    fetchLogs()
  }, 4000)
}

const stopLogPolling = () => {
  if (logTimer) {
    clearInterval(logTimer)
    logTimer = null
  }
}

const closeLogsModal = () => {
  stopLogPolling()
  showLogsModal.value = false
  activeProc.value = null
}

// Helpers
const statusBadgeClass = (s) => {
  if (s === 'online') return 'bg-gradient-success text-white'
  if (s === 'stopping' || s === 'stopped') return 'bg-gradient-secondary text-white'
  if (s === 'errored') return 'bg-gradient-danger text-white'
  return 'bg-gradient-warning text-white'
}

const statusDotClass = (s) => {
  if (s === 'online') return 'bg-white'
  return 'bg-white opacity-5'
}

onMounted(async () => {
  await loadStatus()
  await loadProcesses()
})

onUnmounted(() => {
  stopLogPolling()
})
</script>

<style scoped>
.spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.status-dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
}
</style>
