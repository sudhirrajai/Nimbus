<template>
  <nav
    class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl"
    id="navbarBlur"
  >
    <div class="container-fluid py-1 px-3">
      <!-- Mobile menu toggle button -->
      <div class="d-xl-none">
        <a href="#" class="nav-link text-body p-0" @click.prevent="toggleSidebar">
          <i class="material-symbols-rounded text-dark" style="font-size: 28px;">menu</i>
        </a>
      </div>

      <div
        class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4 show"
        id="navbar"
      >
        <div class="ms-md-auto pe-md-3 d-flex align-items-center">
          <GlobalSearch />
        </div>

        <ul class="navbar-nav d-flex align-items-center justify-content-end">
          <!-- Live Server Clock Capsule -->
          <li class="nav-item pe-3 d-none d-sm-flex align-items-center position-relative" ref="clockDropdownRef">
            <div class="server-time-capsule d-flex align-items-center cursor-pointer"
                 :class="{ 'capsule-active': isClockOpen }"
                 @click="isClockOpen = !isClockOpen"
                 title="Click to view Server vs Local Time details">
              <span class="live-dot"></span>
              <div class="d-flex align-items-center gap-1">
                <i class="material-symbols-rounded clock-icon">schedule</i>
                <span class="time-text">{{ serverTimeDisplay }}</span>
              </div>
              <span class="tz-badge">{{ serverTimezoneShort }}</span>
            </div>

            <!-- Floating Clock Details Popover -->
            <div v-if="isClockOpen" class="clock-popover shadow-lg border rounded-3 p-3 bg-white position-absolute z-index-modal">
              <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                <div class="d-flex align-items-center gap-1.5">
                  <span class="live-dot"></span>
                  <span class="text-xs font-weight-bold text-dark">Time Synchronization</span>
                </div>
                <span class="badge badge-xs bg-success-subtle text-success">Live Sync</span>
              </div>
              
              <!-- Server Time Card -->
              <div class="p-2 rounded-2 mb-2 bg-gray-100 border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-xxs text-secondary text-uppercase font-weight-bold d-flex align-items-center">
                    <i class="material-symbols-rounded text-xxs me-1 text-primary">dns</i>Linux Server
                  </span>
                  <span class="badge badge-xs bg-dark font-monospace text-xxs">{{ serverTimezone }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline">
                  <span class="font-monospace text-sm font-weight-bolder text-dark">{{ serverTimeDisplay }}</span>
                  <span class="text-xxs text-muted">{{ serverDateDisplay }}</span>
                </div>
              </div>

              <!-- Local Time Card -->
              <div class="p-2 rounded-2 mb-2 bg-white border">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="text-xxs text-secondary text-uppercase font-weight-bold d-flex align-items-center">
                    <i class="material-symbols-rounded text-xxs me-1 text-info">laptop_mac</i>Your Browser
                  </span>
                  <span class="badge badge-xs bg-secondary font-monospace text-xxs">{{ localTimezoneShort }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline">
                  <span class="font-monospace text-sm font-weight-bold text-dark">{{ localTimeDisplay }}</span>
                  <span class="text-xxs text-muted">{{ localDateDisplay }}</span>
                </div>
              </div>

              <!-- Difference note -->
              <div class="text-xxs text-secondary bg-gray-100 p-2 rounded-2 d-flex align-items-center gap-1.5">
                <i class="material-symbols-rounded text-info text-xs">info</i>
                <span>{{ timeDiffText }}</span>
              </div>
            </div>
          </li>

          <!-- Report Bug Button -->
          <li class="nav-item pe-3">
            <button 
              type="button" 
              class="btn-report-header" 
              @click="reportModalOpen = true"
              title="Report an issue or bug"
            >
              <i class="material-symbols-rounded">bug_report</i>
              <span class="d-md-inline d-none ms-1">Report</span>
            </button>
          </li>
          
          <!-- User dropdown - Vue controlled -->
          <li class="nav-item dropdown pe-3" ref="dropdownRef">
            <a
              href="#"
              class="nav-link text-body p-0 d-flex align-items-center"
              @click.prevent="toggleDropdown"
            >
              <i class="material-symbols-rounded">account_circle</i>
              <span class="d-sm-inline d-none ms-1 text-dark text-sm">{{ userName }}</span>
              <i class="material-symbols-rounded ms-1 text-sm">expand_more</i>
            </a>
            <ul 
              class="dropdown-menu dropdown-menu-end px-2 py-3" 
              :class="{ 'show': dropdownOpen }"
              :style="dropdownOpen ? 'display: block;' : ''"
            >
              <li>
                <a class="dropdown-item border-radius-md" href="/profile" @click="closeDropdown">
                  <i class="material-symbols-rounded me-2 text-sm">person</i>
                  Profile
                </a>
              </li>
              <li>
                <a class="dropdown-item border-radius-md" href="/settings" @click="closeDropdown">
                  <i class="material-symbols-rounded me-2 text-sm">settings</i>
                  Settings
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item border-radius-md text-danger" href="#" @click.prevent="logout">
                  <i class="material-symbols-rounded me-2 text-sm">logout</i>
                  Logout
                </a>
              </li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>
  
  <!-- Mobile sidebar overlay -->
  <div 
    v-if="sidebarOpen" 
    class="sidenav-overlay" 
    @click="closeSidebar"
  ></div>

  <!-- Bug Reporting Dialog -->
  <ReportBugModal 
    :isOpen="reportModalOpen" 
    @close="reportModalOpen = false" 
  />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import GlobalSearch from '@/Components/GlobalSearch.vue'
import ReportBugModal from '@/Components/ReportBugModal.vue'

const page = usePage()
const sidebarOpen = ref(false)
const dropdownOpen = ref(false)
const dropdownRef = ref(null)
const reportModalOpen = ref(false)

const userName = computed(() => {
  return page.props.auth?.user?.name || 'Admin'
})

const isClockOpen = ref(false)
const clockDropdownRef = ref(null)

// Server Clock State & Sync
const serverInfo = computed(() => page.props.server_info || {})
const serverTimezone = computed(() => serverInfo.value.timezone || 'UTC')
const serverTimezoneShort = computed(() => {
  const tz = serverTimezone.value
  if (tz === 'UTC') return 'UTC'
  const parts = tz.split('/')
  return parts[parts.length - 1] || tz
})

const currentTimestamp = ref(Date.now())
const serverTimeOffset = ref(0)
let clockInterval = null

const updateClock = () => {
  currentTimestamp.value = Date.now() + serverTimeOffset.value
}

// Calculate formatted strings
const serverTimeDisplay = computed(() => {
  try {
    const d = new Date(currentTimestamp.value)
    return d.toLocaleTimeString('en-US', {
      timeZone: serverTimezone.value === 'UTC' ? 'UTC' : serverTimezone.value,
      hour12: false,
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    })
  } catch (e) {
    return new Date(currentTimestamp.value).toTimeString().split(' ')[0]
  }
})

const serverDateDisplay = computed(() => {
  try {
    const d = new Date(currentTimestamp.value)
    return d.toLocaleDateString('en-US', {
      timeZone: serverTimezone.value === 'UTC' ? 'UTC' : serverTimezone.value,
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  } catch (e) {
    return ''
  }
})

const localTimezone = computed(() => {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Local'
})

const localTimezoneShort = computed(() => {
  const tz = localTimezone.value
  const parts = tz.split('/')
  return parts[parts.length - 1] || tz
})

const localTimeDisplay = computed(() => {
  return new Date().toLocaleTimeString('en-US', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
})

const localDateDisplay = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
})

const timeDiffText = computed(() => {
  try {
    const now = new Date()
    const serverDate = new Date(now.toLocaleString('en-US', { timeZone: serverTimezone.value }))
    const localDate = new Date(now.toLocaleString('en-US', { timeZone: localTimezone.value }))
    const diffMs = serverDate - localDate
    const diffHours = Math.round((diffMs / (1000 * 60 * 60)) * 10) / 10
    if (diffHours === 0) return 'Server time is in sync with your local time'
    if (diffHours > 0) return `Server is +${diffHours}h ahead of you`
    return `Server is ${Math.abs(diffHours)}h behind you`
  } catch (e) {
    return 'Timezone synchronized'
  }
})

const toggleDropdown = () => {
  dropdownOpen.value = !dropdownOpen.value
}

const closeDropdown = () => {
  dropdownOpen.value = false
}

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    closeDropdown()
  }
  if (clockDropdownRef.value && !clockDropdownRef.value.contains(event.target)) {
    isClockOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  window.closeMobileSidebar = closeSidebar

  // Sync initial server time offset
  if (serverInfo.value.timestamp) {
    const serverMs = serverInfo.value.timestamp * 1000
    serverTimeOffset.value = serverMs - Date.now()
  }

  updateClock()
  clockInterval = setInterval(updateClock, 1000)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  if (clockInterval) clearInterval(clockInterval)
})

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
  const sidenav = document.getElementById('sidenav-main')
  
  if (sidebarOpen.value) {
    sidenav?.classList.add('show-mobile')
    document.body.style.overflow = 'hidden'
  } else {
    sidenav?.classList.remove('show-mobile')
    document.body.style.overflow = ''
  }
}

const closeSidebar = () => {
  sidebarOpen.value = false
  const sidenav = document.getElementById('sidenav-main')
  sidenav?.classList.remove('show-mobile')
  document.body.style.overflow = ''
}

const logout = () => {
  closeDropdown()
  router.post('/logout')
}
</script>

<style scoped>
.btn-report-header {
  background: none;
  border: none;
  color: #4b5563;
  padding: 6px 12px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s;
  outline: none;
}

.btn-report-header:hover {
  background-color: #f0f2f5;
  color: #1f2937;
}

.btn-report-header i {
  font-size: 20px;
  color: #10b981;
}

.btn-report-header span {
  font-size: 0.85rem;
  font-weight: 600;
}

.dropdown-menu {
  min-width: 180px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  position: absolute;
  right: 0;
  top: 100%;
  margin-top: 8px;
}

.dropdown-item {
  padding: 10px 16px;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.dropdown-item:hover {
  background-color: #f0f2f5;
}

.dropdown-item.text-danger:hover {
  background-color: #fee2e2;
}

.sidenav-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1039;
}

/* Premium Server Time Capsule */
.server-time-capsule {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 4px 8px 4px 10px;
  border-radius: 9999px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  gap: 7px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;
}

.server-time-capsule:hover, .server-time-capsule.capsule-active {
  background: #f8fafc;
  border-color: #cbd5e1;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
}

.live-dot {
  width: 7px;
  height: 7px;
  background-color: #10b981;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
  animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  flex-shrink: 0;
}

.clock-icon {
  font-size: 14px;
  color: #64748b;
  flex-shrink: 0;
}

.time-text {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.78rem;
  font-weight: 600;
  color: #1e293b;
  letter-spacing: -0.01em;
  font-feature-settings: 'tnum';
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.tz-badge {
  background-color: #f1f5f9;
  color: #475569;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 9999px;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border: 1px solid #e2e8f0;
  line-height: 1.2;
}

.clock-popover {
  top: calc(100% + 8px);
  right: 0;
  width: 270px;
  z-index: 1060;
  border-color: #e2e8f0 !important;
  animation: popover-slide 0.15s ease-out;
}

@keyframes popover-slide {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse-ring {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.5;
    transform: scale(1.25);
  }
}

.badge-xs {
  font-size: 0.65rem !important;
  font-weight: 600;
  border-radius: 4px;
  padding: 2px 6px;
}
.bg-success-subtle {
  background-color: #dcfce7 !important;
  color: #15803d !important;
}
.bg-info-subtle {
  background-color: #e0f2fe !important;
  color: #0284c7 !important;
}
.border-info-subtle {
  border-color: #bae6fd !important;
}
</style>

<style>
/* Global styles for mobile sidebar */
@media (max-width: 1199.98px) {
  #sidenav-main {
    transform: translateX(-100%);
    transition: transform 0.3s ease-in-out;
  }
  
  #sidenav-main.show-mobile {
    transform: translateX(0);
    z-index: 1040;
  }
}
</style>
