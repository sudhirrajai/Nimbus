<template>
  <nav
    class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl"
    id="navbarBlur"
  >
    <div class="container-fluid py-1 px-3">
      <!-- Mobile menu toggle button -->
      <div class="d-xl-none">
        <a href="#" class="nav-link text-body p-0" @click.prevent="toggleSidebar">
          <i class="material-symbols-rounded text-dark">menu</i>
        </a>
      </div>

      <div
        class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4 show"
        id="navbar"
      >
        <div class="ms-md-auto pe-md-3 d-flex align-items-center">
          <div class="input-group input-group-outline">
            <label class="form-label">Type here...</label>
            <input type="text" class="form-control" />
          </div>
        </div>

        <ul class="navbar-nav d-flex align-items-center justify-content-end">
          <!-- User dropdown -->
          <li class="nav-item dropdown pe-3">
            <a
              href="#"
              class="nav-link text-body p-0 d-flex align-items-center"
              id="dropdownUser"
              data-bs-toggle="dropdown"
              aria-expanded="false"
            >
              <i class="material-symbols-rounded">account_circle</i>
              <span class="d-sm-inline d-none ms-1 text-dark text-sm">{{ userName }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end px-2 py-3" aria-labelledby="dropdownUser">
              <li>
                <a class="dropdown-item border-radius-md" href="/profile">
                  <i class="material-symbols-rounded me-2 text-sm">person</i>
                  Profile
                </a>
              </li>
              <li>
                <a class="dropdown-item border-radius-md" href="/settings">
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
</template>

<script setup>
import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const page = usePage()

const userName = computed(() => {
  return page.props.auth?.user?.name || 'Admin'
})

const toggleSidebar = () => {
  const body = document.body
  const sidenav = document.getElementById('sidenav-main')
  
  if (body.classList.contains('g-sidenav-pinned')) {
    body.classList.remove('g-sidenav-pinned')
    sidenav?.classList.remove('bg-white')
  } else {
    body.classList.add('g-sidenav-pinned')
    sidenav?.classList.add('bg-white')
  }
}

const logout = () => {
  router.post('/logout')
}
</script>

<style scoped>
.dropdown-menu {
  min-width: 180px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
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
</style>
