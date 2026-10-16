<template>
  <div class="g-sidenav-show main-app-canvas" :class="{ 'dark-mode-canvas': isDark }">
    <!-- Ambient Background Glassmesh & Glow Orbs -->
    <div class="ambient-glow-wrapper" aria-hidden="true">
      <div class="ambient-glow ambient-glow-1"></div>
      <div class="ambient-glow ambient-glow-2"></div>
      <div class="ambient-glow ambient-glow-3"></div>
    </div>

    <Sidebar />

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg d-flex flex-column min-vh-100">
      <Navbar />

      <div v-if="$page.props.license_warning" class="container-fluid pt-3 pb-0 position-relative z-index-2">
        <div class="alert alert-warning text-white alert-dismissible fade show d-flex align-items-center mb-0 shadow-sm rounded-3" role="alert">
          <span class="material-symbols-rounded me-2">warning</span>
          <span class="text-sm font-weight-bold">{{ $page.props.license_warning }}</span>
          <button type="button" class="btn-close text-lg py-3 opacity-10" data-bs-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>

      <div class="container-fluid px-2 px-sm-3 py-2 flex-grow-1 position-relative z-index-1">
        <slot />
      </div>

      <Footer class="mt-auto position-relative z-index-1" />
    </main>
  </div>
</template>

<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import Navbar from '@/Components/AppNavbar.vue';
import Footer from '@/Components/AppFooter.vue';
import { ref, onMounted, onUnmounted } from 'vue';

const isDark = ref(false);

const checkTheme = () => {
  const saved = localStorage.getItem('nimbus_theme');
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  isDark.value = saved ? (saved === 'dark') : prefersDark;
  
  if (isDark.value) {
    document.documentElement.classList.add('dark');
    document.body?.classList.add('dark-version');
  } else {
    document.documentElement.classList.remove('dark');
    document.body?.classList.remove('dark-version');
  }
};

const handleThemeChanged = (e) => {
  if (e?.detail?.isDark !== undefined) {
    isDark.value = e.detail.isDark;
  } else {
    checkTheme();
  }
};

onMounted(() => {
  checkTheme();
  window.addEventListener('nimbus-theme-changed', handleThemeChanged);

  // Load Material Dashboard scripts
  const scripts = [
    '/assets/js/core/popper.min.js',
    '/assets/js/core/bootstrap.min.js',
    '/assets/js/plugins/perfect-scrollbar.min.js',
    '/assets/js/plugins/smooth-scrollbar.min.js',
    '/assets/js/material-dashboard.min.js?v=3.2.0'
  ];

  scripts.forEach(src => {
    if (!document.querySelector(`script[src="${src}"]`)) {
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      document.body.appendChild(script);
    }
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

onUnmounted(() => {
  window.removeEventListener('nimbus-theme-changed', handleThemeChanged);
});
</script>

<style scoped>
.main-app-canvas {
  position: relative;
  min-height: 100vh;
  overflow-x: hidden;
  transition: background-color 0.3s ease;
}

.ambient-glow-wrapper {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  overflow: hidden;
}

.ambient-glow {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(85px);
  -webkit-filter: blur(85px);
  will-change: transform;
}

.ambient-glow-1 {
  top: -80px;
  right: 5%;
  width: 520px;
  height: 520px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.18) 0%, rgba(59, 130, 246, 0.1) 45%, transparent 70%);
  animation: floatOrb 22s ease-in-out infinite alternate;
}

.ambient-glow-2 {
  top: 30%;
  left: -120px;
  width: 560px;
  height: 560px;
  background: radial-gradient(circle, rgba(16, 185, 129, 0.16) 0%, rgba(14, 165, 233, 0.08) 50%, transparent 70%);
  animation: floatOrbReverse 26s ease-in-out infinite alternate;
}

.ambient-glow-3 {
  bottom: 5%;
  right: 20%;
  width: 480px;
  height: 480px;
  background: radial-gradient(circle, rgba(139, 92, 246, 0.14) 0%, rgba(236, 72, 153, 0.06) 50%, transparent 70%);
  animation: floatOrb 30s ease-in-out infinite alternate;
}

/* Enhanced Dark Mode glow intensities */
:global(body.dark-version) .ambient-glow-1 {
  background: radial-gradient(circle, rgba(99, 102, 241, 0.28) 0%, rgba(59, 130, 246, 0.15) 50%, transparent 70%);
  filter: blur(95px);
}

:global(body.dark-version) .ambient-glow-2 {
  background: radial-gradient(circle, rgba(16, 185, 129, 0.22) 0%, rgba(14, 165, 233, 0.12) 50%, transparent 70%);
  filter: blur(95px);
}

:global(body.dark-version) .ambient-glow-3 {
  background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, rgba(56, 189, 248, 0.1) 50%, transparent 70%);
  filter: blur(95px);
}

@keyframes floatOrb {
  0% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(-35px, 45px) scale(1.08); }
  100% { transform: translate(25px, -25px) scale(0.94); }
}

@keyframes floatOrbReverse {
  0% { transform: translate(0, 0) scale(1); }
  50% { transform: translate(45px, -35px) scale(1.06); }
  100% { transform: translate(-25px, 35px) scale(0.92); }
}
</style>