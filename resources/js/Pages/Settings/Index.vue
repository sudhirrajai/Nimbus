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
                                    <p class="text-sm fw-bold">v1.0.0</p>
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

const settings = ref({
    panel_name: 'Nimbus',
    timezone: 'UTC',
    auto_refresh: true,
    session_lifetime: 120
})

const saving = ref(false)
const laravelVersion = ref('11.x')
const phpVersion = ref('8.1')

// Toast
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

onMounted(async () => {
    await loadSettings()
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

const notify = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 4000)
}
</script>
