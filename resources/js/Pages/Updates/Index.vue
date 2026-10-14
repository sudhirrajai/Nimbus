<template>
    <MainLayout>
    <Head title="Updates" />
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card" :class="hasUpdate ? 'bg-gradient-success' : 'bg-gradient-dark'">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4 class="text-white mb-0">
                                        <i class="material-symbols-rounded me-2">system_update</i>
                                        Nimbus OTA Updates
                                    </h4>
                                    <p class="text-white text-sm mb-0 opacity-8">
                                        Current Version: v{{ currentVersion }}
                                        <span v-if="hasUpdate" class="ms-2 badge bg-white text-success">
                                            New version available!
                                        </span>
                                        <span v-if="updateSource" class="ms-2 badge bg-dark text-white opacity-9">
                                            Source: {{ updateSource }}
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                    <button class="btn btn-outline-white btn-sm" @click="checkUpdates"
                                        :disabled="checking">
                                        <span v-if="checking" class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="material-symbols-rounded text-sm me-1">refresh</i>
                                        Check for Updates
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Safe Update Protection Banner -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border border-success bg-light shadow-sm">
                        <div class="card-body py-3 d-flex align-items-center gap-3">
                            <div class="text-success fs-3">
                                <i class="material-symbols-rounded">verified_user</i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 text-dark font-weight-bold">Safe Update & Zero-Downtime Rollback Guarantee</h6>
                                <p class="text-xs text-muted mb-0">
                                    Every update takes a pre-update snapshot of your installation and backs up your SQLite database. If any command or post-update health check encounters an error, an automatic rollback immediately restores the server without disrupting running services.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Update Status -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">info</i>
                                Version Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <p class="text-sm text-secondary mb-1">Current Version</p>
                                    <h5 class="mb-0">v{{ currentVersion }}</h5>
                                </div>
                                <div class="col-6">
                                    <p class="text-sm text-secondary mb-1">Latest Version</p>
                                    <h5 class="mb-0" :class="hasUpdate ? 'text-success' : ''">
                                        v{{ latestVersion }}
                                        <i v-if="hasUpdate"
                                            class="material-symbols-rounded text-success">arrow_upward</i>
                                    </h5>
                                </div>
                            </div>

                            <div v-if="releaseDate" class="mb-3">
                                <p class="text-sm text-secondary mb-1">Release Date</p>
                                <p class="mb-0">{{ formatDate(releaseDate) }}</p>
                            </div>

                            <div v-if="sha256" class="mb-3">
                                <p class="text-sm text-secondary mb-1">SHA-256 Checksum</p>
                                <div class="p-2 bg-dark rounded text-white font-monospace text-xs text-break">
                                    {{ sha256 }}
                                </div>
                            </div>

                            <div v-if="hasUpdate" class="alert alert-success py-2 text-white">
                                <i class="material-symbols-rounded me-1">celebration</i>
                                A new release is available on VMCore Central! Review the changelog and click Update Now.
                            </div>
                            <div v-else class="alert alert-secondary py-2">
                                <i class="material-symbols-rounded me-1">check_circle</i>
                                You're running the latest version.
                            </div>

                            <div v-if="hasUpdate" class="d-grid gap-2">
                                <button class="btn bg-gradient-success btn-lg" @click="startUpdate"
                                    :disabled="updating">
                                    <span v-if="updating" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="material-symbols-rounded me-1">download</i>
                                    {{ updating ? 'Updating & Verifying...' : 'Update Now (Safe Mode)' }}
                                </button>
                                <a v-if="releaseUrl" :href="releaseUrl" target="_blank"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="material-symbols-rounded text-sm me-1">download</i>
                                    Download Release Archive
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Changelog -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">list</i>
                                Release Notes & Changelog
                            </h6>
                        </div>
                        <div class="card-body">
                            <div v-if="changelog.length > 0">
                                <ul class="list-unstyled">
                                    <li v-for="(item, index) in changelog" :key="index" class="mb-2 text-sm">
                                        <i class="material-symbols-rounded text-success text-sm me-2">check_circle</i>
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                            <div v-else class="text-center text-muted py-4">
                                <i class="material-symbols-rounded text-3xl">description</i>
                                <p class="mt-2 text-sm">No changelog notes available</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Update Log -->
            <div v-if="updating || updateLog" class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">terminal</i>
                                Update Progress & Execution Log
                            </h6>
                            <span v-if="updating" class="badge bg-warning">
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Updating...
                            </span>
                            <span v-else-if="updateFailed" class="badge bg-danger">
                                <i class="material-symbols-rounded text-xs me-1">warning</i>
                                Rolled Back (Failed)
                            </span>
                            <span v-else-if="updateComplete" class="badge bg-success">
                                <i class="material-symbols-rounded text-xs me-1">check</i>
                                Complete
                            </span>
                        </div>
                        <div class="card-body">
                            <pre class="update-log bg-dark text-light p-3 rounded font-monospace text-xs" style="max-height: 400px; overflow-y: auto;">{{ updateLog || 'Waiting for update pipeline to start...' }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
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
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { formatDate } from '@/utils/date'

const props = defineProps({
    currentVersion: String
})

const checking = ref(false)
const updating = ref(false)
const latestVersion = ref(props.currentVersion)
const changelog = ref([])
const commands = ref([])
const sha256 = ref(null)
const updateSource = ref('')
const releaseDate = ref(null)
const releaseUrl = ref(null)
const updateLog = ref('')
const updateComplete = ref(false)
const updateFailed = ref(false)

let pollInterval = null

// Toast
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const hasUpdate = computed(() => {
    return latestVersion.value !== props.currentVersion &&
        compareVersions(latestVersion.value, props.currentVersion) > 0
})

onMounted(async () => {
    await checkUpdates()
})

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval)
})

const checkUpdates = async () => {
    checking.value = true
    try {
        const response = await axios.get('/updates/check')
        if (response.data.success) {
            latestVersion.value = response.data.latestVersion
            changelog.value = response.data.changelog || []
            commands.value = response.data.commands || []
            sha256.value = response.data.sha256 || null
            updateSource.value = response.data.source || 'VMCore Central'
            releaseDate.value = response.data.releaseDate
            releaseUrl.value = response.data.releaseUrl
        }
    } catch (error) {
        notify('Failed to check for updates from VMCore Central', 'error')
    } finally {
        checking.value = false
    }
}

const startUpdate = async () => {
    if (!confirm('This will update your panel using the safe update pipeline (with pre-update snapshot & automated rollback). Continue?')) return

    updating.value = true
    updateLog.value = ''
    updateComplete.value = false
    updateFailed.value = false

    try {
        await axios.post('/updates/perform')
        pollInterval = setInterval(pollUpdateStatus, 2000)
    } catch (error) {
        notify('Failed to start update pipeline', 'error')
        updating.value = false
    }
}

const pollUpdateStatus = async () => {
    try {
        const response = await axios.get('/updates/status')
        updateLog.value = response.data.log

        if (response.data.status === 'done') {
            updating.value = false
            updateComplete.value = true
            updateFailed.value = false
            clearInterval(pollInterval)
            notify('Update completed successfully! Please refresh the page.', 'success')
        } else if (response.data.status === 'failed') {
            updating.value = false
            updateComplete.value = false
            updateFailed.value = true
            clearInterval(pollInterval)
            notify('Update encountered an error and was safely rolled back. Your system remains online.', 'error')
        }
    } catch (error) {
        // Temporary network blip during service reload is expected
    }
}

const compareVersions = (v1, v2) => {
    const parts1 = (v1 || '').split('.').map(Number)
    const parts2 = (v2 || '').split('.').map(Number)

    for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
        const p1 = parts1[i] || 0
        const p2 = parts2[i] || 0
        if (p1 > p2) return 1
        if (p1 < p2) return -1
    }
    return 0
}

const notify = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 5000)
}
</script>


