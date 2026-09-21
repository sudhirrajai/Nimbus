<template>
  <MainLayout>
    <Head title="Nginx Configuration" />
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">Nginx Configuration</h4>
              <p class="mb-0 text-sm">Manage nginx configuration files for your domains</p>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="loadDomains" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                Refresh
              </button>
              <button class="btn bg-gradient-info mb-0" @click="testNginxConfig" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">rule</i>
                Test Config
              </button>
              <button class="btn bg-gradient-warning mb-0" @click="confirmReload" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">restart_alt</i>
                Reload Nginx
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
      <div class="row" v-if="loading && domains.length === 0">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-secondary mt-2">Loading domains...</p>
        </div>
      </div>

      <!-- Domains List -->
      <div class="row" v-if="domains.length > 0">
        <div class="col-12">
          <div class="card">
            <div class="card-header pb-0">
              <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                    <input ref="searchInput" v-model="searchQuery" type="text" class="form-control" placeholder="Search domains...">
                  </div>
                  <span class="badge bg-gradient-primary">{{ filteredDomains.length }} domains</span>
                  <button class="btn btn-icon-only btn-rounded bg-white mb-0 shadow-sm border" @click="showShortcutsHelp = true" title="Keyboard Shortcuts (F1 or ?)" style="width: 32px; height: 32px; min-width: 32px; display: flex; align-items: center; justify-content: center;">
                    <i class="material-symbols-rounded text-md text-dark">keyboard</i>
                  </button>
                </div>
              </div>
              <p class="text-sm text-secondary mb-0">Edit nginx configuration for each domain</p>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Config File</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="domain in paginatedDomains" :key="domain.domain" class="domain-row">
                      <td>
                        <div class="d-flex align-items-center px-3 py-2">
                          <div class="icon-box-nginx me-3">
                            <i class="material-symbols-rounded text-info">language</i>
                          </div>
                          <div>
                            <h6 class="mb-0 text-sm font-weight-bold">{{ domain.domain }}</h6>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="material-symbols-rounded text-secondary text-xs me-1">description</i>
                          <span class="text-xs font-monospace text-secondary">{{ domain.configPath }}</span>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex flex-column gap-1">
                          <div v-if="domain.hasConfig" class="status-pill status-active">
                            <span class="pill-dot"></span>
                            Config Exists
                          </div>
                          <div v-else class="status-pill status-error">
                            <span class="pill-dot"></span>
                            No Config
                          </div>
                          
                          <div v-if="domain.isEnabled" class="status-pill status-info" style="font-size: 9px; padding: 2px 8px;">
                            Enabled
                          </div>
                          <div v-else class="status-pill status-secondary" style="font-size: 9px; padding: 2px 8px;">
                            Disabled
                          </div>

                          <div v-if="domain.isProxy" class="status-pill status-proxy mt-1" style="font-size: 9px; padding: 2px 8px;">
                            <i class="material-symbols-rounded text-xs me-1">alt_route</i>
                            Proxy: {{ domain.proxyTarget }}
                          </div>
                          <div v-else-if="domain.hasConfig" class="status-pill status-secondary mt-1" style="font-size: 9px; padding: 2px 8px; opacity: 0.85;">
                            PHP / Static
                          </div>
                        </div>
                      </td>
                      <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                          <button 
                            class="action-btn"
                            :class="domain.isProxy ? 'btn-proxy-active' : 'btn-proxy'"
                            @click="openProxyModal(domain)"
                            :title="domain.isProxy ? 'Reverse Proxy Active — Click to Configure' : 'Configure Reverse Proxy (Node.js, Python, Go, Docker)'"
                            :disabled="!domain.hasConfig"
                          >
                            <i class="material-symbols-rounded">{{ domain.isProxy ? 'alt_route' : 'hub' }}</i>
                          </button>
                          <button 
                            class="action-btn btn-edit" 
                            @click="openEditor(domain)"
                            title="Edit configuration"
                            :disabled="!domain.hasConfig"
                          >
                            <i class="material-symbols-rounded">edit</i>
                          </button>
                          <button 
                            class="action-btn"
                            :class="domain.isEnabled ? 'btn-toggle-on' : 'btn-toggle-off'"
                            @click="toggleDomain(domain)"
                            :title="domain.isEnabled ? 'Disable domain' : 'Enable domain'"
                            :disabled="!domain.hasConfig || toggling === domain.domain"
                          >
                            <span v-if="toggling === domain.domain" class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>
                            <i v-else class="material-symbols-rounded">
                              {{ domain.isEnabled ? 'toggle_on' : 'toggle_off' }}
                            </i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="filteredDomains.length === 0">
                        <td colspan="4" class="text-center py-5 text-secondary">
                          <div class="empty-state">
                            <i class="material-symbols-rounded opacity-3" style="font-size: 64px;">folder_off</i>
                            <p class="mt-3">No domains found matching your search.</p>
                          </div>
                        </td>
                      </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Pagination -->
              <div v-if="filteredDomains.length > itemsPerPage" class="d-flex justify-content-between align-items-center p-3 border-top">
                <div class="text-xs text-secondary">
                  Showing {{ paginationStart + 1 }} to {{ Math.min(paginationEnd, filteredDomains.length) }} of {{ filteredDomains.length }} entries
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

      <!-- Editor Modal -->
      <div class="modal-backdrop fade show" v-if="showEditorModal" @click="closeEditorConfirm"></div>
      <div class="modal fade show d-block" v-if="showEditorModal">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90vw;">
          <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
              <div>
                <h5 class="modal-title mb-0">Nginx Config: {{ editingDomain?.domain }}</h5>
                <p class="text-sm text-secondary mb-0">{{ editingDomain?.configPath }}</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary mb-0 d-flex align-items-center py-1" @click="showShortcutsHelp = true" title="Keyboard Shortcuts (F1 or ?)">
                  <i class="material-symbols-rounded text-sm me-1">keyboard</i>
                  Shortcuts
                </button>
                <button type="button" class="btn-close" @click="closeEditorConfirm"></button>
              </div>
            </div>
            <div class="modal-body">
              <transition name="fade">
                <div v-if="alert.show" :class="`alert alert-${alert.type} alert-dismissible fade show text-white mb-3`" role="alert">
                  <span class="alert-icon"><i class="material-symbols-rounded">{{ getAlertIcon(alert.type) }}</i></span>
                  <span class="alert-text ms-2 font-weight-bold text-white">{{ alert.message }}</span>
                  <button type="button" class="btn-close text-white" @click="alert.show = false" style="filter: invert(1);"></button>
                </div>
              </transition>
              <div class="alert alert-warning py-2 mb-3">
                <i class="material-symbols-rounded text-sm me-1">warning</i>
                <small>Be careful when editing nginx configuration. Invalid settings will be automatically reverted. A backup is created before saving.</small>
              </div>
              <div id="nginx-editor-container" class="nginx-editor-box border shadow-inner"></div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="closeEditorConfirm">Cancel</button>
              <button class="btn bg-gradient-info" @click="saveAndTest" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                Save & Test
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Unsaved Changes Warning Modal -->
      <div class="modal-backdrop fade show" v-if="showUnsavedConfirmModal" @click="showUnsavedConfirmModal = false" style="z-index: 10050;"></div>
      <div class="modal fade show d-block" v-if="showUnsavedConfirmModal" style="z-index: 10051;">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-2xl bg-white">
            <div class="modal-body text-center py-5">
              <div class="modal-icon bg-gradient-warning mx-auto mb-4 shadow-warning d-flex align-items-center justify-content-center border-radius-xl" style="width: 64px; height: 64px; margin: 0 auto;">
                <i class="material-symbols-rounded text-white" style="font-size: 32px;">warning</i>
              </div>
              <h4 class="font-weight-bolder text-dark">Unsaved Changes</h4>
              <p class="text-secondary px-4 mb-4">
                You have unsaved changes in your Nginx configuration. Are you sure you want to close? Your modifications will be lost.
              </p>
              <div class="d-flex justify-content-center gap-3">
                <button class="btn btn-link text-secondary mb-0 font-weight-bold" @click="showUnsavedConfirmModal = false">Keep Editing</button>
                <button class="btn bg-gradient-warning mb-0 border-radius-lg px-4 shadow-warning text-white" @click="showUnsavedConfirmModal = false; closeEditor()">
                  Discard & Close
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Keyboard Shortcuts Help Modal -->
      <div class="modal-backdrop fade show" v-if="showShortcutsHelp" @click="showShortcutsHelp = false" style="z-index: 10060;"></div>
      <div class="modal fade show d-block" v-if="showShortcutsHelp" style="z-index: 10061;">
        <div class="modal-dialog modal-dialog-centered">
          <div class="glass-card modal-content border-0 shadow-2xl bg-white p-4">
            <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
              <h5 class="modal-title font-weight-bolder text-dark d-flex align-items-center mb-0">
                <i class="material-symbols-rounded text-primary align-middle me-2">keyboard</i>
                Nginx Editor Keyboard Shortcuts
              </h5>
              <button type="button" class="btn-close" @click="showShortcutsHelp = false" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body py-3">
              <div class="shortcuts-grid">
                <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                  <span class="text-sm font-weight-bold text-dark">Save progress (Keep Editor open)</span>
                  <kbd class="badge bg-light text-dark font-monospace border shadow-sm">Ctrl + S</kbd>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                  <span class="text-sm font-weight-bold text-dark">Save, Test, & Close</span>
                  <kbd class="badge bg-light text-dark font-monospace border shadow-sm">Ctrl + Enter</kbd>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                  <span class="text-sm font-weight-bold text-dark">Search / Find inside Editor</span>
                  <kbd class="badge bg-light text-dark font-monospace border shadow-sm">Ctrl + F</kbd>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-bottom">
                  <span class="text-sm font-weight-bold text-dark">Deselect / Close Editor Modal</span>
                  <kbd class="badge bg-light text-dark font-monospace border shadow-sm">Escape</kbd>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-0 py-1">
                  <span class="text-sm font-weight-bold text-dark">Toggle Keyboard Helper</span>
                  <kbd class="badge bg-light text-dark font-monospace border shadow-sm">F1 or ?</kbd>
                </div>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button class="btn bg-gradient-primary w-100 mb-0 border-radius-lg text-white" @click="showShortcutsHelp = false">Got it!</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Reload Confirmation Modal -->
      <div class="modal-backdrop fade show" v-if="showReloadModal" @click="showReloadModal = false"></div>
      <div class="modal fade show d-block" v-if="showReloadModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded text-warning me-2">restart_alt</i>
                Reload Nginx
              </h5>
              <button type="button" class="btn-close" @click="showReloadModal = false"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to reload Nginx?</p>
              <p class="text-sm text-secondary mb-0">
                This will apply all configuration changes. Active connections may be briefly interrupted.
              </p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showReloadModal = false">Cancel</button>
              <button class="btn bg-gradient-warning" @click="reloadNginx" :disabled="reloading">
                <span v-if="reloading" class="spinner-border spinner-border-sm me-2"></span>
                Reload Now
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Test Config Modal -->
      <div class="modal-backdrop fade show" v-if="showTestModal" @click="showTestModal = false"></div>
      <div class="modal fade show d-block" v-if="showTestModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="material-symbols-rounded me-2" :class="testResult?.success ? 'text-success' : 'text-danger'">
                  {{ testResult?.success ? 'check_circle' : 'error' }}
                </i>
                Configuration Test
              </h5>
              <button type="button" class="btn-close" @click="showTestModal = false"></button>
            </div>
            <div class="modal-body">
              <div :class="`alert alert-${testResult?.success ? 'success' : 'danger'} mb-3`">
                {{ testResult?.message }}
              </div>
              <pre class="bg-dark text-light p-3 rounded" style="font-size: 12px; max-height: 300px; overflow: auto;">{{ testResult?.output }}</pre>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showTestModal = false">Close</button>
              <button v-if="testResult?.success" class="btn bg-gradient-success" @click="reloadAfterTest">
                <i class="material-symbols-rounded text-sm me-1">restart_alt</i>
                Reload Nginx
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Reverse Proxy Modal -->
      <div class="modal-backdrop fade show" v-if="showProxyModal" @click="showProxyModal = false"></div>
      <div class="modal fade show d-block" v-if="showProxyModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-center">
              <div>
                <div class="d-flex align-items-center gap-2">
                  <h5 class="modal-title font-weight-bolder text-dark mb-0">
                    Reverse Proxy: {{ proxyDomain?.domain }}
                  </h5>
                  <span v-if="proxyForm.is_proxy" class="badge bg-gradient-success text-xxs font-weight-bold">
                    Active ({{ proxyForm.target_url }})
                  </span>
                  <span v-else class="badge bg-light text-secondary text-xxs font-weight-bold">
                    Standard PHP / Static
                  </span>
                </div>
                <p class="text-xs text-secondary mb-0 mt-1">
                  Forward HTTP/WebSocket traffic for this domain to a local application port or service.
                </p>
              </div>
              <button type="button" class="btn-close" @click="showProxyModal = false"></button>
            </div>

            <div class="modal-body py-3">
              <!-- Isolation / Same Server Notice -->
              <div class="alert alert-light border py-2 px-3 mb-3 d-flex align-items-start gap-2 text-dark">
                <i class="material-symbols-rounded text-info mt-1">info</i>
                <div class="text-xs">
                  <strong>Same-Server Routing & Full Isolation:</strong> Runs directly on this server. Nginx forwards requests for <code>{{ proxyDomain?.domain }}</code> to <code>127.0.0.1:PORT</code>. <u>All other websites on this server remain untouched and isolated</u> in their own server blocks.
                </div>
              </div>

              <!-- Presets Grid -->
              <label class="form-label text-xs font-weight-bold text-uppercase text-secondary mb-1">Select Preset</label>
              <div class="row g-2 mb-3">
                <div class="col-6 col-md-3" v-for="p in proxyPresets" :key="p.id">
                  <div 
                    class="preset-card p-2 rounded-3 border text-center cursor-pointer h-100 d-flex flex-column align-items-center justify-content-center transition"
                    :class="{ 'preset-card-selected': proxyForm.preset === p.id }"
                    @click="applyPreset(p)"
                  >
                    <span class="preset-icon mb-1" style="font-size: 24px;">{{ p.icon }}</span>
                    <span class="text-xs font-weight-bold text-dark">{{ p.name }}</span>
                    <span class="text-xxs text-secondary font-monospace">:{{ p.defaultPort }}</span>
                  </div>
                </div>
              </div>

              <!-- Form Inputs -->
              <div class="row g-3">
                <div class="col-12 col-md-8">
                  <label class="form-label text-xs font-weight-bold text-dark mb-1">
                    Target Address / Port <span class="text-danger">*</span>
                  </label>
                  <div class="input-group input-group-outline">
                    <input 
                      v-model="proxyForm.target_url" 
                      type="text" 
                      class="form-control" 
                      placeholder="e.g. 3000, or http://127.0.0.1:3000"
                      :disabled="proxyLoading"
                    />
                  </div>
                  <span class="text-xxs text-secondary">Accepts port number (<code>3000</code>), full URL (<code>http://127.0.0.1:3000</code>), or unix socket.</span>
                </div>

                <div class="col-12 col-md-4">
                  <label class="form-label text-xs font-weight-bold text-dark mb-1">Max Upload Body Size</label>
                  <div class="input-group input-group-outline">
                    <input 
                      v-model="proxyForm.client_max_body_size" 
                      type="text" 
                      class="form-control" 
                      placeholder="100M"
                      :disabled="proxyLoading"
                    />
                  </div>
                  <span class="text-xxs text-secondary">e.g. 50M, 100M, 500M</span>
                </div>

                <!-- Toggles -->
                <div class="col-12">
                  <div class="p-3 bg-light rounded-3 d-flex flex-wrap gap-4 align-items-center justify-content-between">
                    <div class="form-check form-switch mb-0">
                      <input 
                        class="form-check-input" 
                        type="checkbox" 
                        id="proxyWsCheck" 
                        v-model="proxyForm.enable_websocket"
                      >
                      <label class="form-check-label text-xs font-weight-bold text-dark" for="proxyWsCheck">
                        WebSocket Support (Upgrade / Connection)
                      </label>
                      <div class="text-xxs text-secondary">Enables real-time apps (Socket.io, Next.js HMR, GraphQL)</div>
                    </div>

                    <div class="form-check form-switch mb-0">
                      <input 
                        class="form-check-input" 
                        type="checkbox" 
                        id="proxyBufferingCheck" 
                        v-model="proxyForm.proxy_buffering"
                      >
                      <label class="form-check-label text-xs font-weight-bold text-dark" for="proxyBufferingCheck">
                        Proxy Buffering
                      </label>
                      <div class="text-xxs text-secondary">Turn off for streaming responses (LLM AI streams, SSE)</div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                      <label class="text-xs font-weight-bold text-dark mb-0">Timeout:</label>
                      <input 
                        v-model.number="proxyForm.proxy_read_timeout" 
                        type="number" 
                        class="form-control form-control-sm text-center" 
                        style="width: 70px;"
                        min="10"
                        max="3600"
                      />
                      <span class="text-xxs text-secondary">sec</span>
                    </div>
                  </div>
                </div>

                <!-- Live Preview -->
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label text-xs font-weight-bold text-uppercase text-secondary mb-0">
                      Generated Nginx Directive Preview
                    </label>
                    <span class="text-xxs text-secondary">Applied inside server block for {{ proxyDomain?.domain }}</span>
                  </div>
                  <pre class="bg-dark text-light p-2 rounded-3 mb-0 font-monospace text-xxs" style="max-height: 140px; overflow-y: auto;">{{ generatedProxyPreview }}</pre>
                </div>
              </div>
            </div>

            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
              <div>
                <button 
                  v-if="proxyForm.is_proxy" 
                  class="btn btn-outline-danger btn-sm mb-0" 
                  @click="removeProxy" 
                  :disabled="proxyLoading"
                >
                  <span v-if="proxyLoading" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="material-symbols-rounded text-sm me-1">undo</i>
                  Revert to Standard PHP
                </button>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm mb-0" @click="showProxyModal = false">
                  Cancel
                </button>
                <button 
                  class="btn bg-gradient-info btn-sm mb-0 text-white" 
                  @click="saveProxy" 
                  :disabled="proxyLoading || !proxyForm.target_url"
                >
                  <span v-if="proxyLoading" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="material-symbols-rounded text-sm me-1">check</i>
                  {{ proxyForm.is_proxy ? 'Update Reverse Proxy' : 'Apply Reverse Proxy' }}
                </button>
              </div>
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
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import axios from 'axios'
import ace from 'ace-builds'

// Import Ace components
import 'ace-builds/src-noconflict/mode-nginx'
import 'ace-builds/src-noconflict/theme-monokai'
import 'ace-builds/src-noconflict/ext-language_tools'
import 'ace-builds/src-noconflict/ext-searchbox'

const loading = ref(false)
const saving = ref(false)
const reloading = ref(false)
const toggling = ref(null)

const domains = ref([])
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

const showEditorModal = ref(false)
const showUnsavedConfirmModal = ref(false)
const showReloadModal = ref(false)
const showTestModal = ref(false)
const showShortcutsHelp = ref(false)
const searchInput = ref(null)

const editingDomain = ref(null)
const editorContent = ref('')
const originalEditorContent = ref('')
const testResult = ref(null)

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

const handleKeyboardShortcuts = (e) => {
  // Global Ctrl + S / Cmd + S inside Nginx Editor
  if (e.ctrlKey && e.key === 's' && showEditorModal.value) {
    e.preventDefault()
    saveAndTest(false)
    return
  }

  // Global Ctrl + Enter inside Nginx Editor
  if (e.ctrlKey && e.key === 'Enter' && showEditorModal.value) {
    e.preventDefault()
    saveAndTest(true)
    return
  }

  // Escape — Close helper, close editor modal, or blur search input
  if (e.key === 'Escape') {
    e.preventDefault()
    if (showShortcutsHelp.value) {
      showShortcutsHelp.value = false
    } else if (showEditorModal.value) {
      closeEditorConfirm()
    } else if (document.activeElement === searchInput.value) {
      searchInput.value?.blur()
    } else if (searchQuery.value) {
      searchQuery.value = ''
    }
    return
  }

  // F1 or '?' — Show Keyboard Shortcuts Help Modal
  if (e.key === 'F1' || (e.key === '?' && e.shiftKey)) {
    e.preventDefault()
    showShortcutsHelp.value = !showShortcutsHelp.value
    return
  }

  // Global Ctrl + F: Focus Search Input (if editor is NOT open)
  if (e.ctrlKey && e.key === 'f' && !showEditorModal.value) {
    e.preventDefault()
    searchInput.value?.focus()
    return
  }
}

onMounted(() => {
  loadDomains()
  window.addEventListener('keydown', handleKeyboardShortcuts)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyboardShortcuts)
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const getAlertIcon = (type) => {
  const icons = {
    success: 'check_circle',
    danger: 'error',
    warning: 'warning',
    info: 'info'
  }
  return icons[type] || 'info'
}

const loadDomains = async () => {
  try {
    loading.value = true
    const response = await axios.get('/nginx/domains')
    domains.value = response.data.domains
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load domains')
  } finally {
    loading.value = false
  }
}

const filteredDomains = computed(() => {
  if (!searchQuery.value) return domains.value
  const q = searchQuery.value.toLowerCase()
  return domains.value.filter(d => d.domain.toLowerCase().includes(q))
})

const totalPages = computed(() => Math.ceil(filteredDomains.value.length / itemsPerPage.value))
const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage.value)
const paginationEnd = computed(() => currentPage.value * itemsPerPage.value)

const paginatedDomains = computed(() => {
  return filteredDomains.value.slice(paginationStart.value, paginationEnd.value)
})

let aceEditor = null

const initNginxEditor = () => {
  if (aceEditor) aceEditor.destroy()
  
  aceEditor = ace.edit("nginx-editor-container")
  aceEditor.setTheme("ace/theme/monokai")
  aceEditor.session.setMode("ace/mode/nginx")
  aceEditor.setValue(editorContent.value, -1)
  
  aceEditor.setOptions({
    fontSize: "14px",
    enableBasicAutocompletion: true,
    enableLiveAutocompletion: true,
    showPrintMargin: false,
    scrollPastEnd: 0.5,
    wrap: true
  })

  // Close editor on pressing Esc inside the Ace Editor
  aceEditor.commands.addCommand({
    name: 'closeEditorOnEsc',
    bindKey: {win: 'Esc', mac: 'Esc'},
    exec: function(editor) {
      closeEditorConfirm()
    }
  })

  // Save progress (keep open) on pressing Ctrl + S / Cmd + S inside the Ace Editor
  aceEditor.commands.addCommand({
    name: 'saveNginxOnCtrlS',
    bindKey: {win: 'Ctrl-S', mac: 'Command-S'},
    exec: function(editor) {
      saveAndTest(false)
    }
  })

  // Save progress and close modal on pressing Ctrl + Enter inside the Ace Editor
  aceEditor.commands.addCommand({
    name: 'saveNginxOnCtrlEnter',
    bindKey: {win: 'Ctrl-Enter', mac: 'Command-Enter'},
    exec: function(editor) {
      saveAndTest(true)
    }
  })

  aceEditor.on('change', () => {
    editorContent.value = aceEditor.getValue()
  })
}

const openEditor = async (domain) => {
  try {
    loading.value = true
    const response = await axios.post('/nginx/config/read', { domain: domain.domain })
    editorContent.value = response.data.content
    originalEditorContent.value = response.data.content
    editingDomain.value = domain
    showEditorModal.value = true
    
    nextTick(() => {
      initNginxEditor()
    })
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to read configuration')
  } finally {
    loading.value = false
  }
}

const closeEditor = () => {
  if (aceEditor) {
    aceEditor.destroy()
    aceEditor = null
  }
  showEditorModal.value = false
  editingDomain.value = null
  editorContent.value = ''
}

const closeEditorConfirm = () => {
  const isDirty = editorContent.value !== originalEditorContent.value
  if (isDirty) {
    showUnsavedConfirmModal.value = true
  } else {
    closeEditor()
  }
}

const saveAndTest = async (closeAfterSave = true) => {
  try {
    saving.value = true
    await axios.post('/nginx/config/save', {
      domain: editingDomain.value.domain,
      content: editorContent.value
    })
    showAlert('success', 'Configuration saved and tested successfully')
    originalEditorContent.value = editorContent.value
    if (closeAfterSave) {
      closeEditor()
    }
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to save configuration')
  } finally {
    saving.value = false
  }
}

const toggleDomain = async (domain) => {
  try {
    toggling.value = domain.domain
    const response = await axios.post('/nginx/toggle', {
      domain: domain.domain,
      enabled: !domain.isEnabled
    })
    domain.isEnabled = !domain.isEnabled
    showAlert('success', response.data.message)
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to toggle domain')
  } finally {
    toggling.value = null
  }
}

const testNginxConfig = async () => {
  try {
    loading.value = true
    const response = await axios.post('/nginx/test')
    testResult.value = response.data
    showTestModal.value = true
  } catch (error) {
    testResult.value = {
      success: false,
      message: error.response?.data?.message || 'Configuration test failed',
      output: error.response?.data?.output || error.message
    }
    showTestModal.value = true
  } finally {
    loading.value = false
  }
}

const confirmReload = () => {
  showReloadModal.value = true
}

const reloadNginx = async () => {
  try {
    reloading.value = true
    await axios.post('/nginx/reload')
    showAlert('info', 'Nginx reload scheduled. Service will reload in 1 second.')
    showReloadModal.value = false
    
    // Wait and then show success
    setTimeout(() => {
      showAlert('success', 'Nginx reloaded successfully!')
    }, 2000)
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to reload Nginx')
  } finally {
    reloading.value = false
  }
}

const reloadAfterTest = async () => {
  showTestModal.value = false
  await reloadNginx()
}

// ═══════════════════════════════════════════════════════════════
// Reverse Proxy Management
// ═══════════════════════════════════════════════════════════════
const showProxyModal = ref(false)
const proxyDomain = ref(null)
const proxyLoading = ref(false)
const proxyForm = ref({
  domain: '',
  target_url: 'http://127.0.0.1:3000',
  preset: 'nodejs',
  enable_websocket: true,
  client_max_body_size: '100M',
  proxy_buffering: true,
  proxy_read_timeout: 600,
  is_proxy: false
})

const proxyPresets = [
  { id: 'nodejs', name: 'Node.js / Next', defaultPort: '3000', icon: '🚀' },
  { id: 'python', name: 'Python / FastAPI', defaultPort: '8000', icon: '🐍' },
  { id: 'go', name: 'Go / Rust', defaultPort: '8080', icon: '🐹' },
  { id: 'docker', name: 'Docker / Custom', defaultPort: '5000', icon: '🐳' }
]

const applyPreset = (preset) => {
  proxyForm.value.preset = preset.id
  if (!proxyForm.value.target_url || proxyForm.value.target_url.includes('127.0.0.1') || /^\d+$/.test(proxyForm.value.target_url)) {
    proxyForm.value.target_url = `http://127.0.0.1:${preset.defaultPort}`
  }
}

const generatedProxyPreview = computed(() => {
  let target = proxyForm.value.target_url || 'http://127.0.0.1:3000'
  if (/^\d+$/.test(target)) target = `http://127.0.0.1:${target}`
  else if (/^:\d+$/.test(target)) target = `http://127.0.0.1${target}`
  else if (!target.startsWith('http://') && !target.startsWith('https://') && !target.startsWith('unix:')) target = `http://${target}`

  const ws = proxyForm.value.enable_websocket
    ? `    proxy_set_header Upgrade $http_upgrade;\n    proxy_set_header Connection "upgrade";\n`
    : ''
  const buffering = proxyForm.value.proxy_buffering ? 'on' : 'off'
  const timeout = proxyForm.value.proxy_read_timeout || 600
  const maxBody = proxyForm.value.client_max_body_size || '100M'

  return `client_max_body_size ${maxBody};

location / {
    proxy_pass ${target};
    proxy_http_version 1.1;
${ws}    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_buffering ${buffering};
    proxy_read_timeout ${timeout}s;
}`
})

const openProxyModal = async (domain) => {
  proxyDomain.value = domain
  proxyLoading.value = true
  showProxyModal.value = true

  // Set fallback values
  proxyForm.value = {
    domain: domain.domain,
    target_url: domain.proxyTarget || 'http://127.0.0.1:3000',
    preset: domain.proxyPreset || 'nodejs',
    enable_websocket: true,
    client_max_body_size: '100M',
    proxy_buffering: true,
    proxy_read_timeout: 600,
    is_proxy: domain.isProxy || false
  }

  try {
    const res = await axios.post('/nginx/proxy/status', { domain: domain.domain })
    if (res.data) {
      proxyForm.value = {
        domain: domain.domain,
        target_url: res.data.target_url || 'http://127.0.0.1:3000',
        preset: res.data.preset || 'nodejs',
        enable_websocket: res.data.enable_websocket !== false,
        client_max_body_size: res.data.client_max_body_size || '100M',
        proxy_buffering: res.data.proxy_buffering !== false,
        proxy_read_timeout: res.data.proxy_read_timeout || 600,
        is_proxy: res.data.is_proxy || false
      }
    }
  } catch (err) {
    // Keep fallback
  } finally {
    proxyLoading.value = false
  }
}

const saveProxy = async () => {
  try {
    proxyLoading.value = true
    const res = await axios.post('/nginx/proxy/apply', {
      domain: proxyDomain.value.domain,
      target_url: proxyForm.value.target_url,
      preset: proxyForm.value.preset,
      enable_websocket: proxyForm.value.enable_websocket,
      client_max_body_size: proxyForm.value.client_max_body_size,
      proxy_buffering: proxyForm.value.proxy_buffering,
      proxy_read_timeout: proxyForm.value.proxy_read_timeout
    })
    showAlert('success', res.data.message || 'Reverse proxy applied successfully!')
    showProxyModal.value = false
    await loadDomains()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || err.response?.data?.details || 'Failed to apply reverse proxy')
  } finally {
    proxyLoading.value = false
  }
}

const removeProxy = async () => {
  if (!confirm(`Are you sure you want to disable reverse proxy for ${proxyDomain.value.domain} and restore standard PHP / static handling?`)) {
    return
  }
  try {
    proxyLoading.value = true
    const res = await axios.post('/nginx/proxy/remove', {
      domain: proxyDomain.value.domain
    })
    showAlert('success', res.data.message || 'Reverse proxy disabled.')
    showProxyModal.value = false
    await loadDomains()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || err.response?.data?.details || 'Failed to remove reverse proxy')
  } finally {
    proxyLoading.value = false
  }
}
</script>

<style scoped>
.preset-card {
  background: #f8fafc;
  border-color: #e2e8f0;
  transition: all 0.2s ease;
}
.preset-card:hover {
  border-color: #1171ef !important;
  background: #f0f7ff;
  transform: translateY(-2px);
}
.preset-card-selected {
  border-color: #1171ef !important;
  background: #e9f2ff !important;
  box-shadow: 0 0 0 2px rgba(17, 113, 239, 0.2);
}
.btn-proxy {
  color: #5e72e4;
}
.btn-proxy:hover {
  background: #5e72e4;
  color: #fff;
}
.btn-proxy-active {
  background: #2dce89;
  color: #fff;
}
.btn-proxy-active:hover {
  background: #28b879;
  color: #fff;
}
.status-proxy {
  background: #e6f9f0;
  color: #0c6b36;
  font-weight: 700;
}
.domain-row {
  transition: all 0.2s ease;
}
.domain-row:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.icon-box-nginx {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8f9fa;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 50px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.pill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 8px;
  position: relative;
}

.status-active {
  background: #e6f6ec;
  color: #0c6b36;
}
.status-active .pill-dot {
  background: #2dce89;
  box-shadow: 0 0 0 2px rgba(45, 206, 137, 0.2);
}

.status-info {
  background: #e9f2ff;
  color: #1171ef;
}

.status-error {
  background: #feeef2;
  color: #9d174d;
}
.status-error .pill-dot {
  background: #f5365c;
  box-shadow: 0 0 0 2px rgba(245, 54, 92, 0.2);
}

.status-secondary {
  background: #f1f5f9;
  color: #475569;
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

.btn-edit:hover { background: #1171ef; color: #fff; }
.btn-toggle-on { color: #2dce89; }
.btn-toggle-on:hover { background: #2dce89; color: #fff; }
.btn-toggle-off { color: #f5365c; }
.btn-toggle-off:hover { background: #f5365c; color: #fff; }

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.nginx-editor-box {
  width: 100%;
  height: 550px;
  border-radius: 12px;
  overflow: hidden;
  font-family: 'Fira Code', monospace;
}
.glass-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(8px) saturate(160%);
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 1rem;
  box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.1);
}
.btn-icon-only.btn-rounded {
  border-radius: 50% !important;
  width: 38px;
  height: 38px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>


