<template>
  <aside
    class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2"
    id="sidenav-main"
  >
    <div class="sidenav-header">
      <i
        class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-xl-none"
        id="iconSidenav"
      ></i>

      <a class="navbar-brand px-4 py-3 m-0" href="#">
        <span class="ms-1 text-sm text-dark font-weight-bold">Nimbus Control Panel</span>
      </a>
    </div>

    <hr class="horizontal dark mt-0 mb-2" />

    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
      <ul class="navbar-nav">

        <li class="nav-item">
          <Link href="/dashboard" class="nav-link" :class="isActive('/dashboard')">
            <i class="material-symbols-rounded opacity-5">dashboard</i>
            <span class="nav-link-text ms-1">Dashboard</span>
          </Link>
        </li>

        <!-- Server Management Section -->
        <li class="nav-item mt-3">
          <h6
            class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5"
          >
            Server Management
          </h6>
        </li>

        <li class="nav-item">
          <Link href="/domains" class="nav-link" :class="isActive('/domains')">
            <i class="material-symbols-rounded opacity-5">language</i>
            <span class="nav-link-text ms-1">Domains</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/database" class="nav-link" :class="isActive('/databases')">
            <i class="material-symbols-rounded opacity-5">storage</i>
            <span class="nav-link-text ms-1">Databases</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/ssl" class="nav-link" :class="isActive('/ssl')">
            <i class="material-symbols-rounded opacity-5">lock</i>
            <span class="nav-link-text ms-1">SSL Certificates</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/nginx" class="nav-link" :class="isActive('/nginx')">
            <i class="material-symbols-rounded opacity-5">settings_ethernet</i>
            <span class="nav-link-text ms-1">Nginx Configuration</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/supervisor" class="nav-link" :class="isActive('/supervisor')">
            <i class="material-symbols-rounded opacity-5">memory</i>
            <span class="nav-link-text ms-1">Supervisor</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/cron-jobs" class="nav-link" :class="isActive('/cron-jobs')">
            <i class="material-symbols-rounded opacity-5">schedule</i>
            <span class="nav-link-text ms-1">Cron Jobs</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/php" class="nav-link" :class="isActive('/php')">
            <i class="material-symbols-rounded opacity-5">code</i>
            <span class="nav-link-text ms-1">PHP Configuration</span>
          </Link>
        </li>

        <!-- Files & Resources Section -->
        <li class="nav-item mt-3">
          <h6
            class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5"
          >
            Files & Resources
          </h6>
        </li>

        <li class="nav-item">
          <Link href="/backups" class="nav-link" :class="isActive('/backups')">
            <i class="material-symbols-rounded opacity-5">backup</i>
            <span class="nav-link-text ms-1">Backups</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/ftp-accounts" class="nav-link" :class="isActive('/ftp-accounts')">
            <i class="material-symbols-rounded opacity-5">cloud_upload</i>
            <span class="nav-link-text ms-1">FTP Accounts</span>
          </Link>
        </li>

        <!-- Email Section -->
        <li class="nav-item mt-3">
          <h6
            class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5"
          >
            Email
          </h6>
        </li>

        <li class="nav-item">
          <Link href="/email-accounts" class="nav-link" :class="isActive('/email-accounts')">
            <i class="material-symbols-rounded opacity-5">email</i>
            <span class="nav-link-text ms-1">Email Accounts</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/email-forwarders" class="nav-link" :class="isActive('/email-forwarders')">
            <i class="material-symbols-rounded opacity-5">forward_to_inbox</i>
            <span class="nav-link-text ms-1">Forwarders</span>
          </Link>
        </li>

        <!-- Monitoring Section -->
        <li class="nav-item mt-3">
          <h6
            class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5"
          >
            Monitoring
          </h6>
        </li>

        <li class="nav-item">
          <Link href="/logs" class="nav-link" :class="isActive('/logs')">
            <i class="material-symbols-rounded opacity-5">description</i>
            <span class="nav-link-text ms-1">Logs</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/resource-usage" class="nav-link" :class="isActive('/resource-usage')">
            <i class="material-symbols-rounded opacity-5">monitoring</i>
            <span class="nav-link-text ms-1">Resource Usage</span>
          </Link>
        </li>

        <!-- Account Section -->
        <li class="nav-item mt-3">
          <h6
            class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5"
          >
            Account
          </h6>
        </li>

        <li class="nav-item">
          <Link href="/profile" class="nav-link" :class="isActive('/profile')">
            <i class="material-symbols-rounded opacity-5">person</i>
            <span class="nav-link-text ms-1">Profile</span>
          </Link>
        </li>

        <li class="nav-item">
          <Link href="/settings" class="nav-link" :class="isActive('/settings')">
            <i class="material-symbols-rounded opacity-5">settings</i>
            <span class="nav-link-text ms-1">Settings</span>
          </Link>
        </li>

      </ul>
    </div>

    <div class="sidenav-footer position-absolute w-100 bottom-0">
      <div class="mx-3">
        <a class="btn btn-outline-dark mt-4 w-100" href="/documentation">
          <i class="material-symbols-rounded text-sm me-1">article</i>
          Documentation
        </a>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

const isActive = (path) => {
  const currentPath = page.url
  
  // Check if current path matches or starts with the given path
  if (currentPath === path || currentPath.startsWith(path + '/')) {
    return 'active bg-gradient-dark text-white'
  }
  
  return 'text-dark'
}
</script>

<style scoped>
.nav-link {
  transition: all 0.2s ease-in-out;
}

.nav-link:hover:not(.active) {
  background-color: rgba(0, 0, 0, 0.05);
  transform: translateX(5px);
}

.nav-link.active {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>