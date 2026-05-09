<template>
  <MainLayout>
    <Head title="Domains" />
    <div class="container-fluid py-4">

      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">Domain Management</h4>
              <p class="mb-0 text-sm">Manage your hosted domains and websites</p>
            </div>
            <button v-if="isRootOrAdmin" class="btn bg-gradient-dark mb-0" @click="openAddModal">
              <i class="material-symbols-rounded text-sm me-1">add</i>
              Add Domain
            </button>
          </div>
        </div>
      </div>

      <!-- Alert Messages -->
      <div class="row" v-if="alert.show">
        <div class="col-12">
          <div :class="`alert alert-${alert.type} alert-dismissible fade show`" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">{{ alert.type === 'success' ? 'check_circle' : 'error' }}</i></span>
            <span class="alert-text"><strong>{{ alert.type === 'success' ? 'Success!' : 'Error!' }}</strong> {{ alert.message }}</span>
            <button type="button" class="btn-close" @click="alert.show = false"></button>
          </div>
        </div>
      </div>

      <!-- Domain Table -->
      <div class="row">
        <div class="col-12">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
              <h6 class="mb-0">Your Domains</h6>
              <div class="ms-md-auto pe-md-3 d-flex align-items-center">
                <div class="input-group input-group-sm">
                  <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                  <input v-model="searchQuery" type="text" class="form-control" placeholder="Search domains...">
                </div>
              </div>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                        Domain Name
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Status
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Storage
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Document Root
                      </th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                        Actions
                      </th>
                    </tr>
                  </thead>

                   <tbody>
                    <tr v-for="domain in paginatedDomains" :key="domain.name" :class="{ 'opacity-6': !domain.is_active }" class="domain-row">
                      <td>
                        <div class="d-flex px-3 py-2">
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm font-weight-bold">
                              {{ domain.name }}
                              <i v-if="!domain.is_active" class="material-symbols-rounded text-xs text-warning ms-1" title="DNS not pointing to this server">warning</i>
                            </h6>
                            <p class="text-xs text-secondary mb-0 opacity-7">/var/www/{{ domain.name }}</p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span v-if="domain.is_active" class="status-pill status-active">
                          <span class="pill-dot"></span>
                          Active
                        </span>
                        <span v-else class="status-pill status-configuring" :title="`Point A record to ${domain.server_ip}`">
                          <span class="pill-dot"></span>
                          Configuring
                        </span>
                      </td>
                      <td>
                        <span class="text-xs font-weight-bold text-dark">{{ domain.storage || '0B' }}</span>
                      </td>
                      <td>
                        <div class="d-flex align-items-center">
                          <span class="text-xs text-secondary mb-0 me-2">{{ domain.document_root || ('/var/www/' + domain.name) }}</span>
                          <button v-if="isRootOrAdmin" class="btn btn-link text-secondary p-0 mb-0 btn-edit-root" @click="openRootModal(domain)" title="Change document root">
                            <i class="material-symbols-rounded text-xs">edit</i>
                          </button>
                        </div>
                      </td>
                      <td class="align-middle text-center">
                        <div class="d-flex justify-content-center gap-2">
                          <button 
                            class="action-btn btn-view" 
                            @click="viewWebsite(domain.name)"
                            title="View website"
                          >
                            <i class="material-symbols-rounded">visibility</i>
                          </button>
                          <button 
                            class="action-btn btn-folder" 
                            @click="openFileManager(domain.name)"
                            title="File Manager"
                          >
                            <i class="material-symbols-rounded">folder</i>
                          </button>
                          <button 
                            v-if="isRootOrAdmin"
                            class="action-btn btn-edit" 
                            @click="openEditModal(domain.name)"
                            title="Edit domain"
                          >
                            <i class="material-symbols-rounded">edit</i>
                          </button>
                          <button 
                            v-if="isRootOrAdmin"
                            class="action-btn btn-delete" 
                            @click="confirmDelete(domain.name)"
                            title="Delete domain"
                          >
                            <i class="material-symbols-rounded">delete</i>
                          </button>
                        </div>
                      </td>
                    </tr>

                    <tr v-if="domains.length === 0 && !loading">
                      <td colspan="5" class="text-center py-5">
                        <div class="empty-state">
                          <i class="material-symbols-rounded text-secondary opacity-3" style="font-size: 64px;">language</i>
                          <p class="text-secondary mt-3">No domains found. Add your first domain to get started.</p>
                        </div>
                      </td>
                    </tr>

                    <tr v-if="loading">
                      <td colspan="5" class="text-center py-5">
                        <div class="spinner-border text-dark" role="status" style="width: 3rem; height: 3rem;">
                          <span class="visually-hidden">Loading...</span>
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

      <!-- Add/Edit Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">
                {{ isEdit ? "Edit Domain" : "Add New Domain" }}
              </h5>
              <button type="button" class="btn-close" @click="closeModal" :disabled="submitting"></button>
            </div>

            <div class="modal-body">
              <!-- DNS Tip -->
              <div v-if="!isEdit" class="alert alert-info py-2 mb-3 text-white">
                <div class="d-flex align-items-center">
                  <i class="material-symbols-rounded me-2 text-sm">info</i>
                  <small>
                    <strong>Tip:</strong> Point your domain's <strong>A record</strong> to 
                    <code class="text-white bg-dark px-1 rounded">{{ serverIp || 'fetching...' }}</code> 
                    before adding it here.
                  </small>
                </div>
              </div>

              <div class="form-group">
                <label class="form-control-label">Domain Name</label>
                <div class="input-group input-group-outline" :class="{ 'is-invalid': validationError }">
                  <input 
                    type="text" 
                    v-model="domainInput" 
                    class="form-control"
                    :class="{ 'is-invalid': validationError }"
                    placeholder="example.com"
                    @input="clearValidationError"
                    @keyup.enter="isEdit ? updateDomain() : saveDomain()"
                    :disabled="submitting"
                  >
                </div>
                <small class="text-muted d-block mt-1">
                  Enter a valid domain name (e.g., example.com, subdomain.example.com)
                </small>
                <div class="invalid-feedback d-block" v-if="validationError">
                  {{ validationError }}
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button 
                class="btn btn-outline-secondary mb-0" 
                @click="closeModal"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button 
                class="btn bg-gradient-dark mb-0" 
                @click="isEdit ? updateDomain() : saveDomain()"
                :disabled="submitting || !domainInput.trim()"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEdit ? "Update Domain" : "Add Domain" }}
              </button>
            </div>

          </div>
        </div>
      </div>
      <!-- Document Root Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showRootModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">
                Change Document Root
              </h5>
              <button type="button" class="btn-close" @click="closeRootModal" :disabled="submitting"></button>
            </div>

            <div class="modal-body">
              <div class="alert alert-info py-2 mb-3 text-white">
                <div class="d-flex align-items-center">
                  <i class="material-symbols-rounded me-2 text-sm">info</i>
                  <small>
                    This changes where Nginx looks for your website files. It must be inside your domain's folder.
                  </small>
                </div>
              </div>

              <div class="form-group">
                <label class="form-control-label">Document Root Path</label>
                <div class="input-group input-group-outline" :class="{ 'is-invalid': rootValidationError }">
                  <input 
                    type="text" 
                    v-model="rootInput" 
                    class="form-control"
                    :class="{ 'is-invalid': rootValidationError }"
                    placeholder="/var/www/example.com/public"
                    @input="rootValidationError = ''"
                    @keyup.enter="updateDocumentRoot()"
                    :disabled="submitting"
                  >
                </div>
                <div class="invalid-feedback d-block" v-if="rootValidationError">
                  {{ rootValidationError }}
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button 
                class="btn btn-outline-secondary mb-0" 
                @click="closeRootModal"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button 
                class="btn bg-gradient-dark mb-0" 
                @click="updateDocumentRoot()"
                :disabled="submitting || !rootInput.trim()"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                Save
              </button>
            </div>

          </div>
        </div>
      </div>
      <!-- Delete Confirmation Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showDeleteModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder text-danger">
                <i class="material-symbols-rounded me-1">warning</i>
                Confirm Deletion
              </h5>
              <button type="button" class="btn-close" @click="showDeleteModal = false" :disabled="submitting"></button>
            </div>

            <div class="modal-body">
              <p class="mb-0">
                Are you sure you want to delete <strong>{{ domainToDelete }}</strong>?
              </p>
              <p class="text-sm text-danger mb-0 mt-2">
                <i class="material-symbols-rounded text-sm me-1">info</i>
                This will permanently delete the domain folder and all its contents. This action cannot be undone.
              </p>
            </div>

            <div class="modal-footer">
              <button 
                class="btn btn-outline-secondary mb-0" 
                @click="showDeleteModal = false"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button 
                class="btn bg-gradient-danger mb-0" 
                @click="deleteDomain"
                :disabled="submitting"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                Delete Domain
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
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { Head, router, usePage } from '@inertiajs/vue3'

const page = usePage()
const userRole = computed(() => page.props.auth?.user?.role || 'user')
const isRootOrAdmin = computed(() => page.props.auth?.user?.is_root || userRole.value === 'root' || userRole.value === 'admin')

const domains = ref([])
const searchQuery = ref("")
const currentPage = ref(1)
const itemsPerPage = ref(10)
const serverIp = ref("")
const showModal = ref(false)
const showDeleteModal = ref(false)
const showRootModal = ref(false)
const isEdit = ref(false)
const domainInput = ref("")
const rootInput = ref("")
const oldDomain = ref("")
const domainToDelete = ref("")
const loading = ref(false)
const submitting = ref(false)
const validationError = ref("")
const rootValidationError = ref("")

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

onMounted(() => {
  loadDomains()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => {
    alert.value.show = false
  }, 5000)
}

const validateDomain = (domain) => {
  const trimmed = domain.trim()
  
  if (!trimmed) {
    return "Domain name is required"
  }
  
  // Basic domain validation regex
  const domainRegex = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i
  
  if (!domainRegex.test(trimmed)) {
    return "Please enter a valid domain name (e.g., example.com)"
  }
  
  if (trimmed.length > 253) {
    return "Domain name is too long (max 253 characters)"
  }
  
  return null
}

const clearValidationError = () => {
  validationError.value = ""
}

const filteredDomains = computed(() => {
  if (!searchQuery.value) return domains.value
  const q = searchQuery.value.toLowerCase()
  return domains.value.filter(domain => domain.name.toLowerCase().includes(q))
})

const totalPages = computed(() => Math.ceil(filteredDomains.value.length / itemsPerPage.value))
const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage.value)
const paginationEnd = computed(() => currentPage.value * itemsPerPage.value)

const paginatedDomains = computed(() => {
  return filteredDomains.value.slice(paginationStart.value, paginationEnd.value)
})

const loadDomains = async () => {
  try {
    loading.value = true
    const res = await axios.get('/domains/api')
    domains.value = res.data.domains
    serverIp.value = res.data.server_ip
  } catch (error) {
    showAlert('danger', 'Failed to load domains')
    console.error(error)
  } finally {
    loading.value = false
  }
}

const openAddModal = () => {
  domainInput.value = ""
  validationError.value = ""
  isEdit.value = false
  showModal.value = true
}

const openEditModal = (domain) => {
  isEdit.value = true
  oldDomain.value = domain
  domainInput.value = domain
  validationError.value = ""
  showModal.value = true
}

const viewWebsite = (domain) => {
  // Open domain in new tab
  const url = `http://${domain}`
  window.open(url, '_blank')
}

const openFileManager = (domain) => {
  // Navigate to file manager with domain parameter
  router.visit(`/file-manager/${domain}`)
}

const saveDomain = async () => {
  const error = validateDomain(domainInput.value)
  if (error) {
    validationError.value = error
    return
  }

  try {
    submitting.value = true
    await axios.post('/domains', { domain: domainInput.value.trim().toLowerCase() })
    showAlert('success', `Domain "${domainInput.value}" has been added successfully`)
    closeModal()
    loadDomains()
  } catch (error) {
    if (error.response?.status === 409) {
      validationError.value = "This domain already exists"
    } else if (error.response?.data?.error) {
      validationError.value = error.response.data.error
    } else {
      showAlert('danger', 'Failed to add domain. Please try again.')
    }
  } finally {
    submitting.value = false
  }
}

const updateDomain = async () => {
  const error = validateDomain(domainInput.value)
  if (error) {
    validationError.value = error
    return
  }

  try {
    submitting.value = true
    await axios.put(`/domains/${oldDomain.value}`, { 
      domain: domainInput.value.trim().toLowerCase() 
    })
    showAlert('success', `Domain has been updated successfully`)
    closeModal()
    loadDomains()
  } catch (error) {
    if (error.response?.status === 409) {
      validationError.value = "This domain already exists"
    } else if (error.response?.status === 404) {
      validationError.value = "Original domain not found"
    } else if (error.response?.data?.error) {
      validationError.value = error.response.data.error
    } else {
      showAlert('danger', 'Failed to update domain. Please try again.')
    }
  } finally {
    submitting.value = false
  }
}

const confirmDelete = (domain) => {
  domainToDelete.value = domain
  showDeleteModal.value = true
}

const deleteDomain = async () => {
  try {
    submitting.value = true
    await axios.delete(`/domains/${domainToDelete.value}`)
    showAlert('success', `Domain "${domainToDelete.value}" has been deleted`)
    showDeleteModal.value = false
    loadDomains()
  } catch (error) {
    showAlert('danger', 'Failed to delete domain. Please try again.')
  } finally {
    submitting.value = false
  }
}

const updateDocumentRoot = async () => {
  if (!rootInput.value.trim()) {
    rootValidationError.value = "Document root is required"
    return
  }

  try {
    submitting.value = true
    await axios.put(`/domains/${oldDomain.value}/root`, { 
      document_root: rootInput.value.trim()
    })
    showAlert('success', `Document root has been updated successfully`)
    closeRootModal()
    loadDomains()
  } catch (error) {
    if (error.response?.data?.error) {
      rootValidationError.value = error.response.data.error
    } else {
      showAlert('danger', 'Failed to update document root. Please try again.')
    }
  } finally {
    submitting.value = false
  }
}

const openRootModal = (domain) => {
  oldDomain.value = domain.name
  rootInput.value = domain.document_root || `/var/www/${domain.name}`
  rootValidationError.value = ""
  showRootModal.value = true
}

const closeRootModal = () => {
  showRootModal.value = false
  rootInput.value = ""
  rootValidationError.value = ""
}

const closeModal = () => {
  showModal.value = false
  domainInput.value = ""
  validationError.value = ""
}
</script>

<style scoped>
.domain-row {
  transition: all 0.2s ease;
}
.domain-row:hover {
  background-color: rgba(0, 0, 0, 0.015);
}

/* Status Pill Design */
.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 100px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.pill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 8px;
}

.status-active {
  background-color: #e6fffa;
  color: #047857;
}
.status-active .pill-dot {
  background-color: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.status-configuring {
  background-color: #fffbeb;
  color: #92400e;
}
.status-configuring .pill-dot {
  background-color: #f59e0b;
  box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
}

/* Action Buttons */
.action-btn {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  background: transparent;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  color: #67748e;
}

.action-btn i {
  font-size: 1.25rem !important;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-view:hover {
  background-color: #e0f2fe;
  color: #0ea5e9;
}

.btn-folder:hover {
  background-color: #f0fdf4;
  color: #22c55e;
}

.btn-edit:hover {
  background-color: #f8fafc;
  color: #64748b;
}

.btn-delete:hover {
  background-color: #fef2f2;
  color: #ef4444;
}

.btn-edit-root {
  opacity: 0.3;
  transition: opacity 0.2s;
}
.domain-row:hover .btn-edit-root {
  opacity: 1;
}

.empty-state {
  padding: 40px 0;
}
</style>

