<template>
  <div class="login-page">
    <div class="login-container">
      <div class="login-card">
        <div class="login-header">
          <!-- <img src="/assets/img/logo.png" alt="Nimbus" class="logo" onerror="this.style.display='none'"> -->
          <h1 class="brand-name">Nimbus</h1>
          <p class="subtitle">Server Control Panel</p>
        </div>

        <form @submit.prevent="submit" class="login-form">
          <div v-if="errors.email" class="alert alert-danger">
            {{ errors.email }}
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
                autofocus
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
                placeholder="••••••••"
                required
              >
            </div>
          </div>

          <div class="form-group remember">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.remember">
              <span>Remember me</span>
            </label>
          </div>

          <button type="submit" class="btn-login" :disabled="loading">
            <span v-if="loading" class="spinner"></span>
            <span v-else>Sign In</span>
          </button>
        </form>
      </div>

      <p class="footer-text">Nimbus Control Panel &copy; {{ new Date().getFullYear() }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const errors = page.props.errors || {}
const loading = ref(false)

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
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  padding: 20px;
}

.login-container {
  width: 100%;
  max-width: 420px;
}

.login-card {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 40px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
}

.login-header {
  text-align: center;
  margin-bottom: 35px;
}

.logo {
  width: 60px;
  height: 60px;
  margin-bottom: 15px;
}

.brand-name {
  font-size: 2rem;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.subtitle {
  color: #6b7280;
  font-size: 0.9rem;
  margin-top: 5px;
}

.login-form {
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

.remember {
  margin: 0;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 0.9rem;
  color: #4b5563;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #667eea;
}

.btn-login {
  width: 100%;
  padding: 15px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
}

.btn-login:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.btn-login:disabled {
  opacity: 0.7;
  cursor: not-allowed;
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

.footer-text {
  text-align: center;
  color: rgba(255, 255, 255, 0.5);
  font-size: 0.8rem;
  margin-top: 25px;
}
</style>
