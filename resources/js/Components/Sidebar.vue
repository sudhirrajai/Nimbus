<template>
  <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2"
    id="sidenav-main">
    <div class="sidenav-header">
      <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-xl-none"
        id="iconSidenav" @click="closeSidebar" style="cursor: pointer; z-index: 10;"></i>

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

        <!-- ═══ MY WEBSITES (non-root users with assigned domains) ═══ -->
        <template v-if="!isRoot && assignedDomains.length > 0">
          <li class="nav-item mt-3">
            <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">
              My Websites
            </h6>
          </li>

          <li class="nav-item" v-for="domain in assignedDomains" :key="domain">
            <Link :href="'/file-manager/' + domain" class="nav-link" :class="isActive('/file-manager/' + domain)">
              <i class="material-symbols-rounded opacity-5">language</i>
              <span class="nav-link-text ms-1">{{ domain }}</span>
            </Link>
          </li>
        </template>

        <!-- ═══ SERVER MANAGEMENT (Root & Admin) ═══ -->
        <li v-if="isRootOrAdmin" class="nav-item mt-3">
          <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">
            Server Management
          </h6>
        </li>

        <li v-if="isRootOrAdmin" class="nav-item">
          <Link href="/domains" class="nav-link" :class="isActive('/domains')">
            <i class="material-symbols-rounded opacity-5">language</i>
            <span class="nav-link-text ms-1">Domains</span>
          </Link>
        </li>

        <li v-if="isRootOrAdmin || hasPerm('deployments')" class="nav-item">
          <Link href="/deployments" class="nav-link" :class="isActive('/deployments')">
            <i class="material-symbols-rounded opacity-5">rocket_launch</i>
            <span class="nav-link-text ms-1">Git Deployments</span>
          </Link>
        </li>

        <li v-if="isRootOrAdmin || hasPerm('database')" class="nav-item">
          <Link href="/database" class="nav-link" :class="isActive('/database')">
            <i class="material-symbols-rounded opacity-5">storage</i>
            <span class="nav-link-text ms-1">Databases</span>
          </Link>
        </li>

        <li v-if="isRootOrAdmin || hasPerm('ssl')" class="nav-item">
          <Link href="/ssl" class="nav-link" :class="isActive('/ssl')">
            <i class="material-symbols-rounded opacity-5">lock</i>
            <span class="nav-link-text ms-1">SSL Certificates</span>
          </Link>
        </li>

        <li v-if="isRootOrAdmin" class="nav-item">
          <Link href="/nginx" class="nav-link" :class="isActive('/nginx')">
            <i class="material-symbols-rounded opacity-5">settings_ethernet</i>
            <span class="nav-link-text ms-1">Nginx Configuration</span>
          </Link>
        </li>

        <li v-if="isRoot" class="nav-item">
          <Link href="/php" class="nav-link" :class="isActive('/php')">
            <i class="material-symbols-rounded opacity-5">code</i>
            <span class="nav-link-text ms-1">PHP Configuration</span>
          </Link>
        </li>

        <li v-if="isRootOrAdmin || hasPerm('wordpress')" class="nav-item">
          <Link href="/wordpress" class="nav-link" :class="isActive('/wordpress')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.52 122.523" width="20" height="20" class="opacity-5" style="min-width:20px"><g fill="currentColor"><path d="M8.708 61.26c0 20.802 12.089 38.779 29.619 47.298L13.258 39.872a52.354 52.354 0 0 0-4.55 21.388z"/><path d="M96.74 58.608c0-6.495-2.333-10.993-4.334-14.494-2.664-4.329-5.161-7.995-5.161-12.324 0-4.831 3.664-9.328 8.825-9.328.233 0 .454.029.681.042-9.35-8.566-21.807-13.796-35.489-13.796-18.36 0-34.513 9.42-43.91 23.688 1.233.037 2.395.063 3.382.063 5.497 0 14.006-.668 14.006-.668 2.833-.166 3.167 3.994.337 4.329 0 0-2.847.335-6.015.501L48.2 93.547l11.501-34.493-8.188-22.434c-2.83-.166-5.511-.501-5.511-.501-2.832-.166-2.5-4.496.332-4.329 0 0 8.679.668 13.843.668 5.496 0 14.006-.668 14.006-.668 2.835-.166 3.168 3.994.337 4.329 0 0-2.853.335-6.015.501l18.992 56.494 5.242-17.517c2.272-7.269 4.001-12.49 4.001-16.989z"/><path d="M62.184 65.857l-15.768 45.819a52.552 52.552 0 0 0 32.29-.84 4.7 4.7 0 0 1-.377-.726L62.184 65.857z"/><path d="M107.376 36.046c.226 1.674.354 3.471.354 5.404 0 5.333-.996 11.328-3.996 18.824l-16.053 46.413C101.291 98.083 113.812 81.18 113.812 61.26c0-9.192-2.39-17.833-6.436-25.214z"/><path d="M61.262 0C27.483 0 0 27.481 0 61.26c0 33.783 27.483 61.263 61.262 61.263 33.778 0 61.258-27.48 61.258-61.263C122.52 27.481 95.04 0 61.262 0zm0 119.715c-32.23 0-58.453-26.223-58.453-58.455 0-32.23 26.222-58.451 58.453-58.451 32.229 0 58.45 26.221 58.45 58.451 0 32.232-26.221 58.455-58.45 58.455z"/></g></svg>
            <span class="nav-link-text ms-1">WordPress</span>
          </Link>
        </li>

        <!-- ═══ FILES & RESOURCES (Root & Admin) ═══ -->
        <template v-if="isRootOrAdmin">
          <li class="nav-item mt-3">
            <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">
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
            <Link href="/ftp" class="nav-link" :class="isActive('/ftp-accounts')">
              <i class="material-symbols-rounded opacity-5">cloud_upload</i>
              <span class="nav-link-text ms-1">FTP Accounts</span>
            </Link>
          </li>
        </template>

        <!-- ═══ EMAIL (Root & Admin) ═══ -->
        <template v-if="isRootOrAdmin">
          <li class="nav-item mt-3">
            <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">
              Email
            </h6>
          </li>

          <li class="nav-item">
            <Link href="/email" class="nav-link" :class="isActive('/email')">
              <i class="material-symbols-rounded opacity-5">email</i>
              <span class="nav-link-text ms-1">Email Accounts</span>
            </Link>
          </li>
        </template>

        <!-- ═══ AUTOMATION (Root only) ═══ -->
        <li v-if="isRoot" class="nav-item mt-3">
          <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">
            Automation
          </h6>
        </li>

        <li v-if="isRoot" class="nav-item">
          <Link href="/supervisor" class="nav-link" :class="isActive('/supervisor')">
            <i class="material-symbols-rounded opacity-5">memory</i>
            <span class="nav-link-text ms-1">Supervisor</span>
          </Link>
        </li>

        <li v-if="isRoot" class="nav-item">
          <Link href="/cron" class="nav-link" :class="isActive('/cron')">
            <i class="material-symbols-rounded opacity-5">schedule</i>
            <span class="nav-link-text ms-1">Cron Jobs</span>
          </Link>
        </li>

        <!-- ═══ MONITORING (Root & Admin) ═══ -->
        <li v-if="isRootOrAdmin" class="nav-item mt-3">
          <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">
            Monitoring
          </h6>
        </li>

        <li v-if="isRootOrAdmin" class="nav-item">
          <Link href="/logs" class="nav-link" :class="isActive('/logs')">
            <i class="material-symbols-rounded opacity-5">description</i>
            <span class="nav-link-text ms-1">Logs</span>
          </Link>
        </li>

        <li v-if="isRootOrAdmin" class="nav-item">
          <Link href="/resources" class="nav-link" :class="isActive('/resources')">
            <i class="material-symbols-rounded opacity-5">monitoring</i>
            <span class="nav-link-text ms-1">Resource Usage</span>
          </Link>
        </li>

        <!-- ═══ ACCOUNT ═══ -->
        <li class="nav-item mt-3">
          <h6 class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5">
            Account
          </h6>
        </li>

        <li class="nav-item">
          <Link href="/profile" class="nav-link" :class="isActive('/profile')">
            <i class="material-symbols-rounded opacity-5">person</i>
            <span class="nav-link-text ms-1">Profile</span>
          </Link>
        </li>

        <li v-if="isRoot" class="nav-item">
          <Link href="/users" class="nav-link" :class="isActive('/users')">
            <i class="material-symbols-rounded opacity-5">group</i>
            <span class="nav-link-text ms-1">User Management</span>
          </Link>
        </li>

        <li v-if="isRoot" class="nav-item">
          <Link href="/settings" class="nav-link" :class="isActive('/settings')">
            <i class="material-symbols-rounded opacity-5">settings</i>
            <span class="nav-link-text ms-1">Settings</span>
          </Link>
        </li>

        <li v-if="isRoot" class="nav-item">
          <Link href="/updates" class="nav-link" :class="isActive('/updates')">
            <i class="material-symbols-rounded opacity-5">system_update</i>
            <span class="nav-link-text ms-1">Updates</span>
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
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted } from 'vue'

const page = usePage()

const userRole = computed(() => page.props.auth?.user?.role || 'user')
const isRoot = computed(() => page.props.auth?.user?.is_root || userRole.value === 'root')
const isRootOrAdmin = computed(() => isRoot.value || userRole.value === 'admin')
const userPermissions = computed(() => page.props.auth?.user?.permissions || [])
const assignedDomains = computed(() => page.props.auth?.user?.assigned_domains || [])

const hasPerm = (perm) => isRoot.value || userPermissions.value.includes(perm)

const isActive = (path) => {
  const currentPath = page.url

  // Check if current path matches or starts with the given path
  if (currentPath === path || currentPath.startsWith(path + '/')) {
    return 'active bg-gradient-dark text-white'
  }

  return 'text-dark'
}

const closeSidebar = () => {
  // Use global function if available (set by navbar)
  if (window.closeMobileSidebar) {
    window.closeMobileSidebar()
  } else {
    document.body.classList.remove('g-sidenav-pinned')
    const sidenav = document.getElementById('sidenav-main')
    sidenav?.classList.remove('show-mobile')
    document.body.style.overflow = ''
  }
}

// Close sidebar on navigation (fixes scroll issue)
let removeListener = null

onMounted(() => {
  removeListener = router.on('navigate', () => {
    closeSidebar()
    // Extra safety: ensure body overflow is reset
    document.body.style.overflow = ''
  })
})

onUnmounted(() => {
  if (removeListener) {
    removeListener()
  }
})
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