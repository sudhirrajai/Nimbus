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

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #1a1a2e;
  font-family: 'Inter', sans-serif;
}

.login-container {
  display: flex;
  width: 100%;
  max-width: 1000px;
  min-height: 600px;
  margin: 20px;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
}

/* Left Branding Section */
.branding-section {
  flex: 1;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
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
  font-size: 2.5rem;
  font-weight: 700;
  margin: 0 0 8px;
  letter-spacing: -0.5px;
}

.brand-subtitle {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0 0 40px;
}

.features-list {
  text-align: left;
  margin-top: 30px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  opacity: 0.9;
  font-size: 0.95rem;
}

.feature-item i {
  font-size: 22px;
  background: rgba(255, 255, 255, 0.2);
  padding: 8px;
  border-radius: 10px;
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
  max-width: 380px;
}

.form-header {
  margin-bottom: 35px;
}

.form-header h2 {
  color: #fff;
  font-size: 1.8rem;
  font-weight: 600;
  margin: 0 0 8px;
}

.form-header p {
  color: #8b8b9a;
  font-size: 0.95rem;
  margin: 0;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.form-group label {
  display: block;
  color: #a0a0b0;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 10px;
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
  padding: 16px 16px 16px 52px;
  background: #252536;
  border: 2px solid #2d2d44;
  border-radius: 14px;
  color: #fff;
  font-size: 0.95rem;
  transition: all 0.3s ease;
}

.input-field input::placeholder {
  color: #5c5c6e;
}

.input-field input:focus {
  outline: none;
  border-color: #667eea;
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
  transition: color 0.2s;
}

.toggle-password:hover i {
  color: #a0a0b0;
}

.form-options {
  display: flex;
  align-items: center;
}

.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  color: #8b8b9a;
  font-size: 0.9rem;
}

.checkbox-wrapper input {
  display: none;
}

.checkmark {
  width: 20px;
  height: 20px;
  border: 2px solid #3d3d55;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.checkbox-wrapper input:checked + .checkmark {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-color: transparent;
}

.checkbox-wrapper input:checked + .checkmark::after {
  content: '✓';
  color: white;
  font-size: 12px;
  font-weight: bold;
}

.btn-submit {
  width: 100%;
  padding: 16px 24px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 14px;
  color: white;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.3s ease;
  margin-top: 10px;
}

.btn-submit:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
}

.btn-submit:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-submit i {
  font-size: 20px;
  transition: transform 0.2s;
}

.btn-submit:hover:not(:disabled) i {
  transform: translateX(4px);
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
  align-items: center;
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
}

.form-footer {
  margin-top: 40px;
  text-align: center;
}

.form-footer p {
  color: #5c5c6e;
  font-size: 0.8rem;
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .login-container {
    flex-direction: column;
    max-width: 440px;
  }
  
  .branding-section {
    padding: 40px 30px;
  }
  
  .features-list {
    display: none;
  }
  
  .form-section {
    padding: 40px 30px;
  }
}
</style>
