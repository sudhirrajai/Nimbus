<template>
  <MainLayout>
    <div class="container-fluid py-4">

      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">Domain Management</h4>
              <p class="mb-0 text-sm">Manage your hosted domains and websites</p>
            </div>
            <button class="btn bg-gradient-dark mb-0" @click="openAddModal">
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
          <div class="card">
            <div class="card-header pb-0">
              <h6>Your Domains</h6>
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
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                        Actions
                      </th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="domain in domains" :key="domain">
                      <td>
                        <div class="d-flex px-2 py-1">
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm">{{ domain }}</h6>
                            <p class="text-xs text-secondary mb-0">/var/www/{{ domain }}</p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="badge badge-sm bg-gradient-success">Active</span>
                      </td>
                      <td class="align-middle text-center">
                        <button 
                          class="btn btn-link text-info mb-0 px-2" 
                          @click="viewWebsite(domain)"
                          title="View website"
                        >
                          <i class="material-symbols-rounded text-sm">visibility</i>
                        </button>
                        <button 
                          class="btn btn-link text-primary mb-0 px-2" 
                          @click="openFileManager(domain)"
                          title="File Manager"
                        >
                          <i class="material-symbols-rounded text-sm">folder</i>
                        </button>
                        <button 
                          class="btn btn-link text-secondary mb-0 px-2" 
                          @click="openEditModal(domain)"
                          title="Edit domain"
                        >
                          <i class="material-symbols-rounded text-sm">edit</i>
                        </button>
                        <button 
                          class="btn btn-link text-danger mb-0 px-2" 
                          @click="confirmDelete(domain)"
                          title="Delete domain"
                        >
                          <i class="material-symbols-rounded text-sm">delete</i>
                        </button>
                      </td>
                    </tr>

                    <tr v-if="domains.length === 0 && !loading">
                      <td colspan="3" class="text-center py-4">
                        <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">language</i>
                        <p class="text-secondary mb-0">No domains found. Add your first domain to get started.</p>
                      </td>
                    </tr>

                    <tr v-if="loading">
                      <td colspan="3" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                          <span class="visually-hidden">Loading...</span>
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
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const domains = ref([])
const showModal = ref(false)
const showDeleteModal = ref(false)
const isEdit = ref(false)
const domainInput = ref("")
const oldDomain = ref("")
const domainToDelete = ref("")
const loading = ref(false)
const submitting = ref(false)
const validationError = ref("")

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

const loadDomains = async () => {
  try {
    loading.value = true
    const res = await axios.get('/domains')
    domains.value = res.data
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

const closeModal = () => {
  showModal.value = false
  domainInput.value = ""
  validationError.value = ""
}
</script>

<style scoped>
.modal {
  background: rgba(0, 0, 0, 0.5);
}

.modal-content {
  border: none;
  border-radius: 1rem;
}

.is-invalid {
  border-color: #f44335 !important;
}

.invalid-feedback {
  color: #f44335;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.btn-link {
  text-decoration: none;
}

.btn-link:hover i {
  transform: scale(1.1);
  transition: transform 0.2s;
}
</style>