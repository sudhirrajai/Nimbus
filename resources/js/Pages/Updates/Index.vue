<template>
    <MainLayout>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card" :class="hasUpdate ? 'bg-gradient-success' : 'bg-gradient-dark'">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h4 class="text-white mb-0">
                                        <i class="material-symbols-rounded me-2">system_update</i>
                                        Panel Updates
                                    </h4>
                                    <p class="text-white text-sm mb-0 opacity-8">
                                        Current Version: v{{ currentVersion }}
                                        <span v-if="hasUpdate" class="ms-2 badge bg-white text-success">
                                            New version available!
                                        </span>
                                    </p>
                                </div>
                                <div class="col-4 text-end">
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

                            <div v-if="hasUpdate" class="alert alert-success py-2">
                                <i class="material-symbols-rounded me-1">celebration</i>
                                A new version is available! Review the changelog and click Update to upgrade.
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
                                    {{ updating ? 'Updating...' : 'Update Now' }}
                                </button>
                                <a v-if="releaseUrl" :href="releaseUrl" target="_blank"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="material-symbols-rounded text-sm me-1">open_in_new</i>
                                    View on GitHub
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
                                Changelog
                            </h6>
                        </div>
                        <div class="card-body">
                            <div v-if="changelog.length > 0">
                                <ul class="list-unstyled">
                                    <li v-for="(item, index) in changelog" :key="index" class="mb-2">
                                        <i class="material-symbols-rounded text-success text-sm me-2">check_circle</i>
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                            <div v-else class="text-center text-muted py-4">
                                <i class="material-symbols-rounded text-4xl">description</i>
                                <p class="mt-2">No changelog available</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Update Log -->
            <div v-if="updating || updateLog" class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 d-flex justify-content-between">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">terminal</i>
                                Update Progress
                            </h6>
                            <span v-if="updating" class="badge bg-warning">
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Updating...
                            </span>
                            <span v-else-if="updateComplete" class="badge bg-success">Complete</span>
                        </div>
                        <div class="card-body">
                            <pre class="update-log">{{ updateLog || 'Waiting for update to start...' }}</pre>
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    currentVersion: String
})

const checking = ref(false)
const updating = ref(false)
const latestVersion = ref(props.currentVersion)
const changelog = ref([])
const releaseDate = ref(null)
const releaseUrl = ref(null)
const updateLog = ref('')
const updateComplete = ref(false)

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
            releaseDate.value = response.data.releaseDate
            releaseUrl.value = response.data.releaseUrl
        }
    } catch (error) {
        notify('Failed to check for updates', 'error')
    } finally {
        checking.value = false
    }
}

const startUpdate = async () => {
    if (!confirm('This will update your panel. A backup will be created. Continue?')) return

    updating.value = true
    updateLog.value = ''
    updateComplete.value = false

    try {
        await axios.post('/updates/perform')
        // Start polling for status
        pollInterval = setInterval(pollUpdateStatus, 2000)
    } catch (error) {
        notify('Failed to start update', 'error')
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
            clearInterval(pollInterval)
            notify('Update completed! Please refresh the page.', 'success')
        }
    } catch (error) {
        // Error might mean server restarted, which is expected
    }
}

const compareVersions = (v1, v2) => {
    const parts1 = v1.split('.').map(Number)
    const parts2 = v2.split('.').map(Number)

    for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
        const p1 = parts1[i] || 0
        const p2 = parts2[i] || 0
        if (p1 > p2) return 1
        if (p1 < p2) return -1
    }
    return 0
}

const formatDate = (date) => {
    if (!date) return 'N/A'
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    })
}

const notify = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 4000)
}
</script>

<style scoped>
.update-log {
    background-color: #1e1e2e;
    color: #a6e3a1;
    font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
    font-size: 12px;
    line-height: 1.5;
    padding: 16px;
    border-radius: 8px;
    height: 300px;
    overflow: auto;
    white-space: pre-wrap;
    margin: 0;
}
</style>
