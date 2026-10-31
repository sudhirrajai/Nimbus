<template>
  <Head title="Two-Factor Authentication" />
  <div class="login-page">
    <div class="login-container">
      <!-- Left side - Branding -->
      <div class="branding-section">
        <div class="brand-content">
          <div class="logo-wrapper" style="overflow: hidden; background: rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 12px; backdrop-filter: blur(10px);">
            <img :src="'/assets/img/nimbus_logo.png?v=2'" style="width: 100%; height: 100%; object-fit: contain; border-radius: 12px;" alt="Nimbus">
          </div>
          <h1 class="brand-title">nimbus</h1>
          <p class="brand-subtitle">by <a href="https://sudhirrajai.com/" target="_blank" style="color: #ffffff; text-decoration: none; font-weight: 700;">Sudhir</a></p>
          <div class="features-list">
            <div class="feature-item">
              <i class="material-symbols-rounded">security</i>
              <span>Two-Factor Protection</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">lock</i>
              <span>Hardware & App Authenticator</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">verified_user</i>
              <span>Encrypted Session Tokens</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side - Challenge Form -->
      <div class="form-section">
        <div class="form-card">
          <div class="form-header">
            <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="background: rgba(235, 20, 102, 0.1); color: #eb1466;">
              <i class="material-symbols-rounded" style="font-size: 32px;">phonelink_lock</i>
            </div>
            <h2>Two-Factor Challenge</h2>
            <p v-if="!useRecovery">
              Enter the 6-digit verification code from your authenticator application.
            </p>
            <p v-else>
              Enter one of your emergency recovery codes to access your account.
            </p>
          </div>

          <form @submit.prevent="submit" class="login-form">
            <div v-if="form.errors.code" class="alert alert-danger">
              <i class="material-symbols-rounded">error</i>
              {{ form.errors.code }}
            </div>

            <!-- TOTP 6-digit Code -->
            <div v-if="!useRecovery" class="form-group">
              <label for="code">Authentication Code</label>
              <div class="input-field">
                <i class="material-symbols-rounded">dialpad</i>
                <input 
                  type="text" 
                  id="code" 
                  v-model="form.code" 
                  placeholder="123456"
                  maxlength="6"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  required
                  autofocus
                  style="letter-spacing: 4px; font-weight: 700; font-size: 1.25rem;"
                >
              </div>
            </div>

            <!-- Emergency Recovery Code -->
            <div v-else class="form-group">
              <label for="recovery_code">Recovery Code</label>
              <div class="input-field">
                <i class="material-symbols-rounded">key</i>
                <input 
                  type="text" 
                  id="recovery_code" 
                  v-model="form.recovery_code" 
                  placeholder="XXXXX-XXXXX"
                  autocomplete="off"
                  required
                  autofocus
                  style="letter-spacing: 2px; font-weight: 600;"
                >
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
              <button 
                type="button" 
                class="btn btn-link p-0 text-sm text-decoration-none" 
                style="color: #eb1466; font-weight: 600;"
                @click="toggleRecovery"
              >
                {{ useRecovery ? '← Use authentication app code' : 'Use an emergency recovery code' }}
              </button>
            </div>

            <button type="submit" class="btn-submit" :disabled="form.processing">
              <span v-if="form.processing" class="loading-spinner"></span>
              <span v-else>
                Verify & Continue
                <i class="material-symbols-rounded">arrow_forward</i>
              </span>
            </button>
          </form>

          <div class="text-center mt-3">
            <a href="/login" class="text-muted text-sm text-decoration-none">
              ← Cancel and return to sign in
            </a>
          </div>

          <div class="form-footer">
            <p>&copy; {{ new Date().getFullYear() }} <a href="https://sudhirrajai.com/" target="_blank" style="color: inherit; text-decoration: none;">nimbus by Sudhir Rajai</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

const useRecovery = ref(false)

const form = useForm({
  code: '',
  recovery_code: ''
})

const toggleRecovery = () => {
  useRecovery.value = !useRecovery.value
  form.code = ''
  form.recovery_code = ''
  form.clearErrors()
}

const submit = () => {
  form.post('/two-factor-challenge', {
    preserveScroll: true,
  })
}
</script>
