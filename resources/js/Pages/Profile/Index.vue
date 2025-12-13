<template>
    <MainLayout>
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
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed } from 'vue'
import axios from 'axios'

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
.avatar {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-xl {
    width: 80px;
    height: 80px;
}
</style>
