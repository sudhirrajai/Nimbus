<template>
    <MainLayout>
    <Head title="Profile" />
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card bg-gradient-primary">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="avatar avatar-xl bg-white text-primary rounded-circle">
                                        <span class="text-lg fw-bold">{{ userInitials }}</span>
                                    </div>
                                </div>
                                <div class="col">
                                    <h4 class="text-white mb-0">{{ user.name }}</h4>
                                    <p class="text-white text-sm mb-0 opacity-8">{{ user.email }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">person</i>
                                Profile Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="updateProfile">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" v-model="profileForm.name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" v-model="profileForm.email" required>
                                </div>
                                <button type="submit" class="btn bg-gradient-primary" :disabled="savingProfile">
                                    <span v-if="savingProfile" class="spinner-border spinner-border-sm me-2"></span>
                                    {{ savingProfile ? 'Saving...' : 'Update Profile' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">lock</i>
                                Change Password
                            </h6>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="changePassword">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" v-model="passwordForm.current_password"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" v-model="passwordForm.password" required
                                        minlength="8">
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control"
                                        v-model="passwordForm.password_confirmation" required>
                                </div>
                                <button type="submit" class="btn bg-gradient-warning" :disabled="savingPassword">
                                    <span v-if="savingPassword" class="spinner-border spinner-border-sm me-2"></span>
                                    {{ savingPassword ? 'Changing...' : 'Change Password' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Factor Authentication (2FA) -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    <i class="material-symbols-rounded text-sm me-1 text-primary">security</i>
                                    Two-Factor Authentication (2FA)
                                </h6>
                                <p class="text-xs text-secondary mb-0">
                                    Add an extra layer of security using standard TOTP authenticator apps (Google Authenticator, Authy, 1Password).
                                </p>
                            </div>
                            <span class="badge" :class="hasTwoFactor ? 'bg-gradient-success' : 'bg-gradient-secondary'">
                                {{ hasTwoFactor ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <!-- When 2FA is NOT enabled and NOT setting up -->
                            <div v-if="!hasTwoFactor && !settingUp2fa">
                                <p class="text-sm text-secondary">
                                    When two-factor authentication is enabled, you will be prompted for a secure 6-digit one-time token from your authenticator application in addition to your password during sign-in.
                                </p>
                                <button type="button" class="btn bg-gradient-primary btn-sm mb-0" @click="start2faSetup" :disabled="loading2fa">
                                    <span v-if="loading2fa" class="spinner-border spinner-border-sm me-2"></span>
                                    <i class="material-symbols-rounded text-sm me-1">lock_open</i>
                                    Enable Two-Factor Authentication
                                </button>
                            </div>

                            <!-- Setup Flow (QR Code & Secret) -->
                            <div v-if="settingUp2fa" class="border rounded p-4 bg-gray-100">
                                <h6 class="text-sm font-weight-bold mb-3">
                                    <span class="badge bg-primary me-2">1</span> Scan this QR Code with your Authenticator App
                                </h6>
                                <div class="row align-items-center mb-4">
                                    <div class="col-auto">
                                        <div class="p-2 bg-white rounded shadow-sm d-inline-block" v-html="setupData.qr_svg" style="max-width: 200px; max-height: 200px;"></div>
                                    </div>
                                    <div class="col">
                                        <p class="text-xs text-secondary mb-1">Or manually enter this secret setup key:</p>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <code class="p-2 bg-white rounded border font-weight-bold text-dark text-xs" style="letter-spacing: 1px;">
                                                {{ setupData.secret }}
                                            </code>
                                            <button type="button" class="btn btn-outline-secondary btn-sm mb-0 py-1 px-2" @click="copyText(setupData.secret)">
                                                <i class="material-symbols-rounded text-xs">content_copy</i>
                                            </button>
                                        </div>
                                        <p class="text-xs text-muted mb-0">Compatible with Google Authenticator, Authy, Apple Passwords, Microsoft Authenticator, and 1Password.</p>
                                    </div>
                                </div>

                                <h6 class="text-sm font-weight-bold mb-2">
                                    <span class="badge bg-primary me-2">2</span> Enter the 6-Digit Verification Code
                                </h6>
                                <form @submit.prevent="confirm2fa" class="row g-2 align-items-center" style="max-width: 450px;">
                                    <div class="col">
                                        <input 
                                            type="text" 
                                            class="form-control form-control-sm text-center fw-bold" 
                                            v-model="confirmCode" 
                                            placeholder="123456" 
                                            maxlength="6"
                                            inputmode="numeric"
                                            required
                                            style="letter-spacing: 4px; font-size: 1.1rem;"
                                        >
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn bg-gradient-success btn-sm mb-0" :disabled="confirming2fa || confirmCode.length !== 6">
                                            <span v-if="confirming2fa" class="spinner-border spinner-border-sm me-1"></span>
                                            Verify & Activate
                                        </button>
                                    </div>
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-link text-secondary btn-sm mb-0" @click="cancel2faSetup">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- When 2FA is ENABLED -->
                            <div v-if="hasTwoFactor && !settingUp2fa">
                                <div class="alert alert-success text-white d-flex align-items-center mb-3 py-2 px-3">
                                    <i class="material-symbols-rounded me-2">verified_user</i>
                                    <span class="text-sm">Two-factor authentication is active and protecting your account.</span>
                                </div>

                                <!-- Display Recovery Codes if just generated or regenerated -->
                                <div v-if="recoveryCodes.length > 0" class="border border-warning rounded p-3 mb-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="text-sm text-warning font-weight-bold mb-0">
                                            <i class="material-symbols-rounded text-sm me-1">warning</i>
                                            Emergency Recovery Codes
                                        </h6>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-dark btn-sm py-1 px-2 mb-0" @click="copyRecoveryCodes">
                                                <i class="material-symbols-rounded text-xs me-1">content_copy</i> Copy All
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-sm py-1 px-2 mb-0" @click="downloadRecoveryCodes">
                                                <i class="material-symbols-rounded text-xs me-1">download</i> Download (.txt)
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-secondary mb-2">
                                        Store these recovery codes in a secure password manager. Each code can only be used once if you lose access to your authenticator app.
                                    </p>
                                    <div class="row g-2">
                                        <div v-for="(code, idx) in recoveryCodes" :key="idx" class="col-md-3 col-6">
                                            <code class="d-block p-2 bg-white text-dark rounded border text-center font-weight-bold text-xs" style="letter-spacing: 1px;">
                                                {{ code }}
                                            </code>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm mb-0" @click="promptRegenerateCodes" :disabled="regeneratingCodes">
                                        <span v-if="regeneratingCodes" class="spinner-border spinner-border-sm me-1"></span>
                                        <i class="material-symbols-rounded text-sm me-1">sync</i>
                                        Regenerate Recovery Codes
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm mb-0" @click="promptDisable2fa" :disabled="disabling2fa">
                                        <span v-if="disabling2fa" class="spinner-border spinner-border-sm me-1"></span>
                                        <i class="material-symbols-rounded text-sm me-1">lock_reset</i>
                                        Disable Two-Factor Authentication
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Prompt Modal for 2FA actions -->
            <div v-if="showPasswordModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.6);" role="dialog">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title font-weight-bold">
                                <i class="material-symbols-rounded text-sm me-1 text-danger">lock</i>
                                Confirm Password
                            </h6>
                            <button type="button" class="btn-close text-dark" @click="closePasswordModal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-sm text-secondary mb-3">
                                For your security, please confirm your account password before proceeding with this action.
                            </p>
                            <div class="mb-3">
                                <label class="form-label text-xs">Current Password</label>
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    v-model="modalPassword" 
                                    placeholder="Enter your current password"
                                    required 
                                    autofocus
                                    @keyup.enter="executePasswordAction"
                                >
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm mb-0" @click="closePasswordModal">Cancel</button>
                            <button type="button" class="btn bg-gradient-danger btn-sm mb-0" @click="executePasswordAction" :disabled="modalLoading || !modalPassword">
                                <span v-if="modalLoading" class="spinner-border spinner-border-sm me-1"></span>
                                Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Info -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h6 class="mb-0">
                                <i class="material-symbols-rounded text-sm me-1">info</i>
                                Account Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <p class="text-sm mb-0 text-secondary">Account Created</p>
                                    <p class="text-sm fw-bold">{{ formatDate(user.created_at) }}</p>
                                </div>
                                <div class="col-md-4">
                                    <p class="text-sm mb-0 text-secondary">Last Updated</p>
                                    <p class="text-sm fw-bold">{{ formatDate(user.updated_at) }}</p>
                                </div>
                                <div class="col-md-4">
                                    <p class="text-sm mb-0 text-secondary">Role</p>
                                    <p class="text-sm fw-bold"><span
                                            class="badge bg-gradient-primary">Administrator</span></p>
                                </div>
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
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed } from 'vue'
import axios from 'axios'
import { formatDate } from '@/utils/date'

const props = defineProps({
    user: Object
})

const profileForm = ref({
    name: props.user.name,
    email: props.user.email
})

const passwordForm = ref({
    current_password: '',
    password: '',
    password_confirmation: ''
})

const savingProfile = ref(false)
const savingPassword = ref(false)

// 2FA state
const hasTwoFactor = ref(!!props.user.has_2fa)
const settingUp2fa = ref(false)
const loading2fa = ref(false)
const confirming2fa = ref(false)
const disabling2fa = ref(false)
const regeneratingCodes = ref(false)
const setupData = ref({ secret: '', qr_svg: '' })
const confirmCode = ref('')
const recoveryCodes = ref([])

// Password Confirmation Modal
const showPasswordModal = ref(false)
const modalPassword = ref('')
const modalAction = ref('') // 'disable' or 'regenerate'
const modalLoading = ref(false)

// Toast
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const userInitials = computed(() => {
    return props.user.name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .substring(0, 2)
})

const updateProfile = async () => {
    savingProfile.value = true
    try {
        await axios.post('/profile/update', profileForm.value)
        notify('Profile updated successfully', 'success')
    } catch (error) {
        notify(error.response?.data?.message || 'Failed to update profile', 'error')
    } finally {
        savingProfile.value = false
    }
}

const changePassword = async () => {
    if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
        notify('Passwords do not match', 'error')
        return
    }

    savingPassword.value = true
    try {
        await axios.post('/profile/password', passwordForm.value)
        notify('Password changed successfully', 'success')
        passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    } catch (error) {
        notify(error.response?.data?.errors?.current_password?.[0] || 'Failed to change password', 'error')
    } finally {
        savingPassword.value = false
    }
}

const start2faSetup = async () => {
    loading2fa.value = true
    try {
        const res = await axios.post('/profile/2fa/setup')
        setupData.value = res.data
        settingUp2fa.value = true
        confirmCode.value = ''
    } catch (err) {
        notify(err.response?.data?.message || 'Failed to initialize 2FA setup', 'error')
    } finally {
        loading2fa.value = false
    }
}

const cancel2faSetup = () => {
    settingUp2fa.value = false
    setupData.value = { secret: '', qr_svg: '' }
    confirmCode.value = ''
}

const confirm2fa = async () => {
    confirming2fa.value = true
    try {
        const res = await axios.post('/profile/2fa/confirm', {
            secret: setupData.value.secret,
            code: confirmCode.value
        })
        hasTwoFactor.value = true
        settingUp2fa.value = false
        recoveryCodes.value = res.data.recovery_codes || []
        notify(res.data.message || 'Two-Factor Authentication enabled!', 'success')
    } catch (err) {
        notify(err.response?.data?.message || 'Invalid verification code. Please try again.', 'error')
    } finally {
        confirming2fa.value = false
    }
}

const promptDisable2fa = () => {
    modalAction.value = 'disable'
    modalPassword.value = ''
    showPasswordModal.value = true
}

const promptRegenerateCodes = () => {
    modalAction.value = 'regenerate'
    modalPassword.value = ''
    showPasswordModal.value = true
}

const closePasswordModal = () => {
    showPasswordModal.value = false
    modalPassword.value = ''
}

const executePasswordAction = async () => {
    if (!modalPassword.value) return
    modalLoading.value = true
    try {
        if (modalAction.value === 'disable') {
            await axios.post('/profile/2fa/disable', { password: modalPassword.value })
            hasTwoFactor.value = false
            recoveryCodes.value = []
            notify('Two-Factor Authentication disabled', 'success')
            closePasswordModal()
        } else if (modalAction.value === 'regenerate') {
            const res = await axios.post('/profile/2fa/recovery-codes', { password: modalPassword.value })
            recoveryCodes.value = res.data.recovery_codes || []
            notify('Recovery codes regenerated successfully', 'success')
            closePasswordModal()
        }
    } catch (err) {
        notify(err.response?.data?.message || 'Password incorrect', 'error')
    } finally {
        modalLoading.value = false
    }
}

const copyText = (text) => {
    navigator.clipboard.writeText(text)
    notify('Copied to clipboard!', 'success')
}

const copyRecoveryCodes = () => {
    const text = recoveryCodes.value.join('\n')
    navigator.clipboard.writeText(text)
    notify('All recovery codes copied to clipboard!', 'success')
}

const downloadRecoveryCodes = () => {
    const text = "NIMBUS 2FA EMERGENCY RECOVERY CODES\n" +
                 "Generated: " + new Date().toISOString() + "\n\n" +
                 recoveryCodes.value.join('\n') + "\n\n" +
                 "Keep these codes in a safe place. Each code can only be used once to regain account access."
    const blob = new Blob([text], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `nimbus-recovery-codes-${new Date().toISOString().slice(0, 10)}.txt`
    a.click()
    URL.revokeObjectURL(url)
}



const notify = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 4000)
}
</script>


