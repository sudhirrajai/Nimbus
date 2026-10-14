<template>
    <MainLayout>
        <Head title="Updates" />
        <div class="container-fluid py-3 py-md-4 px-2 px-sm-3">
            <!-- Header Hero Card -->
            <div class="row mb-3 mb-md-4">
                <div class="col-12">
                    <div class="card header-hero-card border-0 overflow-hidden" :class="hasUpdate ? 'hero-update' : 'hero-normal'">
                        <div class="card-body p-3 p-sm-4 position-relative">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="hero-icon-box shadow-sm">
                                            <i class="material-symbols-rounded">system_update</i>
                                        </div>
                                        <div>
                                            <h4 class="text-white mb-0 font-weight-bolder tracking-tight">Nimbus OTA Updates</h4>
                                            <span class="text-white-50 text-xs">Automated & Safe Panel Releases</span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                        <span class="badge bg-white text-dark font-monospace text-xs px-2.5 py-1.5 shadow-sm">
                                            Current: v{{ currentVersion }}
                                        </span>
                                        <span v-if="hasUpdate" class="badge bg-success text-white text-xs px-2.5 py-1.5 shadow-sm d-inline-flex align-items-center">
                                            <i class="material-symbols-rounded text-xs me-1 animate-spin-slow">bolt</i> v{{ latestVersion }} Available!
                                        </span>
                                        <span v-else class="badge bg-dark-subtle text-white-50 text-xs px-2.5 py-1.5">
                                            <i class="material-symbols-rounded text-xs me-1 text-success">check</i> Up to date
                                        </span>
                                        <span v-if="updateSource" class="badge bg-black-subtle text-white-50 text-xxs px-2 py-1">
                                            Source: {{ updateSource }}
                                        </span>
                                    </div>
                                </div>

                                <div class="w-100 w-md-auto d-flex flex-column flex-sm-row gap-2">
                                    <button class="btn btn-outline-white btn-sm mb-0 px-3 py-2 w-100 w-sm-auto d-flex align-items-center justify-content-center gap-1.5 shadow-sm"
                                        @click="checkUpdates"
                                        :disabled="checking">
                                        <span v-if="checking" class="spinner-border spinner-border-sm"></span>
                                        <i v-else class="material-symbols-rounded text-sm">sync</i>
                                        <span>{{ checking ? 'Checking...' : 'Check for Updates' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Safe Update Protection Banner -->
            <div class="row mb-3 mb-md-4">
                <div class="col-12">
                    <div class="card safe-banner-card border-0 shadow-sm">
                        <div class="card-body p-3 p-sm-3 d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
                            <div class="shield-badge text-success shadow-xs">
                                <i class="material-symbols-rounded">verified_user</i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <h6 class="mb-0 text-dark font-weight-bold text-sm text-sm-base">Safe Update & Zero-Downtime Rollback Guarantee</h6>
                                    <span class="badge badge-xs bg-success-subtle text-success">Protected</span>
                                </div>
                                <p class="text-xs text-muted mb-0 line-height-relaxed">
                                    Every update takes a pre-update snapshot of your installation and backs up your SQLite database. If any command or post-update health check encounters an error, an automatic rollback immediately restores the server without disrupting running services.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 g-md-4">
                <!-- Update Status & Metrics Card -->
                <div class="col-12 col-lg-6 mb-2 mb-lg-0">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header pb-2 border-bottom bg-transparent d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-dark font-weight-bold d-flex align-items-center">
                                <i class="material-symbols-rounded text-primary me-2 text-base">info</i>
                                Version Information
                            </h6>
                            <span v-if="hasUpdate" class="badge bg-success-subtle text-success text-xxs font-weight-bold">
                                Update Ready
                            </span>
                        </div>
                        <div class="card-body p-3 p-sm-4 d-flex flex-column">
                            <!-- Version Metrics Grid -->
                            <div class="row g-2 g-sm-3 mb-3">
                                <div class="col-6">
                                    <div class="p-3 rounded-3 bg-gray-50 border h-100 d-flex flex-column justify-content-between">
                                        <span class="text-xxs text-uppercase text-secondary font-weight-bold">Current Version</span>
                                        <div class="mt-1">
                                            <h4 class="mb-0 font-weight-bolder text-dark">v{{ currentVersion }}</h4>
                                            <span class="text-xxs text-muted">Installed locally</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded-3 border h-100 d-flex flex-column justify-content-between"
                                         :class="hasUpdate ? 'bg-success-subtle border-success-subtle' : 'bg-gray-50'">
                                        <span class="text-xxs text-uppercase font-weight-bold"
                                              :class="hasUpdate ? 'text-success' : 'text-secondary'">Latest Available</span>
                                        <div class="mt-1">
                                            <h4 class="mb-0 font-weight-bolder" :class="hasUpdate ? 'text-success' : 'text-dark'">
                                                v{{ latestVersion }}
                                                <i v-if="hasUpdate" class="material-symbols-rounded text-sm align-middle">arrow_upward</i>
                                            </h4>
                                            <span class="text-xxs" :class="hasUpdate ? 'text-success font-weight-medium' : 'text-muted'">
                                                {{ hasUpdate ? 'New release ready' : 'Up to date' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="releaseDate" class="mb-3 p-2.5 rounded-3 bg-gray-50 border d-flex justify-content-between align-items-center">
                                <span class="text-xs text-secondary font-weight-medium">Release Date</span>
                                <span class="text-xs text-dark font-weight-bold">{{ formatDate(releaseDate) }}</span>
                            </div>

                            <!-- SHA-256 Checksum with Copy Button -->
                            <div v-if="sha256" class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-xxs text-uppercase text-secondary font-weight-bold">SHA-256 Checksum</span>
                                    <button class="btn btn-link btn-xs text-secondary p-0 mb-0 d-flex align-items-center gap-1 shadow-none"
                                            @click="copySha256" :title="copiedSha ? 'Copied!' : 'Copy checksum'">
                                        <i class="material-symbols-rounded text-xs">{{ copiedSha ? 'done' : 'content_copy' }}</i>
                                        <span class="text-xxs">{{ copiedSha ? 'Copied' : 'Copy' }}</span>
                                    </button>
                                </div>
                                <div class="p-2.5 bg-dark rounded-3 text-white font-monospace text-xxs text-break user-select-all shadow-inner">
                                    {{ sha256 }}
                                </div>
                            </div>

                            <!-- Status Alert -->
                            <div class="mt-auto">
                                <div v-if="hasUpdate" class="alert alert-success py-2.5 px-3 text-white rounded-3 mb-3 d-flex align-items-center gap-2">
                                    <i class="material-symbols-rounded text-base">celebration</i>
                                    <span class="text-xs">A new release is ready on VMCore Central! Review changes and click Update Now.</span>
                                </div>
                                <div v-else class="alert alert-light border py-2.5 px-3 text-secondary rounded-3 mb-3 d-flex align-items-center gap-2">
                                    <i class="material-symbols-rounded text-success text-base">check_circle</i>
                                    <span class="text-xs">You are currently running the latest installed version.</span>
                                </div>

                                <!-- Action Buttons -->
                                <div v-if="hasUpdate" class="d-grid gap-2">
                                    <button class="btn bg-gradient-success btn-lg mb-0 d-flex align-items-center justify-content-center gap-2 shadow-success"
                                        @click="startUpdate" :disabled="updating">
                                        <span v-if="updating" class="spinner-border spinner-border-sm"></span>
                                        <i v-else class="material-symbols-rounded">download</i>
                                        <span class="font-weight-bold">{{ updating ? 'Applying Safe Update Pipeline...' : 'Update Now (Safe Mode)' }}</span>
                                    </button>
                                    <a v-if="releaseUrl" :href="releaseUrl" target="_blank"
                                        class="btn btn-outline-secondary btn-sm mb-0 d-flex align-items-center justify-content-center gap-1">
                                        <i class="material-symbols-rounded text-sm">archive</i>
                                        <span>Download Release Archive</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Changelog Card -->
                <div class="col-12 col-lg-6 mb-2 mb-lg-0">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header pb-2 border-bottom bg-transparent d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-dark font-weight-bold d-flex align-items-center">
                                <i class="material-symbols-rounded text-info me-2 text-base">format_list_bulleted</i>
                                Release Notes & Changelog
                            </h6>
                            <span v-if="changelog.length > 0" class="badge bg-gray-200 text-dark text-xxs font-monospace">
                                {{ changelog.length }} changes
                            </span>
                        </div>
                        <div class="card-body p-3 p-sm-4">
                            <div v-if="changelog.length > 0" class="changelog-list d-flex flex-column gap-2">
                                <div v-for="(item, index) in changelog" :key="index"
                                     class="p-2.5 rounded-3 border bg-gray-50 d-flex align-items-start gap-2.5 changelog-item">
                                    <span class="changelog-bullet text-success mt-0.5">
                                        <i class="material-symbols-rounded text-sm">check_circle</i>
                                    </span>
                                    <span class="text-xs text-dark font-weight-medium line-height-relaxed flex-grow-1">
                                        {{ item }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-center text-muted py-5 d-flex flex-column align-items-center justify-content-center">
                                <div class="p-3 rounded-circle bg-gray-100 mb-2">
                                    <i class="material-symbols-rounded text-secondary" style="font-size: 32px;">description</i>
                                </div>
                                <h6 class="text-sm text-secondary font-weight-medium mb-1">No Changelog Notes</h6>
                                <p class="text-xs text-muted mb-0">Click "Check for Updates" above to fetch release notes.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terminal / Execution Log -->
            <div v-if="updating || updateLog" class="row mt-3 mt-md-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm overflow-hidden terminal-card">
                        <div class="card-header bg-dark text-white py-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="terminal-dots d-flex gap-1.5">
                                    <span class="dot dot-red"></span>
                                    <span class="dot dot-yellow"></span>
                                    <span class="dot dot-green"></span>
                                </div>
                                <span class="font-monospace text-xs text-white-50 ms-2 d-none d-sm-inline">safe-update-pipeline.sh</span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <span v-if="updating" class="badge bg-warning text-dark text-xxs d-inline-flex align-items-center">
                                    <span class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span>
                                    In Progress
                                </span>
                                <span v-else-if="updateFailed" class="badge bg-danger text-xxs d-inline-flex align-items-center">
                                    <i class="material-symbols-rounded text-xxs me-1">warning</i> Rolled Back
                                </span>
                                <span v-else-if="updateComplete" class="badge bg-success text-xxs d-inline-flex align-items-center">
                                    <i class="material-symbols-rounded text-xxs me-1">check</i> Complete
                                </span>
                                <button class="btn btn-link btn-xs text-white-50 p-0 mb-0 shadow-none" @click="copyLog" title="Copy Log">
                                    <i class="material-symbols-rounded text-xs">content_copy</i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0 bg-darker">
                            <pre class="update-log m-0 p-3 text-light font-monospace text-xxs text-sm-xs"
                                 style="max-height: 380px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{ updateLog || 'Waiting for update pipeline to start...' }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast -->
            <div class="position-fixed bottom-0 end-0 p-3 toast-container" style="z-index: 1080">
                <div class="toast align-items-center border-0 shadow-lg"
                    :class="toastType === 'success' ? 'bg-success text-white' : 'bg-danger text-white'"
                    :style="showToast ? 'display: block;' : 'display: none;'" role="alert">
                    <div class="d-flex">
                        <div class="toast-body text-xs font-weight-medium">{{ toastMessage }}</div>
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

const copiedSha = ref(false)
const copySha256 = () => {
    if (!sha256.value) return
    navigator.clipboard.writeText(sha256.value)
    copiedSha.value = true
    notify('SHA-256 Checksum copied to clipboard', 'success')
    setTimeout(() => { copiedSha.value = false }, 2500)
}

const copyLog = () => {
    if (!updateLog.value) return
    navigator.clipboard.writeText(updateLog.value)
    notify('Execution log copied to clipboard', 'success')
}
</script>

<style scoped>
.header-hero-card {
    border-radius: 1.25rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    transition: all 0.3s ease;
}

.hero-normal {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
}

.hero-update {
    background: linear-gradient(135deg, #065f46 0%, #047857 50%, #0f172a 100%);
}

.hero-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
}

.hero-icon-box i {
    font-size: 24px;
}

.safe-banner-card {
    border-radius: 1rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0 !important;
}

.shield-badge {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #dcfce7;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.shield-badge i {
    font-size: 26px;
}

.bg-gray-50 {
    background-color: #f8fafc !important;
}

.bg-success-subtle {
    background-color: #dcfce7 !important;
}

.border-success-subtle {
    border-color: #86efac !important;
}

.bg-dark-subtle {
    background-color: rgba(255, 255, 255, 0.15) !important;
}

.bg-black-subtle {
    background-color: rgba(0, 0, 0, 0.25) !important;
}

.badge-xs {
    font-size: 0.65rem !important;
    padding: 3px 6px;
    font-weight: 600;
}

.shadow-xs {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.shadow-inner {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
}

.btn-xs {
    font-size: 0.72rem;
    padding: 2px 6px;
}

.terminal-card {
    border-radius: 0.85rem;
}

.bg-darker {
    background-color: #0b0f19;
}

.terminal-dots .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.dot-red { background-color: #ef4444; }
.dot-yellow { background-color: #f59e0b; }
.dot-green { background-color: #10b981; }

.changelog-item {
    transition: transform 0.15s ease, background-color 0.15s ease;
}

.changelog-item:hover {
    background-color: #f1f5f9 !important;
    transform: translateX(2px);
}

.changelog-bullet {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.update-log::-webkit-scrollbar {
    width: 6px;
}

.update-log::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.65; }
}

/* Mobile Responsiveness Improvements */
@media (max-width: 575.98px) {
    .header-hero-card .card-body {
        padding: 1.1rem 0.9rem !important;
    }

    .hero-icon-box {
        width: 38px;
        height: 38px;
    }

    .hero-icon-box i {
        font-size: 20px;
    }

    .toast-container {
        left: 0.75rem;
        right: 0.75rem;
        bottom: 1rem;
        padding: 0 !important;
    }

    .toast {
        width: 100% !important;
        max-width: 100% !important;
    }

    .safe-banner-card .shield-badge {
        width: 36px;
        height: 36px;
    }

    .safe-banner-card .shield-badge i {
        font-size: 22px;
    }
}
</style>


