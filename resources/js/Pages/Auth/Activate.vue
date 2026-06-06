<template>
  <Head title="Activate Nimbus" />
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
              <i class="material-symbols-rounded">vpn_key</i>
              <span>Activate Panel License</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">verified_user</i>
              <span>Unlock Enterprise Features</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">dns</i>
              <span>Automatic Server Updates</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side - Activation Form -->
      <div class="form-section">
        <div class="form-card">
          <div class="form-header">
            <h2>Activate Nimbus</h2>
            <p>Enter your license key to unlock the enterprise panel</p>
          </div>

          <form @submit.prevent="submit" class="login-form">
            <div v-if="error" class="alert alert-danger mb-4 animate__animated animate__shakeX">
              <i class="material-symbols-rounded">warning</i>
              {{ error }}
            </div>

            <div class="form-group">
              <label for="license_key">License Key</label>
              <div class="input-field">
                <i class="material-symbols-rounded">key</i>
                <input 
                  type="text" 
                  id="license_key" 
                  v-model="form.license_key" 
                  placeholder="VM-XXXX-XXXX-XXXX"
                  required
                  autofocus
                >
              </div>
            </div>

            <!-- Server Info Cards/Details -->
            <div class="server-info-box mb-3" style="background: #252536; border: 2px solid #2d2d44; border-radius: 14px; padding: 15px;">
              <div class="d-flex justify-content-between mb-2" style="font-size: 0.85rem;">
                <span style="color: #a0a0b0;">Server IP:</span>
                <span class="font-monospace text-white">{{ serverIp }}</span>
              </div>
              <div class="d-flex justify-content-between" style="font-size: 0.85rem;">
                <span style="color: #a0a0b0;">Machine ID:</span>
                <span class="font-monospace text-white text-truncate" style="max-width: 200px;" :title="machineId">{{ machineId }}</span>
              </div>
            </div>

            <button type="submit" class="btn-submit" :disabled="form.processing">
              <span v-if="form.processing" class="loading-spinner"></span>
              <span v-else>
                Activate Now
                <i class="material-symbols-rounded">bolt</i>
              </span>
            </button>
          </form>

          <div class="form-footer">
            <p>
              Don't have a license? 
              <a href="https://vmcore.in/pricing" target="_blank" style="color: #667eea; text-decoration: none; font-weight: 700;">Get one here</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
  error: String,
  machineId: String,
  serverIp: String
})

const form = useForm({
  license_key: ''
})

const submit = () => {
  form.post(route('activate.submit'))
}
</script>
