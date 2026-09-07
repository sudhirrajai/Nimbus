<template>
  <Head title="Webmail Login - Nimbus" />
  <div class="login-page">
    <div class="login-container">
      <!-- Left side - Branding -->
      <div class="branding-section">
        <div class="brand-content">
          <div class="logo-wrapper" style="overflow: hidden; background: rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 12px; backdrop-filter: blur(10px);">
            <img :src="'/assets/img/nimbus_logo.png?v=2'" style="width: 100%; height: 100%; object-fit: contain; border-radius: 12px;" alt="Nimbus">
          </div>
          <h1 class="brand-title">nimbus</h1>
          <p class="brand-subtitle">Private Webmail by <a href="https://vmcore.in" target="_blank" style="color: #ffffff; text-decoration: none; font-weight: 700;">VMCore</a></p>
          <div class="features-list">
            <div class="feature-item">
              <i class="material-symbols-rounded">mail</i>
              <span>High Speed IMAP & SMTP</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">security</i>
              <span>End-to-End Encrypted Storage</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">attach_file</i>
              <span>Full Attachment Support</span>
            </div>
            <div class="feature-item">
              <i class="material-symbols-rounded">dark_mode</i>
              <span>Light & Dark Themes</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side - Login Form -->
      <div class="form-section">
        <div class="form-card">
          <div class="form-header">
            <h2>Nimbus Webmail</h2>
            <p>Sign in to your mailbox account</p>
          </div>

          <form @submit.prevent="submit" class="login-form">
            <div v-if="timedOut" class="alert alert-warning d-flex align-items-center mb-3" style="background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; border-radius: 8px; padding: 10px 14px;">
              <i class="material-symbols-rounded me-2 text-warning">schedule</i>
              <span class="text-xs font-weight-bold">Your session timed out due to inactivity. Please log in again.</span>
            </div>

            <div v-if="form.errors.email" class="alert alert-danger">
              <i class="material-symbols-rounded">error</i>
              {{ form.errors.email }}
            </div>
            <div v-if="form.errors.password" class="alert alert-danger">
              <i class="material-symbols-rounded">error</i>
              {{ form.errors.password }}
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <div class="input-field">
                <i class="material-symbols-rounded">mail</i>
                <input 
                  type="email" 
                  id="email" 
                  v-model="form.email" 
                  placeholder="name@yourdomain.com"
                  required
                  autofocus
                >
              </div>
            </div>

            <div class="form-group">
              <label for="password">Mailbox Password</label>
              <div class="input-field">
                <i class="material-symbols-rounded">lock</i>
                <input 
                  :type="showPassword ? 'text' : 'password'" 
                  id="password" 
                  v-model="form.password" 
                  placeholder="Enter mailbox password"
                  required
                >
                <button type="button" class="toggle-password" @click="showPassword = !showPassword">
                  <i class="material-symbols-rounded">{{ showPassword ? 'visibility_off' : 'visibility' }}</i>
                </button>
              </div>
            </div>

            <button type="submit" class="btn-submit" :disabled="form.processing">
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
              <span v-else>
                Sign In to Webmail
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
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
  timedOut: Boolean
});

const showPassword = ref(false);

const form = useForm({
  email: '',
  password: ''
});

const submit = () => {
  form.post('/webmail/login');
};
</script>
