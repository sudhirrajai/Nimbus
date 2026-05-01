<template>
  <div class="setup-page">
    <div class="setup-container">
      <!-- Left side - Branding -->
      <div class="branding-section">
        <div class="brand-content">
          <div class="logo-wrapper">
            <i class="material-symbols-rounded logo-icon">rocket_launch</i>
          </div>
          <h1 class="brand-title">Welcome to Nimbus</h1>
          <p class="brand-subtitle">Let's set up your control panel</p>
          <div class="steps-list">
            <div class="step-item active">
              <div class="step-number">1</div>
              <span>Create Admin Account</span>
            </div>
            <div class="step-item">
              <div class="step-number">2</div>
              <span>Configure Server</span>
            </div>
            <div class="step-item">
              <div class="step-number">3</div>
              <span>Start Managing</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side - Setup Form -->
      <div class="form-section">
        <div class="form-card">
          <div class="form-header">
            <h2>Create Admin Account</h2>
            <p>Set up your administrator credentials</p>
          </div>

          <form @submit.prevent="submit" class="setup-form">
            <div v-if="Object.keys(errors).length > 0" class="alert alert-danger">
              <i class="material-symbols-rounded">error</i>
              <div>
                <span v-for="(error, key) in errors" :key="key">{{ error }}</span>
              </div>
            </div>

            <div class="form-group">
              <label for="name">Full Name</label>
              <div class="input-field">
                <i class="material-symbols-rounded">person</i>
                <input 
                  type="text" 
                  id="name" 
                  v-model="form.name" 
                  placeholder="John Doe"
                  required
                  autofocus
                >
              </div>
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <div class="input-field">
                <i class="material-symbols-rounded">mail</i>
                <input 
                  type="email" 
                  id="email" 
                  v-model="form.email" 
                  placeholder="admin@example.com"
                  required
                >
              </div>
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-field">
                <i class="material-symbols-rounded">lock</i>
                <input 
                  :type="showPassword ? 'text' : 'password'" 
                  id="password" 
                  v-model="form.password" 
                  placeholder="Minimum 8 characters"
                  required
                  minlength="8"
                >
                <button type="button" class="toggle-password" @click="showPassword = !showPassword">
                  <i class="material-symbols-rounded">{{ showPassword ? 'visibility_off' : 'visibility' }}</i>
                </button>
              </div>
            </div>

            <div class="form-group">
              <label for="password_confirmation">Confirm Password</label>
              <div class="input-field">
                <i class="material-symbols-rounded">lock</i>
                <input 
                  :type="showPassword ? 'text' : 'password'" 
                  id="password_confirmation" 
                  v-model="form.password_confirmation" 
                  placeholder="Repeat your password"
                  required
                >
              </div>
              <p v-if="form.password && form.password_confirmation && form.password !== form.password_confirmation" class="password-mismatch">
                <i class="material-symbols-rounded">warning</i>
                Passwords do not match
              </p>
            </div>

            <button type="submit" class="btn-submit" :disabled="loading || !isValid">
              <span v-if="loading" class="loading-spinner"></span>
              <span v-else>
                Create Account
                <i class="material-symbols-rounded">arrow_forward</i>
              </span>
            </button>
          </form>

          <div class="security-notice">
            <i class="material-symbols-rounded">verified_user</i>
            <span>Your password is encrypted with bcrypt</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const errors = page.props.errors || {}
const loading = ref(false)
const showPassword = ref(false)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const isValid = computed(() => {
  return form.name && 
         form.email && 
         form.password.length >= 8 && 
         form.password === form.password_confirmation
})

const submit = () => {
  loading.value = true
  form.post('/setup', {
    onFinish: () => {
      loading.value = false
    }
  })
}
</script>


