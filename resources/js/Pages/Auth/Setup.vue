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

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

.setup-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #1a1a2e;
  font-family: 'Inter', sans-serif;
}

.setup-container {
  display: flex;
  width: 100%;
  max-width: 1000px;
  min-height: 650px;
  margin: 20px;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
}

/* Left Branding Section */
.branding-section {
  flex: 1;
  background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
  padding: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.branding-section::before {
  content: '';
  position: absolute;
  width: 400px;
  height: 400px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  top: -100px;
  right: -100px;
}

.branding-section::after {
  content: '';
  position: absolute;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 50%;
  bottom: -50px;
  left: -50px;
}

.brand-content {
  position: relative;
  z-index: 1;
  text-align: center;
  color: white;
}

.logo-wrapper {
  width: 100px;
  height: 100px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 25px;
  backdrop-filter: blur(10px);
}

.logo-icon {
  font-size: 56px;
  color: white;
}

.brand-title {
  font-size: 2rem;
  font-weight: 700;
  margin: 0 0 8px;
  letter-spacing: -0.5px;
}

.brand-subtitle {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0 0 40px;
}

.steps-list {
  text-align: left;
  margin-top: 40px;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 16px 0;
  opacity: 0.6;
  font-size: 1rem;
}

.step-item.active {
  opacity: 1;
}

.step-number {
  width: 36px;
  height: 36px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.9rem;
}

.step-item.active .step-number {
  background: white;
  color: #059669;
}

/* Right Form Section */
.form-section {
  flex: 1;
  background: #1e1e2f;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 50px;
}

.form-card {
  width: 100%;
  max-width: 400px;
}

.form-header {
  margin-bottom: 30px;
}

.form-header h2 {
  color: #fff;
  font-size: 1.7rem;
  font-weight: 600;
  margin: 0 0 8px;
}

.form-header p {
  color: #8b8b9a;
  font-size: 0.95rem;
  margin: 0;
}

.setup-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-group label {
  display: block;
  color: #a0a0b0;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 8px;
}

.input-field {
  position: relative;
  display: flex;
  align-items: center;
}

.input-field i {
  position: absolute;
  left: 16px;
  color: #6c6c7e;
  font-size: 20px;
}

.input-field input {
  width: 100%;
  padding: 14px 14px 14px 52px;
  background: #252536;
  border: 2px solid #2d2d44;
  border-radius: 12px;
  color: #fff;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.input-field input::placeholder {
  color: #5c5c6e;
}

.input-field input:focus {
  outline: none;
  border-color: #10b981;
  background: #2a2a3f;
}

.toggle-password {
  position: absolute;
  right: 16px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.toggle-password i {
  position: static;
  color: #6c6c7e;
  font-size: 20px;
}

.password-mismatch {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  color: #f59e0b;
  font-size: 0.8rem;
}

.password-mismatch i {
  font-size: 16px;
}

.btn-submit {
  width: 100%;
  padding: 15px 24px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border: none;
  border-radius: 12px;
  color: white;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.3s ease;
  margin-top: 8px;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
}

.btn-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-submit i {
  font-size: 20px;
}

.loading-spinner {
  width: 22px;
  height: 22px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 18px;
  border-radius: 12px;
  font-size: 0.9rem;
}

.alert-danger {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.alert i {
  font-size: 20px;
  flex-shrink: 0;
}

.security-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #2d2d44;
  color: #5c5c6e;
  font-size: 0.8rem;
}

.security-notice i {
  font-size: 18px;
  color: #10b981;
}

/* Responsive */
@media (max-width: 768px) {
  .setup-container {
    flex-direction: column;
    max-width: 440px;
  }
  
  .branding-section {
    padding: 40px 30px;
  }
  
  .steps-list {
    display: none;
  }
  
  .form-section {
    padding: 40px 30px;
  }
}
</style>
