<template>
  <MainLayout>
    <Head title="DNS Management" />
    <div class="container-fluid py-4">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">DNS Management</h4>
              <p class="mb-0 text-sm">Manage DNS records via Cloudflare</p>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-secondary mb-0" @click="loadDomains" :disabled="loading">
                <i class="material-symbols-rounded text-sm me-1">refresh</i>
                Refresh
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

      <!-- Domains List -->
      <div class="row" v-if="!selectedDomain">
        <div class="col-12">
          <div class="card">
            <div class="card-header pb-0">
              <h6 class="mb-0">Select a Domain</h6>
            </div>
            <div class="card-body">
              <div v-if="loading" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
              </div>
              <div v-else class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Domain</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="domain in domains" :key="domain.domain">
                      <td>
                        <div class="d-flex align-items-center">
                          <i class="material-symbols-rounded text-info me-2">language</i>
                          <h6 class="mb-0 text-sm">{{ domain.domain }}</h6>
                        </div>
                      </td>
                      <td>
                        <span v-if="domain.is_connected" class="badge bg-gradient-success">Connected</span>
                        <span v-else class="badge bg-gradient-secondary">Not Connected</span>
                      </td>
                      <td class="text-center">
                        <button class="btn btn-link text-primary mb-0 px-2" @click="selectDomain(domain)">
                          Manage
                        </button>
                      </td>
                    </tr>
                    <tr v-if="domains.length === 0">
                      <td colspan="3" class="text-center py-4 text-secondary">No domains found</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Domain Details -->
      <div class="row" v-else>
        <div class="col-12">
          <div class="card">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
              <div>
                <button class="btn btn-link px-0 text-secondary mb-0 me-3" @click="selectedDomain = null">
                  <i class="material-symbols-rounded">arrow_back</i> Back
                </button>
                <h6 class="mb-0 d-inline-block">{{ selectedDomain.domain }}</h6>
              </div>
              <button v-if="selectedDomain.is_connected" class="btn bg-gradient-dark mb-0" @click="showAddRecordModal = true">
                <i class="material-symbols-rounded text-sm me-1">add</i> Add Record
              </button>
            </div>
            <div class="card-body">
              
              <!-- Setup Credentials -->
              <div v-if="!selectedDomain.is_connected" class="py-4">
                <div class="alert alert-info">
                  Connect your Cloudflare account to manage DNS records for this domain.
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <label class="form-label text-xs font-weight-bolder">Cloudflare API Token</label>
                    <div class="input-group input-group-outline mb-3">
                      <input type="password" class="form-control" v-model="credentialsForm.api_token" placeholder="Enter API Token">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-xs font-weight-bolder">Zone ID</label>
                    <div class="input-group input-group-outline mb-3">
                      <input type="text" class="form-control" v-model="credentialsForm.zone_id" placeholder="Enter Zone ID">
                    </div>
                  </div>
                </div>
                <button class="btn bg-gradient-info" @click="saveCredentials" :disabled="savingCredentials">
                  <span v-if="savingCredentials" class="spinner-border spinner-border-sm me-2"></span>
                  Save & Connect
                </button>
              </div>

              <!-- Records List -->
              <div v-else>
                <div v-if="loadingRecords" class="text-center py-4">
                  <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div v-else class="table-responsive">
                  <table class="table align-items-center mb-0">
                    <thead>
                      <tr>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Type</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Name</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Content</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Proxy Status</th>
                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">TTL</th>
                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="record in records" :key="record.id">
                        <td><span class="badge bg-light text-dark border">{{ record.type }}</span></td>
                        <td><span class="text-sm font-weight-bold">{{ record.name }}</span></td>
                        <td><span class="text-sm text-truncate d-inline-block" style="max-width: 250px;">{{ record.content }}</span></td>
                        <td>
                          <span v-if="record.proxied" class="badge bg-gradient-warning"><i class="material-symbols-rounded text-xs me-1">cloud</i> Proxied</span>
                          <span v-else class="badge bg-gradient-secondary">DNS Only</span>
                        </td>
                        <td><span class="text-xs">{{ record.ttl === 1 ? 'Auto' : record.ttl }}</span></td>
                        <td class="text-center">
                          <button class="btn btn-link text-primary mb-0 px-2" @click="editRecord(record)">
                            <i class="material-symbols-rounded text-sm">edit</i>
                          </button>
                          <button class="btn btn-link text-danger mb-0 px-2" @click="deleteRecord(record)">
                            <i class="material-symbols-rounded text-sm">delete</i>
                          </button>
                        </td>
                      </tr>
                      <tr v-if="records.length === 0">
                        <td colspan="6" class="text-center py-4 text-secondary">No records found</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- Add/Edit Record Modal -->
      <div class="modal-backdrop fade show" v-if="showAddRecordModal || editingRecord"></div>
      <div class="modal fade show d-block" v-if="showAddRecordModal || editingRecord">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">{{ editingRecord ? 'Edit Record' : 'Add Record' }}</h5>
              <button type="button" class="btn-close" @click="closeRecordModal"></button>
            </div>
            <div class="modal-body">
              <label class="form-label text-xs font-weight-bolder">Type</label>
              <div class="input-group input-group-outline mb-3">
                <select class="form-control" v-model="recordForm.type">
                  <option value="A">A</option>
                  <option value="AAAA">AAAA</option>
                  <option value="CNAME">CNAME</option>
                  <option value="TXT">TXT</option>
                  <option value="MX">MX</option>
                </select>
              </div>
              <label class="form-label text-xs font-weight-bolder">Name (@ for root)</label>
              <div class="input-group input-group-outline mb-3">
                <input type="text" class="form-control" v-model="recordForm.name" placeholder="@ or subdomain">
              </div>
              <label class="form-label text-xs font-weight-bolder">Content</label>
              <div class="input-group input-group-outline mb-3">
                <input type="text" class="form-control" v-model="recordForm.content" placeholder="IP Address or value">
              </div>
              <div v-if="recordForm.type === 'MX'">
                <label class="form-label text-xs font-weight-bolder">Priority</label>
                <div class="input-group input-group-outline mb-3">
                  <input type="number" class="form-control" v-model="recordForm.priority" placeholder="10">
                </div>
              </div>
              <label class="form-label text-xs font-weight-bolder">TTL</label>
              <div class="input-group input-group-outline mb-3">
                <select class="form-control" v-model="recordForm.ttl">
                  <option :value="1">Auto</option>
                  <option :value="120">2 min</option>
                  <option :value="300">5 min</option>
                  <option :value="3600">1 hr</option>
                </select>
              </div>
              <div class="form-check form-switch ps-0 mt-3 d-flex align-items-center">
                <input class="form-check-input ms-auto" type="checkbox" v-model="recordForm.proxied">
                <label class="form-check-label text-body ms-3 mb-0">Proxy through Cloudflare</label>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="closeRecordModal">Cancel</button>
              <button class="btn bg-gradient-info" @click="saveRecord" :disabled="savingRecord">
                <span v-if="savingRecord" class="spinner-border spinner-border-sm me-2"></span>
                Save
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
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const domains = ref([])
const selectedDomain = ref(null)

const loadingRecords = ref(false)
const records = ref([])

const savingCredentials = ref(false)
const credentialsForm = ref({
  api_token: '',
  zone_id: ''
})

const showAddRecordModal = ref(false)
const editingRecord = ref(null)
const savingRecord = ref(false)
const recordForm = ref({
  type: 'A',
  name: '@',
  content: '',
  ttl: 1,
  proxied: true,
  priority: 10
})

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
    const response = await axios.get('/dns/domains')
    domains.value = response.data.domains
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load domains')
  } finally {
    loading.value = false
  }
}

const selectDomain = (domain) => {
  selectedDomain.value = domain
  if (domain.is_connected) {
    loadRecords()
  } else {
    credentialsForm.value = { api_token: '', zone_id: '' }
  }
}

const saveCredentials = async () => {
  try {
    savingCredentials.value = true
    await axios.post(`/dns/${selectedDomain.value.domain}/credentials`, credentialsForm.value)
    showAlert('success', 'Cloudflare connected successfully')
    selectedDomain.value.is_connected = true
    loadRecords()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to connect to Cloudflare')
  } finally {
    savingCredentials.value = false
  }
}

const loadRecords = async () => {
  try {
    loadingRecords.value = true
    const response = await axios.get(`/dns/${selectedDomain.value.domain}/records`)
    records.value = response.data.records
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to load records')
  } finally {
    loadingRecords.value = false
  }
}

const editRecord = (record) => {
  editingRecord.value = record
  recordForm.value = {
    type: record.type,
    name: record.name,
    content: record.content,
    ttl: record.ttl,
    proxied: record.proxied,
    priority: record.priority || 10
  }
}

const closeRecordModal = () => {
  showAddRecordModal.value = false
  editingRecord.value = null
  recordForm.value = { type: 'A', name: '@', content: '', ttl: 1, proxied: true, priority: 10 }
}

const saveRecord = async () => {
  try {
    savingRecord.value = true
    if (editingRecord.value) {
      await axios.put(`/dns/${selectedDomain.value.domain}/records/${editingRecord.value.id}`, recordForm.value)
      showAlert('success', 'Record updated successfully')
    } else {
      await axios.post(`/dns/${selectedDomain.value.domain}/records`, recordForm.value)
      showAlert('success', 'Record created successfully')
    }
    closeRecordModal()
    loadRecords()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to save record')
  } finally {
    savingRecord.value = false
  }
}

const deleteRecord = async (record) => {
  if (!confirm(`Are you sure you want to delete this ${record.type} record?`)) return
  try {
    await axios.delete(`/dns/${selectedDomain.value.domain}/records/${record.id}`)
    showAlert('success', 'Record deleted successfully')
    loadRecords()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to delete record')
  }
}
</script>
