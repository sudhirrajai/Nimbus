<template>
  <div class="g-sidenav-show bg-gray-100">
    <Sidebar />

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
      <Navbar />

      <div class="container-fluid py-2">
        <slot />
      </div>

      <Footer />
    </main>

    <!-- <SettingsPanel /> -->
  </div>
</template>

<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import Navbar from '@/Components/AppNavbar.vue';
import Footer from '@/Components/AppFooter.vue';
import SettingsPanel from '@/Components/SettingsPanel.vue';
import { onMounted } from 'vue';

onMounted(() => {
  // Load Material Dashboard scripts
  const scripts = [
    '/assets/js/core/popper.min.js',
    '/assets/js/core/bootstrap.min.js',
    '/assets/js/plugins/perfect-scrollbar.min.js',
    '/assets/js/plugins/smooth-scrollbar.min.js',
    '/assets/js/material-dashboard.min.js?v=3.2.0'
  ];

  scripts.forEach(src => {
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    document.body.appendChild(script);
  });

  // Initialize scrollbar
  const win = navigator.platform.indexOf('Win') > -1;
  if (win && document.querySelector('#sidenav-scrollbar')) {
    const options = { damping: '0.5' };
    if (window.Scrollbar) {
      window.Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  }
});
</script>