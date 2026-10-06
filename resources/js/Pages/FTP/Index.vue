<template>
  <MainLayout>
    <Head title="FTP Accounts" />
    <div class="py-2">

      <!-- Page Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <h4 class="font-weight-bolder mb-0">FTP Accounts</h4>
                <span class="badge bg-gradient-info text-xxs d-inline-flex align-items-center">
                  <i class="material-symbols-rounded text-xxs me-1">lock</i> Pure-FTPd (Isolated)
                </span>
              </div>
              <p class="mb-0 text-sm text-secondary">Create and manage isolated virtual FTP accounts for your websites</p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 w-100 w-md-auto justify-content-start justify-content-md-end">
              <button 
                type="button" 
                class="btn btn-outline-dark mb-0 d-flex align-items-center justify-content-center gap-1 flex-grow-1 flex-md-grow-0"
                @click="showGuideModal = true"
              >
                <i class="material-symbols-rounded text-sm">settings_ethernet</i>
                <span>Connection Guide</span>
              </button>
              <button 
                type="button" 
                class="btn bg-gradient-dark mb-0 d-flex align-items-center justify-content-center gap-1 shadow-sm flex-grow-1 flex-md-grow-0"
                @click="openCreateModal"
              >
                <i class="material-symbols-rounded text-sm">person_add</i>
                <span>New FTP Account</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Live Connection Info Banner -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card shadow-sm border bg-white overflow-hidden">
            <div class="card-body p-3">
              <div class="row g-3 align-items-center">
                
                <!-- Host -->
                <div class="col-12 col-sm-6 col-xl-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="icon icon-shape bg-gradient-primary shadow-primary text-center border-radius-lg d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                      <i class="material-symbols-rounded text-white" style="font-size: 22px;">dns</i>
                    </div>
                    <div class="overflow-hidden">
                      <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Server Host / IP</span>
                      <div class="d-flex align-items-center gap-2">
                        <span class="font-monospace text-sm font-weight-bolder text-dark text-truncate">{{ connectionInfo.host }}</span>
                        <button 
                          type="button" 
                          class="btn-copy-icon flex-shrink-0" 
                          @click="copyText(connectionInfo.host, 'Host copied to clipboard')"
                          title="Copy Host"
                        >
                          <i class="material-symbols-rounded text-xs">content_copy</i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Default Port -->
                <div class="col-12 col-sm-6 col-xl-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="icon icon-shape bg-gradient-success shadow-success text-center border-radius-lg d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                      <i class="material-symbols-rounded text-white" style="font-size: 22px;">cable</i>
                    </div>
                    <div>
                      <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Default Port</span>
                      <span class="font-monospace text-sm font-weight-bolder text-dark">21</span>
                      <span class="text-xxs text-muted ms-1">(Passive: {{ connectionInfo.passive_ports }})</span>
                    </div>
                  </div>
                </div>

                <!-- Encryption -->
                <div class="col-12 col-sm-6 col-xl-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="icon icon-shape bg-gradient-info shadow-info text-center border-radius-lg d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                      <i class="material-symbols-rounded text-white" style="font-size: 22px;">security</i>
                    </div>
                    <div>
                      <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Encryption</span>
                      <span class="badge bg-success-subtle text-success text-xxs font-weight-bold">
                        <i class="material-symbols-rounded text-xxs me-1">verified</i> FTPS (Explicit TLS)
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Service Status -->
                <div class="col-12 col-sm-6 col-xl-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="icon icon-shape bg-gradient-dark text-center border-radius-lg d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                      <i class="material-symbols-rounded text-white" style="font-size: 22px;">power_settings_new</i>
                    </div>
                    <div>
                      <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Service Status</span>
                      <span class="badge text-xxs font-weight-bold" :class="connectionInfo.is_running ? 'bg-success text-white' : 'bg-danger text-white'">
                        <span class="pill-dot me-1"></span>
                        {{ connectionInfo.is_running ? 'Online & Listening' : 'Service Stopped' }}
                      </span>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Flash Notification Toast -->
      <transition name="fade">
        <div v-if="toast.show" class="position-fixed top-3 end-3 border-radius-xl p-3 d-flex align-items-center gap-3 text-white shadow-2xl z-index-modal" 
          :class="toast.type === 'success' ? 'bg-gradient-success' : 'bg-gradient-danger'"
          style="min-width: 320px; max-width: 480px; z-index: 10099;">
          <i class="material-symbols-rounded fs-4">{{ toast.type === 'success' ? 'check_circle' : 'error' }}</i>
          <div class="flex-grow-1">
            <h6 class="text-white font-weight-bolder text-xs mb-0">{{ toast.type === 'success' ? 'Success' : 'Notice' }}</h6>
            <p class="text-white text-xs mb-0 opacity-9">{{ toast.message }}</p>
          </div>
          <button class="btn btn-link text-white p-0 m-0 text-xs" @click="toast.show = false">
            <i class="material-symbols-rounded text-sm">close</i>
          </button>
        </div>
      </transition>

      <!-- Main Accounts Card -->
      <div class="row">
        <div class="col-12">
          <div class="card shadow-sm border">
            <!-- Header Controls: Title & Search -->
            <div class="card-header pb-3 pt-3 border-bottom">
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                <div class="d-flex align-items-center gap-2">
                  <h6 class="mb-0 font-weight-bolder text-dark">Active FTP Accounts</h6>
                  <span class="badge bg-secondary text-white text-xxs">{{ filteredAccounts.length }}</span>
                </div>

                <div class="d-flex align-items-center gap-2 w-100 w-sm-auto ms-sm-auto">
                  <div class="input-group input-group-sm w-100" style="min-width: 180px; max-width: 260px;">
                    <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                    <input 
                      v-model="searchQuery" 
                      type="text" 
                      class="form-control" 
                      placeholder="Search accounts or domains..."
                    >
                    <button v-if="searchQuery" class="btn btn-link text-secondary p-0 px-2 mb-0" type="button" @click="searchQuery = ''">
                      <i class="material-symbols-rounded text-xs">close</i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Table Body -->
            <div class="card-body px-0 pt-0 pb-2">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="min-width: 200px;">
                        FTP Username
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Website / Domain
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2" style="min-width: 220px;">
                        Chrooted Home Directory
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Disk Quota
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Status
                      </th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="min-width: 170px;">
                        Actions
                      </th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="acc in filteredAccounts" :key="acc.id">
                      <!-- Username -->
                      <td>
                        <div class="d-flex align-items-center px-3 py-2">
                          <div class="icon-avatar me-2 bg-gradient-light border d-flex align-items-center justify-content-center rounded-circle" style="width: 34px; height: 34px;">
                            <i class="material-symbols-rounded text-dark" style="font-size: 18px;">person</i>
                          </div>
                          <div class="d-flex flex-column">
                            <div class="d-flex align-items-center gap-1.5">
                              <span class="text-xs font-weight-bold text-dark font-monospace">{{ acc.username }}</span>
                              <button 
                                type="button" 
                                class="btn-copy-icon" 
                                @click="copyText(acc.username, 'Username copied')" 
                                title="Copy username"
                              >
                                <i class="material-symbols-rounded text-xxs">content_copy</i>
                              </button>
                            </div>
                            <span v-if="acc.notes" class="text-xxs text-muted text-truncate" style="max-width: 180px;">{{ acc.notes }}</span>
                          </div>
                        </div>
                      </td>

                      <!-- Domain -->
                      <td>
                        <span class="badge bg-gray-100 text-dark border text-xxs font-weight-bold d-inline-flex align-items-center gap-1">
                          <i class="material-symbols-rounded text-xxs text-primary">language</i>
                          {{ acc.domain }}
                        </span>
                      </td>

                      <!-- Home Directory -->
                      <td>
                        <div class="d-flex align-items-center gap-1.5">
                          <span class="text-xs text-secondary font-monospace text-truncate" style="max-width: 220px;" :title="acc.homedir">
                            {{ acc.homedir }}
                          </span>
                          <button 
                            type="button" 
                            class="btn-copy-icon" 
                            @click="copyText(acc.homedir, 'Directory path copied')" 
                            title="Copy directory path"
                          >
                            <i class="material-symbols-rounded text-xxs">content_copy</i>
                          </button>
                          <a 
                            :href="`/file-manager/${acc.domain}`" 
                            target="_blank" 
                            class="btn-copy-icon" 
                            title="Open in File Manager"
                          >
                            <i class="material-symbols-rounded text-xxs text-info">folder_open</i>
                          </a>
                        </div>
                      </td>

                      <!-- Quota -->
                      <td>
                        <div class="d-flex align-items-center gap-1">
                          <span v-if="!acc.quota_mb" class="badge bg-light text-muted border text-xxs">
                            Unlimited
                          </span>
                          <span v-else class="text-xs font-weight-bold text-dark font-monospace">
                            {{ acc.quota_mb }} MB
                          </span>
                          <button 
                            type="button" 
                            class="btn-edit-action" 
                            @click="openQuotaModal(acc)"
                            title="Change disk quota"
                          >
                            <i class="material-symbols-rounded text-xs">edit</i>
                          </button>
                        </div>
                      </td>

                      <!-- Status -->
                      <td>
                        <button 
                          type="button" 
                          class="status-toggle-badge border-0 bg-transparent p-0 cursor-pointer"
                          @click="toggleStatus(acc)"
                          :title="acc.is_active ? 'Click to suspend FTP account' : 'Click to activate FTP account'"
                          :disabled="actionLoading === acc.id"
                        >
                          <span v-if="actionLoading === acc.id" class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span>
                          <span v-else-if="acc.is_active" class="status-pill status-active">
                            <span class="pill-dot"></span>
                            Active
                          </span>
                          <span v-else class="status-pill status-suspended">
                            <span class="pill-dot"></span>
                            Suspended
                          </span>
                        </button>
                      </td>

                      <!-- Actions -->
                      <td class="align-middle text-center">
                        <div class="d-flex justify-content-center gap-2">
                          <button 
                            type="button" 
                            class="action-btn btn-key" 
                            @click="openPasswordModal(acc)"
                            title="Change Password"
                          >
                            <i class="material-symbols-rounded">vpn_key</i>
                          </button>

                          <button 
                            type="button" 
                            class="action-btn btn-info" 
                            @click="openAccountGuide(acc)"
                            title="Connection Settings"
                          >
                            <i class="material-symbols-rounded">info</i>
                          </button>

                          <button 
                            type="button" 
                            class="action-btn btn-delete" 
                            @click="confirmDelete(acc)"
                            title="Delete FTP Account"
                          >
                            <i class="material-symbols-rounded">delete</i>
                          </button>
                        </div>
                      </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="filteredAccounts.length === 0">
                      <td colspan="6" class="text-center py-5">
                        <div class="d-flex flex-column align-items-center">
                          <div class="icon-empty-state mb-3">
                            <i class="material-symbols-rounded text-secondary opacity-4" style="font-size: 52px;">cloud_upload</i>
                          </div>
                          <h6 class="text-dark font-weight-bold mb-1">No FTP accounts found</h6>
                          <p class="text-secondary text-xs mb-3">
                            {{ searchQuery ? `No accounts matched "${searchQuery}"` : 'Create your first FTP account to enable secure file transfers.' }}
                          </p>
                          <button 
                            v-if="!searchQuery" 
                            type="button" 
                            class="btn btn-sm bg-gradient-dark mb-0 d-flex align-items-center gap-1"
                            @click="openCreateModal"
                          >
                            <i class="material-symbols-rounded text-sm">person_add</i>
                            Create FTP Account
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

    </div>

    <!-- ========================================== -->
    <!-- MODAL 1: CREATE FTP ACCOUNT                -->
    <!-- ========================================== -->
    <div v-if="showCreateModal" class="modal-backdrop fade show"></div>
    <div v-if="showCreateModal" class="modal fade show d-block" tabindex="-1" role="dialog" @click.self="showCreateModal = false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-2xl border-0 border-radius-xl overflow-hidden">
          
          <div class="modal-header bg-gradient-dark text-white p-3">
            <div class="d-flex align-items-center gap-2">
              <i class="material-symbols-rounded text-info">person_add</i>
              <h6 class="modal-title font-weight-bold text-white mb-0">Create New FTP Account</h6>
            </div>
            <button type="button" class="btn-close text-white" @click="showCreateModal = false" :disabled="formSubmitting"></button>
          </div>

          <form @submit.prevent="submitCreate">
            <div class="modal-body p-3 p-sm-4">
              
              <!-- Domain Picker -->
              <div class="mb-3">
                <label class="form-label text-xs text-uppercase font-weight-bold text-secondary">Target Domain / Website <span class="text-danger">*</span></label>
                <select 
                  v-model="createForm.domain" 
                  class="form-select border-radius-md"
                  required
                  @change="onDomainChange"
                >
                  <option value="" disabled>Select a domain...</option>
                  <option v-for="d in domains" :key="d" :value="d">
                    🌐 {{ d }}
                  </option>
                </select>
                <small class="text-xxs text-muted">The user will be isolated strictly to files within this website.</small>
              </div>

              <!-- Username -->
              <div class="mb-3">
                <label class="form-label text-xs text-uppercase font-weight-bold text-secondary">FTP Username <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text"><i class="material-symbols-rounded text-sm">badge</i></span>
                  <input 
                    v-model="createForm.username" 
                    type="text" 
                    class="form-control" 
                    placeholder="e.g. dev_client"
                    required
                    pattern="[a-zA-Z0-9_\-\.]+"
                  >
                </div>
                <small class="text-xxs text-muted">Allowed characters: letters, numbers, underscores, dashes, and periods.</small>
              </div>

              <!-- Password -->
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label text-xs text-uppercase font-weight-bold text-secondary mb-0">Password <span class="text-danger">*</span></label>
                  <button 
                    type="button" 
                    class="btn-auto-gen" 
                    @click="generatePassword('create')"
                    title="Generate strong password and copy to clipboard"
                  >
                    <i class="material-symbols-rounded text-xs me-1">auto_awesome</i>
                    Auto-Generate & Copy
                  </button>
                </div>
                <div class="input-group">
                  <span class="input-group-text"><i class="material-symbols-rounded text-sm">lock</i></span>
                  <input 
                    :type="showCreatePassword ? 'text' : 'password'" 
                    v-model="createForm.password" 
                    class="form-control" 
                    placeholder="Enter or generate password"
                    required
                    minlength="6"
                  >
                  <button 
                    type="button" 
                    class="btn btn-outline-secondary mb-0 px-2.5" 
                    @click="showCreatePassword = !showCreatePassword"
                  >
                    <i class="material-symbols-rounded text-sm">{{ showCreatePassword ? 'visibility_off' : 'visibility' }}</i>
                  </button>
                </div>
              </div>

              <!-- Directory Picker (Radio presets) -->
              <div class="mb-3">
                <label class="form-label text-xs text-uppercase font-weight-bold text-secondary">Directory Access Scope</label>
                
                <div class="dir-preset-options d-flex flex-column gap-2 mb-2">
                  <label class="dir-preset-card p-2.5 border rounded-3 d-flex align-items-center gap-2.5 cursor-pointer" :class="{ selected: dirPreset === 'root' }">
                    <input type="radio" v-model="dirPreset" value="root" @change="applyDirPreset" class="form-check-input mt-0">
                    <div>
                      <span class="text-xs font-weight-bold text-dark d-block">Full Project Root</span>
                      <span class="text-xxs text-muted font-monospace">/var/www/{{ createForm.domain || 'domain.com' }}</span>
                    </div>
                  </label>

                  <label class="dir-preset-card p-2.5 border rounded-3 d-flex align-items-center gap-2.5 cursor-pointer" :class="{ selected: dirPreset === 'public' }">
                    <input type="radio" v-model="dirPreset" value="public" @change="applyDirPreset" class="form-check-input mt-0">
                    <div>
                      <span class="text-xs font-weight-bold text-dark d-block">Public Web Root Only (/public)</span>
                      <span class="text-xxs text-muted font-monospace">/var/www/{{ createForm.domain || 'domain.com' }}/public</span>
                    </div>
                  </label>

                  <label class="dir-preset-card p-2.5 border rounded-3 d-flex align-items-center gap-2.5 cursor-pointer" :class="{ selected: dirPreset === 'custom' }">
                    <input type="radio" v-model="dirPreset" value="custom" @change="applyDirPreset" class="form-check-input mt-0">
                    <div>
                      <span class="text-xs font-weight-bold text-dark d-block">Custom Directory</span>
                      <span class="text-xxs text-muted">Specify a custom subdirectory path</span>
                    </div>
                  </label>
                </div>

                <!-- Custom path input -->
                <div v-if="dirPreset === 'custom'" class="input-group input-group-sm mt-1">
                  <span class="input-group-text"><i class="material-symbols-rounded text-sm">folder</i></span>
                  <input 
                    v-model="createForm.homedir" 
                    type="text" 
                    class="form-control font-monospace text-xs" 
                    required
                  >
                </div>
              </div>

              <!-- Quota & Notes Row -->
              <div class="row">
                <div class="col-sm-6 mb-3">
                  <label class="form-label text-xs text-uppercase font-weight-bold text-secondary">Disk Quota (MB)</label>
                  <input 
                    v-model="createForm.quota_mb" 
                    type="number" 
                    class="form-control" 
                    placeholder="Leave blank for Unlimited"
                    min="0"
                  >
                  <small class="text-xxs text-muted">0 or blank = Unlimited storage.</small>
                </div>

                <div class="col-sm-6 mb-3">
                  <label class="form-label text-xs text-uppercase font-weight-bold text-secondary">Notes (Optional)</label>
                  <input 
                    v-model="createForm.notes" 
                    type="text" 
                    class="form-control" 
                    placeholder="e.g. Freelancer / Designer"
                    maxlength="100"
                  >
                </div>
              </div>

            </div>

            <div class="modal-footer p-3 bg-gray-50 border-top d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-outline-secondary mb-0" @click="showCreateModal = false" :disabled="formSubmitting">
                Cancel
              </button>
              <button type="submit" class="btn bg-gradient-dark mb-0 d-flex align-items-center gap-1" :disabled="formSubmitting">
                <span v-if="formSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm">check</i>
                Create Account
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: RESET PASSWORD                   -->
    <!-- ========================================== -->
    <div v-if="showPasswordModal" class="modal-backdrop fade show"></div>
    <div v-if="showPasswordModal" class="modal fade show d-block" tabindex="-1" role="dialog" @click.self="showPasswordModal = false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-2xl border-0 border-radius-xl overflow-hidden">
          
          <div class="modal-header bg-gradient-dark text-white p-3">
            <div class="d-flex align-items-center gap-2">
              <i class="material-symbols-rounded text-warning">vpn_key</i>
              <h6 class="modal-title font-weight-bold text-white mb-0">Reset Password for {{ activeAccount?.username }}</h6>
            </div>
            <button type="button" class="btn-close text-white" @click="showPasswordModal = false" :disabled="formSubmitting"></button>
          </div>

          <form @submit.prevent="submitPasswordUpdate">
            <div class="modal-body p-3 p-sm-4">
              
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label text-xs text-uppercase font-weight-bold text-secondary mb-0">New Password <span class="text-danger">*</span></label>
                  <button 
                    type="button" 
                    class="btn-auto-gen" 
                    @click="generatePassword('reset')"
                    title="Generate strong password and copy"
                  >
                    <i class="material-symbols-rounded text-xs me-1">auto_awesome</i>
                    Auto-Generate & Copy
                  </button>
                </div>
                <div class="input-group">
                  <span class="input-group-text"><i class="material-symbols-rounded text-sm">lock</i></span>
                  <input 
                    :type="showResetPassword ? 'text' : 'password'" 
                    v-model="passwordForm.password" 
                    class="form-control" 
                    placeholder="Enter new password"
                    required
                    minlength="6"
                  >
                  <button 
                    type="button" 
                    class="btn btn-outline-secondary mb-0 px-2.5" 
                    @click="showResetPassword = !showResetPassword"
                  >
                    <i class="material-symbols-rounded text-sm">{{ showResetPassword ? 'visibility_off' : 'visibility' }}</i>
                  </button>
                </div>
                <small class="text-xxs text-muted">Password must be at least 6 characters.</small>
              </div>

            </div>

            <div class="modal-footer p-3 bg-gray-50 border-top d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-outline-secondary mb-0" @click="showPasswordModal = false" :disabled="formSubmitting">
                Cancel
              </button>
              <button type="submit" class="btn bg-gradient-dark mb-0 d-flex align-items-center gap-1" :disabled="formSubmitting">
                <span v-if="formSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="material-symbols-rounded text-sm">lock_reset</i>
                Update Password
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 3: EDIT QUOTA                       -->
    <!-- ========================================== -->
    <div v-if="showQuotaModal" class="modal-backdrop fade show"></div>
    <div v-if="showQuotaModal" class="modal fade show d-block" tabindex="-1" role="dialog" @click.self="showQuotaModal = false">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-2xl border-0 border-radius-xl overflow-hidden">
          
          <div class="modal-header bg-gradient-dark text-white p-3">
            <div class="d-flex align-items-center gap-2">
              <i class="material-symbols-rounded text-info">storage</i>
              <h6 class="modal-title font-weight-bold text-white mb-0">Edit Disk Quota</h6>
            </div>
            <button type="button" class="btn-close text-white" @click="showQuotaModal = false" :disabled="formSubmitting"></button>
          </div>

          <form @submit.prevent="submitQuotaUpdate">
            <div class="modal-body p-3 p-sm-4">
              <p class="text-xs text-secondary mb-3">Adjust disk storage quota for <strong>{{ activeAccount?.username }}</strong>.</p>
              
              <div class="mb-3">
                <label class="form-label text-xs text-uppercase font-weight-bold text-secondary">Disk Quota (MB)</label>
                <input 
                  v-model="quotaForm.quota_mb" 
                  type="number" 
                  class="form-control" 
                  placeholder="0 or blank for Unlimited"
                  min="0"
                >
                <small class="text-xxs text-muted">Enter 0 or leave empty for unlimited storage.</small>
              </div>
            </div>

            <div class="modal-footer p-3 bg-gray-50 border-top d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-outline-secondary mb-0" @click="showQuotaModal = false" :disabled="formSubmitting">
                Cancel
              </button>
              <button type="submit" class="btn bg-gradient-dark mb-0 d-flex align-items-center gap-1" :disabled="formSubmitting">
                <span v-if="formSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Save Quota
              </button>
            </div>
          </form>

        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 4: CONNECTION GUIDE (CLIENT CONFIG)  -->
    <!-- ========================================== -->
    <div v-if="showGuideModal" class="modal-backdrop fade show"></div>
    <div v-if="showGuideModal" class="modal fade show d-block" tabindex="-1" role="dialog" @click.self="showGuideModal = false">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-2xl border-0 border-radius-xl overflow-hidden">
          
          <div class="modal-header bg-gradient-dark text-white p-3">
            <div class="d-flex align-items-center gap-2">
              <i class="material-symbols-rounded text-info">settings_ethernet</i>
              <h6 class="modal-title font-weight-bold text-white mb-0">FTP Client Configuration Guide</h6>
            </div>
            <button type="button" class="btn-close text-white" @click="showGuideModal = false"></button>
          </div>

          <div class="modal-body p-3 p-sm-4">
            
            <!-- Connection Details Card -->
            <div class="bg-gray-100 p-3 rounded-3 border mb-3">
              <h6 class="text-xs text-uppercase font-weight-bolder text-dark mb-2">Connection Credentials</h6>
              
              <div class="row g-2">
                <div class="col-sm-6">
                  <div class="bg-white p-2 rounded-2 border d-flex justify-content-between align-items-center">
                    <div>
                      <span class="text-xxs text-muted d-block">Host / Server:</span>
                      <span class="font-monospace text-xs font-weight-bold text-dark">{{ connectionInfo.host }}</span>
                    </div>
                    <button class="btn-copy-icon" @click="copyText(connectionInfo.host, 'Host copied')">
                      <i class="material-symbols-rounded text-xs">content_copy</i>
                    </button>
                  </div>
                </div>

                <div class="col-sm-6">
                  <div class="bg-white p-2 rounded-2 border d-flex justify-content-between align-items-center">
                    <div>
                      <span class="text-xxs text-muted d-block">Port:</span>
                      <span class="font-monospace text-xs font-weight-bold text-dark">21</span>
                    </div>
                    <button class="btn-copy-icon" @click="copyText('21', 'Port copied')">
                      <i class="material-symbols-rounded text-xs">content_copy</i>
                    </button>
                  </div>
                </div>

                <div class="col-sm-6">
                  <div class="bg-white p-2 rounded-2 border d-flex justify-content-between align-items-center">
                    <div>
                      <span class="text-xxs text-muted d-block">Username:</span>
                      <span class="font-monospace text-xs font-weight-bold text-dark">{{ guideAccount ? guideAccount.username : 'Your FTP Username' }}</span>
                    </div>
                    <button v-if="guideAccount" class="btn-copy-icon" @click="copyText(guideAccount.username, 'Username copied')">
                      <i class="material-symbols-rounded text-xs">content_copy</i>
                    </button>
                  </div>
                </div>

                <div class="col-sm-6">
                  <div class="bg-white p-2 rounded-2 border d-flex justify-content-between align-items-center">
                    <div>
                      <span class="text-xxs text-muted d-block">Encryption:</span>
                      <span class="font-monospace text-xs font-weight-bold text-success">Require explicit FTP over TLS</span>
                    </div>
                    <span class="badge bg-success-subtle text-success text-xxs font-weight-bold">FTPS</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Software Configuration Tabs -->
            <h6 class="text-xs text-uppercase font-weight-bolder text-dark mb-2">Supported FTP Clients</h6>
            <div class="client-setup-cards d-flex flex-column gap-2">
              
              <div class="p-3 border rounded-3 bg-white">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge bg-primary text-white text-xxs">FileZilla</span>
                  <strong class="text-xs text-dark">FileZilla Setup</strong>
                </div>
                <p class="text-xs text-secondary mb-0">
                  Open FileZilla &rarr; <strong>Site Manager</strong> &rarr; <strong>New Site</strong>.<br>
                  Protocol: <strong>FTP - File Transfer Protocol</strong><br>
                  Host: <code class="text-dark">{{ connectionInfo.host }}</code> &bull; Port: <code class="text-dark">21</code><br>
                  Encryption: <strong>Require explicit FTP over TLS</strong><br>
                  Logon Type: <strong>Normal</strong> (enter username and password).
                </p>
              </div>

              <div class="p-3 border rounded-3 bg-white">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge bg-warning text-dark text-xxs">Cyberduck</span>
                  <strong class="text-xs text-dark">Cyberduck Setup</strong>
                </div>
                <p class="text-xs text-secondary mb-0">
                  Click <strong>Open Connection</strong> &rarr; Select <strong>FTP-SSL (Explicit AUTH TLS)</strong>.<br>
                  Server: <code class="text-dark">{{ connectionInfo.host }}</code> &bull; Port: <code class="text-dark">21</code><br>
                  Username: your FTP account username &bull; Password: your password.
                </p>
              </div>

              <div class="p-3 border rounded-3 bg-white">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge bg-info text-white text-xxs">WinSCP</span>
                  <strong class="text-xs text-dark">WinSCP Setup</strong>
                </div>
                <p class="text-xs text-secondary mb-0">
                  File protocol: <strong>FTP</strong> &bull; Encryption: <strong>TLS/SSL Explicit encryption</strong>.<br>
                  Host name: <code class="text-dark">{{ connectionInfo.host }}</code> &bull; Port number: <code class="text-dark">21</code>.
                </p>
              </div>

            </div>

          </div>

          <div class="modal-footer p-3 bg-gray-50 border-top d-flex justify-content-end">
            <button type="button" class="btn bg-gradient-dark mb-0" @click="showGuideModal = false">
              Done
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 5: DELETE CONFIRMATION               -->
    <!-- ========================================== -->
    <div v-if="showDeleteModal" class="modal-backdrop fade show"></div>
    <div v-if="showDeleteModal" class="modal fade show d-block" tabindex="-1" role="dialog" @click.self="showDeleteModal = false">
      <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-2xl border-0 border-radius-xl overflow-hidden">
          
          <div class="modal-header bg-gradient-danger text-white p-3">
            <div class="d-flex align-items-center gap-2">
              <i class="material-symbols-rounded text-white">delete_forever</i>
              <h6 class="modal-title font-weight-bold text-white mb-0">Delete FTP Account</h6>
            </div>
            <button type="button" class="btn-close text-white" @click="showDeleteModal = false" :disabled="formSubmitting"></button>
          </div>

          <div class="modal-body p-3 p-sm-4">
            <div class="d-flex align-items-center gap-3 mb-3 p-3 rounded-3 bg-gray-50 border">
              <div class="icon-avatar bg-danger-subtle text-danger border d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 46px; height: 46px;">
                <i class="material-symbols-rounded fs-3">person_remove</i>
              </div>
              <div class="overflow-hidden">
                <span class="text-xxs text-uppercase text-secondary font-weight-bold d-block">Account to delete</span>
                <h6 class="text-sm font-weight-bold text-dark font-monospace mb-0 text-truncate">{{ accountToDelete?.username }}</h6>
                <span class="text-xxs text-muted d-block text-truncate">{{ accountToDelete?.domain }}</span>
              </div>
            </div>

            <p class="text-sm text-dark mb-3">
              Are you sure you want to permanently delete FTP user <strong class="font-monospace text-danger">{{ accountToDelete?.username }}</strong>?
            </p>

            <div class="alert alert-warning text-white p-2.5 rounded-3 d-flex align-items-start gap-2 mb-0" role="alert">
              <i class="material-symbols-rounded text-sm flex-shrink-0 mt-0.5">shield</i>
              <span class="text-xs">
                <strong>Safe deletion:</strong> Only the FTP login access and credentials will be removed. Your website files inside <code class="text-white font-monospace">{{ accountToDelete?.homedir }}</code> will remain completely intact.
              </span>
            </div>
          </div>

          <div class="modal-footer p-3 bg-gray-50 border-top d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary mb-0" @click="showDeleteModal = false" :disabled="formSubmitting">
              Cancel
            </button>
            <button 
              type="button" 
              class="btn bg-gradient-danger mb-0 d-flex align-items-center gap-1 shadow-sm"
              @click="executeDelete" 
              :disabled="formSubmitting"
            >
              <span v-if="formSubmitting" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="material-symbols-rounded text-sm">delete</i>
              Delete Account
            </button>
          </div>

        </div>
      </div>
    </div>

  </MainLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'

const props = defineProps({
  accounts: { type: Array, default: () => [] },
  connectionInfo: { type: Object, default: () => ({ host: '127.0.0.1', port: 21, passive_ports: '40000 - 40100', is_running: true }) },
  domains: { type: Array, default: () => [] },
})

const searchQuery = ref('')
const formSubmitting = ref(false)
const actionLoading = ref(null)

// Toast Notification
const toast = ref({
  show: false,
  type: 'success',
  message: '',
})

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

// Filtered Accounts
const filteredAccounts = computed(() => {
  if (!searchQuery.value) return props.accounts
  const q = searchQuery.value.toLowerCase().trim()
  return props.accounts.filter(a => 
    a.username.toLowerCase().includes(q) ||
    a.domain.toLowerCase().includes(q) ||
    a.homedir.toLowerCase().includes(q) ||
    (a.notes && a.notes.toLowerCase().includes(q))
  )
})

// Create Modal State
const showCreateModal = ref(false)
const showCreatePassword = ref(false)
const dirPreset = ref('root')
const createForm = ref({
  domain: '',
  username: '',
  password: '',
  homedir: '',
  quota_mb: '',
  notes: '',
})

const openCreateModal = () => {
  const firstDomain = props.domains.length > 0 ? props.domains[0] : ''
  createForm.value = {
    domain: firstDomain,
    username: firstDomain ? `ftp_${firstDomain.replace(/[^a-zA-Z0-9]/g, '_').slice(0, 10)}` : '',
    password: '',
    homedir: firstDomain ? `/var/www/${firstDomain}` : '',
    quota_mb: '',
    notes: '',
  }
  dirPreset.value = 'root'
  showCreatePassword.value = false
  showCreateModal.value = true
}

const onDomainChange = () => {
  if (!createForm.value.domain) return
  if (!createForm.value.username || createForm.value.username.startsWith('ftp_')) {
    createForm.value.username = `ftp_${createForm.value.domain.replace(/[^a-zA-Z0-9]/g, '_').slice(0, 10)}`
  }
  applyDirPreset()
}

const applyDirPreset = () => {
  const d = createForm.value.domain || 'domain'
  if (dirPreset.value === 'root') {
    createForm.value.homedir = `/var/www/${d}`
  } else if (dirPreset.value === 'public') {
    createForm.value.homedir = `/var/www/${d}/public`
  }
}

const submitCreate = () => {
  formSubmitting.value = true
  router.post('/ftp', createForm.value, {
    onSuccess: (page) => {
      formSubmitting.value = false
      if (page.props.flash?.error) {
        showToast(page.props.flash.error, 'error')
        return
      }
      showCreateModal.value = false
      showToast('FTP account created successfully!')
    },
    onError: (errors) => {
      formSubmitting.value = false
      const first = Object.values(errors)[0]
      showToast(first || 'Failed to create account', 'error')
    }
  })
}

// Password Reset Modal State
const showPasswordModal = ref(false)
const showResetPassword = ref(false)
const activeAccount = ref(null)
const passwordForm = ref({ password: '' })

const openPasswordModal = (account) => {
  activeAccount.value = account
  passwordForm.value.password = ''
  showResetPassword.value = false
  showPasswordModal.value = true
}

const submitPasswordUpdate = () => {
  if (!activeAccount.value) return
  formSubmitting.value = true
  router.put(`/ftp/${activeAccount.value.id}/password`, passwordForm.value, {
    onSuccess: (page) => {
      formSubmitting.value = false
      if (page.props.flash?.error) {
        showToast(page.props.flash.error, 'error')
        return
      }
      showPasswordModal.value = false
      showToast(`Password updated for ${activeAccount.value.username}`)
    },
    onError: (errors) => {
      formSubmitting.value = false
      const first = Object.values(errors)[0]
      showToast(first || 'Failed to update password', 'error')
    }
  })
}

// Quota Modal State
const showQuotaModal = ref(false)
const quotaForm = ref({ quota_mb: '' })

const openQuotaModal = (account) => {
  activeAccount.value = account
  quotaForm.value.quota_mb = account.quota_mb || ''
  showQuotaModal.value = true
}

const submitQuotaUpdate = () => {
  if (!activeAccount.value) return
  formSubmitting.value = true
  router.put(`/ftp/${activeAccount.value.id}/quota`, quotaForm.value, {
    onSuccess: (page) => {
      formSubmitting.value = false
      if (page.props.flash?.error) {
        showToast(page.props.flash.error, 'error')
        return
      }
      showQuotaModal.value = false
      showToast('Quota updated successfully')
    },
    onError: (errors) => {
      formSubmitting.value = false
      showToast('Failed to update quota', 'error')
    }
  })
}

// Toggle Status
const toggleStatus = (account) => {
  actionLoading.value = account.id
  router.post(`/ftp/${account.id}/toggle-status`, {}, {
    onSuccess: (page) => {
      actionLoading.value = null
      if (page.props.flash?.error) {
        showToast(page.props.flash.error, 'error')
        return
      }
      showToast(`Account ${account.is_active ? 'suspended' : 'activated'}`)
    },
    onError: () => {
      actionLoading.value = null
      showToast('Failed to change status', 'error')
    }
  })
}

// Delete Modal State & Handlers
const showDeleteModal = ref(false)
const accountToDelete = ref(null)

const confirmDelete = (account) => {
  accountToDelete.value = account
  showDeleteModal.value = true
}

const executeDelete = () => {
  if (!accountToDelete.value) return
  formSubmitting.value = true
  const username = accountToDelete.value.username

  router.delete(`/ftp/${accountToDelete.value.id}`, {
    onSuccess: (page) => {
      formSubmitting.value = false
      showDeleteModal.value = false
      accountToDelete.value = null
      if (page.props.flash?.error) {
        showToast(page.props.flash.error, 'error')
        return
      }
      showToast(`FTP account "${username}" deleted successfully`)
    },
    onError: () => {
      formSubmitting.value = false
      showToast('Failed to delete account', 'error')
    }
  })
}

// Connection Guide Modal
const showGuideModal = ref(false)
const guideAccount = ref(null)

const openAccountGuide = (account) => {
  guideAccount.value = account
  showGuideModal.value = true
}

// Utility: Generate Password
const generatePassword = (target) => {
  const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*'
  let pwd = ''
  for (let i = 0; i < 14; i++) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length))
  }

  if (target === 'create') {
    createForm.value.password = pwd
    showCreatePassword.value = true
  } else {
    passwordForm.value.password = pwd
    showResetPassword.value = true
  }

  copyText(pwd, 'Password generated and copied to clipboard!')
}

// Utility: Copy text
const copyText = (text, message = 'Copied to clipboard') => {
  if (!text) return
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text)
    showToast(message)
  }
}
</script>

<style scoped>
.btn-copy-icon {
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn-copy-icon:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.btn-edit-action {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 1px 4px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s;
}

.btn-edit-action:hover {
  color: #3b82f6;
  background: #eff6ff;
}

.btn-auto-gen {
  background: transparent;
  border: none;
  color: #6366f1;
  font-size: 0.72rem;
  font-weight: 700;
  cursor: pointer;
  padding: 2px 6px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s;
}

.btn-auto-gen:hover {
  background: #eef2ff;
  color: #4f46e5;
}

.action-btn {
  width: 32px;
  height: 32px;
  min-width: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  background: #f8fafc;
  color: #475569;
  transition: all 0.2s;
}

.action-btn:hover {
  transform: translateY(-1px);
}

.btn-key:hover {
  background: #fef3c7;
  color: #b45309;
}

.btn-info:hover {
  background: #e0f2fe;
  color: #0284c7;
}

.btn-delete:hover {
  background: #fee2e2;
  color: #dc2626;
}

.dir-preset-card {
  transition: all 0.2s;
  background: #ffffff;
}

.dir-preset-card:hover {
  border-color: #cbd5e1 !important;
  background: #f8fafc;
}

.dir-preset-card.selected {
  border-color: #3b82f6 !important;
  background: #eff6ff;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
