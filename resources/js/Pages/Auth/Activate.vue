<template>
  <div class="activation-page">
    <div class="activation-content animate__animated animate__fadeIn">
      <div class="glass-card">
        <div class="header text-center mb-4">
          <div class="icon-blob mb-3">
            <i class="material-symbols-rounded">vpn_key</i>
          </div>
          <h2 class="text-white fw-bold mb-1">Activate nimbus</h2>
          <p class="text-white-50 small">Enter your license key to unlock the enterprise panel</p>
        </div>

        <div v-if="error" class="alert-custom mb-4 animate__animated animate__shakeX">
          <i class="material-symbols-rounded">warning</i>
          <span>{{ error }}</span>
        </div>

        <form @submit.prevent="submit" class="activation-form">
          <div class="form-group mb-4">
            <label class="custom-label">LICENSE KEY</label>
            <div class="input-wrapper">
              <i class="material-symbols-rounded">key</i>
              <input 
                v-model="form.license_key" 
                type="text" 
                placeholder="VM-XXXX-XXXX-XXXX"
                required
              >
            </div>
          </div>

          <div class="server-info mb-4">
            <div class="info-row">
              <span class="label">SERVER IP</span>
              <span class="value">{{ serverIp }}</span>
            </div>
            <div class="info-row">
              <span class="label">MACHINE ID</span>
              <span class="value text-truncate" :title="machineId">{{ machineId }}</span>
            </div>
          </div>

          <button 
            type="submit" 
            class="submit-btn"
            :disabled="form.processing"
          >
            <span v-if="form.processing" class="spinner"></span>
            <i v-else class="material-symbols-rounded">bolt</i>
            {{ form.processing ? 'Verifying...' : 'Activate Now' }}
          </button>
        </form>

        <div class="footer text-center mt-4">
          <p class="text-white-50 text-xs">
            Don't have a license? 
            <a href="https://vmcore.in/pricing" target="_blank" class="text-primary fw-bold">Get one here</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

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

<style scoped>
.activation-page {
  min-height: 100vh;
  background: radial-gradient(circle at top right, #2d1b4e, #1a1a2e 50%, #0f0f1a 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  font-family: 'Inter', sans-serif;
}

.glass-card {
  width: 100%;
  max-width: 440px;
  background: rgba(255, 255, 255, 0.03);
  backdrop-filter: blur(25px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 28px;
  padding: 40px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.icon-blob {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, rgba(94, 114, 228, 0.2), rgba(130, 94, 228, 0.2));
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  color: #5e72e4;
}

.icon-blob i {
  font-size: 36px;
}

.alert-custom {
  background: rgba(245, 54, 92, 0.1);
  border: 1px solid rgba(245, 54, 92, 0.2);
  color: #f5365c;
  padding: 12px 16px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
}

.custom-label {
  color: rgba(255, 255, 255, 0.5);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
  margin-left: 5px;
}

.input-wrapper {
  position: relative;
  margin-top: 8px;
}

.input-wrapper i {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(255, 255, 255, 0.3);
  font-size: 20px;
}

.input-wrapper input {
  width: 100%;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 14px;
  padding: 14px 14px 14px 45px;
  color: white;
  transition: all 0.3s;
}

.input-wrapper input:focus {
  outline: none;
  border-color: #5e72e4;
  background: rgba(255, 255, 255, 0.08);
  box-shadow: 0 0 0 4px rgba(94, 114, 228, 0.1);
}

.server-info {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 18px;
  padding: 15px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 5px 0;
}

.info-row .label {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.3);
  font-weight: 600;
}

.info-row .value {
  font-size: 12px;
  color: white;
  font-family: 'Monaco', 'Consolas', monospace;
}

.submit-btn {
  width: 100%;
  background: linear-gradient(135deg, #5e72e4, #825ee4);
  color: white;
  border: none;
  border-radius: 16px;
  padding: 16px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  transition: all 0.3s;
}

.submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(94, 114, 228, 0.4);
}

.submit-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.text-xs { font-size: 12px; }
</style>
