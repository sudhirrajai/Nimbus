<template>
  <div class="setup-page">
    <div class="setup-container">
      <div class="setup-card">
        <div class="setup-header">
          <div class="icon-wrapper">
            <i class="material-symbols-rounded">rocket_launch</i>
          </div>
          <h1>Welcome to Nimbus</h1>
          <p class="subtitle">Set up your admin account to get started</p>
        </div>

        <form @submit.prevent="submit" class="setup-form">
          <div v-if="Object.keys(errors).length > 0" class="alert alert-danger">
            <ul>
              <li v-for="(error, key) in errors" :key="key">{{ error }}</li>
            </ul>
          </div>

          <div class="form-group">
            <label for="name">Full Name</label>
            <div class="input-wrapper">
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
            <div class="input-wrapper">
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
            <div class="input-wrapper">
              <i class="material-symbols-rounded">lock</i>
              <input 
                type="password" 
                id="password" 
                v-model="form.password" 
                placeholder="Minimum 8 characters"
                required
                minlength="8"
              >
            </div>
          </div>

          <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <div class="input-wrapper">
              <i class="material-symbols-rounded">lock</i>
              <input 
                type="password" 
                id="password_confirmation" 
                v-model="form.password_confirmation" 
                placeholder="Repeat your password"
                required
              >
            </div>
          </div>

          <button type="submit" class="btn-setup" :disabled="loading || !isValid">
            <span v-if="loading" class="spinner"></span>
            <span v-else>
              <i class="material-symbols-rounded">check</i>
              Create Admin Account
            </span>
          </button>
        </form>

        <div class="security-note">
          <i class="material-symbols-rounded">shield</i>
          <span>Your credentials are stored securely with bcrypt encryption</span>
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

<style scoped>
.setup-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  padding: 20px;
}

.setup-container {
  width: 100%;
  max-width: 480px;
}

.setup-card {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 24px;
  padding: 45px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
}

.setup-header {
  text-align: center;
  margin-bottom: 35px;
}

.icon-wrapper {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.icon-wrapper i {
  font-size: 40px;
  color: white;
}

.setup-header h1 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0;
}

.subtitle {
  color: #6b7280;
  font-size: 0.95rem;
  margin-top: 8px;
}

.setup-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-wrapper i {
  position: absolute;
  left: 15px;
  color: #9ca3af;
  font-size: 20px;
}

.input-wrapper input {
  width: 100%;
  padding: 14px 14px 14px 50px;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  font-size: 1rem;
  transition: all 0.3s ease;
  background: #f9fafb;
}

.input-wrapper input:focus {
  outline: none;
  border-color: #667eea;
  background: #fff;
  box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.btn-setup {
  width: 100%;
  padding: 16px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 10px;
}

.btn-setup:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
}

.btn-setup:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.btn-setup i {
  font-size: 20px;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 0.9rem;
}

.alert-danger {
  background: #fee2e2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.alert ul {
  margin: 0;
  padding-left: 20px;
}

.security-note {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
  color: #6b7280;
  font-size: 0.8rem;
}

.security-note i {
  font-size: 18px;
  color: #10b981;
}
</style>
