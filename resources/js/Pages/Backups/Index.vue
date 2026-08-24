<template>
  <MainLayout>
    <Head title="Backups" />
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h4 class="font-weight-bolder mb-0">Backups & Disaster Recovery</h4>
              <p class="mb-0 text-sm">Automated scheduling, on-demand snapshots, and one-click restore for databases & files</p>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="refreshData">
                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                Refresh
              </button>
              <button class="btn bg-gradient-info mb-0" @click="openCreateScheduleModal">
                <i class="material-symbols-rounded text-sm me-1">alarm</i>
                Add Schedule
              </button>
              <button class="btn bg-gradient-primary mb-0" @click="openCreateBackupModal">
                <i class="material-symbols-rounded text-sm me-1">cloud_upload</i>
                Create Backup Now
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Flash / Alert Messages -->
      <div class="row" v-if="$page.props.flash?.success || $page.props.flash?.error || localAlert.show">
        <div class="col-12">
          <div v-if="$page.props.flash?.success" class="alert alert-success alert-dismissible fade show text-white" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">check_circle</i></span>
            <span class="alert-text">{{ $page.props.flash.success }}</span>
            <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div v-if="$page.props.flash?.error" class="alert alert-danger alert-dismissible fade show text-white" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">error</i></span>
            <span class="alert-text">{{ $page.props.flash.error }}</span>
            <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div v-if="localAlert.show" :class="`alert alert-${localAlert.type} alert-dismissible fade show text-white`" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">{{ localAlert.type === 'success' ? 'check_circle' : 'error' }}</i></span>
            <span class="alert-text">{{ localAlert.message }}</span>
            <button type="button" class="btn-close text-white" @click="localAlert.show = false">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Overview Cards -->
      <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Total Backups</p>
                  <h4 class="mb-0">{{ stats.total_backups }}</h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">inventory_2</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <p class="mb-0 text-xs text-secondary">Verified archives on disk</p>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Storage Used</p>
                  <h4 class="mb-0">{{ stats.total_size }}</h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-info shadow-info shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">hard_drive</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <p class="mb-0 text-xs text-secondary">Location: /var/backups/nimbus</p>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Active Schedules</p>
                  <h4 class="mb-0">{{ stats.active_schedules }}</h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-success shadow-success shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">schedule</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <p class="mb-0 text-xs text-secondary">Automated recurring tasks</p>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-sm-6">
          <div class="card">
            <div class="card-header p-2 ps-3">
              <div class="d-flex justify-content-between">
                <div>
                  <p class="text-sm mb-0 text-capitalize">Last Backup</p>
                  <h4 class="mb-0 text-sm font-weight-bold">{{ stats.last_backup_at }}</h4>
                </div>
                <div class="icon icon-md icon-shape bg-gradient-warning shadow-warning shadow text-center border-radius-lg">
                  <i class="material-symbols-rounded opacity-10">history</i>
                </div>
              </div>
            </div>
            <hr class="dark horizontal my-0">
            <div class="card-footer p-2 ps-3">
              <span v-if="stats.last_backup_status === 'completed'" class="badge badge-sm bg-gradient-success">Operational</span>
              <span v-else-if="stats.last_backup_status === 'failed'" class="badge badge-sm bg-gradient-danger">Check Errors</span>
              <span v-else class="text-xs text-secondary">No backups yet</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="row mb-3">
        <div class="col-12">
          <div class="nav-wrapper position-relative end-0">
            <ul class="nav nav-pills nav-fill p-1 bg-gray-100 border-radius-lg" role="tablist">
              <li class="nav-item">
                <a class="nav-link mb-0 px-0 py-1 font-weight-bold cursor-pointer"
                   :class="{ 'active bg-white text-dark shadow-sm': activeTab === 'backups', 'text-secondary': activeTab !== 'backups' }"
                   @click="activeTab = 'backups'">
                  <i class="material-symbols-rounded text-sm me-1">inventory_2</i>
                  Backups Archive ({{ backups.length }})
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link mb-0 px-0 py-1 font-weight-bold cursor-pointer"
                   :class="{ 'active bg-white text-dark shadow-sm': activeTab === 'schedules', 'text-secondary': activeTab !== 'schedules' }"
                   @click="activeTab = 'schedules'">
                  <i class="material-symbols-rounded text-sm me-1">alarm</i>
                  Automated Schedules ({{ schedules.length }})
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link mb-0 px-0 py-1 font-weight-bold cursor-pointer"
                   :class="{ 'active bg-white text-dark shadow-sm': activeTab === 'info', 'text-secondary': activeTab !== 'info' }"
                   @click="activeTab = 'info'">
                  <i class="material-symbols-rounded text-sm me-1">notifications</i>
                  Email Alerts & Policies
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- TAB 1: BACKUPS ARCHIVE -->
      <div v-if="activeTab === 'backups'" class="row">
        <div class="col-12">
          <div class="card my-4">
            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
              <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="text-white text-capitalize mb-0">Backup Snapshots</h6>
                <div class="d-flex gap-2 align-items-center">
                  <div class="input-group input-group-sm input-group-outline bg-white rounded" style="width: 220px;">
                    <input type="text" v-model="searchQuery" class="form-control form-control-sm px-2" placeholder="Search target or file...">
                  </div>
                  <select v-model="filterType" class="form-select form-select-sm bg-white border-0" style="width: 140px;">
                    <option value="all">All Types</option>
                    <option value="full">Full (Both)</option>
                    <option value="database">Database</option>
                    <option value="files">Project Files</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="card-body px-0 pb-2">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Target / Domain</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Type</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Size</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Created</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="filteredBackups.length === 0">
                      <td colspan="6" class="text-center py-5">
                        <i class="material-symbols-rounded text-secondary mb-2" style="font-size: 3rem;">inventory_2</i>
                        <h6 class="text-secondary font-weight-normal mb-1">No backups found</h6>
                        <p class="text-xs text-muted mb-3">Create your first on-demand backup or schedule automated snapshots.</p>
                        <button class="btn btn-sm bg-gradient-primary" @click="openCreateBackupModal">
                          <i class="material-symbols-rounded text-sm me-1">cloud_upload</i> Create Backup
                        </button>
                      </td>
                    </tr>
                    <tr v-for="backup in filteredBackups" :key="backup.id">
                      <td>
                        <div class="d-flex px-3 py-1">
                          <div class="avatar avatar-sm me-3 border-radius-md d-flex align-items-center justify-content-center"
                               :class="getTargetBadgeBg(backup.type)">
                            <i class="material-symbols-rounded text-white text-sm">{{ getTargetIcon(backup.type) }}</i>
                          </div>
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm font-weight-bold">{{ backup.domain || backup.database_name || 'System' }}</h6>
                            <p class="text-xs text-secondary mb-0 font-monospace" style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" :title="backup.file_name">
                              {{ backup.file_name }}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="badge badge-sm" :class="getTypeBadgeClass(backup.type)">
                          {{ formatTypeLabel(backup.type) }}
                        </span>
                        <div v-if="backup.metadata?.included_database && backup.type === 'full'" class="text-xxs text-muted mt-1">
                          <i class="material-symbols-rounded align-middle" style="font-size: 10px;">database</i> {{ backup.metadata.included_database }}
                        </div>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span class="text-secondary font-weight-bold text-xs">{{ backup.formatted_size }}</span>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span v-if="backup.status === 'completed'" class="badge badge-sm bg-gradient-success">
                          <i class="material-symbols-rounded align-middle me-1" style="font-size: 12px;">check</i> Completed
                        </span>
                        <span v-else-if="backup.status === 'in_progress'" class="badge badge-sm bg-gradient-warning">
                          <span class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span> Running
                        </span>
                        <span v-else-if="backup.status === 'failed'" class="badge badge-sm bg-gradient-danger cursor-pointer" :title="backup.error_message">
                          <i class="material-symbols-rounded align-middle me-1" style="font-size: 12px;">error</i> Failed
                        </span>
                        <span v-else class="badge badge-sm bg-gradient-secondary">{{ backup.status }}</span>
                      </td>
                      <td class="align-middle text-center">
                        <span class="text-secondary text-xs font-weight-bold">{{ backup.created_at }}</span>
                        <p class="text-xxs text-muted mb-0">by {{ backup.created_by }}</p>
                      </td>
                      <td class="align-middle text-center">
                        <div class="d-flex justify-content-center gap-1">
                          <!-- Restore Button -->
                          <button class="btn btn-link text-info px-2 mb-0"
                                  :disabled="backup.status !== 'completed' || isRestoring"
                                  title="Restore Backup"
                                  @click="openRestoreModal(backup)">
                            <i class="material-symbols-rounded text-sm">restore</i>
                          </button>

                          <!-- Download Button -->
                          <a :href="`/backups/${backup.id}/download`"
                             class="btn btn-link text-primary px-2 mb-0"
                             :class="{ 'disabled opacity-5': backup.status !== 'completed' }"
                             title="Download Archive">
                            <i class="material-symbols-rounded text-sm">download</i>
                          </a>

                          <!-- Delete Button -->
                          <button class="btn btn-link text-danger px-2 mb-0"
                                  title="Delete Backup"
                                  @click="confirmDeleteBackup(backup)">
                            <i class="material-symbols-rounded text-sm">delete</i>
                          </button>
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

      <!-- TAB 2: AUTOMATED SCHEDULES -->
      <div v-if="activeTab === 'schedules'" class="row">
        <div class="col-12">
          <div class="card my-4">
            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
              <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                  <h6 class="text-white text-capitalize mb-0">Recurring Backup Schedules</h6>
                  <p class="text-white text-xs opacity-8 mb-0">Cron-driven background backup automation with retention policies</p>
                </div>
                <button class="btn btn-sm bg-white text-dark mb-0 font-weight-bold" @click="openCreateScheduleModal">
                  <i class="material-symbols-rounded text-sm me-1">add</i> New Schedule
                </button>
              </div>
            </div>
            <div class="card-body px-0 pb-2">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Schedule Name</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Target</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Frequency</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Retention</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Next Run</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="schedules.length === 0">
                      <td colspan="7" class="text-center py-5">
                        <i class="material-symbols-rounded text-secondary mb-2" style="font-size: 3rem;">alarm_off</i>
                        <h6 class="text-secondary font-weight-normal mb-1">No automated schedules configured</h6>
                        <p class="text-xs text-muted mb-3">Set up hourly, daily, weekly, or monthly auto-backups with retention management.</p>
                        <button class="btn btn-sm bg-gradient-info" @click="openCreateScheduleModal">
                          <i class="material-symbols-rounded text-sm me-1">alarm</i> Configure First Schedule
                        </button>
                      </td>
                    </tr>
                    <tr v-for="schedule in schedules" :key="schedule.id">
                      <td>
                        <div class="d-flex px-3 py-1">
                          <div class="avatar avatar-sm me-3 border-radius-md bg-gradient-dark d-flex align-items-center justify-content-center">
                            <i class="material-symbols-rounded text-white text-sm">schedule</i>
                          </div>
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm font-weight-bold">{{ schedule.name }}</h6>
                            <p class="text-xs text-secondary mb-0">
                              {{ schedule.records_count }} backups created
                            </p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex flex-column">
                          <span class="text-sm font-weight-bold text-dark">{{ schedule.domain || schedule.database_name || 'All Server Sites' }}</span>
                          <span class="badge badge-sm d-inline-block text-start px-0" :class="getTypeBadgeClass(schedule.type)" style="width: fit-content;">
                            {{ formatTypeLabel(schedule.type) }}
                          </span>
                        </div>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span class="badge bg-light text-dark font-weight-bold text-xs text-capitalize">
                          {{ schedule.frequency }} @ {{ schedule.time }}
                        </span>
                        <p v-if="schedule.frequency === 'weekly'" class="text-xxs text-muted mb-0">
                          Day: {{ getDayName(schedule.day_of_week) }}
                        </p>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span class="text-xs font-weight-bold text-secondary">Keep last {{ schedule.retention_count }}</span>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span v-if="schedule.is_active" class="text-xs font-weight-bold text-info">
                          {{ schedule.next_run_at || 'Pending calculation' }}
                        </span>
                        <span v-else class="text-xs text-muted">Paused</span>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <div class="form-check form-switch d-flex justify-content-center">
                          <input class="form-check-input cursor-pointer" type="checkbox" :checked="schedule.is_active" @change="toggleScheduleActive(schedule)">
                        </div>
                      </td>
                      <td class="align-middle text-center">
                        <div class="d-flex justify-content-center gap-1">
                          <button class="btn btn-link text-success px-2 mb-0"
                                  title="Run Schedule Now"
                                  @click="runScheduleNow(schedule)">
                            <i class="material-symbols-rounded text-sm">play_arrow</i>
                          </button>
                          <button class="btn btn-link text-secondary px-2 mb-0"
                                  title="Edit Schedule"
                                  @click="openEditScheduleModal(schedule)">
                            <i class="material-symbols-rounded text-sm">edit</i>
                          </button>
                          <button class="btn btn-link text-danger px-2 mb-0"
                                  title="Delete Schedule"
                                  @click="confirmDeleteSchedule(schedule)">
                            <i class="material-symbols-rounded text-sm">delete</i>
                          </button>
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

      <!-- TAB 3: EMAIL ALERTS & POLICIES -->
      <div v-if="activeTab === 'info'" class="row">
        <div class="col-lg-6 col-12 mb-4">
          <div class="card h-100">
            <div class="card-header pb-0 p-3">
              <h6 class="mb-0 d-flex align-items-center">
                <i class="material-symbols-rounded text-primary me-2">notifications</i>
                Automated Email Notifications
              </h6>
            </div>
            <div class="card-body p-3">
              <p class="text-sm text-secondary">
                Nimbus sends instant alerts for backup lifecycle events directly to your configured administrator alert emails.
              </p>
              <div class="list-group">
                <div class="list-group-item border-0 d-flex p-3 mb-2 bg-gray-100 border-radius-lg">
                  <div class="d-flex flex-column">
                    <h6 class="mb-1 text-sm font-weight-bold text-success">
                      <i class="material-symbols-rounded align-middle me-1">check_circle</i> Success Notifications
                    </h6>
                    <span class="text-xs text-secondary">Delivers backup summary, file archive size, and checksum hash when a backup completes successfully.</span>
                  </div>
                </div>
                <div class="list-group-item border-0 d-flex p-3 mb-2 bg-gray-100 border-radius-lg">
                  <div class="d-flex flex-column">
                    <h6 class="mb-1 text-sm font-weight-bold text-danger">
                      <i class="material-symbols-rounded align-middle me-1">error</i> Failure Alerts (High Priority)
                    </h6>
                    <span class="text-xs text-secondary">Dispatched immediately if mysqldump, tar archiving, or storage operations encounter an error or run out of disk space.</span>
                  </div>
                </div>
                <div class="list-group-item border-0 d-flex p-3 bg-gray-100 border-radius-lg">
                  <div class="d-flex flex-column">
                    <h6 class="mb-1 text-sm font-weight-bold text-info">
                      <i class="material-symbols-rounded align-middle me-1">restore</i> Restoration Confirmation
                    </h6>
                    <span class="text-xs text-secondary">Records and confirms completed restoration operations with timestamp and affected target.</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6 col-12 mb-4">
          <div class="card h-100">
            <div class="card-header pb-0 p-3">
              <h6 class="mb-0 d-flex align-items-center">
                <i class="material-symbols-rounded text-info me-2">auto_delete</i>
                Retention & Auto-Purge Mechanics
              </h6>
            </div>
            <div class="card-body p-3">
              <p class="text-sm text-secondary">
                To prevent backups from filling your server's disk space, Nimbus automatically enforces rolling retention windows per schedule.
              </p>
              <ul class="list-unstyled text-sm text-secondary ps-2">
                <li class="mb-2">
                  <i class="material-symbols-rounded text-success text-xs me-2">verified</i>
                  <strong>Per-Target Retention:</strong> Keeps the latest <em>N</em> copies (default: 7) for each target domain or database.
                </li>
                <li class="mb-2">
                  <i class="material-symbols-rounded text-success text-xs me-2">verified</i>
                  <strong>Zero-Downtime Dumps:</strong> MySQL dumps use <code>--single-transaction --quick</code> to avoid locking database tables during backups.
                </li>
                <li class="mb-2">
                  <i class="material-symbols-rounded text-success text-xs me-2">verified</i>
                  <strong>Exclusion Rules:</strong> Node modules, git history, and temporary cache folders are omitted to keep archives compact.
                </li>
              </ul>
              <div class="alert alert-light border text-xs text-muted mt-3 mb-0">
                <i class="material-symbols-rounded text-sm align-middle me-1">info</i>
                Local backups are stored with restricted <code>0700</code> file permissions under <code>/var/backups/nimbus/</code>.
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- MODAL: CREATE BACKUP NOW -->
    <div class="modal fade" id="modalCreateBackup" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bolder">
              <i class="material-symbols-rounded align-middle me-1 text-primary">cloud_upload</i> Create On-Demand Backup
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form @submit.prevent="submitCreateBackup">
            <div class="modal-body">
              
              <!-- Scope Type Selection -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-sm">Target Scope</label>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm flex-grow-1"
                          :class="backupForm.scope === 'domain' ? 'bg-gradient-primary text-white' : 'btn-outline-secondary'"
                          @click="backupForm.scope = 'domain'">
                    <i class="material-symbols-rounded text-xs me-1">language</i> Project / Website
                  </button>
                  <button type="button" class="btn btn-sm flex-grow-1"
                          :class="backupForm.scope === 'database' ? 'bg-gradient-primary text-white' : 'btn-outline-secondary'"
                          @click="backupForm.scope = 'database'">
                    <i class="material-symbols-rounded text-xs me-1">database</i> Standalone Database
                  </button>
                </div>
              </div>

              <!-- Domain Select -->
              <div class="mb-3" v-if="backupForm.scope === 'domain'">
                <label class="form-label font-weight-bold text-sm">Select Website / Domain</label>
                <select v-model="backupForm.domain" class="form-select" required @change="onDomainSelected">
                  <option value="" disabled>-- Select a domain --</option>
                  <option v-for="dom in domains" :key="dom.domain" :value="dom.domain">
                    {{ dom.domain }} {{ dom.associated_db ? `(DB: ${dom.associated_db})` : '' }}
                  </option>
                </select>
              </div>

              <!-- Database Select -->
              <div class="mb-3" v-if="backupForm.scope === 'database'">
                <label class="form-label font-weight-bold text-sm">Select MySQL Database</label>
                <select v-model="backupForm.database_name" class="form-select" required>
                  <option value="" disabled>-- Select a database --</option>
                  <option v-for="db in databases" :key="db" :value="db">{{ db }}</option>
                </select>
              </div>

              <!-- Backup Mode / Type Selection -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-sm">Backup Type</label>
                <div class="row g-2">
                  <div class="col-4" v-if="backupForm.scope === 'domain'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100"
                         :class="{ 'border-primary shadow-sm bg-gray-100': backupForm.type === 'full' }"
                         @click="backupForm.type = 'full'">
                      <i class="material-symbols-rounded text-primary mb-1">inventory_2</i>
                      <span class="text-xs font-weight-bold">Full (Both)</span>
                      <span class="text-xxs text-muted">DB + Files</span>
                    </div>
                  </div>
                  <div :class="backupForm.scope === 'domain' ? 'col-4' : 'col-6'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100"
                         :class="{ 'border-primary shadow-sm bg-gray-100': backupForm.type === 'database' }"
                         @click="backupForm.type = 'database'">
                      <i class="material-symbols-rounded text-info mb-1">database</i>
                      <span class="text-xs font-weight-bold">Database Only</span>
                      <span class="text-xxs text-muted">SQL dump</span>
                    </div>
                  </div>
                  <div class="col-4" v-if="backupForm.scope === 'domain'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100"
                         :class="{ 'border-primary shadow-sm bg-gray-100': backupForm.type === 'files' }"
                         @click="backupForm.type = 'files'">
                      <i class="material-symbols-rounded text-secondary mb-1">folder_zip</i>
                      <span class="text-xs font-weight-bold">Files Only</span>
                      <span class="text-xxs text-muted">Web root</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Optional Backup Label -->
              <div class="mb-3">
                <label class="form-label text-sm">Custom Note / Label (Optional)</label>
                <input type="text" v-model="backupForm.name" class="form-control px-2 border" placeholder="e.g. Before plugin update">
              </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn bg-gradient-primary" :disabled="isCreatingBackup">
                <span v-if="isCreatingBackup" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">cloud_upload</i>
                {{ isCreatingBackup ? 'Generating Backup...' : 'Start Backup' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: CREATE / EDIT SCHEDULE -->
    <div class="modal fade" id="modalSchedule" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bolder">
              <i class="material-symbols-rounded align-middle me-1 text-info">alarm</i>
              {{ scheduleForm.id ? 'Edit Automated Schedule' : 'Create Automated Schedule' }}
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form @submit.prevent="submitScheduleForm">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label font-weight-bold text-sm">Schedule Name</label>
                <input type="text" v-model="scheduleForm.name" class="form-control px-2 border" required placeholder="e.g. Daily Website Backup">
              </div>

              <!-- Scope Selection -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-sm">Target</label>
                <select v-model="scheduleForm.targetType" class="form-select mb-2" @change="onScheduleTargetTypeChange">
                  <option value="domain">Project / Website Domain</option>
                  <option value="database">MySQL Database</option>
                </select>

                <select v-if="scheduleForm.targetType === 'domain'" v-model="scheduleForm.domain" class="form-select" required>
                  <option value="" disabled>-- Select a domain --</option>
                  <option v-for="dom in domains" :key="dom.domain" :value="dom.domain">{{ dom.domain }}</option>
                </select>

                <select v-if="scheduleForm.targetType === 'database'" v-model="scheduleForm.database_name" class="form-select" required>
                  <option value="" disabled>-- Select a database --</option>
                  <option v-for="db in databases" :key="db" :value="db">{{ db }}</option>
                </select>
              </div>

              <!-- Type -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-sm">Backup Content</label>
                <select v-model="scheduleForm.type" class="form-select" required>
                  <option value="full" v-if="scheduleForm.targetType === 'domain'">Full Backup (Database + Project Files)</option>
                  <option value="database">Database Dump Only</option>
                  <option value="files" v-if="scheduleForm.targetType === 'domain'">Project Files Only</option>
                </select>
              </div>

              <!-- Frequency & Time -->
              <div class="row mb-3">
                <div class="col-6">
                  <label class="form-label font-weight-bold text-sm">Frequency</label>
                  <select v-model="scheduleForm.frequency" class="form-select" required>
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label font-weight-bold text-sm">Run Time (24h)</label>
                  <input type="time" v-model="scheduleForm.time" class="form-control px-2 border" required>
                </div>
              </div>

              <!-- Day of week (Weekly) -->
              <div class="mb-3" v-if="scheduleForm.frequency === 'weekly'">
                <label class="form-label font-weight-bold text-sm">Day of Week</label>
                <select v-model="scheduleForm.day_of_week" class="form-select">
                  <option :value="0">Sunday</option>
                  <option :value="1">Monday</option>
                  <option :value="2">Tuesday</option>
                  <option :value="3">Wednesday</option>
                  <option :value="4">Thursday</option>
                  <option :value="5">Friday</option>
                  <option :value="6">Saturday</option>
                </select>
              </div>

              <!-- Retention count -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-sm">Retention Window (Keep last N copies)</label>
                <input type="number" v-model="scheduleForm.retention_count" class="form-control px-2 border" min="1" max="100" required>
                <span class="text-xxs text-muted">Older backups exceeding this count will be automatically pruned.</span>
              </div>

              <!-- Email notification toggle -->
              <div class="form-check form-switch ps-0 mb-2">
                <input class="form-check-input ms-0 me-2" type="checkbox" id="emailNotif" v-model="scheduleForm.email_notifications">
                <label class="form-check-label text-sm" for="emailNotif">Send email alerts on completion / failures</label>
              </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn bg-gradient-info" :disabled="isSavingSchedule">
                <span v-if="isSavingSchedule" class="spinner-border spinner-border-sm me-1"></span>
                {{ scheduleForm.id ? 'Save Changes' : 'Create Schedule' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: RESTORE CONFIRMATION -->
    <div class="modal fade" id="modalRestore" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" v-if="selectedRestoreBackup">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bolder text-danger">
              <i class="material-symbols-rounded align-middle me-1">warning</i> Restore Backup
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning text-white text-xs mb-3">
              <strong>Caution:</strong> Restoring this backup will overwrite the current live files or database tables for <strong>{{ selectedRestoreBackup.domain || selectedRestoreBackup.database_name }}</strong>.
            </div>

            <div class="bg-gray-100 p-3 rounded mb-3 text-sm">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Target:</span>
                <span class="font-weight-bold">{{ selectedRestoreBackup.domain || selectedRestoreBackup.database_name }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Type:</span>
                <span class="font-weight-bold">{{ formatTypeLabel(selectedRestoreBackup.type) }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Archive Size:</span>
                <span class="font-weight-bold">{{ selectedRestoreBackup.formatted_size }}</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-secondary">Snapshot Date:</span>
                <span class="font-weight-bold">{{ selectedRestoreBackup.created_at }}</span>
              </div>
            </div>

            <div class="form-check ps-0">
              <input class="form-check-input ms-0 me-2" type="checkbox" id="snapBeforeRestore" v-model="createSnapshotBeforeRestore">
              <label class="form-check-label text-sm" for="snapBeforeRestore">
                Create a safety snapshot of current live state before restoring
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn bg-gradient-danger" :disabled="isRestoring" @click="performRestore">
              <span v-if="isRestoring" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="material-symbols-rounded text-sm me-1">restore</i>
              {{ isRestoring ? 'Restoring Snapshot...' : 'Yes, Restore Now' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'

const props = defineProps({
  backups: { type: Array, default: () => [] },
  schedules: { type: Array, default: () => [] },
  domains: { type: Array, default: () => [] },
  databases: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
})

const activeTab = ref('backups')
const searchQuery = ref('')
const filterType = ref('all')

const localAlert = ref({ show: false, message: '', type: 'success' })

const isCreatingBackup = ref(false)
const isSavingSchedule = ref(false)
const isRestoring = ref(false)

const selectedRestoreBackup = ref(null)
const createSnapshotBeforeRestore = ref(true)

// Forms
const backupForm = ref({
  scope: 'domain',
  domain: '',
  database_name: '',
  type: 'full',
  name: '',
  retention_count: 7,
})

const scheduleForm = ref({
  id: null,
  name: '',
  targetType: 'domain',
  domain: '',
  database_name: '',
  type: 'full',
  frequency: 'daily',
  time: '02:00',
  day_of_week: 0,
  day_of_month: 1,
  retention_count: 7,
  email_notifications: true,
})

// Initialize defaults
onMounted(() => {
  if (props.domains && props.domains.length > 0) {
    backupForm.value.domain = props.domains[0].domain
    scheduleForm.value.domain = props.domains[0].domain
  }
  if (props.databases && props.databases.length > 0) {
    backupForm.value.database_name = props.databases[0]
    scheduleForm.value.database_name = props.databases[0]
  }
})

// Filtered backups list
const filteredBackups = computed(() => {
  return props.backups.filter(b => {
    const matchesType = filterType.value === 'all' || b.type === filterType.value
    const target = (b.domain || b.database_name || '').toLowerCase()
    const fileName = (b.file_name || '').toLowerCase()
    const query = searchQuery.value.toLowerCase()
    const matchesSearch = !query || target.includes(query) || fileName.includes(query)
    return matchesType && matchesSearch
  })
})

const onDomainSelected = () => {
  // Domain selection handled
}

const onScheduleTargetTypeChange = () => {
  if (scheduleForm.value.targetType === 'database') {
    scheduleForm.value.type = 'database'
  } else {
    scheduleForm.value.type = 'full'
  }
}

const refreshData = () => {
  router.reload({ preserveScroll: true })
}

// Modal Triggers
let modalBackupInst = null
let modalScheduleInst = null
let modalRestoreInst = null

const getModalInstance = (id) => {
  const el = document.getElementById(id)
  if (el && window.bootstrap) {
    return new window.bootstrap.Modal(el)
  }
  return null
}

const openCreateBackupModal = () => {
  modalBackupInst = getModalInstance('modalCreateBackup')
  modalBackupInst?.show()
}

const openCreateScheduleModal = () => {
  scheduleForm.value = {
    id: null,
    name: 'Daily Backup',
    targetType: props.domains.length > 0 ? 'domain' : 'database',
    domain: props.domains[0]?.domain || '',
    database_name: props.databases[0] || '',
    type: 'full',
    frequency: 'daily',
    time: '02:00',
    day_of_week: 0,
    day_of_month: 1,
    retention_count: 7,
    email_notifications: true,
  }
  modalScheduleInst = getModalInstance('modalSchedule')
  modalScheduleInst?.show()
}

const openEditScheduleModal = (schedule) => {
  scheduleForm.value = {
    id: schedule.id,
    name: schedule.name,
    targetType: schedule.domain ? 'domain' : 'database',
    domain: schedule.domain || '',
    database_name: schedule.database_name || '',
    type: schedule.type,
    frequency: schedule.frequency,
    time: schedule.time,
    day_of_week: schedule.day_of_week || 0,
    day_of_month: schedule.day_of_month || 1,
    retention_count: schedule.retention_count || 7,
    email_notifications: schedule.email_notifications,
  }
  modalScheduleInst = getModalInstance('modalSchedule')
  modalScheduleInst?.show()
}

const openRestoreModal = (backup) => {
  selectedRestoreBackup.value = backup
  createSnapshotBeforeRestore.value = true
  modalRestoreInst = getModalInstance('modalRestore')
  modalRestoreInst?.show()
}

// Submit Create Backup
const submitCreateBackup = () => {
  isCreatingBackup.value = true
  const payload = {
    domain: backupForm.value.scope === 'domain' ? backupForm.value.domain : null,
    database_name: backupForm.value.scope === 'database' ? backupForm.value.database_name : null,
    type: backupForm.value.type,
    name: backupForm.value.name,
    retention_count: backupForm.value.retention_count,
  }

  router.post('/backups', payload, {
    onSuccess: () => {
      modalBackupInst?.hide()
      isCreatingBackup.value = false
    },
    onError: (errors) => {
      isCreatingBackup.value = false
    },
    onFinish: () => {
      isCreatingBackup.value = false
    }
  })
}

// Submit Schedule Form
const submitScheduleForm = () => {
  isSavingSchedule.value = true
  const payload = {
    name: scheduleForm.value.name,
    domain: scheduleForm.value.targetType === 'domain' ? scheduleForm.value.domain : null,
    database_name: scheduleForm.value.targetType === 'database' ? scheduleForm.value.database_name : null,
    type: scheduleForm.value.type,
    frequency: scheduleForm.value.frequency,
    time: scheduleForm.value.time,
    day_of_week: scheduleForm.value.day_of_week,
    day_of_month: scheduleForm.value.day_of_month,
    retention_count: scheduleForm.value.retention_count,
    email_notifications: scheduleForm.value.email_notifications,
  }

  if (scheduleForm.value.id) {
    router.put(`/backups/schedules/${scheduleForm.value.id}`, payload, {
      onSuccess: () => {
        modalScheduleInst?.hide()
        isSavingSchedule.value = false
      },
      onFinish: () => { isSavingSchedule.value = false }
    })
  } else {
    router.post('/backups/schedules', payload, {
      onSuccess: () => {
        modalScheduleInst?.hide()
        isSavingSchedule.value = false
      },
      onFinish: () => { isSavingSchedule.value = false }
    })
  }
}

// Toggle Schedule Active
const toggleScheduleActive = (schedule) => {
  router.post(`/backups/schedules/${schedule.id}/toggle`, {}, { preserveScroll: true })
}

// Run Schedule Now
const runScheduleNow = (schedule) => {
  if (confirm(`Trigger execution for '${schedule.name}' immediately?`)) {
    router.post(`/backups/schedules/${schedule.id}/run`, {}, { preserveScroll: true })
  }
}

// Delete Schedule
const confirmDeleteSchedule = (schedule) => {
  if (confirm(`Are you sure you want to delete schedule '${schedule.name}'?`)) {
    router.delete(`/backups/schedules/${schedule.id}`, { preserveScroll: true })
  }
}

// Delete Backup
const confirmDeleteBackup = (backup) => {
  if (confirm(`Are you sure you want to permanently delete backup '${backup.file_name}'?`)) {
    router.delete(`/backups/${backup.id}`, { preserveScroll: true })
  }
}

// Perform Restore
const performRestore = () => {
  if (!selectedRestoreBackup.value) return
  isRestoring.value = true

  router.post(`/backups/${selectedRestoreBackup.value.id}/restore`, {
    create_snapshot_before_restore: createSnapshotBeforeRestore.value
  }, {
    onSuccess: () => {
      modalRestoreInst?.hide()
      isRestoring.value = false
    },
    onError: () => {
      isRestoring.value = false
    },
    onFinish: () => {
      isRestoring.value = false
    }
  })
}

// UI Formatting Helpers
const getTargetBadgeBg = (type) => {
  if (type === 'database') return 'bg-gradient-info'
  if (type === 'files') return 'bg-gradient-secondary'
  return 'bg-gradient-primary'
}

const getTargetIcon = (type) => {
  if (type === 'database') return 'database'
  if (type === 'files') return 'folder_zip'
  return 'inventory_2'
}

const getTypeBadgeClass = (type) => {
  if (type === 'database') return 'bg-gradient-info text-white'
  if (type === 'files') return 'bg-gradient-secondary text-white'
  return 'bg-gradient-primary text-white'
}

const formatTypeLabel = (type) => {
  if (type === 'database') return 'Database Only'
  if (type === 'files') return 'Files Only'
  return 'Full Site (Both)'
}

const getDayName = (day) => {
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
  return days[day] || 'Sunday'
}
</script>

<style scoped>
.avatar {
  width: 36px;
  height: 36px;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
