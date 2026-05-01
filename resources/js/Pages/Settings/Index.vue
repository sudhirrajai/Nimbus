<template>
    <MainLayout>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-dark">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h4 class="text-white mb-0">
                                        <i class="material-symbols-rounded me-2">settings</i>
                                        Panel Settings
                                    </h4>
                                    <p class="text-white text-sm mb-0 opacity-8">Configure your Nimbus Control Panel</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- General Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">tune</i>
                                General Settings
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Panel Name</label>
                                <input type="text" class="form-control" v-model="settings.panel_name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Timezone</label>
                                <select class="form-control form-select" v-model="settings.timezone">
                                    <option value="UTC">UTC</option>
                                    <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                                    <option value="America/New_York">America/New_York (EST)</option>
                                    <option value="America/Los_Angeles">America/Los_Angeles (PST)</option>
                                    <option value="Europe/London">Europe/London (GMT)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" v-model="settings.auto_refresh"
                                        id="autoRefresh">
                                    <label class="form-check-label" for="autoRefresh">
                                        <strong>Auto Refresh</strong><br>
                                        <small class="text-muted">Automatically refresh data on pages</small>
                                    </label>
                                </div>
                            </div>
                            <button class="btn bg-gradient-primary" @click="saveSettings" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ saving ? 'Saving...' : 'Save Settings' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">security</i>
                                Security Settings
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Session Lifetime (minutes)</label>
                                <input type="number" class="form-control" v-model="settings.session_lifetime" min="15"
                                    max="1440">
                                <small class="text-muted">How long before you're automatically logged out</small>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactor" disabled>
                                    <label class="form-check-label" for="twoFactor">
                                        <strong>Two-Factor Authentication</strong><br>
                                        <small class="text-muted">Coming soon</small>
                                    </label>
                                </div>
                            </div>
                            <hr class="horizontal dark">
                            <div class="mt-3">
                                <h6 class="text-uppercase text-xs font-weight-bolder opacity-7">IP Access Control</h6>
                                <div class="mb-3">
                                    <label class="form-label">Restriction Mode</label>
                                    <select class="form-control form-select" v-model="securityMode" @change="updateSecurityMode">
                                        <option value="off">Off (Allow All)</option>
                                        <option value="whitelist">Whitelist (Allow Only Listed IPs)</option>
                                        <option value="blacklist">Blacklist (Block Listed IPs)</option>
                                    </select>
                                    <p class="text-xs text-muted mt-2">
                                        <i class="material-symbols-rounded text-xs me-1">info</i>
                                        Your current IP: <strong>{{ currentIp }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IP Rules Table -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">gpp_good</i>
                                IP Access Rules
                            </h6>
                            <button class="btn btn-sm bg-gradient-dark mb-0" @click="showAddRule = true">
                                <i class="material-symbols-rounded text-sm">add</i> Add Rule
                            </button>
                        </div>
                        <div class="card-body px-0 pt-0 pb-2">
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">IP Address</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Type</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Description</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date Added</th>
                                            <th class="text-secondary opacity-7"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="rule in securityRules" :key="rule.id">
                                            <td>
                                                <div class="d-flex px-3 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm">{{ rule.ip_address }}</h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span :class="['badge badge-sm', rule.type === 'allow' ? 'bg-gradient-success' : 'bg-gradient-danger']">
                                                    {{ rule.type.toUpperCase() }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input class="form-check-input" type="checkbox" :checked="rule.is_active" @change="toggleRule(rule)">
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">{{ rule.description || '-' }}</p>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold">{{ formatDate(rule.created_at) }}</span>
                                            </td>
                                            <td class="align-middle">
                                                <button class="btn btn-link text-danger text-gradient px-3 mb-0" @click="deleteRule(rule)">
                                                    <i class="material-symbols-rounded text-sm me-2">delete</i>Delete
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="securityRules.length === 0">
                                            <td colspan="6" class="text-center py-4">
                                                <p class="text-sm text-secondary mb-0">No IP rules found.</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Rule Modal -->
            <div v-if="showAddRule" class="modal fade show" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-gradient-dark border-0">
                            <h6 class="modal-title text-white">Add Security Rule</h6>
                            <button type="button" class="btn-close btn-close-white" @click="showAddRule = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" class="form-control" v-model="newRule.ip_address" placeholder="e.g. 192.168.1.1 or 10.0.0.0/24">
                                <div class="mt-1">
                                    <button class="btn btn-link btn-sm p-0 text-primary" @click="newRule.ip_address = currentIp">
                                        Use current IP ({{ currentIp }})
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rule Type</label>
                                <select class="form-control form-select" v-model="newRule.type">
                                    <option value="allow">Allow</option>
                                    <option value="block">Block</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description (Optional)</label>
                                <input type="text" class="form-control" v-model="newRule.description" placeholder="e.g. Office IP">
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-link text-dark mb-0" @click="showAddRule = false">Cancel</button>
                            <button type="button" class="btn bg-gradient-dark mb-0" @click="saveRule" :disabled="savingRule">
                                <span v-if="savingRule" class="spinner-border spinner-border-sm me-2"></span>
                                Save Rule
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">info</i>
                                System Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <p class="text-sm mb-0 text-secondary">Panel Version</p>
                                    <p class="text-sm fw-bold">v{{ panelVersion }}</p>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-sm mb-0 text-secondary">Laravel Version</p>
                                    <p class="text-sm fw-bold">{{ laravelVersion }}</p>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-sm mb-0 text-secondary">PHP Version</p>
                                    <p class="text-sm fw-bold">{{ phpVersion }}</p>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-sm mb-0 text-secondary">Environment</p>
                                    <p class="text-sm fw-bold"><span class="badge bg-gradient-success">Production</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">link</i>
                                Quick Links
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="/profile" class="btn btn-outline-primary btn-sm">
                                    <i class="material-symbols-rounded text-sm me-1">person</i>
                                    Edit Profile
                                </a>
                                <a href="/logs" class="btn btn-outline-info btn-sm">
                                    <i class="material-symbols-rounded text-sm me-1">description</i>
                                    View Logs
                                </a>
                                <a href="/resources" class="btn btn-outline-success btn-sm">
                                    <i class="material-symbols-rounded text-sm me-1">monitoring</i>
                                    Server Resources
                                </a>
                                <a href="https://github.com/sudhirrajai/Nimbus" target="_blank"
                                    class="btn btn-outline-dark btn-sm">
                                    <i class="material-symbols-rounded text-sm me-1">code</i>
                                    Documentation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                <div class="toast align-items-center border-0"
                    :class="toastType === 'success' ? 'bg-success' : 'bg-danger'"
                    :style="showToast ? 'display: block;' : 'display: none;'" role="alert">
                    <div class="d-flex">
                        <div class="toast-body text-white">{{ toastMessage }}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            @click="showToast = false"></button>
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

const props = defineProps({
    panelVersion: { type: String, default: '1.0.0' },
    laravelVersion: { type: String, default: '12.x' },
    phpVersion: { type: String, default: '8.2' }
})

const settings = ref({
    panel_name: 'Nimbus',
    timezone: 'UTC',
    auto_refresh: true,
    session_lifetime: 120
})

const securityRules = ref([])
const securityMode = ref('off')
const currentIp = ref('')
const showAddRule = ref(false)
const savingRule = ref(false)
const newRule = ref({
    ip_address: '',
    type: 'allow',
    description: ''
})

const saving = ref(false)

// Toast
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

onMounted(async () => {
    await loadSettings()
    await loadSecurityData()
})

const loadSettings = async () => {
    try {
        const response = await axios.get('/settings/data')
        if (response.data.success) {
            settings.value = { ...settings.value, ...response.data.settings }
        }
    } catch (error) {
        console.error('Failed to load settings:', error)
    }
}

const loadSecurityData = async () => {
    try {
        const response = await axios.get('/settings/security')
        if (response.data.success) {
            securityRules.value = response.data.rules
            securityMode.value = response.data.mode
            currentIp.value = response.data.current_ip
        }
    } catch (error) {
        console.error('Failed to load security data:', error)
    }
}

const saveSettings = async () => {
    saving.value = true
    try {
        await axios.post('/settings/update', settings.value)
        notify('Settings saved successfully', 'success')
    } catch (error) {
        notify('Failed to save settings', 'error')
    } finally {
        saving.value = false
    }
}

const updateSecurityMode = async () => {
    try {
        await axios.post('/settings/security/mode', { mode: securityMode.value })
        notify('Security mode updated', 'success')
    } catch (error) {
        notify('Failed to update security mode', 'error')
    }
}

const saveRule = async () => {
    if (!newRule.value.ip_address) {
        notify('IP address is required', 'error')
        return
    }
    savingRule.value = true
    try {
        await axios.post('/settings/security/rules', newRule.value)
        notify('Security rule added', 'success')
        showAddRule.value = false
        newRule.value = { ip_address: '', type: 'allow', description: '' }
        await loadSecurityData()
    } catch (error) {
        notify(error.response?.data?.message || 'Failed to add rule', 'error')
    } finally {
        savingRule.value = false
    }
}

const toggleRule = async (rule) => {
    try {
        await axios.post(`/settings/security/rules/${rule.id}/toggle`)
        notify('Rule status updated', 'success')
        await loadSecurityData()
    } catch (error) {
        notify('Failed to update rule', 'error')
    }
}

const deleteRule = async (rule) => {
    if (!confirm('Are you sure you want to delete this security rule?')) return
    try {
        await axios.delete(`/settings/security/rules/${rule.id}`)
        notify('Rule deleted', 'success')
        await loadSecurityData()
    } catch (error) {
        notify('Failed to delete rule', 'error')
    }
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    const date = new Date(dateString)
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const notify = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 4000)
}
</script>
