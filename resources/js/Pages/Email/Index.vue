<template>
  <MainLayout>
    <Head title="Email Management" />
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">Mail Server Management</h4>
              <p class="mb-0 text-sm text-secondary">Configure virtual email hosting, manage secure user accounts, and setup forwarders.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Alert Messages -->
      <div class="row" v-if="alert.show">
        <div class="col-12">
          <div :class="`alert alert-${alert.type} alert-dismissible text-white fade show`" role="alert" style="border: none;">
            <div class="d-flex align-items-center">
              <span class="alert-icon me-2">
                <i class="material-symbols-rounded text-white" style="vertical-align: middle;">
                  {{ alert.type === 'success' ? 'check_circle' : (alert.type === 'danger' ? 'error' : 'info') }}
                </i>
              </span>
              <span class="alert-text"><strong>{{ alert.type === 'success' ? 'Success!' : 'Notice:' }}</strong> {{ alert.message }}</span>
              <button type="button" class="btn-close text-white ms-auto font-weight-bold" @click="alert.show = false" style="background: none; border: none; font-size: 1.2rem; line-height: 1;">&times;</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Loader state -->
      <div v-if="loading" class="row py-5">
        <div class="col-12 text-center">
          <div class="spinner-border text-dark" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-secondary mt-3">Fetching mail server status...</p>
        </div>
      </div>

      <!-- NOT INSTALLED: Setup Wizard -->
      <div v-else-if="!status.installed" class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card shadow-lg border-radius-xl">
            <div class="card-header bg-gradient-dark text-white p-4">
              <h5 class="text-white mb-1 font-weight-bold d-flex align-items-center">
                <i class="material-symbols-rounded me-2 text-warning" style="font-size: 1.8rem;">rocket_launch</i> Setup Nimbus Mail Server
              </h5>
              <p class="text-white opacity-8 mb-0 text-sm">
                Install and auto-configure Postfix (SMTP), Dovecot (IMAP/POP3), and Roundcube (Webmail) directly on your machine.
              </p>
            </div>
            <div class="card-body p-4">
              <div v-if="!installing && !installIsComplete && !installIsFailed">
                <div class="alert alert-info text-white text-sm mb-4" style="border: none;">
                  <div class="d-flex align-items-center">
                    <i class="material-symbols-rounded me-2 text-lg">info</i>
                    <span>
                      The hostname must be a fully qualified domain name (FQDN) that resolves to this server (e.g., <strong>mail.yourdomain.com</strong>).
                    </span>
                  </div>
                </div>

                <div class="form-group mb-4">
                  <label class="form-control-label font-weight-bold text-xs text-uppercase text-secondary">Mail Server Hostname (FQDN)</label>
                  <div class="input-group input-group-outline mt-1">
                    <input 
                      type="text" 
                      v-model="hostnameInput" 
                      class="form-control"
                      placeholder="mail.example.com"
                      :disabled="installing"
                    >
                  </div>
                  <small class="text-muted text-xs mt-1 d-block">
                    Ensure DNS MX and A records are pointed to this server IP address before you begin.
                  </small>
                </div>

                <button 
                  class="btn bg-gradient-dark btn-lg w-100 mb-0 d-flex align-items-center justify-content-center"
                  @click="triggerInstallation"
                  :disabled="!hostnameInput.trim()"
                >
                  <i class="material-symbols-rounded me-2">construction</i>
                  Begin Installation
                </button>
              </div>

              <!-- Installation Log (Terminal view) -->
              <div v-if="installing || installIsComplete || installIsFailed">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-xs font-weight-bold text-uppercase text-secondary">
                    Installation Progress: 
                    <span v-if="installIsComplete" class="text-success font-weight-bold">Success</span>
                    <span v-else-if="installIsFailed" class="text-danger font-weight-bold">Failed</span>
                    <span v-else class="text-info font-weight-bold">Running</span>
                  </span>
                  <div class="spinner-border spinner-border-sm text-dark" v-if="installing && !installIsComplete && !installIsFailed"></div>
                </div>

                <!-- Custom styled log console matching need of user -->
                <div 
                  ref="terminalRef" 
                  class="terminal-output mt-3 p-3 text-white font-monospace text-xs" 
                  style="height: 350px; overflow-y: auto; background-color: #0f172a; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); line-height: 1.5; white-space: pre-wrap;"
                >{{ installLog }}</div>

                <div class="d-flex justify-content-between mt-3">
                  <button 
                    v-if="installing" 
                    class="btn btn-outline-danger mb-0 btn-sm"
                    @click="clearLock"
                  >
                    Force Reset / Clear Lock
                  </button>
                  <div class="ms-auto" v-if="installIsComplete || installIsFailed">
                    <button 
                      class="btn bg-gradient-secondary mb-0 btn-sm" 
                      @click="resetSetup"
                    >
                      Back to Setup
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- INSTALLED: Tabs Dashboard -->
      <div v-else>
        <!-- Navigation Tabs -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="nav-wrapper position-relative end-0">
              <ul class="nav nav-pills nav-pills-primary nav-fill p-1" role="tablist" style="background-color: #e9ecef; border-radius: 0.75rem;">
                <li class="nav-item">
                  <a 
                    class="nav-link mb-0 px-0 py-1 d-flex align-items-center justify-content-center" 
                    :class="{ 'active': activeTab === 'status' }" 
                    @click="changeTab('status')" 
                    href="javascript:;"
                  >
                    <i class="material-symbols-rounded text-sm me-2">dashboard</i>
                    Status & Stats
                  </a>
                </li>
                <li class="nav-item">
                  <a 
                    class="nav-link mb-0 px-0 py-1 d-flex align-items-center justify-content-center" 
                    :class="{ 'active': activeTab === 'domains' }" 
                    @click="changeTab('domains')" 
                    href="javascript:;"
                  >
                    <i class="material-symbols-rounded text-sm me-2">dns</i>
                    Virtual Domains
                  </a>
                </li>
                <li class="nav-item">
                  <a 
                    class="nav-link mb-0 px-0 py-1 d-flex align-items-center justify-content-center" 
                    :class="{ 'active': activeTab === 'accounts' }" 
                    @click="changeTab('accounts')" 
                    href="javascript:;"
                  >
                    <i class="material-symbols-rounded text-sm me-2">mail</i>
                    Email Accounts
                  </a>
                </li>
                <li class="nav-item">
                  <a 
                    class="nav-link mb-0 px-0 py-1 d-flex align-items-center justify-content-center" 
                    :class="{ 'active': activeTab === 'aliases' }" 
                    @click="changeTab('aliases')" 
                    href="javascript:;"
                  >
                    <i class="material-symbols-rounded text-sm me-2">forward_to_inbox</i>
                    Mail Forwarders
                  </a>
                </li>
                <li class="nav-item">
                  <a 
                    class="nav-link mb-0 px-0 py-1 d-flex align-items-center justify-content-center" 
                    :class="{ 'active': activeTab === 'client' }" 
                    @click="changeTab('client')" 
                    href="javascript:;"
                  >
                    <i class="material-symbols-rounded text-sm me-2">settings_ethernet</i>
                    Client Config
                  </a>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Tab 1: Status & Stats -->
        <div v-if="activeTab === 'status'">
          <!-- Stats cards -->
          <div class="row">
            <div class="col-xl-4 col-sm-6 mb-4">
              <div class="card h-100 shadow-sm">
                <div class="card-header p-2 ps-3">
                  <div class="d-flex justify-content-between">
                    <div>
                      <p class="text-sm mb-0 text-capitalize text-muted font-weight-bold">Virtual Domains</p>
                      <h4 class="mb-0 font-weight-bolder">{{ status.stats.domains }}</h4>
                    </div>
                    <div class="icon icon-md icon-shape bg-gradient-primary shadow-primary text-center border-radius-lg">
                      <i class="material-symbols-rounded opacity-10">dns</i>
                    </div>
                  </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                  <p class="mb-0 text-xs text-muted">Enabled domains hosting mail services</p>
                </div>
              </div>
            </div>

            <div class="col-xl-4 col-sm-6 mb-4">
              <div class="card h-100 shadow-sm">
                <div class="card-header p-2 ps-3">
                  <div class="d-flex justify-content-between">
                    <div>
                      <p class="text-sm mb-0 text-capitalize text-muted font-weight-bold">Email Accounts</p>
                      <h4 class="mb-0 font-weight-bolder">{{ status.stats.accounts }}</h4>
                    </div>
                    <div class="icon icon-md icon-shape bg-gradient-success shadow-success text-center border-radius-lg">
                      <i class="material-symbols-rounded opacity-10">mail</i>
                    </div>
                  </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                  <p class="mb-0 text-xs text-muted">Active user mailboxes configured</p>
                </div>
              </div>
            </div>

            <div class="col-xl-4 col-sm-6 mb-4">
              <div class="card h-100 shadow-sm">
                <div class="card-header p-2 ps-3">
                  <div class="d-flex justify-content-between">
                    <div>
                      <p class="text-sm mb-0 text-capitalize text-muted font-weight-bold">Forwarders</p>
                      <h4 class="mb-0 font-weight-bolder">{{ status.stats.aliases }}</h4>
                    </div>
                    <div class="icon icon-md icon-shape bg-gradient-info shadow-info text-center border-radius-lg">
                      <i class="material-symbols-rounded opacity-10">forward_to_inbox</i>
                    </div>
                  </div>
                </div>
                <hr class="dark horizontal my-0">
                <div class="card-footer p-2 ps-3">
                  <p class="mb-0 text-xs text-muted">Active mail routing forwarders</p>
                </div>
              </div>
            </div>
          </div>

          <!-- System Services Status -->
          <div class="row mt-4">
            <div class="col-lg-8 mb-4">
              <div class="card h-100 shadow-sm">
                <div class="card-header pb-0 p-3">
                  <h6 class="mb-0 font-weight-bold">System Services Status</h6>
                  <p class="text-xs text-muted">Core mail transmission and delivery agents running on the server</p>
                </div>
                <div class="card-body p-3">
                  <ul class="list-group">
                    <!-- Postfix -->
                    <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-3 border-radius-lg align-items-center">
                      <div class="d-flex align-items-center">
                        <div class="icon icon-sm icon-shape bg-gradient-dark shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                          <i class="material-symbols-rounded opacity-10 text-white text-xs">alt_route</i>
                        </div>
                        <div class="d-flex flex-column">
                          <h6 class="mb-1 text-dark text-sm font-weight-bold">Postfix SMTP Server</h6>
                          <p class="text-xs text-muted mb-0">Handles outgoing and incoming SMTP mail transmission</p>
                        </div>
                      </div>
                      <div class="d-flex align-items-center">
                        <span class="badge badge-sm" :class="status.postfix.running ? 'bg-gradient-success' : 'bg-gradient-danger'">
                          {{ status.postfix.running ? 'Running' : 'Stopped' }}
                        </span>
                      </div>
                    </li>

                    <!-- Dovecot -->
                    <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-3 border-radius-lg align-items-center">
                      <div class="d-flex align-items-center">
                        <div class="icon icon-sm icon-shape bg-gradient-dark shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                          <i class="material-symbols-rounded opacity-10 text-white text-xs">inbox</i>
                        </div>
                        <div class="d-flex flex-column">
                          <h6 class="mb-1 text-dark text-sm font-weight-bold">Dovecot IMAP/POP3 Server</h6>
                          <p class="text-xs text-muted mb-0">Provides mailbox retrieval protocols and folder sync</p>
                        </div>
                      </div>
                      <div class="d-flex align-items-center">
                        <span class="badge badge-sm" :class="status.dovecot.running ? 'bg-gradient-success' : 'bg-gradient-danger'">
                          {{ status.dovecot.running ? 'Running' : 'Stopped' }}
                        </span>
                      </div>
                    </li>

                    <!-- Roundcube Webmail -->
                    <li class="list-group-item border-0 d-flex justify-content-between ps-0 border-radius-lg align-items-center">
                      <div class="d-flex align-items-center">
                        <div class="icon icon-sm icon-shape bg-gradient-dark shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                          <i class="material-symbols-rounded opacity-10 text-white text-xs">web</i>
                        </div>
                        <div class="d-flex flex-column">
                          <h6 class="mb-1 text-dark text-sm font-weight-bold">Roundcube Webmail Client</h6>
                          <p class="text-xs text-muted mb-0">Interactive web interface to read/send emails</p>
                        </div>
                      </div>
                      <div class="d-flex align-items-center">
                        <span class="badge badge-sm me-3" :class="status.roundcube.installed ? 'bg-gradient-success' : 'bg-gradient-warning'">
                          {{ status.roundcube.installed ? 'Installed' : 'Not Configured' }}
                        </span>
                        <button 
                          v-if="!status.roundcube.installed && isRootOrAdmin" 
                          class="btn btn-sm bg-gradient-dark mb-0 py-1 px-3"
                          @click="configureRoundcube"
                          :disabled="configuringRoundcube"
                        >
                          <span v-if="configuringRoundcube" class="spinner-border spinner-border-sm me-1"></span>
                          Configure Webmail
                        </button>
                        <a 
                          v-else-if="status.roundcube.installed"
                          href="/roundcube" 
                          target="_blank"
                          class="btn btn-sm btn-outline-dark mb-0 py-1 px-3"
                        >
                          Open Webmail
                        </a>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4 mb-4">
            <div class="card h-100 shadow-sm">
              <div class="card-header pb-0 p-3">
                <h6 class="mb-0 font-weight-bold">Webmail Quick Link</h6>
              </div>
              <div class="card-body p-3 d-flex flex-column justify-content-center text-center">
                <i class="material-symbols-rounded text-primary opacity-3" style="font-size: 64px;">alternate_email</i>
                <h6 class="text-dark font-weight-bold mt-3">Access Webmail Client</h6>
                <p class="text-xs text-muted px-3">Log in to check your system email accounts directly from your browser.</p>
                <a href="/roundcube" target="_blank" class="btn bg-gradient-primary w-100 mb-0 mt-3">
                  <i class="material-symbols-rounded text-sm me-1">open_in_new</i> Open Webmail
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 2: Virtual Domains -->
        <div v-if="activeTab === 'domains'">
          <div class="row">
            <div class="col-12">
              <div class="card my-2 shadow-sm">
                <div class="card-header p-3 pb-0 d-flex justify-content-between align-items-center">
                  <h6 class="mb-0 font-weight-bold">Email Enabled Domains</h6>
                  <button v-if="isRootOrAdmin" class="btn bg-gradient-dark mb-0 btn-sm" @click="showAddDomainModal = true">
                    <i class="material-symbols-rounded text-xs me-1">add</i>
                    Enable Domain
                  </button>
                </div>
                <div class="card-body px-0 pb-2">
                  <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                      <thead>
                        <tr>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain Name</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Mailboxes</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Created At</th>
                          <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="domain in domains" :key="domain.id">
                          <td>
                            <div class="d-flex px-3 py-2">
                              <div class="icon icon-sm icon-shape bg-gradient-light shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="material-symbols-rounded opacity-10 text-dark text-xs">dns</i>
                              </div>
                              <div class="d-flex flex-column justify-content-center">
                                <h6 class="mb-0 text-sm font-weight-bold text-dark">{{ domain.name }}</h6>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span class="badge badge-sm" :class="domain.active ? 'bg-gradient-success' : 'bg-gradient-secondary'">
                              {{ domain.active ? 'Active' : 'Inactive' }}
                            </span>
                          </td>
                          <td>
                            <span class="text-xs font-weight-bold text-dark">{{ domain.account_count || 0 }}</span>
                          </td>
                          <td>
                            <span class="text-xs text-muted font-weight-bold">{{ new Date(domain.created_at).toLocaleDateString() }}</span>
                          </td>
                          <td class="align-middle text-center">
                            <button 
                              v-if="isRootOrAdmin"
                              class="btn btn-link text-danger p-0 mb-0" 
                              @click="confirmDisableDomain(domain.name)"
                              title="Disable Email for Domain"
                            >
                              <i class="material-symbols-rounded text-lg">delete_forever</i>
                            </button>
                          </td>
                        </tr>
                        <tr v-if="domains.length === 0">
                          <td colspan="5" class="text-center py-5">
                            <i class="material-symbols-rounded text-secondary opacity-3" style="font-size: 48px;">dns</i>
                            <p class="text-secondary text-sm mt-3">No virtual domains found. Enable email for your first domain.</p>
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

        <!-- Tab 3: Email Accounts -->
        <div v-if="activeTab === 'accounts'">
          <div class="row">
            <div class="col-12">
              <div class="card my-2 shadow-sm">
                <div class="card-header p-3 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                  <div class="d-flex align-items-center gap-2">
                    <h6 class="mb-0 font-weight-bold me-3">Mailboxes</h6>
                    <select 
                      v-model="selectedDomainFilter" 
                      @change="loadAccounts"
                      class="form-select form-select-sm"
                      style="max-width: 200px; border: 1px solid #d2d6da; border-radius: 4px; padding: 4px 8px; font-size: 0.875rem;"
                    >
                      <option value="">All Domains</option>
                      <option v-for="d in domains" :key="d.id" :value="d.name">{{ d.name }}</option>
                    </select>
                  </div>
                  <button 
                    v-if="isRootOrAdmin && domains.length > 0" 
                    class="btn bg-gradient-dark mb-0 btn-sm" 
                    @click="openAddAccountModal"
                  >
                    <i class="material-symbols-rounded text-xs me-1">add</i>
                    Create Mailbox
                  </button>
                  <div v-else-if="isRootOrAdmin && domains.length === 0" class="text-xs text-warning">
                    * Enable a domain first to create a mailbox.
                  </div>
                </div>
                <div class="card-body px-0 pb-2">
                  <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                      <thead>
                        <tr>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Email Address</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Storage Used</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Created At</th>
                          <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="account in accounts" :key="account.id">
                          <td>
                            <div class="d-flex px-3 py-2">
                              <div class="icon icon-sm icon-shape bg-gradient-light shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="material-symbols-rounded opacity-10 text-dark text-xs">alternate_email</i>
                              </div>
                              <div class="d-flex flex-column justify-content-center">
                                <h6 class="mb-0 text-sm font-weight-bold text-dark">{{ account.email }}</h6>
                              </div>
                            </div>
                          </td>
                          <td>
                            <div class="d-flex align-items-center">
                              <span class="text-xs text-dark font-weight-bold me-2">{{ account.used || 0 }} MB / {{ account.quota }} MB</span>
                              <div class="progress" style="width: 80px; height: 6px; margin-bottom: 0;">
                                <div 
                                  class="progress-bar bg-gradient-success" 
                                  role="progressbar" 
                                  :style="`width: ${Math.min(((account.used || 0) / account.quota) * 100, 100)}%`"
                                ></div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span class="badge badge-sm" :class="account.active ? 'bg-gradient-success' : 'bg-gradient-secondary'">
                              {{ account.active ? 'Active' : 'Inactive' }}
                            </span>
                          </td>
                          <td>
                            <span class="text-xs text-muted font-weight-bold">{{ new Date(account.created_at).toLocaleDateString() }}</span>
                          </td>
                          <td class="align-middle text-center">
                            <div class="d-flex justify-content-center gap-2">
                              <button 
                                class="btn btn-link text-info p-0 mb-0" 
                                @click="loginWebmail(account.email)"
                                title="Auto-Login Webmail"
                              >
                                <i class="material-symbols-rounded text-lg">open_in_new</i>
                              </button>
                              <button 
                                v-if="isRootOrAdmin"
                                class="btn btn-link text-secondary p-0 mb-0" 
                                @click="openChangePasswordModal(account.email)"
                                title="Change Password"
                              >
                                <i class="material-symbols-rounded text-lg">password</i>
                              </button>
                              <button 
                                v-if="isRootOrAdmin"
                                class="btn btn-link text-secondary p-0 mb-0" 
                                @click="openChangeQuotaModal(account.email, account.quota)"
                                title="Change Quota"
                              >
                                <i class="material-symbols-rounded text-lg">database</i>
                              </button>
                              <button 
                                v-if="isRootOrAdmin"
                                class="btn btn-link text-danger p-0 mb-0" 
                                @click="confirmDeleteAccount(account.email)"
                                title="Delete Account"
                              >
                                <i class="material-symbols-rounded text-lg">delete</i>
                              </button>
                            </div>
                          </td>
                        </tr>
                        <tr v-if="accounts.length === 0">
                          <td colspan="5" class="text-center py-5">
                            <i class="material-symbols-rounded text-secondary opacity-3" style="font-size: 48px;">mail</i>
                            <p class="text-secondary text-sm mt-3">No mailboxes found.</p>
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

        <!-- Tab 4: Forwarders (Aliases) -->
        <div v-if="activeTab === 'aliases'">
          <div class="row">
            <div class="col-12">
              <div class="card my-2 shadow-sm">
                <div class="card-header p-3 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                  <div class="d-flex align-items-center gap-2">
                    <h6 class="mb-0 font-weight-bold me-3">Mail Forwarders</h6>
                    <select 
                      v-model="selectedDomainFilter" 
                      @change="loadAliases"
                      class="form-select form-select-sm"
                      style="max-width: 200px; border: 1px solid #d2d6da; border-radius: 4px; padding: 4px 8px; font-size: 0.875rem;"
                    >
                      <option value="">All Domains</option>
                      <option v-for="d in domains" :key="d.id" :value="d.name">{{ d.name }}</option>
                    </select>
                  </div>
                  <button 
                    v-if="isRootOrAdmin && domains.length > 0" 
                    class="btn bg-gradient-dark mb-0 btn-sm" 
                    @click="openAddAliasModal"
                  >
                    <i class="material-symbols-rounded text-xs me-1">add</i>
                    Create Forwarder
                  </button>
                </div>
                <div class="card-body px-0 pb-2">
                  <div class="table-responsive p-0">
                    <table class="table align-items-center mb-0">
                      <thead>
                        <tr>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Source Email</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Routing</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Destination Email</th>
                          <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                          <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="alias in aliases" :key="alias.id">
                          <td>
                            <div class="d-flex px-3 py-2">
                              <div class="icon icon-sm icon-shape bg-gradient-light shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="material-symbols-rounded opacity-10 text-dark text-xs">alternate_email</i>
                              </div>
                              <div class="d-flex flex-column justify-content-center">
                                <h6 class="mb-0 text-sm font-weight-bold text-dark">{{ alias.source }}</h6>
                              </div>
                            </div>
                          </td>
                          <td>
                            <i class="material-symbols-rounded text-secondary" style="font-size: 1.5rem; vertical-align: middle;">arrow_right_alt</i>
                          </td>
                          <td>
                            <span class="text-xs font-weight-bold text-dark">{{ alias.destination }}</span>
                          </td>
                          <td>
                            <span class="badge badge-sm" :class="alias.active ? 'bg-gradient-success' : 'bg-gradient-secondary'">
                              {{ alias.active ? 'Active' : 'Inactive' }}
                            </span>
                          </td>
                          <td class="align-middle text-center">
                            <button 
                              v-if="isRootOrAdmin"
                              class="btn btn-link text-danger p-0 mb-0" 
                              @click="confirmDeleteAlias(alias.id)"
                              title="Delete Forwarder"
                            >
                              <i class="material-symbols-rounded text-lg">delete</i>
                            </button>
                          </td>
                        </tr>
                        <tr v-if="aliases.length === 0">
                          <td colspan="5" class="text-center py-5">
                            <i class="material-symbols-rounded text-secondary opacity-3" style="font-size: 48px;">forward_to_inbox</i>
                            <p class="text-secondary text-sm mt-3">No mail forwarders found.</p>
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

        <!-- Tab 5: Client Configuration -->
        <div v-if="activeTab === 'client'">
          <div class="row">
            <div class="col-lg-6 mb-4">
              <div class="card h-100 shadow-sm">
                <div class="card-header pb-0 p-3">
                  <h6 class="mb-0 font-weight-bold">Incoming Mail Server Settings</h6>
                </div>
                <div class="card-body p-3">
                  <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                      <tbody>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">IMAP Server</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.incoming.imap.server }}</td>
                        </tr>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">IMAP Port</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.incoming.imap.port }}</td>
                        </tr>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">IMAP Security</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.incoming.imap.security }}</td>
                        </tr>
                        <tr class="border-top">
                          <td class="text-xs font-weight-bold text-muted ps-0 pt-3">POP3 Server</td>
                          <td class="text-xs font-weight-bold text-dark pt-3">{{ clientSettings.incoming.pop3.server }}</td>
                        </tr>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">POP3 Port</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.incoming.pop3.port }}</td>
                        </tr>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">POP3 Security</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.incoming.pop3.security }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-6 mb-4">
              <div class="card h-100 shadow-sm">
                <div class="card-header pb-0 p-3">
                  <h6 class="mb-0 font-weight-bold">Outgoing Mail Server Settings</h6>
                </div>
                <div class="card-body p-3">
                  <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                      <tbody>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">SMTP Server</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.outgoing.smtp.server }}</td>
                        </tr>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">SMTP Port</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.outgoing.smtp.port }}</td>
                        </tr>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">SMTP Security</td>
                          <td class="text-xs font-weight-bold text-dark">{{ clientSettings.outgoing.smtp.security }}</td>
                        </tr>
                        <tr>
                          <td class="text-xs font-weight-bold text-muted ps-0">Authentication</td>
                          <td class="text-xs font-weight-bold text-dark">Required (Use same credentials as IMAP)</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="alert alert-light mt-4 mb-0 text-dark text-xs p-3 border-radius-lg">
                    <h6 class="text-xs font-weight-bold text-dark mb-2">Important Client Connection Tips:</h6>
                    <ul class="mb-0 ps-3">
                      <li>Always use your <strong>full email address</strong> (e.g., info@domain.com) as the username.</li>
                      <li>SSL/TLS protocols are strictly enforced for mail security and encryption.</li>
                      <li>If you receive a certificate warning, ensure your mail client trusts the server's self-signed/Let's Encrypt certificate.</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= MODALS SECTION ================= -->
      
      <!-- Enable Domain Modal -->
      <div class="modal-backdrop fade show" v-if="showAddDomainModal" @click="showAddDomainModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showAddDomainModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">Enable Domain for Email</h5>
              <button type="button" class="btn-close text-dark" @click="showAddDomainModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">Domain Name</label>
                <div class="input-group input-group-outline mt-1">
                  <input 
                    type="text" 
                    v-model="domainInput" 
                    class="form-control"
                    placeholder="example.com"
                    @input="domainValidationError = ''"
                    :disabled="domainSubmitting"
                  >
                </div>
                <div class="text-danger text-xs mt-1" v-if="domainValidationError">
                  {{ domainValidationError }}
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showAddDomainModal = false" :disabled="domainSubmitting">Cancel</button>
              <button class="btn bg-gradient-dark mb-0" @click="enableDomain" :disabled="domainSubmitting || !domainInput.trim()">
                <span v-if="domainSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Enable
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Disable Domain Confirmation Modal -->
      <div class="modal-backdrop fade show" v-if="showDisableDomainModal" @click="showDisableDomainModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showDisableDomainModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder text-danger">Disable Domain Email</h5>
              <button type="button" class="btn-close text-dark" @click="showDisableDomainModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <p class="text-sm">Are you sure you want to disable email hosting for <strong>{{ domainToDelete }}</strong>?</p>
              <p class="text-xs text-danger">
                <strong>Warning:</strong> This will delete the virtual domain routing database entries. Mailboxes hosted on this domain will not receive mails.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showDisableDomainModal = false" :disabled="domainSubmitting">Cancel</button>
              <button class="btn bg-gradient-danger mb-0" @click="disableDomain" :disabled="domainSubmitting">
                <span v-if="domainSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Disable Domain
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Mailbox Modal -->
      <div class="modal-backdrop fade show" v-if="showAddAccountModal" @click="showAddAccountModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showAddAccountModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">Create Email Account</h5>
              <button type="button" class="btn-close text-dark" @click="showAddAccountModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">Email Username</label>
                <div class="d-flex align-items-center gap-2 mt-1">
                  <div class="input-group input-group-outline" style="flex: 1;">
                    <input 
                      type="text" 
                      v-model="accountInput.username" 
                      class="form-control"
                      placeholder="info"
                      :disabled="accountSubmitting"
                    >
                  </div>
                  <span class="text-secondary font-weight-bold">@</span>
                  <select 
                    v-model="accountInput.domain" 
                    class="form-select border p-2"
                    style="border-radius: 4px; max-width: 200px;"
                    :disabled="accountSubmitting"
                  >
                    <option v-for="d in domains" :key="d.id" :value="d.name">{{ d.name }}</option>
                  </select>
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">Password</label>
                <div class="input-group input-group-outline mt-1">
                  <input 
                    type="password" 
                    v-model="accountInput.password" 
                    class="form-control"
                    placeholder="Min 8 characters"
                    :disabled="accountSubmitting"
                  >
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">Quota Limit (MB)</label>
                <div class="input-group input-group-outline mt-1">
                  <input 
                    type="number" 
                    v-model="accountInput.quota" 
                    class="form-control"
                    placeholder="1024"
                    :disabled="accountSubmitting"
                  >
                </div>
                <small class="text-xs text-muted">Recommend: 1024MB (1GB). Min: 10MB, Max: 10240MB (10GB).</small>
              </div>

              <div class="text-danger text-xs mt-1" v-if="accountValidationError">
                {{ accountValidationError }}
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showAddAccountModal = false" :disabled="accountSubmitting">Cancel</button>
              <button class="btn bg-gradient-dark mb-0" @click="createAccount" :disabled="accountSubmitting">
                <span v-if="accountSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Create Account
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Change Password Modal -->
      <div class="modal-backdrop fade show" v-if="showChangePasswordModal" @click="showChangePasswordModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showChangePasswordModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">Change Account Password</h5>
              <button type="button" class="btn-close text-dark" @click="showChangePasswordModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <p class="text-xs font-weight-bold text-secondary text-uppercase mb-2">Changing password for: {{ changePasswordInput.email }}</p>
              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">New Password</label>
                <div class="input-group input-group-outline mt-1">
                  <input 
                    type="password" 
                    v-model="changePasswordInput.password" 
                    class="form-control"
                    placeholder="Min 8 characters"
                    :disabled="accountSubmitting"
                  >
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showChangePasswordModal = false" :disabled="accountSubmitting">Cancel</button>
              <button class="btn bg-gradient-dark mb-0" @click="updatePassword" :disabled="accountSubmitting || !changePasswordInput.password.trim()">
                <span v-if="accountSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Save
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Change Quota Modal -->
      <div class="modal-backdrop fade show" v-if="showChangeQuotaModal" @click="showChangeQuotaModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showChangeQuotaModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">Modify Storage Quota</h5>
              <button type="button" class="btn-close text-dark" @click="showChangeQuotaModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <p class="text-xs font-weight-bold text-secondary text-uppercase mb-2">Modifying quota for: {{ changeQuotaInput.email }}</p>
              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">Mailbox Quota (MB)</label>
                <div class="input-group input-group-outline mt-1">
                  <input 
                    type="number" 
                    v-model="changeQuotaInput.quota" 
                    class="form-control"
                    placeholder="1024"
                    :disabled="accountSubmitting"
                  >
                </div>
                <small class="text-xs text-muted">Min: 10MB, Max: 10240MB (10GB).</small>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showChangeQuotaModal = false" :disabled="accountSubmitting">Cancel</button>
              <button class="btn bg-gradient-dark mb-0" @click="updateQuota" :disabled="accountSubmitting">
                <span v-if="accountSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Save
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Account Modal -->
      <div class="modal-backdrop fade show" v-if="showDeleteAccountModal" @click="showDeleteAccountModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showDeleteAccountModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder text-danger">Delete Email Account</h5>
              <button type="button" class="btn-close text-dark" @click="showDeleteAccountModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <p class="text-sm">Are you sure you want to permanently delete mailbox <strong>{{ accountToDelete }}</strong>?</p>
              <p class="text-xs text-danger">
                <strong>Warning:</strong> This deletes the user record from the database. Mailbox directories on the server are preserved for security reasons but will become inaccessible.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showDeleteAccountModal = false" :disabled="accountSubmitting">Cancel</button>
              <button class="btn bg-gradient-danger mb-0" @click="deleteAccount" :disabled="accountSubmitting">
                <span v-if="accountSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Delete Mailbox
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Forwarder Modal -->
      <div class="modal-backdrop fade show" v-if="showAddAliasModal" @click="showAddAliasModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showAddAliasModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">Create Mail Forwarder</h5>
              <button type="button" class="btn-close text-dark" @click="showAddAliasModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">Source Email Address</label>
                <div class="input-group input-group-outline mt-1">
                  <input 
                    type="email" 
                    v-model="aliasInput.source" 
                    class="form-control"
                    placeholder="sales@example.com"
                    :disabled="aliasSubmitting"
                  >
                </div>
                <small class="text-xs text-muted">Mail sent to this address will be forwarded.</small>
              </div>

              <div class="form-group mb-3">
                <label class="form-control-label text-xs font-weight-bold text-secondary text-uppercase">Destination Email Address</label>
                <div class="input-group input-group-outline mt-1">
                  <input 
                    type="email" 
                    v-model="aliasInput.destination" 
                    class="form-control"
                    placeholder="personal@gmail.com"
                    :disabled="aliasSubmitting"
                  >
                </div>
                <small class="text-xs text-muted">Forwarding target mailbox.</small>
              </div>

              <div class="text-danger text-xs mt-1" v-if="aliasValidationError">
                {{ aliasValidationError }}
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showAddAliasModal = false" :disabled="aliasSubmitting">Cancel</button>
              <button class="btn bg-gradient-dark mb-0" @click="createAlias" :disabled="aliasSubmitting">
                <span v-if="aliasSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Create Forwarder
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Forwarder Confirmation Modal -->
      <div class="modal-backdrop fade show" v-if="showDeleteAliasModal" @click="showDeleteAliasModal = false"></div>
      <div class="modal fade show d-block" tabindex="-1" v-if="showDeleteAliasModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder text-danger">Remove Forwarder</h5>
              <button type="button" class="btn-close text-dark" @click="showDeleteAliasModal = false" style="background: none; border: none; font-size: 1.5rem;">&times;</button>
            </div>
            <div class="modal-body">
              <p class="text-sm">Are you sure you want to delete this forwarder route?</p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary mb-0" @click="showDeleteAliasModal = false" :disabled="aliasSubmitting">Cancel</button>
              <button class="btn bg-gradient-danger mb-0" @click="deleteAlias" :disabled="aliasSubmitting">
                <span v-if="aliasSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                Delete Route
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
import { Head, usePage } from '@inertiajs/vue3'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import axios from 'axios'

const page = usePage()
const userRole = computed(() => page.props.auth?.user?.role || 'user')
const isRootOrAdmin = computed(() => page.props.auth?.user?.is_root || userRole.value === 'root' || userRole.value === 'admin')

// Navigation & Tab state
const activeTab = ref('status')

// Main status state
const loading = ref(true)
const status = ref({
  installed: false,
  postfix: { installed: false, running: false },
  dovecot: { installed: false, running: false },
  roundcube: { installed: false },
  stats: { domains: 0, accounts: 0, aliases: 0 }
})

// Installation Wizard state
const hostnameInput = ref(window.location.hostname || '')
const installing = ref(false)
const installLog = ref('')
const installStatus = ref('unknown')
const installIsComplete = ref(false)
const installIsFailed = ref(false)
let logInterval = null

// Domains state
const domains = ref([])
const showAddDomainModal = ref(false)
const showDisableDomainModal = ref(false)
const domainInput = ref('')
const domainToDelete = ref('')
const domainSubmitting = ref(false)
const domainValidationError = ref('')

// Accounts state
const accounts = ref([])
const selectedDomainFilter = ref('') // Dropdown filter
const showAddAccountModal = ref(false)
const showChangePasswordModal = ref(false)
const showChangeQuotaModal = ref(false)
const showDeleteAccountModal = ref(false)

const accountInput = ref({
  username: '',
  domain: '',
  password: '',
  quota: 1024
})
const changePasswordInput = ref({
  email: '',
  password: ''
})
const changeQuotaInput = ref({
  email: '',
  quota: 1024
})
const accountToDelete = ref('')
const accountSubmitting = ref(false)
const accountValidationError = ref('')

// Aliases state
const aliases = ref([])
const showAddAliasModal = ref(false)
const showDeleteAliasModal = ref(false)
const aliasInput = ref({
  source: '',
  destination: ''
})
const aliasToDelete = ref(null)
const aliasSubmitting = ref(false)
const aliasValidationError = ref('')

// Client Settings State
const clientSettings = ref({
  incoming: {
    imap: { server: 'fetching...', port: 993, security: 'SSL/TLS' },
    pop3: { server: 'fetching...', port: 995, security: 'SSL/TLS' }
  },
  outgoing: {
    smtp: { server: 'fetching...', port: 587, security: 'STARTTLS' }
  }
})

// Global alert system
const alert = ref({
  show: false,
  type: 'success', // success, danger, warning, info
  message: ''
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => {
    alert.value.show = false
  }, 5000)
}

// Fetch general mail server status
const loadMailServerStatus = async () => {
  try {
    loading.value = true
    const res = await axios.get('/email/status')
    status.value = res.data
    
    // If installed, load dynamic data based on active tab
    if (status.value.installed) {
      loadDataForActiveTab()
      loadClientSettings()
    } else {
      // Check if installation is currently running
      checkInstallProgress()
    }
  } catch (err) {
    showAlert('danger', 'Failed to load mail server status')
    console.error(err)
  } finally {
    loading.value = false
  }
}

// Load data depending on selected tab
const loadDataForActiveTab = () => {
  if (activeTab.value === 'domains') {
    loadDomains()
  } else if (activeTab.value === 'accounts') {
    loadAccounts()
    loadDomains() // Needed for dropdown selections
  } else if (activeTab.value === 'aliases') {
    loadAliases()
    loadDomains() // Needed for dropdown selection
  } else if (activeTab.value === 'status') {
    // Refresh stats
    loadMailServerStatusWithoutLoadingState()
  }
}

const loadMailServerStatusWithoutLoadingState = async () => {
  try {
    const res = await axios.get('/email/status')
    status.value = res.data
  } catch (err) {
    console.error(err)
  }
}

// Client Settings
const loadClientSettings = async () => {
  try {
    const res = await axios.get('/email/client-settings')
    clientSettings.value = res.data
  } catch (err) {
    console.error(err)
  }
}

const changeTab = (tab) => {
  activeTab.value = tab
  loadDataForActiveTab()
}

// Setup & Install
const clearLock = async () => {
  if (!confirm('Are you sure you want to force reset and clear the installation lock? Use this only if the installation is stuck.')) {
    return
  }
  
  try {
    const res = await axios.post('/email/clear-lock')
    if (res.data.success) {
      showAlert('success', 'Installation lock cleared. You can now restart the installation.')
      if (logInterval) clearInterval(logInterval)
      installing.value = false
      installIsFailed.value = false
      installIsComplete.value = false
      installLog.value = ''
      loadMailServerStatus()
    } else {
      showAlert('danger', res.data.error || 'Failed to clear lock.')
    }
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'An error occurred while clearing the lock.')
  }
}

const resetSetup = async () => {
  try {
    const res = await axios.post('/email/clear-lock')
    if (res.data.success) {
      if (logInterval) clearInterval(logInterval)
      installing.value = false
      installIsFailed.value = false
      installIsComplete.value = false
      installLog.value = ''
      loadMailServerStatus()
    }
  } catch (err) {
    console.error(err)
  }
}

const triggerInstallation = async () => {
  if (!hostnameInput.value.trim()) {
    showAlert('danger', 'Please provide a valid hostname.')
    return
  }
  
  // Enforce strict regex validation locally as well
  const hostnameRegex = /^[a-zA-Z0-9.-]+$/
  if (!hostnameRegex.test(hostnameInput.value.trim())) {
    showAlert('danger', 'Hostname contains invalid characters. Only alphanumeric characters, dots, and hyphens are allowed.')
    return
  }

  try {
    installing.value = true
    installIsFailed.value = false
    installIsComplete.value = false
    installLog.value = 'Initializing installation process...\n'
    
    const res = await axios.post('/email/install', {
      hostname: hostnameInput.value.trim()
    })
    
    if (res.data.success) {
      showAlert('info', 'Installation process started successfully in background.')
      startPollingLog()
    } else {
      showAlert('danger', res.data.error || 'Failed to start installation.')
      installing.value = false
    }
  } catch (err) {
    const errorMsg = err.response?.data?.error || err.message || 'An error occurred.'
    showAlert('danger', errorMsg)
    installing.value = false
  }
}

const startPollingLog = () => {
  if (logInterval) clearInterval(logInterval)
  
  logInterval = setInterval(async () => {
    try {
      const res = await axios.get('/email/install-log')
      installLog.value = res.data.log || 'Waiting for log output...'
      installStatus.value = res.data.status
      installIsComplete.value = res.data.isComplete
      installIsFailed.value = res.data.isFailed
      
      // Auto scroll terminal
      scrollToBottom()
      
      if (res.data.isComplete) {
        clearInterval(logInterval)
        installing.value = false
        showAlert('success', 'Nimbus Mail Server has been installed successfully!')
        setTimeout(() => {
          loadMailServerStatus()
        }, 2000)
      } else if (res.data.isFailed) {
        clearInterval(logInterval)
        installing.value = false
        showAlert('danger', 'Installation process failed. Please check the logs.')
      }
    } catch (err) {
      console.error('Error polling install logs', err)
    }
  }, 1500)
}

const checkInstallProgress = async () => {
  try {
    const res = await axios.get('/email/install-log')
    if (res.data.isRunning) {
      installing.value = true
      startPollingLog()
    } else if (res.data.status !== 'unknown' && res.data.status !== '') {
      installLog.value = res.data.log
      installIsComplete.value = res.data.isComplete
      installIsFailed.value = res.data.isFailed
    }
  } catch (err) {
    console.error(err)
  }
}

const terminalRef = ref(null)
const scrollToBottom = () => {
  setTimeout(() => {
    if (terminalRef.value) {
      terminalRef.value.scrollTop = terminalRef.value.scrollHeight
    }
  }, 100)
}

// Roundcube
const configuringRoundcube = ref(false)
const configureRoundcube = async () => {
  try {
    configuringRoundcube.value = true
    const res = await axios.post('/email/configure-roundcube')
    if (res.data.success) {
      showAlert('success', res.data.message || 'Roundcube configured successfully.')
      loadMailServerStatus()
    } else {
      showAlert('danger', res.data.error || 'Failed to configure Roundcube.')
    }
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'An error occurred during configuration.')
  } finally {
    configuringRoundcube.value = false
  }
}

// Domains
const loadDomains = async () => {
  try {
    const res = await axios.get('/email/domains')
    domains.value = res.data.domains
  } catch (err) {
    showAlert('danger', 'Failed to load virtual domains')
  }
}

const enableDomain = async () => {
  domainValidationError.value = ''
  
  if (!domainInput.value.trim()) {
    domainValidationError.value = 'Domain name is required.'
    return
  }

  const domainRegex = /^[a-zA-Z0-9.-]+$/
  if (!domainRegex.test(domainInput.value.trim())) {
    domainValidationError.value = 'Invalid domain format. Only alphanumeric characters, dots, and hyphens are allowed.'
    return
  }

  try {
    domainSubmitting.value = true
    const res = await axios.post('/email/domain/enable', {
      domain: domainInput.value.trim().toLowerCase()
    })
    if (res.data.success) {
      showAlert('success', res.data.message || 'Domain enabled successfully for email.')
      showAddDomainModal.value = false
      domainInput.value = ''
      loadDomains()
    } else {
      domainValidationError.value = res.data.error || 'Failed to enable domain.'
    }
  } catch (err) {
    domainValidationError.value = err.response?.data?.error || 'Failed to enable domain.'
  } finally {
    domainSubmitting.value = false
  }
}

const confirmDisableDomain = (domainName) => {
  domainToDelete.value = domainName
  showDisableDomainModal.value = true
}

const disableDomain = async () => {
  try {
    domainSubmitting.value = true
    const res = await axios.post('/email/domain/disable', {
      domain: domainToDelete.value
    })
    showAlert('success', res.data.message || 'Domain disabled successfully.')
    showDisableDomainModal.value = false
    loadDomains()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to disable domain.')
  } finally {
    domainSubmitting.value = false
  }
}

// Mailboxes
const loadAccounts = async () => {
  try {
    const res = await axios.get('/email/accounts', {
      params: { domain: selectedDomainFilter.value }
    })
    accounts.value = res.data.accounts
  } catch (err) {
    showAlert('danger', 'Failed to load email accounts')
  }
}

const openAddAccountModal = () => {
  accountValidationError.value = ''
  accountInput.value = {
    username: '',
    domain: domains.value.length > 0 ? domains.value[0].name : '',
    password: '',
    quota: 1024
  }
  showAddAccountModal.value = true
}

const createAccount = async () => {
  accountValidationError.value = ''
  
  if (!accountInput.value.username.trim()) {
    accountValidationError.value = 'Username is required.'
    return
  }
  
  const usernameRegex = /^[a-z0-9._-]+$/
  if (!usernameRegex.test(accountInput.value.username.trim().toLowerCase())) {
    accountValidationError.value = 'Invalid username format. Only lowercase alphanumeric, dots, underscores, and hyphens are allowed.'
    return
  }

  if (!accountInput.value.domain) {
    accountValidationError.value = 'Domain is required.'
    return
  }

  if (!accountInput.value.password || accountInput.value.password.length < 8) {
    accountValidationError.value = 'Password must be at least 8 characters.'
    return
  }

  try {
    accountSubmitting.value = true
    const res = await axios.post('/email/account/create', {
      username: accountInput.value.username.trim().toLowerCase(),
      domain: accountInput.value.domain,
      password: accountInput.value.password,
      quota: accountInput.value.quota
    })
    if (res.data.success) {
      showAlert('success', res.data.message || 'Email account created successfully.')
      showAddAccountModal.value = false
      loadAccounts()
    } else {
      accountValidationError.value = res.data.error || 'Failed to create email account.'
    }
  } catch (err) {
    accountValidationError.value = err.response?.data?.error || 'Failed to create email account.'
  } finally {
    accountSubmitting.value = false
  }
}

const openChangePasswordModal = (email) => {
  changePasswordInput.value = {
    email,
    password: ''
  }
  showChangePasswordModal.value = true
}

const updatePassword = async () => {
  if (!changePasswordInput.value.password || changePasswordInput.value.password.length < 8) {
    showAlert('danger', 'Password must be at least 8 characters.')
    return
  }

  try {
    accountSubmitting.value = true
    const res = await axios.post('/email/account/password', {
      email: changePasswordInput.value.email,
      password: changePasswordInput.value.password
    })
    showAlert('success', res.data.message || 'Password updated successfully.')
    showChangePasswordModal.value = false
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to update password.')
  } finally {
    accountSubmitting.value = false
  }
}

const openChangeQuotaModal = (email, currentQuota) => {
  changeQuotaInput.value = {
    email,
    quota: currentQuota || 1024
  }
  showChangeQuotaModal.value = true
}

const updateQuota = async () => {
  if (!changeQuotaInput.value.quota || changeQuotaInput.value.quota < 10 || changeQuotaInput.value.quota > 10240) {
    showAlert('danger', 'Quota must be between 10MB and 10GB (10240MB).')
    return
  }

  try {
    accountSubmitting.value = true
    const res = await axios.post('/email/account/quota', {
      email: changeQuotaInput.value.email,
      quota: changeQuotaInput.value.quota
    })
    showAlert('success', res.data.message || 'Quota updated successfully.')
    showChangeQuotaModal.value = false
    loadAccounts()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to update quota.')
  } finally {
    accountSubmitting.value = false
  }
}

const confirmDeleteAccount = (email) => {
  accountToDelete.value = email
  showDeleteAccountModal.value = true
}

const deleteAccount = async () => {
  try {
    accountSubmitting.value = true
    const res = await axios.post('/email/account/delete', {
      email: accountToDelete.value
    })
    showAlert('success', res.data.message || 'Email account deleted.')
    showDeleteAccountModal.value = false
    loadAccounts()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to delete email account.')
  } finally {
    accountSubmitting.value = false
  }
}

const loginWebmail = async (email) => {
  try {
    const res = await axios.post('/email/webmail-login', { email })
    if (res.data.success && res.data.url) {
      window.open(res.data.url, '_blank')
    } else {
      showAlert('danger', 'Failed to generate webmail login link.')
    }
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to log in to webmail.')
  }
}

// Forwarders
const loadAliases = async () => {
  try {
    const res = await axios.get('/email/aliases', {
      params: { domain: selectedDomainFilter.value }
    })
    aliases.value = res.data.aliases
  } catch (err) {
    showAlert('danger', 'Failed to load mail forwarders')
  }
}

const openAddAliasModal = () => {
  aliasValidationError.value = ''
  aliasInput.value = {
    source: '',
    destination: ''
  }
  showAddAliasModal.value = true
}

const createAlias = async () => {
  aliasValidationError.value = ''

  if (!aliasInput.value.source.trim() || !aliasInput.value.destination.trim()) {
    aliasValidationError.value = 'Both source and destination emails are required.'
    return
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(aliasInput.value.source.trim()) || !emailRegex.test(aliasInput.value.destination.trim())) {
    aliasValidationError.value = 'Please enter valid email addresses.'
    return
  }

  try {
    aliasSubmitting.value = true
    const res = await axios.post('/email/alias/create', {
      source: aliasInput.value.source.trim().toLowerCase(),
      destination: aliasInput.value.destination.trim().toLowerCase()
    })
    if (res.data.success) {
      showAlert('success', res.data.message || 'Forwarder created successfully.')
      showAddAliasModal.value = false
      loadAliases()
    } else {
      aliasValidationError.value = res.data.error || 'Failed to create forwarder.'
    }
  } catch (err) {
    aliasValidationError.value = err.response?.data?.error || 'Failed to create forwarder.'
  } finally {
    aliasSubmitting.value = false
  }
}

const confirmDeleteAlias = (aliasId) => {
  aliasToDelete.value = aliasId
  showDeleteAliasModal.value = true
}

const deleteAlias = async () => {
  try {
    aliasSubmitting.value = true
    const res = await axios.post('/email/alias/delete', {
      id: aliasToDelete.value
    })
    if (res.data.success) {
      showAlert('success', res.data.message || 'Forwarder deleted.')
      showDeleteAliasModal.value = false
      loadAliases()
    } else {
      showAlert('danger', res.data.error || 'Failed to delete forwarder.')
    }
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to delete forwarder.')
  } finally {
    aliasSubmitting.value = false
  }
}

onMounted(() => {
  loadMailServerStatus()
})

onUnmounted(() => {
  if (logInterval) clearInterval(logInterval)
})
</script>

<style scoped>
.nav-pills .nav-link {
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 700;
  font-size: 0.875rem;
  color: #495057;
}
.nav-pills .nav-link.active {
  background: linear-gradient(195deg, #4974ec, #2549b8) !important;
  color: #fff !important;
  box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(37, 73, 184, 0.4) !important;
}

.terminal-output {
  box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.8);
}

.terminal-output::-webkit-scrollbar {
  width: 8px;
}
.terminal-output::-webkit-scrollbar-track {
  background: #0f172a;
}
.terminal-output::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 4px;
}
.terminal-output::-webkit-scrollbar-thumb:hover {
  background: #475569;
}
</style>
