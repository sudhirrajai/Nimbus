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
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  window.closeMobileSidebar = closeSidebar
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
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
