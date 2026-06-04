<template>
    <MainLayout>
        <Head title="License Settings" />
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-dark">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-8">
                                    <h4 class="text-white mb-0">
                                        <i class="material-symbols-rounded me-2">vpn_key</i>
                                        License Management
                                    </h4>
                                    <p class="text-white text-sm mb-0 opacity-8">Verify and manage your Nimbus panel license subscription details</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages/Alerts -->
            <div v-if="$page.props.flash?.success || $page.props.flash?.error || errorMsg" class="row mb-4">
                <div class="col-12">
                    <div v-if="$page.props.flash?.success" class="alert alert-success alert-dismissible text-white text-sm border-0 fade show" role="alert">
                        <span class="alert-icon align-middle me-2">
                            <i class="material-symbols-rounded text-md">check_circle</i>
                        </span>
                        <span class="alert-text">{{ $page.props.flash.success }}</span>
                        <button type="button" class="btn-close text-lg py-3 opacity-10" data-bs-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div v-if="$page.props.flash?.error || errorMsg" class="alert alert-danger alert-dismissible text-white text-sm border-0 fade show" role="alert">
                        <span class="alert-icon align-middle me-2">
                            <i class="material-symbols-rounded text-md">warning</i>
                        </span>
                        <span class="alert-text">{{ $page.props.flash?.error || errorMsg }}</span>
                        <button type="button" class="btn-close text-lg py-3 opacity-10" data-bs-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- License Info Details -->
                <div class="col-lg-7 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">info</i>
                                License Status
                            </h6>
                            <span :class="license?.status ? 'badge bg-gradient-success' : 'badge bg-gradient-danger'" class="text-xxs uppercase font-weight-bold">
                                {{ license?.status ? 'Active' : 'Invalid / Inactive' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                    <div class="text-secondary text-sm">License Key</div>
                                    <div class="d-flex align-items-center">
                                        <code class="text-sm font-weight-bold text-dark font-mono me-2">{{ maskedKey }}</code>
                                        <button class="btn btn-link btn-xs p-0 text-primary mb-0" @click="showFullKey = !showFullKey">
                                            <i class="material-symbols-rounded text-base">{{ showFullKey ? 'visibility_off' : 'visibility' }}</i>
                                        </button>
                                    </div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="text-secondary text-sm">Active Plan</div>
                                    <div class="text-sm font-weight-bold text-dark uppercase">{{ license?.plan || 'Free' }}</div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="text-secondary text-sm">Renewal / Expiration</div>
                                    <div class="text-sm font-weight-bold text-dark">{{ license?.expires_at || 'Never (Lifetime)' }}</div>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="text-secondary text-sm">Licensing Server Status</div>
                                    <div class="text-xs text-muted d-flex align-items-center">
                                        <span class="badge badge-dot me-2 border-0 bg-success p-1"></span>
                                        {{ license?.message || 'Verification complete.' }}
                                    </div>
                                </li>
                            </ul>

                            <div class="d-flex gap-2 mt-4 pt-2">
                                <button class="btn bg-gradient-primary mb-0 d-flex align-items-center gap-1" @click="syncLicense" :disabled="syncing">
                                    <span v-if="syncing" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="material-symbols-rounded text-sm">sync</i>
                                    Sync Status
                                </button>
                                <button class="btn btn-outline-danger mb-0 d-flex align-items-center gap-1" @click="deactivateLicense">
                                    <i class="material-symbols-rounded text-sm">no_encryption</i>
                                    Deactivate Key
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Server Info Details -->
                <div class="col-lg-5 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">computer</i>
                                Server Hardware ID
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="text-xs text-secondary mb-1">MACHINE ID (UUID)</label>
                                <div class="bg-light p-3 rounded text-xs font-mono text-dark break-all position-relative select-all">
                                    {{ machineId }}
                                </div>
                                <small class="text-muted text-xxs">This hardware hash binds the license key to this specific EC2 host.</small>
                            </div>
                            <div class="mb-3">
                                <label class="text-xs text-secondary mb-1">SERVER PUBLIC IP</label>
                                <div class="bg-light p-3 rounded text-xs font-mono text-dark select-all">
                                    {{ serverIp }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    license: Object,
    licenseKey: String,
    machineId: String,
    serverIp: String,
    errors: Object
})

const syncing = ref(false)
const showFullKey = ref(false)

const errorMsg = computed(() => props.errors?.error || '')

const maskedKey = computed(() => {
    if (!props.licenseKey) return 'No Key Registered'
    if (showFullKey.value) return props.licenseKey

    const parts = props.licenseKey.split('-')
    if (parts.length >= 2) {
        return parts.slice(0, 2).join('-') + '-••••-••••'
    }
    return props.licenseKey.substring(0, 7) + '-••••-••••'
})

const syncLicense = () => {
    syncing.value = true
    router.post(route('settings.license.sync'), {}, {
        onFinish: () => {
            syncing.value = false
        }
    })
}

const deactivateLicense = () => {
    if (confirm('Are you sure you want to deactivate and remove this license key? Your panel will be locked until you enter a new key.')) {
        router.post(route('settings.license.deactivate'))
    }
}
</script>

<style scoped>
.select-all {
    user-select: all;
}
.break-all {
    word-break: break-all;
}
</style>
