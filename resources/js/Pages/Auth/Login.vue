<template>
  <div class="login-page">
    <div class="login-container">
      <!-- Left side - Branding -->
      <div class="branding-section">
        <div class="brand-content">
          <div class="logo-wrapper">
            <i class="material-symbols-rounded logo-icon">cloud_circle</i>
          </div>
          <h1 class="brand-title">Nimbus</h1>
          <p class="brand-subtitle">Server Control Panel</p>
          <div class="features-list">
            <div class="feature-item">
              <i class="material-symbols-rounded">dns</i>
              <span>Domain Management</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">database</i>
              <span>Database Control</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">security</i>
              <span>SSL Certificates</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">terminal</i>
              <span>PHP Configuration</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side - Login Form -->
      <div class="form-section">
        <div class="form-card">
          <div class="form-header">
            <h2>Welcome Back</h2>
            <p>Sign in to your control panel</p>
          </div>

          <form @submit.prevent="submit" class="login-form">
            <div v-if="errors.email" class="alert alert-danger">
              <i class="material-symbols-rounded">error</i>
              {{ errors.email }}
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <div class="input-field">
                <i class="material-symbols-rounded">mail</i>
                <input 
                  type="email" 
                  id="email" 
                  v-model="form.email" 
                  placeholder="you@example.com"
                  required
                  autofocus
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
                  placeholder="Enter your password"
                  required
                >
                <button type="button" class="toggle-password" @click="showPassword = !showPassword">
                  <i class="material-symbols-rounded">{{ showPassword ? 'visibility_off' : 'visibility' }}</i>
                </button>
              </div>
            </div>

            <div class="form-options">
              <label class="checkbox-wrapper">
                <input type="checkbox" v-model="form.remember">
                <span class="checkmark"></span>
                <span class="label-text">Remember me</span>
              </label>
            </div>

            <button type="submit" class="btn-submit" :disabled="loading">
              <span v-if="loading" class="loading-spinner"></span>
              <span v-else>
                Sign In
                <i class="material-symbols-rounded">arrow_forward</i>
              </span>
            </button>
          </form>

          <div class="form-footer">
            <p>&copy; {{ new Date().getFullYear() }} Nimbus Control Panel</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const errors = page.props.errors || {}
const loading = ref(false)
const showPassword = ref(false)

const form = useForm({
  email: '',
  password: '',
  remember: false
})

const submit = () => {
  loading.value = true
  form.post('/login', {
    onFinish: () => {
      loading.value = false
    }
  })
}
</script>


