<template>
  <Head title="Login" />
  <div class="login-page">
    <div class="login-container">
      <!-- Left side - Branding -->
      <div class="branding-section">
        <div class="brand-content">
          <div class="logo-wrapper" style="overflow: hidden; background: rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 12px; backdrop-filter: blur(10px);">
            <img :src="'/assets/img/nimbus_logo.png?v=2'" style="width: 100%; height: 100%; object-fit: contain; border-radius: 12px;">
          </div>
          <h1 class="brand-title">nimbus</h1>
          <p class="brand-subtitle">by <a href="https://vmcore.in" target="_blank" style="color: #ffffff; text-decoration: none; font-weight: 700;">VMCore</a></p>
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
            <div v-if="form.errors.email" class="alert alert-danger">
              <i class="material-symbols-rounded">error</i>
              {{ form.errors.email }}
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

            <!-- Adaptive Captcha (shown on 2+ failed attempts) -->
            <div v-if="captcha && captcha.required" class="form-group">
              <label for="captcha">Security Verification</label>
              <div class="d-flex align-items-center gap-2 mb-2">
                <img :src="captcha.image" alt="Security Puzzle" style="border-radius: 8px; border: 1px solid rgba(255,255,255,0.15);">
                <span class="text-xs text-muted">Solve the puzzle to verify you are human</span>
              </div>
              <div class="input-field">
                <i class="material-symbols-rounded">calculate</i>
                <input 
                  type="number" 
                  id="captcha" 
                  v-model="form.captcha_answer" 
                  placeholder="Enter the result"
                  required
                  autocomplete="off"
                >
              </div>
              <div v-if="form.errors.captcha_answer" class="text-danger text-xs mt-1">
                {{ form.errors.captcha_answer }}
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
            <p>&copy; {{ new Date().getFullYear() }} <a href="https://vmcore.in" target="_blank" style="color: inherit; text-decoration: none;">nimbus by VMCore</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  captcha: Object
})

const page = usePage()
const loading = ref(false)
const showPassword = ref(false)

const form = useForm({
  email: '',
  password: '',
  captcha_answer: '',
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


