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
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="refreshData" :disabled="isPolling">
                <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin-icon': isPolling }">refresh</i>
                Refresh
              </button>
              <button class="btn btn-outline-dark mb-0" @click="activeTab = 'destinations'">
                <i class="material-symbols-rounded text-sm me-1">cloud_sync</i>
                Storage Providers ({{ destinations.length }})
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
          <div v-if="$page.props.flash?.success" class="alert bg-gradient-success alert-dismissible fade show text-white shadow-success" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">check_circle</i></span>
            <span class="alert-text ms-2 font-weight-bold">{{ $page.props.flash.success }}</span>
            <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div v-if="$page.props.flash?.error" class="alert bg-gradient-danger alert-dismissible fade show text-white shadow-danger" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">error</i></span>
            <span class="alert-text ms-2 font-weight-bold">{{ $page.props.flash.error }}</span>
            <button type="button" class="btn-close text-white" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div v-if="localAlert.show" :class="localAlert.type === 'success' ? 'alert bg-gradient-success text-white shadow-success' : 'alert bg-gradient-danger text-white shadow-danger'" class="alert-dismissible fade show" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">{{ localAlert.type === 'success' ? 'check_circle' : 'error' }}</i></span>
            <span class="alert-text ms-2 font-weight-bold">{{ localAlert.message }}</span>
            <button type="button" class="btn-close text-white" @click="localAlert.show = false">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Live In-Progress Active Backup Banner -->
      <div class="row mb-4" v-if="activeRunningBackup">
        <div class="col-12">
          <div class="card text-white shadow-dark border-radius-lg overflow-hidden position-relative p-3"
               style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: 1px solid rgba(56, 189, 248, 0.4);">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div class="d-flex align-items-center gap-3">
                <div class="spinner-border text-info" style="width: 2.2rem; height: 2.2rem;" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
                <div>
                  <h6 class="text-white mb-0 font-weight-bold d-flex align-items-center">
                    <i class="material-symbols-rounded text-info me-2">cloud_sync</i>
                    Database & Files Backup in progress: {{ activeRunningBackup.domain || activeRunningBackup.database_name || 'System Target' }}
                  </h6>
                  <p class="text-xs text-white opacity-8 mb-0 mt-1">
                    Exporting {{ formatTypeLabel(activeRunningBackup.type) }} dump and streaming gzip archive... Auto-refreshing status.
                  </p>
                </div>
              </div>
              <span class="badge bg-gradient-info text-white font-weight-bold px-3 py-2">
                <span class="spinner-grow spinner-grow-sm me-1" style="width: 8px; height: 8px;"></span> Live Processing
              </span>
            </div>
            <div class="progress mt-3" style="height: 6px; background-color: rgba(255,255,255,0.1);">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-gradient-info w-100"></div>
            </div>
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
              <li class="nav-item">
                <a class="nav-link mb-0 px-0 py-1 font-weight-bold cursor-pointer"
                   :class="{ 'active bg-white text-dark shadow-sm': activeTab === 'destinations', 'text-secondary': activeTab !== 'destinations' }"
                   @click="activeTab = 'destinations'">
                  <i class="material-symbols-rounded text-sm me-1">cloud_sync</i>
                  Storage Providers & Destinations ({{ destinations.length }})
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
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Storage / Offsite</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Created</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="filteredBackups.length === 0">
                      <td colspan="7" class="text-center py-5">
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
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                              <h6 class="mb-0 text-sm font-weight-bold">{{ backup.domain || backup.database_name || 'System' }}</h6>
                              <!-- Safety Snapshot / Scheduled Origin Pill -->
                              <span v-if="backup.created_by?.toLowerCase().includes('safety')" class="badge badge-xs bg-warning-subtle text-warning border border-warning-subtle">
                                Safety Snapshot
                              </span>
                              <span v-else-if="backup.schedule_id" class="badge badge-xs bg-info-subtle text-info border border-info-subtle">
                                Scheduled
                              </span>
                            </div>
                            <p class="text-xs text-secondary mb-0 font-monospace" style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" :title="backup.file_name">
                              {{ backup.file_name }}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <!-- Refined Soft Badge -->
                        <span class="badge badge-xs text-xxs px-2 py-1 border rounded-pill d-inline-flex align-items-center"
                              :class="backup.type === 'database' ? 'badge-db' : (backup.type === 'files' ? 'badge-files' : 'badge-full')">
                          <i class="material-symbols-rounded text-xxs me-1" style="font-size: 12px;">
                            {{ backup.type === 'database' ? 'database' : (backup.type === 'files' ? 'folder_zip' : 'inventory_2') }}
                          </i>
                          {{ formatTypeLabel(backup.type) }}
                        </span>
                        <div v-if="backup.metadata?.included_database && backup.type === 'full'" class="text-xxs text-muted mt-1">
                          <i class="material-symbols-rounded align-middle" style="font-size: 10px;">database</i> {{ backup.metadata.included_database }}
                        </div>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span class="text-secondary font-weight-bold text-xs">{{ backup.formatted_size }}</span>
                      </td>

                      <!-- Storage / Offsite Destination & Status -->
                      <td class="align-middle text-center">
                        <div class="d-flex flex-column align-items-center">
                          <span class="badge badge-xs px-2 py-1 border rounded-pill d-inline-flex align-items-center gap-1 mb-1"
                                :class="getDestinationBadgeClass(backup.storage_driver)">
                            <i class="material-symbols-rounded text-xxs">{{ getDestinationIcon(backup.storage_driver) }}</i>
                            {{ backup.destination_name || getDriverLabel(backup.storage_driver) }}
                          </span>
                          <span v-if="backup.remote_status === 'synced'" class="badge badge-xs bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                            <i class="material-symbols-rounded text-xxs">cloud_done</i> Synced Offsite
                          </span>
                          <span v-else-if="backup.remote_status === 'failed'" class="badge badge-xs bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1 cursor-pointer" :title="backup.remote_error">
                            <i class="material-symbols-rounded text-xxs">cloud_off</i> Upload Failed
                          </span>
                          <span v-else-if="backup.remote_status === 'pending'" class="badge badge-xs bg-warning-subtle text-warning border border-warning-subtle d-inline-flex align-items-center gap-1">
                            <span class="spinner-border spinner-border-sm" style="width: 8px; height: 8px;"></span> Uploading...
                          </span>
                          <span v-else class="text-xxs text-muted">Local storage</span>
                          <div class="text-xxs text-muted mt-1">
                            <i class="material-symbols-rounded text-success align-middle" style="font-size: 11px;">check_circle</i> Server copy safe
                          </div>
                        </div>
                      </td>

                      <td class="align-middle text-center text-sm">
                        <span v-if="backup.status === 'completed'" class="badge badge-sm bg-gradient-success">
                          <i class="material-symbols-rounded align-middle me-1" style="font-size: 12px;">check</i> Completed
                        </span>
                        <span v-else-if="backup.status === 'in_progress'" class="badge badge-sm bg-gradient-warning text-white">
                          <span class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span> Running
                        </span>
                        <span v-else-if="backup.status === 'failed'" class="badge badge-sm bg-gradient-danger cursor-pointer" :title="backup.error_message">
                          <i class="material-symbols-rounded align-middle me-1" style="font-size: 12px;">error</i> Failed
                        </span>
                        <span v-else class="badge badge-sm bg-gradient-secondary">{{ backup.status }}</span>

                        <!-- Live Restored State Badge -->
                        <div v-if="backup.metadata?.last_restored_at" class="mt-1">
                          <span class="badge badge-xs bg-gradient-info text-white d-inline-flex align-items-center px-2 py-1"
                                :title="'Restored to live server on ' + backup.metadata.last_restored_at + ' by ' + (backup.metadata.restored_by || 'Admin')">
                            <i class="material-symbols-rounded text-xxs me-1" style="font-size: 11px;">published_with_changes</i>
                            Restored on Live
                          </span>
                        </div>
                      </td>
                      <td class="align-middle text-center">
                        <span class="text-secondary text-xs font-weight-bold">{{ backup.created_at }}</span>
                        <p class="text-xxs text-muted mb-0">by {{ backup.created_by }}</p>
                        <!-- Restored Timestamp Details -->
                        <div v-if="backup.metadata?.last_restored_at" class="text-xxs text-info font-weight-bold mt-1">
                          <i class="material-symbols-rounded align-middle" style="font-size: 11px;">history</i>
                          {{ backup.metadata.last_restored_at }}
                        </div>
                      </td>
                      <td class="align-middle text-center">
                        <div class="d-flex justify-content-center gap-1 align-items-center flex-wrap">
                          <!-- Retry Remote Sync Button -->
                          <button v-if="backup.status === 'completed' && backup.storage_driver !== 'local' && backup.remote_status !== 'synced'"
                                  class="btn btn-sm btn-outline-warning px-2 py-1 mb-0 d-inline-flex align-items-center"
                                  :disabled="retryingBackupId === backup.id"
                                  title="Retry uploading to third-party offsite storage"
                                  @click="retryUpload(backup)">
                            <span v-if="retryingBackupId === backup.id" class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span>
                            <i v-else class="material-symbols-rounded text-sm me-1">cloud_sync</i>
                            <span class="text-xs">Retry Offsite</span>
                          </button>

                          <!-- 1. Restore Button -->
                          <button v-if="backup.status === 'completed'"
                                  class="btn btn-sm btn-outline-info px-2 py-1 mb-0 d-inline-flex align-items-center"
                                  :disabled="isRestoring"
                                  title="Restore snapshot to live site/database"
                                  @click="openRestoreModal(backup)">
                            <i class="material-symbols-rounded text-sm me-1">restore</i>
                            <span class="text-xs">Restore</span>
                          </button>

                          <!-- 2. Download Button -->
                          <a v-if="backup.status === 'completed'"
                             :href="`/backups/${backup.id}/download`"
                             class="btn btn-sm btn-outline-primary px-2 py-1 mb-0 d-inline-flex align-items-center"
                             title="Download archive file">
                            <i class="material-symbols-rounded text-sm me-1">download</i>
                            <span class="text-xs">Download</span>
                          </a>

                          <!-- 3. Delete Button -->
                          <button class="btn btn-sm btn-outline-danger px-2 py-1 mb-0 d-inline-flex align-items-center"
                                  title="Delete backup archive"
                                  @click="openDeleteBackupModal(backup)">
                            <i class="material-symbols-rounded text-sm me-1">delete</i>
                            <span class="text-xs">Delete</span>
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
        <!-- Server Time & Timezone Sync Info Card -->
        <div class="col-12 mb-3">
          <div class="card border shadow-xs bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-sm bg-gradient-info border-radius-md d-flex align-items-center justify-content-center text-white flex-shrink-0">
                  <i class="material-symbols-rounded">schedule</i>
                </div>
                <div>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-xs text-secondary font-weight-bold text-uppercase">Linux Server Time:</span>
                    <span class="badge bg-dark font-monospace text-xs px-2 py-1">{{ liveServerTimeFormatted }}</span>
                    <span class="badge badge-xs bg-info-subtle text-info border border-info-subtle font-monospace">{{ serverTimezone }}</span>
                  </div>
                  <p class="text-xxs text-muted mb-0 mt-1">
                    Your browser local time: <strong>{{ localTimeFormatted }}</strong> ({{ localTimezone }})
                    <span v-if="timeDifferenceText" class="text-info font-weight-bold ms-1">&bull; {{ timeDifferenceText }}</span>
                  </p>
                </div>
              </div>
              <div class="text-xxs text-secondary bg-gray-100 px-3 py-2 border rounded-3 d-flex align-items-center gap-2">
                <i class="material-symbols-rounded text-warning text-sm">info</i>
                <div>All backup schedules trigger strictly according to the <strong>Linux Server Clock</strong>.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card my-2">
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
                    <tr v-for="schedule in schedules" :key="schedule.id" :class="{ 'table-active': isScheduleRunning(schedule) }">
                      <td>
                        <div class="d-flex px-3 py-1">
                          <div class="avatar avatar-sm me-3 border-radius-md bg-gradient-dark d-flex align-items-center justify-content-center">
                            <i class="material-symbols-rounded text-white text-sm" :class="{ 'spin-icon': isScheduleRunning(schedule) }">
                              {{ isScheduleRunning(schedule) ? 'autorenew' : 'schedule' }}
                            </i>
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
                        <div class="d-flex flex-column align-items-start gap-1">
                          <span class="text-sm font-weight-bold text-dark">{{ schedule.domain || schedule.database_name || 'All Server Sites' }}</span>
                          <!-- Refined Soft Badge for Database / Files / Full -->
                          <span class="badge badge-xs text-xxs px-2 py-1 border rounded-pill d-inline-flex align-items-center"
                                :class="schedule.type === 'database' ? 'badge-db' : (schedule.type === 'files' ? 'badge-files' : 'badge-full')">
                            <i class="material-symbols-rounded text-xxs me-1" style="font-size: 11px;">
                              {{ schedule.type === 'database' ? 'database' : (schedule.type === 'files' ? 'folder_zip' : 'inventory_2') }}
                            </i>
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
                        <span v-if="isScheduleRunning(schedule)" class="badge badge-sm bg-gradient-warning text-white d-inline-flex align-items-center">
                          <span class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span>
                          Backup in progress
                        </span>
                        <span v-else-if="schedule.is_active" class="text-xs font-weight-bold text-info">
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
                        <div class="d-flex justify-content-center gap-1 align-items-center">
                          <!-- Run Now / In-Progress Button -->
                          <button v-if="isScheduleRunning(schedule)"
                                  class="btn btn-sm bg-gradient-info text-white px-2 py-1 mb-0 d-inline-flex align-items-center shadow-none"
                                  disabled>
                            <span class="spinner-border spinner-border-sm me-1" style="width: 12px; height: 12px;"></span>
                            <span class="text-xs">Running...</span>
                          </button>
                          <button v-else
                                  class="btn btn-sm btn-outline-success px-2 py-1 mb-0 d-inline-flex align-items-center"
                                  title="Run Schedule Now"
                                  @click="openRunScheduleModal(schedule)">
                            <i class="material-symbols-rounded text-sm me-1">play_arrow</i>
                            <span class="text-xs">Run Now</span>
                          </button>

                          <button class="btn btn-sm btn-outline-secondary px-2 py-1 mb-0 d-inline-flex align-items-center"
                                  title="Edit Schedule"
                                  @click="openEditScheduleModal(schedule)">
                            <i class="material-symbols-rounded text-sm me-1">edit</i>
                            <span class="text-xs">Edit</span>
                          </button>
                          <button class="btn btn-sm btn-outline-danger px-2 py-1 mb-0 d-inline-flex align-items-center"
                                  title="Delete Schedule"
                                  @click="openDeleteScheduleModal(schedule)">
                            <i class="material-symbols-rounded text-sm me-1">delete</i>
                            <span class="text-xs">Delete</span>
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
                Local backups are stored with restricted <code>0775</code> / <code>0664</code> file permissions under <code>/var/backups/nimbus/</code>.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 4: STORAGE PROVIDERS & DESTINATIONS -->
      <div v-if="activeTab === 'destinations'" class="row">
        <div class="col-12">
          <!-- Info / Explainer Banner -->
          <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white;">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                  <div class="avatar avatar-lg bg-white text-dark rounded-circle shadow-sm d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="material-symbols-rounded text-info" style="font-size: 28px;">cloud_sync</i>
                  </div>
                  <div>
                    <h5 class="text-white mb-1">Multi-Provider Offsite Storage Destinations</h5>
                    <p class="text-xs text-white opacity-8 mb-0">
                      Configure multiple offsite storage destinations simultaneously (Google Drive, Backblaze B2, Cloudflare R2, Wasabi, Amazon S3).
                      <br>
                      <span class="badge bg-success-subtle text-success border border-success-subtle mt-1 font-weight-bold">
                        <i class="material-symbols-rounded text-xxs align-middle me-1">verified_user</i> Guaranteed Fail-Safe Redundancy
                      </span>
                      &mdash; Backups are always created and verified on this server first. If an upload to third-party storage fails, your local copy remains 100% safe and restorable.
                    </p>
                  </div>
                </div>
                <div>
                  <button class="btn btn-white text-dark mb-0 font-weight-bold" @click="openCreateDestinationModal()">
                    <i class="material-symbols-rounded text-sm me-1">add</i>
                    Add Storage Destination
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Destinations Cards Grid -->
          <div class="row g-3">
            <div class="col-lg-4 col-md-6" v-for="dest in destinations" :key="dest.id">
              <div class="card h-100 border shadow-sm position-relative overflow-hidden">
                <div class="card-header pb-2 pt-3 px-3 d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center gap-2">
                    <div class="avatar avatar-sm border-radius-md d-flex align-items-center justify-content-center"
                         :class="getDestinationBadgeBg(dest.driver)">
                      <i class="material-symbols-rounded text-white text-sm">{{ getDestinationIcon(dest.driver) }}</i>
                    </div>
                    <div>
                      <h6 class="mb-0 text-sm font-weight-bold">{{ dest.name }}</h6>
                      <span class="text-xxs text-secondary">{{ getDriverLabel(dest.driver) }}</span>
                    </div>
                  </div>
                  <span v-if="dest.is_default" class="badge badge-sm bg-gradient-success">Default</span>
                </div>
                <div class="card-body py-2 px-3">
                  <!-- Connection Status -->
                  <div class="d-flex justify-content-between align-items-center p-2 rounded bg-gray-100 mb-2 text-xxs">
                    <span class="text-secondary font-weight-bold">Connection Status:</span>
                    <span v-if="dest.last_test_status === 'success'" class="text-success font-weight-bold d-flex align-items-center gap-1">
                      <i class="material-symbols-rounded text-xxs">check_circle</i> Verified
                    </span>
                    <span v-else-if="dest.last_test_status === 'failed'" class="text-danger font-weight-bold d-flex align-items-center gap-1" :title="dest.last_test_error">
                      <i class="material-symbols-rounded text-xxs">error</i> Error
                    </span>
                    <span v-else class="text-muted">Not Tested Yet</span>
                  </div>

                  <!-- Config Details -->
                  <div class="text-xxs text-secondary">
                    <div v-if="dest.driver === 'local'" class="text-muted">
                      Built-in local storage under <code>/var/backups/nimbus</code>. Primary fail-safe destination.
                    </div>
                    <div v-if="dest.credentials?.bucket" class="mb-1 d-flex justify-content-between">
                      <span>Bucket:</span> <strong class="text-dark">{{ dest.credentials.bucket }}</strong>
                    </div>
                    <div v-if="dest.credentials?.region" class="mb-1 d-flex justify-content-between">
                      <span>Region:</span> <span class="font-monospace text-dark">{{ dest.credentials.region }}</span>
                    </div>
                    <div v-if="dest.credentials?.folder_id" class="mb-1 d-flex justify-content-between">
                      <span>Folder ID:</span> <span class="font-monospace text-dark">{{ dest.credentials.folder_id }}</span>
                    </div>
                    <div v-if="dest.credentials?.auth_type" class="mb-1 d-flex justify-content-between">
                      <span>Auth:</span> <span class="badge badge-xs bg-light text-dark">{{ dest.credentials.auth_type === 'service_account' ? 'Service Account' : 'OAuth 2.0' }}</span>
                    </div>
                    <div v-if="dest.last_tested_at" class="text-muted mt-2">
                      <i class="material-symbols-rounded text-xxs align-middle">schedule</i> Tested: {{ dest.last_tested_at }}
                    </div>
                  </div>
                </div>
                <div class="card-footer pt-2 pb-3 px-3 border-top bg-light d-flex justify-content-between align-items-center flex-wrap gap-1">
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-outline-secondary mb-0 d-inline-flex align-items-center"
                            :disabled="testingDestinationId === dest.id"
                            @click="testDestination(dest)">
                      <span v-if="testingDestinationId === dest.id" class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span>
                      <i v-else class="material-symbols-rounded text-xxs me-1">network_check</i>
                      Test
                    </button>
                    <button v-if="!dest.is_default" class="btn btn-xs btn-outline-primary mb-0"
                            @click="setDefaultDestination(dest)">
                      Set Default
                    </button>
                  </div>
                  <div class="d-flex gap-1">
                    <button class="btn btn-xs btn-link text-dark p-1 mb-0" title="Edit Destination" @click="editDestination(dest)">
                      <i class="material-symbols-rounded text-sm">edit</i>
                    </button>
                    <button v-if="dest.driver !== 'local'" class="btn btn-xs btn-link text-danger p-1 mb-0" title="Delete Destination" @click="deleteDestination(dest)">
                      <i class="material-symbols-rounded text-sm">delete</i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    <!-- MODAL: CREATE BACKUP NOW -->
    <div class="modal fade" id="modalCreateBackup" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
          <div class="modal-header bg-gray-100 py-3">
            <h5 class="modal-title font-weight-bolder text-dark d-flex align-items-center mb-0">
              <i class="material-symbols-rounded text-primary me-2">cloud_upload</i> Create On-Demand Backup
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form @submit.prevent="submitCreateBackup">
            <div class="modal-body p-4">
              
              <!-- Scope Type Selection (Segmented Pill) -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-2">Target Type</label>
                <div class="btn-group w-100 p-1 bg-gray-100 border-radius-lg" role="group">
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="backupForm.scope === 'domain' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="setBackupScope('domain')">
                    <i class="material-symbols-rounded text-xs me-1 align-middle">language</i> Website Project
                  </button>
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="backupForm.scope === 'database' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="setBackupScope('database')">
                    <i class="material-symbols-rounded text-xs me-1 align-middle">database</i> MySQL Database
                  </button>
                </div>
              </div>

              <!-- Domain Select -->
              <div class="mb-3" v-if="backupForm.scope === 'domain'">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Select Domain / Website</label>
                <SearchableSelect v-model="backupForm.domain"
                                  :items="domains"
                                  placeholder="Select domain or project..."
                                  search-placeholder="Search domains & websites..."
                                  icon="language"
                                  :required="true" />
              </div>

              <!-- Database Select -->
              <div class="mb-3" v-if="backupForm.scope === 'database'">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Select MySQL Database</label>
                <SearchableSelect v-model="backupForm.database_name"
                                  :items="databases"
                                  placeholder="Select MySQL database..."
                                  search-placeholder="Search databases..."
                                  icon="database"
                                  :required="true" />
              </div>

              <!-- Backup Mode / Type Selection (Cards) -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-2">Backup Content</label>
                <div class="row g-2">
                  <div class="col-4" v-if="backupForm.scope === 'domain'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': backupForm.type === 'full' }"
                         @click="backupForm.type = 'full'">
                      <i class="material-symbols-rounded text-primary mb-1">inventory_2</i>
                      <span class="text-xs font-weight-bold text-dark">Full (Both)</span>
                      <span class="text-xxs text-muted">DB + Files</span>
                    </div>
                  </div>
                  <div :class="backupForm.scope === 'domain' ? 'col-4' : 'col-6'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': backupForm.type === 'database' }"
                         @click="backupForm.type = 'database'">
                      <i class="material-symbols-rounded text-info mb-1">database</i>
                      <span class="text-xs font-weight-bold text-dark">Database Only</span>
                      <span class="text-xxs text-muted">SQL dump</span>
                    </div>
                  </div>
                  <div class="col-4" v-if="backupForm.scope === 'domain'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': backupForm.type === 'files' }"
                         @click="backupForm.type = 'files'">
                      <i class="material-symbols-rounded text-secondary mb-1">folder_zip</i>
                      <span class="text-xs font-weight-bold text-dark">Files Only</span>
                      <span class="text-xxs text-muted">Web root</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Optional Backup Label -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Custom Note / Label (Optional)</label>
                <input type="text" v-model="backupForm.name" class="form-control custom-form-input px-3" placeholder="e.g. Before plugin upgrade">
              </div>

              <!-- Storage Destination Selector -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1 d-flex justify-content-between align-items-center">
                  <span>Storage Destination</span>
                  <a href="javascript:;" class="text-xxs text-info font-weight-bold" @click="openCreateDestinationModal()">+ Add New Destination</a>
                </label>
                <select v-model="backupForm.destination_id" class="form-select custom-form-select">
                  <option :value="null">Default Destination ({{ defaultDestinationName }})</option>
                  <option v-for="dest in destinations" :key="dest.id" :value="dest.id">
                    {{ dest.name }} ({{ getDriverLabel(dest.driver) }}) {{ dest.is_default ? '— Default' : '' }}
                  </option>
                </select>
                <div class="p-2 mt-2 border-radius-md bg-light-info text-xxs text-secondary d-flex align-items-center border border-info-subtle">
                  <i class="material-symbols-rounded text-info me-2" style="font-size: 18px;">verified_user</i>
                  <span><strong>Dual-Copy Fail-Safe:</strong> A copy of this backup will always be safely stored on the local server first. If the offsite upload fails, your local backup remains intact and ready to restore.</span>
                </div>
              </div>

            </div>
            <div class="modal-footer bg-gray-100 py-3">
              <button type="button" class="btn btn-outline-secondary mb-0" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn bg-gradient-primary mb-0 shadow-primary" :disabled="isCreatingBackup">
                <span v-if="isCreatingBackup" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">cloud_upload</i>
                {{ isCreatingBackup ? 'Generating Archive...' : 'Start Backup Now' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: CREATE / EDIT SCHEDULE -->
    <div class="modal fade" id="modalSchedule" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
          <div class="modal-header bg-gray-100 py-3">
            <h5 class="modal-title font-weight-bolder text-dark d-flex align-items-center mb-0">
              <i class="material-symbols-rounded text-info me-2">alarm</i>
              {{ scheduleForm.id ? 'Edit Automated Schedule' : 'Create Automated Schedule' }}
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form @submit.prevent="submitScheduleForm">
            <div class="modal-body p-4">
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Schedule Name</label>
                <input type="text" v-model="scheduleForm.name" class="form-control custom-form-input px-3" required placeholder="e.g. Daily Production Backup">
              </div>

              <!-- Scope Selection (Segmented Pill) -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-2">Target Type</label>
                <div class="btn-group w-100 p-1 bg-gray-100 border-radius-lg" role="group">
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="scheduleForm.targetType === 'domain' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="setScheduleTargetType('domain')">
                    <i class="material-symbols-rounded text-xs me-1 align-middle">language</i> Website Project
                  </button>
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="scheduleForm.targetType === 'database' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="setScheduleTargetType('database')">
                    <i class="material-symbols-rounded text-xs me-1 align-middle">database</i> MySQL Database
                  </button>
                </div>
              </div>

              <!-- Domain Select -->
              <div class="mb-3" v-if="scheduleForm.targetType === 'domain'">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Select Domain / Website</label>
                <SearchableSelect v-model="scheduleForm.domain"
                                  :items="domains"
                                  placeholder="Select domain or project..."
                                  search-placeholder="Search domains & websites..."
                                  icon="language"
                                  :required="true" />
              </div>

              <!-- Database Select -->
              <div class="mb-3" v-if="scheduleForm.targetType === 'database'">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Select MySQL Database</label>
                <SearchableSelect v-model="scheduleForm.database_name"
                                  :items="databases"
                                  placeholder="Select MySQL database..."
                                  search-placeholder="Search databases..."
                                  icon="database"
                                  :required="true" />
              </div>

              <!-- Backup Content (Cards) -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-2">Backup Content</label>
                <div class="row g-2">
                  <div class="col-4" v-if="scheduleForm.targetType === 'domain'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': scheduleForm.type === 'full' }"
                         @click="scheduleForm.type = 'full'">
                      <i class="material-symbols-rounded text-primary mb-1">inventory_2</i>
                      <span class="text-xs font-weight-bold text-dark">Full (Both)</span>
                      <span class="text-xxs text-muted">DB + Files</span>
                    </div>
                  </div>
                  <div :class="scheduleForm.targetType === 'domain' ? 'col-4' : 'col-6'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': scheduleForm.type === 'database' }"
                         @click="scheduleForm.type = 'database'">
                      <i class="material-symbols-rounded text-info mb-1">database</i>
                      <span class="text-xs font-weight-bold text-dark">Database Only</span>
                      <span class="text-xxs text-muted">SQL dump</span>
                    </div>
                  </div>
                  <div class="col-4" v-if="scheduleForm.targetType === 'domain'">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': scheduleForm.type === 'files' }"
                         @click="scheduleForm.type = 'files'">
                      <i class="material-symbols-rounded text-secondary mb-1">folder_zip</i>
                      <span class="text-xs font-weight-bold text-dark">Files Only</span>
                      <span class="text-xxs text-muted">Web root</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Frequency Selector (Pills) -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-2">Frequency</label>
                <div class="btn-group w-100 p-1 bg-gray-100 border-radius-lg" role="group">
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="scheduleForm.frequency === 'hourly' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="scheduleForm.frequency = 'hourly'">Hourly</button>
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="scheduleForm.frequency === 'daily' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="scheduleForm.frequency = 'daily'">Daily</button>
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="scheduleForm.frequency === 'weekly' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="scheduleForm.frequency = 'weekly'">Weekly</button>
                  <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                          :class="scheduleForm.frequency === 'monthly' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                          @click="scheduleForm.frequency = 'monthly'">Monthly</button>
                </div>
              </div>

              <!-- Run Time & Day -->
              <div class="row mb-3">
                <div :class="scheduleForm.frequency === 'weekly' ? 'col-6' : 'col-12'">
                  <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Execution Time (24h)</label>
                  <input type="time" v-model="scheduleForm.time" class="form-control custom-form-input px-3" required>
                  <div class="text-xxs text-muted mt-1" v-if="scheduleForm.time">
                    <i class="material-symbols-rounded align-middle text-xxs me-1 text-info">schedule</i>
                    Runs at <strong>{{ scheduleForm.time }} (Server {{ serverTimezone }})</strong>
                    <span v-if="localEquivalentTime" class="text-dark font-weight-bold">&bull; Approx <strong>{{ localEquivalentTime }}</strong> in your browser time</span>
                  </div>
                </div>
                <div class="col-6" v-if="scheduleForm.frequency === 'weekly'">
                  <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Day of Week</label>
                  <select v-model="scheduleForm.day_of_week" class="form-select custom-form-select">
                    <option :value="0">Sunday</option>
                    <option :value="1">Monday</option>
                    <option :value="2">Tuesday</option>
                    <option :value="3">Wednesday</option>
                    <option :value="4">Thursday</option>
                    <option :value="5">Friday</option>
                    <option :value="6">Saturday</option>
                  </select>
                </div>
              </div>

              <!-- Retention Window -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Retention Window (Keep last N copies)</label>
                <input type="number" v-model="scheduleForm.retention_count" class="form-control custom-form-input px-3" min="1" max="100" required>
                <p class="text-xxs text-muted mb-0 mt-1">Older archives exceeding this threshold are automatically purged to save server disk space.</p>
              </div>

              <!-- Storage Destination Selector -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1 d-flex justify-content-between align-items-center">
                  <span>Target Storage Destination</span>
                  <a href="javascript:;" class="text-xxs text-info font-weight-bold" @click="openCreateDestinationModal()">+ Add New Destination</a>
                </label>
                <select v-model="scheduleForm.destination_id" class="form-select custom-form-select">
                  <option :value="null">Default Destination ({{ defaultDestinationName }})</option>
                  <option v-for="dest in destinations" :key="dest.id" :value="dest.id">
                    {{ dest.name }} ({{ getDriverLabel(dest.driver) }}) {{ dest.is_default ? '— Default' : '' }}
                  </option>
                </select>
                <p class="text-xxs text-muted mb-0 mt-1">
                  Choose where this scheduled website/database backup will be stored (Google Drive, Backblaze B2, S3, or Local Server).
                </p>
              </div>

              <!-- Email notification toggle Card -->
              <div class="p-3 bg-gray-100 border-radius-lg d-flex align-items-center justify-content-between">
                <div>
                  <h6 class="mb-0 text-xs font-weight-bold text-dark d-flex align-items-center">
                    <i class="material-symbols-rounded text-info text-sm me-1">mail</i> Super Admin Email Alerts
                  </h6>
                  <p class="text-xxs text-muted mb-0">Dispatches success and error reports to administrator alert inbox.</p>
                </div>
                <div class="form-check form-switch ps-0 mb-0">
                  <input class="form-check-input ms-0 cursor-pointer" type="checkbox" id="emailNotif" v-model="scheduleForm.email_notifications">
                </div>
              </div>

            </div>
            <div class="modal-footer bg-gray-100 py-3">
              <button type="button" class="btn btn-outline-secondary mb-0" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn bg-gradient-info mb-0 shadow-info" :disabled="isSavingSchedule">
                <span v-if="isSavingSchedule" class="spinner-border spinner-border-sm me-1"></span>
                {{ scheduleForm.id ? 'Save Changes' : 'Create Schedule' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL: RUN SCHEDULE NOW CONFIRMATION -->
    <div class="modal fade" id="modalRunSchedule" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" v-if="selectedScheduleToRun">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bolder text-success">
              <i class="material-symbols-rounded align-middle me-1">play_circle</i> Trigger Automated Schedule
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p class="text-sm mb-3">
              Do you want to run the backup schedule <strong>{{ selectedScheduleToRun.name }}</strong> right now?
            </p>
            <div class="bg-gray-100 p-3 rounded mb-3 text-sm">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Target:</span>
                <span class="font-weight-bold">{{ selectedScheduleToRun.domain || selectedScheduleToRun.database_name || 'All Server Sites' }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Content Type:</span>
                <span class="font-weight-bold">{{ formatTypeLabel(selectedScheduleToRun.type) }}</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-secondary">Retention:</span>
                <span class="font-weight-bold">Keep last {{ selectedScheduleToRun.retention_count }}</span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn bg-gradient-success" :disabled="isRunningSchedule" @click="performRunScheduleNow">
              <span v-if="isRunningSchedule" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="material-symbols-rounded text-sm me-1">play_arrow</i>
              {{ isRunningSchedule ? 'Starting Dump...' : 'Run Backup Now' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: DELETE SCHEDULE CONFIRMATION -->
    <div class="modal fade" id="modalDeleteSchedule" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" v-if="selectedScheduleToDelete">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bolder text-danger">
              <i class="material-symbols-rounded align-middle me-1">alarm_off</i> Delete Automated Schedule
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p class="text-sm">
              Are you sure you want to delete the schedule <strong>{{ selectedScheduleToDelete.name }}</strong>?
            </p>
            <p class="text-xs text-muted mb-0">
              Recurring automated backups for this target will be stopped. Existing completed archives will not be deleted.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn bg-gradient-danger" :disabled="isDeletingSchedule" @click="performDeleteSchedule">
              <span v-if="isDeletingSchedule" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="material-symbols-rounded text-sm me-1">delete</i>
              {{ isDeletingSchedule ? 'Deleting...' : 'Yes, Delete Schedule' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: DELETE BACKUP ARCHIVE CONFIRMATION -->
    <div class="modal fade" id="modalDeleteBackup" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" v-if="selectedBackupToDelete">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bolder text-danger">
              <i class="material-symbols-rounded align-middle me-1">delete_forever</i> Delete Backup Archive
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="p-3 mb-3 border-radius-md bg-light border border-danger text-dark text-xs d-flex align-items-center">
              <i class="material-symbols-rounded text-danger me-2" style="font-size: 22px;">warning</i>
              <div>
                <strong class="text-danger">Warning:</strong> This action cannot be undone. The backup archive file will be permanently removed from disk storage.
              </div>
            </div>
            <div class="bg-gray-100 p-3 rounded mb-2 text-sm">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Target:</span>
                <span class="font-weight-bold text-dark">{{ selectedBackupToDelete.domain || selectedBackupToDelete.database_name }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Archive Size:</span>
                <span class="font-weight-bold text-dark">{{ selectedBackupToDelete.formatted_size }}</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-secondary">Created:</span>
                <span class="font-weight-bold text-dark">{{ selectedBackupToDelete.created_at }}</span>
              </div>
            </div>
            <p class="text-xs text-muted font-monospace mb-0 text-break">
              File: {{ selectedBackupToDelete.file_name }}
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn bg-gradient-danger" :disabled="isDeletingBackup" @click="performDeleteBackup">
              <span v-if="isDeletingBackup" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="material-symbols-rounded text-sm me-1">delete</i>
              {{ isDeletingBackup ? 'Deleting...' : 'Permanently Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL: RESTORE CONFIRMATION -->
    <div class="modal fade" id="modalRestore" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bolder text-danger">
              <i class="material-symbols-rounded align-middle me-1">restore</i> Restore Backup Snapshot
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" v-if="selectedRestoreBackup">
            <div class="p-3 mb-3 border-radius-md bg-light border border-warning text-dark text-xs d-flex align-items-center">
              <i class="material-symbols-rounded text-warning me-2" style="font-size: 22px;">warning</i>
              <div>
                <strong class="text-dark">Caution:</strong> Restoring this backup will replace current live files or database tables with the snapshot version for <strong class="text-dark">{{ selectedRestoreBackup.domain || selectedRestoreBackup.database_name }}</strong>.
              </div>
            </div>

            <div class="bg-gray-100 p-3 rounded mb-3 text-sm">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Target:</span>
                <span class="font-weight-bold text-dark">{{ selectedRestoreBackup.domain || selectedRestoreBackup.database_name }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Type:</span>
                <span class="font-weight-bold text-dark">{{ formatTypeLabel(selectedRestoreBackup.type) }}</span>
              </div>
              <div class="d-flex justify-content-between mb-1">
                <span class="text-secondary">Archive Size:</span>
                <span class="font-weight-bold text-dark">{{ selectedRestoreBackup.formatted_size }}</span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-secondary">Snapshot Date:</span>
                <span class="font-weight-bold text-dark">{{ selectedRestoreBackup.created_at }}</span>
              </div>
            </div>

            <div class="form-check ps-0">
              <input class="form-check-input ms-0 me-2" type="checkbox" id="snapBeforeRestore" v-model="createSnapshotBeforeRestore">
              <label class="form-check-label text-sm text-dark" for="snapBeforeRestore">
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

    <!-- MODAL: ADD / EDIT STORAGE DESTINATION -->
    <div class="modal fade" id="modalDestination" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content shadow-lg border-0">
          <div class="modal-header bg-gray-100 py-3">
            <h5 class="modal-title font-weight-bolder text-dark d-flex align-items-center mb-0">
              <i class="material-symbols-rounded text-info me-2">cloud_upload</i>
              {{ destinationForm.id ? 'Edit Storage Destination' : 'Configure Storage Destination' }}
            </h5>
            <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form @submit.prevent="submitDestinationForm">
            <div class="modal-body p-4">
              <!-- Driver Selection Cards -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-2">Storage Provider</label>
                <div class="row g-2">
                  <div class="col-md-4 col-6">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': destinationForm.driver === 'backblaze' }"
                         @click="destinationForm.driver = 'backblaze'">
                      <i class="material-symbols-rounded text-danger mb-1">local_fire_department</i>
                      <span class="text-xs font-weight-bold text-dark">Backblaze B2</span>
                      <span class="text-xxs text-muted">$6/TB &bull; S3 Compatible</span>
                    </div>
                  </div>
                  <div class="col-md-4 col-6">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': destinationForm.driver === 'google_drive' }"
                         @click="destinationForm.driver = 'google_drive'">
                      <i class="material-symbols-rounded text-info mb-1">cloud</i>
                      <span class="text-xs font-weight-bold text-dark">Google Drive</span>
                      <span class="text-xxs text-muted">Service Account / OAuth</span>
                    </div>
                  </div>
                  <div class="col-md-4 col-6">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': destinationForm.driver === 'r2' }"
                         @click="destinationForm.driver = 'r2'">
                      <i class="material-symbols-rounded text-warning mb-1">cloud_done</i>
                      <span class="text-xs font-weight-bold text-dark">Cloudflare R2</span>
                      <span class="text-xxs text-muted">$0 Egress &bull; S3 Compatible</span>
                    </div>
                  </div>
                  <div class="col-md-4 col-6">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': destinationForm.driver === 'wasabi' }"
                         @click="destinationForm.driver = 'wasabi'">
                      <i class="material-symbols-rounded text-success mb-1">hard_drive</i>
                      <span class="text-xs font-weight-bold text-dark">Wasabi Storage</span>
                      <span class="text-xxs text-muted">$6.99/TB &bull; No API fees</span>
                    </div>
                  </div>
                  <div class="col-md-4 col-6">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': destinationForm.driver === 's3' }"
                         @click="destinationForm.driver = 's3'">
                      <i class="material-symbols-rounded text-warning mb-1">storage</i>
                      <span class="text-xs font-weight-bold text-dark">Amazon S3</span>
                      <span class="text-xxs text-muted">Standard AWS Bucket</span>
                    </div>
                  </div>
                  <div class="col-md-4 col-6">
                    <div class="card card-body p-2 text-center border cursor-pointer h-100 selection-card"
                         :class="{ 'active-card': destinationForm.driver === 'custom_s3' }"
                         @click="destinationForm.driver = 'custom_s3'">
                      <i class="material-symbols-rounded text-secondary mb-1">dns</i>
                      <span class="text-xs font-weight-bold text-dark">Custom S3 / MinIO</span>
                      <span class="text-xxs text-muted">Any S3 Endpoint</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Destination Name -->
              <div class="mb-3">
                <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Destination Friendly Name</label>
                <input type="text" v-model="destinationForm.name" class="form-control custom-form-input px-3" required placeholder="e.g. Backblaze B2 Vault, Google Drive Offsite">
              </div>

              <!-- BACKBLAZE B2 FIELDS -->
              <template v-if="destinationForm.driver === 'backblaze'">
                <div class="alert alert-light border text-xxs p-2 mb-3">
                  <i class="material-symbols-rounded text-danger text-sm align-middle me-1">info</i>
                  In your Backblaze account, go to <strong>App Keys</strong> to generate a <strong>keyID</strong> and <strong>applicationKey</strong> with read/write access to your bucket.
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Bucket Name</label>
                    <input type="text" v-model="destinationForm.credentials.bucket" class="form-control custom-form-input px-3" required placeholder="e.g. my-nimbus-backups">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">S3 Region / Endpoint</label>
                    <input type="text" v-model="destinationForm.credentials.region" class="form-control custom-form-input px-3" required placeholder="e.g. us-west-004">
                    <span class="text-xxs text-muted">Found on your B2 Bucket Details (e.g. s3.<strong>us-west-004</strong>.backblazeb2.com)</span>
                  </div>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Key ID (Access Key)</label>
                    <input type="text" v-model="destinationForm.credentials.key" class="form-control custom-form-input px-3" required placeholder="B2 Key ID">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Application Key (Secret)</label>
                    <input type="password" v-model="destinationForm.credentials.secret" class="form-control custom-form-input px-3" :placeholder="destinationForm.id ? 'Leave blank to keep existing' : 'B2 Application Key'">
                  </div>
                </div>
              </template>

              <!-- GOOGLE DRIVE FIELDS -->
              <template v-if="destinationForm.driver === 'google_drive'">
                <div class="mb-3">
                  <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Authentication Method</label>
                  <div class="btn-group w-100 p-1 bg-gray-100 border-radius-lg" role="group">
                    <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                            :class="destinationForm.credentials.auth_type === 'service_account' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                            @click="destinationForm.credentials.auth_type = 'service_account'">
                      <i class="material-symbols-rounded text-xs me-1 align-middle">vpn_key</i> Service Account JSON (Recommended)
                    </button>
                    <button type="button" class="btn btn-sm mb-0 border-0 flex-grow-1 font-weight-bold transition-all"
                            :class="destinationForm.credentials.auth_type === 'oauth' ? 'bg-white text-dark shadow-sm' : 'text-secondary bg-transparent'"
                            @click="destinationForm.credentials.auth_type = 'oauth'">
                      <i class="material-symbols-rounded text-xs me-1 align-middle">lock</i> OAuth 2.0 Client
                    </button>
                  </div>
                </div>

                <div v-if="destinationForm.credentials.auth_type === 'service_account'" class="mb-3">
                  <div class="alert alert-light border text-xxs p-2 mb-2">
                    <i class="material-symbols-rounded text-info text-sm align-middle me-1">lightbulb</i>
                    <strong>Quick Setup:</strong> Create a Service Account in Google Cloud Console, download the JSON key, and paste it below. Then share your Google Drive backup folder with the Service Account's <code>client_email</code>.
                  </div>
                  <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Service Account JSON Key</label>
                  <textarea v-model="destinationForm.credentials.service_account_json" class="form-control custom-form-input px-3 font-monospace text-xs" rows="4" :placeholder="destinationForm.id ? 'Paste new JSON or leave blank to keep existing' : 'Paste full JSON content here { &quot;type&quot;: &quot;service_account&quot;, ... }'"></textarea>
                </div>

                <div v-if="destinationForm.credentials.auth_type === 'oauth'" class="mb-3">
                  <div class="row g-2 mb-2">
                    <div class="col-md-6">
                      <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">OAuth Client ID</label>
                      <input type="text" v-model="destinationForm.credentials.client_id" class="form-control custom-form-input px-3" placeholder="Google OAuth Client ID">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">OAuth Client Secret</label>
                      <input type="password" v-model="destinationForm.credentials.client_secret" class="form-control custom-form-input px-3" :placeholder="destinationForm.id ? 'Leave blank to keep existing' : 'Client Secret'">
                    </div>
                  </div>
                  <div class="mb-2">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Refresh Token</label>
                    <input type="password" v-model="destinationForm.credentials.refresh_token" class="form-control custom-form-input px-3" :placeholder="destinationForm.id ? 'Leave blank to keep existing' : 'Refresh Token with drive.file scope'">
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Target Google Drive Folder ID (Optional)</label>
                  <input type="text" v-model="destinationForm.credentials.folder_id" class="form-control custom-form-input px-3" placeholder="e.g. 1a2b3c4d5e... (from Drive URL: /folders/FOLDER_ID)">
                  <span class="text-xxs text-muted">If blank, backups will be stored in your Drive root directory.</span>
                </div>
              </template>

              <!-- CLOUDFLARE R2 FIELDS -->
              <template v-if="destinationForm.driver === 'r2'">
                <div class="alert alert-light border text-xxs p-2 mb-3">
                  <i class="material-symbols-rounded text-warning text-sm align-middle me-1">info</i>
                  Cloudflare R2 provides zero egress fees. Find your Account ID in your Cloudflare dashboard URL or R2 overview.
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Cloudflare Account ID</label>
                    <input type="text" v-model="destinationForm.credentials.account_id" class="form-control custom-form-input px-3" required placeholder="32-character Account ID">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Bucket Name</label>
                    <input type="text" v-model="destinationForm.credentials.bucket" class="form-control custom-form-input px-3" required placeholder="R2 Bucket Name">
                  </div>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Access Key ID</label>
                    <input type="text" v-model="destinationForm.credentials.key" class="form-control custom-form-input px-3" required placeholder="R2 Access Key ID">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Secret Access Key</label>
                    <input type="password" v-model="destinationForm.credentials.secret" class="form-control custom-form-input px-3" :placeholder="destinationForm.id ? 'Leave blank to keep existing' : 'R2 Secret Access Key'">
                  </div>
                </div>
              </template>

              <!-- WASABI FIELDS -->
              <template v-if="destinationForm.driver === 'wasabi'">
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Bucket Name</label>
                    <input type="text" v-model="destinationForm.credentials.bucket" class="form-control custom-form-input px-3" required placeholder="Wasabi Bucket Name">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Wasabi Region</label>
                    <input type="text" v-model="destinationForm.credentials.region" class="form-control custom-form-input px-3" required placeholder="e.g. us-east-1, eu-central-1">
                  </div>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Access Key</label>
                    <input type="text" v-model="destinationForm.credentials.key" class="form-control custom-form-input px-3" required placeholder="Wasabi Access Key">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Secret Key</label>
                    <input type="password" v-model="destinationForm.credentials.secret" class="form-control custom-form-input px-3" :placeholder="destinationForm.id ? 'Leave blank to keep existing' : 'Wasabi Secret Key'">
                  </div>
                </div>
              </template>

              <!-- S3 / CUSTOM S3 FIELDS -->
              <template v-if="destinationForm.driver === 's3' || destinationForm.driver === 'custom_s3'">
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Bucket Name</label>
                    <input type="text" v-model="destinationForm.credentials.bucket" class="form-control custom-form-input px-3" required placeholder="Bucket Name">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Region</label>
                    <input type="text" v-model="destinationForm.credentials.region" class="form-control custom-form-input px-3" required placeholder="e.g. us-east-1">
                  </div>
                </div>
                <div class="row g-2 mb-2" v-if="destinationForm.driver === 'custom_s3'">
                  <div class="col-12">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Custom S3 Endpoint URL</label>
                    <input type="text" v-model="destinationForm.credentials.endpoint" class="form-control custom-form-input px-3" required placeholder="e.g. https://play.min.io or https://nyc3.digitaloceanspaces.com">
                  </div>
                </div>
                <div class="row g-2 mb-2">
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Access Key</label>
                    <input type="text" v-model="destinationForm.credentials.key" class="form-control custom-form-input px-3" required placeholder="Access Key">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label font-weight-bold text-xs text-uppercase text-secondary mb-1">Secret Key</label>
                    <input type="password" v-model="destinationForm.credentials.secret" class="form-control custom-form-input px-3" :placeholder="destinationForm.id ? 'Leave blank to keep existing' : 'Secret Key'">
                  </div>
                </div>
              </template>

              <!-- Default Toggle -->
              <div class="form-check form-switch ps-0 mt-3">
                <input class="form-check-input ms-0 cursor-pointer" type="checkbox" id="destDefault" v-model="destinationForm.is_default">
                <label class="form-check-label text-xs font-weight-bold text-dark ms-2 cursor-pointer" for="destDefault">
                  Set as default destination for new backups and schedules
                </label>
              </div>

              <!-- Test Result Alert Banner -->
              <div v-if="modalTestResult" class="mt-3 p-3 rounded text-xs d-flex align-items-center gap-2"
                   :class="modalTestResult.success ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'">
                <i class="material-symbols-rounded text-sm">{{ modalTestResult.success ? 'check_circle' : 'error' }}</i>
                <span>{{ modalTestResult.message }}</span>
              </div>

            </div>
            <div class="modal-footer bg-gray-100 py-3 d-flex justify-content-between">
              <div>
                <button type="button" class="btn btn-outline-dark mb-0" :disabled="isTestingInModal" @click="testCurrentFormInModal">
                  <span v-if="isTestingInModal" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="material-symbols-rounded text-sm me-1">network_check</i>
                  Test Connection
                </button>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary mb-0" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn bg-gradient-info mb-0 shadow-info" :disabled="isSavingDestination">
                  <span v-if="isSavingDestination" class="spinner-border spinner-border-sm me-1"></span>
                  {{ destinationForm.id ? 'Update Destination' : 'Save Destination' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'

const page = usePage()

const props = defineProps({
  backups: { type: Array, default: () => [] },
  schedules: { type: Array, default: () => [] },
  domains: { type: Array, default: () => [] },
  databases: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
  destinations: { type: Array, default: () => [] },
})

const activeTab = ref('backups')
const searchQuery = ref('')
const filterType = ref('all')

const localAlert = ref({ show: false, message: '', type: 'success' })

const isCreatingBackup = ref(false)
const isSavingSchedule = ref(false)
const isRestoring = ref(false)
const isRunningSchedule = ref(false)
const isDeletingSchedule = ref(false)
const isDeletingBackup = ref(false)
const isPolling = ref(false)

const testingDestinationId = ref(null)
const isTestingInModal = ref(false)
const modalTestResult = ref(null)
const isSavingDestination = ref(false)
const retryingBackupId = ref(null)

const runningScheduleId = ref(null)

const selectedRestoreBackup = ref(null)
const selectedScheduleToRun = ref(null)
const selectedScheduleToDelete = ref(null)
const selectedBackupToDelete = ref(null)
const createSnapshotBeforeRestore = ref(true)

let pollingTimer = null
let serverClockTimer = null

// Server Clock State & Timezone Sync
const serverInfo = computed(() => page.props.server_info || {})
const serverTimezone = computed(() => serverInfo.value.timezone || 'UTC')
const liveServerTimestamp = ref(Date.now())
const serverTimeOffset = ref(0)

const updateServerClock = () => {
  liveServerTimestamp.value = Date.now() + serverTimeOffset.value
}

const liveServerTimeFormatted = computed(() => {
  try {
    const d = new Date(liveServerTimestamp.value)
    return d.toLocaleString('en-US', {
      timeZone: serverTimezone.value === 'UTC' ? 'UTC' : serverTimezone.value,
      dateStyle: 'medium',
      timeStyle: 'medium',
    })
  } catch (e) {
    return new Date(liveServerTimestamp.value).toTimeString().split(' ')[0]
  }
})

const localTimezone = computed(() => {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local'
})

const localTimeFormatted = computed(() => {
  return new Date().toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'medium' })
})

const timeDifferenceText = computed(() => {
  try {
    const now = new Date()
    const serverDate = new Date(now.toLocaleString('en-US', { timeZone: serverTimezone.value }))
    const localDate = new Date(now.toLocaleString('en-US', { timeZone: localTimezone.value }))
    const diffMs = serverDate - localDate
    const diffHours = Math.round((diffMs / (1000 * 60 * 60)) * 10) / 10
    if (diffHours === 0) return 'Server time matches your local time'
    if (diffHours > 0) return `Server is +${diffHours}h ahead of you`
    return `Server is ${Math.abs(diffHours)}h behind you`
  } catch (e) {
    return ''
  }
})

const localEquivalentTime = computed(() => {
  if (!scheduleForm.value.time) return ''
  try {
    const [h, m] = scheduleForm.value.time.split(':').map(Number)
    const now = new Date()
    const nowServerStr = now.toLocaleString('en-US', { timeZone: serverTimezone.value })
    const nowLocalStr = now.toLocaleString('en-US', { timeZone: localTimezone.value })
    const diffMs = new Date(nowLocalStr) - new Date(nowServerStr)
    const d = new Date()
    d.setHours(h, m, 0, 0)
    const localTarget = new Date(d.getTime() + diffMs)
    return localTarget.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
  } catch (e) {
    return ''
  }
})

// Forms
const destinationForm = ref({
  id: null,
  name: '',
  driver: 'b2',
  is_default: false,
  credentials: {
    auth_type: 'service_account',
    bucket: '',
    endpoint: '',
    region: 'us-east-005',
    key: '',
    secret: '',
    path_prefix: 'backups',
    folder_id: '',
    service_account_json: '',
    client_id: '',
    client_secret: '',
    refresh_token: '',
    account_id: '',
  }
})

const backupForm = ref({
  scope: 'domain',
  domain: '',
  database_name: '',
  type: 'full',
  name: '',
  retention_count: 7,
  destination_id: null,
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
  destination_id: null,
})

// Check if any backup is currently in progress
const activeRunningBackup = computed(() => {
  return props.backups.find(b => b.status === 'in_progress')
})

const isScheduleRunning = (schedule) => {
  if (runningScheduleId.value === schedule.id) return true
  if (!activeRunningBackup.value) return false
  const target = schedule.domain || schedule.database_name
  const activeTarget = activeRunningBackup.value.domain || activeRunningBackup.value.database_name
  return Boolean(target && activeTarget && target.toLowerCase() === activeTarget.toLowerCase())
}

// Lifecycle
onMounted(() => {
  if (props.domains && props.domains.length > 0) {
    backupForm.value.domain = props.domains[0].domain
    scheduleForm.value.domain = props.domains[0].domain
  }
  if (props.databases && props.databases.length > 0) {
    backupForm.value.database_name = props.databases[0]
    scheduleForm.value.database_name = props.databases[0]
  }

  // Pre-select default destination if available
  const defaultDest = props.destinations.find(d => d.is_default)
  if (defaultDest) {
    backupForm.value.destination_id = defaultDest.id
    scheduleForm.value.destination_id = defaultDest.id
  } else if (props.destinations && props.destinations.length > 0) {
    backupForm.value.destination_id = props.destinations[0].id
    scheduleForm.value.destination_id = props.destinations[0].id
  }

  // Sync initial server time offset
  if (serverInfo.value.timestamp) {
    const serverMs = serverInfo.value.timestamp * 1000
    serverTimeOffset.value = serverMs - Date.now()
  }

  updateServerClock()
  serverClockTimer = setInterval(updateServerClock, 1000)

  // If there's an in-progress backup on load, start polling
  if (activeRunningBackup.value) {
    startPolling()
  }
})

onUnmounted(() => {
  stopPolling()
  if (serverClockTimer) clearInterval(serverClockTimer)
})

const startPolling = () => {
  if (pollingTimer) return
  isPolling.value = true
  pollingTimer = setInterval(() => {
    router.reload({
      preserveScroll: true,
      onSuccess: () => {
        if (!activeRunningBackup.value) {
          runningScheduleId.value = null
          stopPolling()
        }
      }
    })
  }, 3000)
}

const stopPolling = () => {
  if (pollingTimer) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
  isPolling.value = false
}

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

const refreshData = () => {
  isPolling.value = true
  router.reload({
    preserveScroll: true,
    onFinish: () => { isPolling.value = false }
  })
}

// Modal Helper
const getModalInstance = (id) => {
  const el = document.getElementById(id)
  if (el) {
    if (window.bootstrap?.Modal) {
      return window.bootstrap.Modal.getOrCreateInstance(el)
    }
  }
  return null
}

const openCreateBackupModal = () => {
  const defaultDest = props.destinations.find(d => d.is_default)
  if (!backupForm.value.destination_id && defaultDest) {
    backupForm.value.destination_id = defaultDest.id
  } else if (!backupForm.value.destination_id && props.destinations.length > 0) {
    backupForm.value.destination_id = props.destinations[0].id
  }
  const modal = getModalInstance('modalCreateBackup')
  modal?.show()
}

const openCreateScheduleModal = () => {
  const defaultDest = props.destinations.find(d => d.is_default)
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
    destination_id: defaultDest ? defaultDest.id : (props.destinations[0]?.id || null),
  }
  const modal = getModalInstance('modalSchedule')
  modal?.show()
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
    destination_id: schedule.destination_id || null,
  }
  const modal = getModalInstance('modalSchedule')
  modal?.show()
}

const openRestoreModal = async (backup) => {
  selectedRestoreBackup.value = backup
  createSnapshotBeforeRestore.value = true
  await nextTick()
  const modal = getModalInstance('modalRestore')
  modal?.show()
}

const openRunScheduleModal = async (schedule) => {
  selectedScheduleToRun.value = schedule
  await nextTick()
  const modal = getModalInstance('modalRunSchedule')
  modal?.show()
}

const openDeleteScheduleModal = async (schedule) => {
  selectedScheduleToDelete.value = schedule
  await nextTick()
  const modal = getModalInstance('modalDeleteSchedule')
  modal?.show()
}

const openDeleteBackupModal = async (backup) => {
  selectedBackupToDelete.value = backup
  await nextTick()
  const modal = getModalInstance('modalDeleteBackup')
  modal?.show()
}

// Destination Management Actions
const openCreateDestinationModal = (driver = 'b2') => {
  modalTestResult.value = null
  destinationForm.value = {
    id: null,
    name: '',
    driver: driver,
    is_default: false,
    credentials: {
      auth_type: 'service_account',
      bucket: '',
      endpoint: '',
      region: driver === 'b2' ? 'us-east-005' : (driver === 'wasabi' ? 'us-east-1' : 'us-east-1'),
      key: '',
      secret: '',
      path_prefix: 'backups',
      folder_id: '',
      service_account_json: '',
      client_id: '',
      client_secret: '',
      refresh_token: '',
      account_id: '',
    }
  }
  const modal = getModalInstance('modalDestination')
  modal?.show()
}

const editDestination = (dest) => {
  modalTestResult.value = null
  destinationForm.value = {
    id: dest.id,
    name: dest.name,
    driver: dest.driver,
    is_default: Boolean(dest.is_default),
    credentials: {
      auth_type: dest.credentials?.auth_type || 'service_account',
      bucket: dest.credentials?.bucket || '',
      endpoint: dest.credentials?.endpoint || '',
      region: dest.credentials?.region || '',
      key: dest.credentials?.key || '',
      secret: '',
      path_prefix: dest.credentials?.path_prefix || 'backups',
      folder_id: dest.credentials?.folder_id || '',
      service_account_json: '',
      client_id: dest.credentials?.client_id || '',
      client_secret: '',
      refresh_token: '',
      account_id: dest.credentials?.account_id || '',
    }
  }
  const modal = getModalInstance('modalDestination')
  modal?.show()
}

const submitDestinationForm = () => {
  isSavingDestination.value = true
  modalTestResult.value = null

  router.post('/backups/destinations', destinationForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      getModalInstance('modalDestination')?.hide()
      isSavingDestination.value = false
      localAlert.value = {
        show: true,
        message: destinationForm.value.id ? 'Storage destination updated successfully.' : 'Storage destination created successfully.',
        type: 'success'
      }
    },
    onError: (errors) => {
      isSavingDestination.value = false
      const msg = Object.values(errors).flat().join(', ') || 'Failed to save storage destination.'
      modalTestResult.value = { success: false, message: msg }
    },
    onFinish: () => {
      isSavingDestination.value = false
    }
  })
}

const testCurrentFormInModal = async () => {
  isTestingInModal.value = true
  modalTestResult.value = null

  try {
    const res = await fetch('/backups/destinations/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(destinationForm.value)
    })
    const data = await res.json()
    modalTestResult.value = data
  } catch (err) {
    modalTestResult.value = {
      success: false,
      message: err.message || 'Connection test failed to reach server.'
    }
  } finally {
    isTestingInModal.value = false
  }
}

const testDestination = async (dest) => {
  testingDestinationId.value = dest.id
  try {
    const res = await fetch('/backups/destinations/test', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify({ id: dest.id, driver: dest.driver })
    })
    const data = await res.json()
    localAlert.value = {
      show: true,
      message: `${dest.name}: ${data.message}`,
      type: data.success ? 'success' : 'danger'
    }
  } catch (err) {
    localAlert.value = {
      show: true,
      message: `${dest.name}: Connection test failed.`,
      type: 'danger'
    }
  } finally {
    testingDestinationId.value = null
  }
}

const setDefaultDestination = (dest) => {
  router.post(`/backups/destinations/${dest.id}/default`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      localAlert.value = {
        show: true,
        message: `${dest.name} is now the default backup destination.`,
        type: 'success'
      }
    }
  })
}

const deleteDestination = (dest) => {
  if (!confirm(`Are you sure you want to remove ${dest.name}? Backups already created will retain their history.`)) {
    return
  }
  router.delete(`/backups/destinations/${dest.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      localAlert.value = {
        show: true,
        message: `${dest.name} was removed.`,
        type: 'success'
      }
    }
  })
}

const retryUpload = (backup) => {
  retryingBackupId.value = backup.id
  router.post(`/backups/${backup.id}/retry-upload`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      retryingBackupId.value = null
      localAlert.value = {
        show: true,
        message: `Offsite upload retry completed for ${backup.file_name || backup.id}.`,
        type: 'success'
      }
    },
    onError: () => {
      retryingBackupId.value = null
    },
    onFinish: () => {
      retryingBackupId.value = null
    }
  })
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
    destination_id: backupForm.value.destination_id || null,
  }

  router.post('/backups', payload, {
    onSuccess: () => {
      getModalInstance('modalCreateBackup')?.hide()
      isCreatingBackup.value = false
      startPolling()
    },
    onError: () => {
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
    destination_id: scheduleForm.value.destination_id || null,
  }

  if (scheduleForm.value.id) {
    router.put(`/backups/schedules/${scheduleForm.value.id}`, payload, {
      onSuccess: () => {
        getModalInstance('modalSchedule')?.hide()
        isSavingSchedule.value = false
      },
      onFinish: () => { isSavingSchedule.value = false }
    })
  } else {
    router.post('/backups/schedules', payload, {
      onSuccess: () => {
        getModalInstance('modalSchedule')?.hide()
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

// Perform Run Schedule Now
const performRunScheduleNow = () => {
  if (!selectedScheduleToRun.value) return
  const schedId = selectedScheduleToRun.value.id
  isRunningSchedule.value = true
  runningScheduleId.value = schedId

  router.post(`/backups/schedules/${schedId}/run`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      getModalInstance('modalRunSchedule')?.hide()
      isRunningSchedule.value = false
      startPolling()
    },
    onError: () => {
      isRunningSchedule.value = false
      runningScheduleId.value = null
    },
    onFinish: () => {
      isRunningSchedule.value = false
    }
  })
}

// Perform Delete Schedule
const performDeleteSchedule = () => {
  if (!selectedScheduleToDelete.value) return
  isDeletingSchedule.value = true

  router.delete(`/backups/schedules/${selectedScheduleToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      getModalInstance('modalDeleteSchedule')?.hide()
      isDeletingSchedule.value = false
    },
    onFinish: () => {
      isDeletingSchedule.value = false
    }
  })
}

// Perform Delete Backup
const performDeleteBackup = () => {
  if (!selectedBackupToDelete.value) return
  isDeletingBackup.value = true

  router.delete(`/backups/${selectedBackupToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      getModalInstance('modalDeleteBackup')?.hide()
      isDeletingBackup.value = false
    },
    onFinish: () => {
      isDeletingBackup.value = false
    }
  })
}

// Perform Restore
const performRestore = () => {
  if (!selectedRestoreBackup.value) return
  isRestoring.value = true

  router.post(`/backups/${selectedRestoreBackup.value.id}/restore`, {
    create_snapshot_before_restore: createSnapshotBeforeRestore.value
  }, {
    onSuccess: () => {
      getModalInstance('modalRestore')?.hide()
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
const defaultDestinationName = computed(() => {
  const d = props.destinations.find(dest => dest.is_default)
  return d ? d.name : 'Local Server Storage'
})

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

const formatTypeLabel = (type) => {
  if (type === 'database') return 'Database Only'
  if (type === 'files') return 'Files Only'
  return 'Full Site (Both)'
}

const getDayName = (day) => {
  const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
  return days[day] || 'Sunday'
}

const setScheduleTargetType = (type) => {
  scheduleForm.value.targetType = type
  if (type === 'database') {
    scheduleForm.value.type = 'database'
    if (!scheduleForm.value.database_name && props.databases.length > 0) {
      scheduleForm.value.database_name = props.databases[0]
    }
  } else {
    scheduleForm.value.type = 'full'
    if (!scheduleForm.value.domain && props.domains.length > 0) {
      scheduleForm.value.domain = props.domains[0].domain
    }
  }
}

const setBackupScope = (scope) => {
  backupForm.value.scope = scope
  if (scope === 'database') {
    backupForm.value.type = 'database'
    if (!backupForm.value.database_name && props.databases.length > 0) {
      backupForm.value.database_name = props.databases[0]
    }
  } else {
    backupForm.value.type = 'full'
    if (!backupForm.value.domain && props.domains.length > 0) {
      backupForm.value.domain = props.domains[0].domain
    }
  }
}

const onScheduleTargetTypeChange = () => {
  setScheduleTargetType(scheduleForm.value.targetType)
}

const getDriverLabel = (driver) => {
  const map = {
    local: 'Local Storage',
    b2: 'Backblaze B2',
    google_drive: 'Google Drive',
    r2: 'Cloudflare R2',
    wasabi: 'Wasabi Hot Cloud',
    s3: 'AWS S3',
    custom_s3: 'Custom S3'
  }
  return map[driver] || driver?.toUpperCase()
}

const getDestinationIcon = (dest) => {
  if (!dest) return 'hard_drive'
  const d = typeof dest === 'string' ? dest : dest.driver
  if (d === 'google_drive') return 'add_to_drive'
  if (d === 'b2') return 'cloud'
  if (d === 'r2') return 'cloud_done'
  if (d === 'wasabi') return 'cloud_queue'
  if (d === 's3') return 'cloud_sync'
  if (d === 'custom_s3') return 'dns'
  return 'hard_drive'
}

const getDestinationBadgeBg = (dest) => {
  if (!dest) return 'bg-gradient-secondary'
  const d = typeof dest === 'string' ? dest : dest.driver
  if (d === 'google_drive') return 'bg-gradient-warning'
  if (d === 'b2') return 'bg-gradient-danger'
  if (d === 'r2') return 'bg-gradient-info'
  if (d === 'wasabi') return 'bg-gradient-success'
  if (d === 's3') return 'bg-gradient-primary'
  return 'bg-gradient-dark'
}

const getDestinationBadgeClass = (dest) => {
  if (!dest) return 'badge bg-light text-secondary border'
  const d = typeof dest === 'string' ? dest : dest.driver
  if (d === 'google_drive') return 'badge bg-warning text-dark'
  if (d === 'b2') return 'badge bg-danger text-white'
  if (d === 'r2') return 'badge bg-info text-white'
  if (d === 'wasabi') return 'badge bg-success text-white'
  if (d === 's3') return 'badge bg-primary text-white'
  return 'badge bg-secondary text-white'
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
.spin-icon {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.badge-xs {
  font-size: 0.70rem !important;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.badge-db {
  background-color: #e0f2fe !important;
  color: #0284c7 !important;
  border-color: #bae6fd !important;
}

.badge-files {
  background-color: #f1f5f9 !important;
  color: #475569 !important;
  border-color: #cbd5e1 !important;
}

.badge-full {
  background-color: #f3e8ff !important;
  color: #7e22ce !important;
  border-color: #e9d5ff !important;
}

.bg-warning-subtle {
  background-color: #fef3c7 !important;
  color: #b45309 !important;
}
.border-warning-subtle {
  border-color: #fde68a !important;
}

.bg-success-subtle {
  background-color: #dcfce7 !important;
  color: #15803d !important;
}
.border-success-subtle {
  border-color: #bbf7d0 !important;
}

.custom-form-input, .custom-form-select {
  border: 1px solid #d2d6da !important;
  border-radius: 0.5rem !important;
  padding: 0.55rem 0.85rem !important;
  font-size: 0.875rem !important;
  color: #344767 !important;
  background-color: #fff !important;
  box-shadow: none !important;
  transition: all 0.2s ease-in-out;
}
.custom-form-input:focus, .custom-form-select:focus {
  border-color: #1a73e8 !important;
  box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2) !important;
}
.selection-card {
  border: 1px solid #e9ecef !important;
  border-radius: 0.6rem !important;
  transition: all 0.2s ease;
  background: #fff;
}
.selection-card:hover {
  border-color: #adb5bd !important;
  transform: translateY(-1px);
}
.selection-card.active-card {
  border-color: #1a73e8 !important;
  background: #f0f7ff !important;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
}
</style>
