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
        <!-- <img src="/assets/img/logo-ct-dark.png" width="26" height="26" /> -->
        <span class="ms-1 text-sm text-dark">Creative Tim</span>
      </a>
    </div>

    <hr class="horizontal dark mt-0 mb-2" />

    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
      <ul class="navbar-nav">

        <li class="nav-item">
          <Link href="/" class="nav-link active bg-gradient-dark text-white">
            <i class="material-symbols-rounded opacity-5">dashboard</i>
            <span class="nav-link-text ms-1">Dashboard</span>
          </Link>
        </li>

        <li class="nav-item">
          <a class="nav-link text-dark" href="#">
            <i class="material-symbols-rounded opacity-5">table_view</i>
            <span class="nav-link-text ms-1">Tables</span>
          </a>
        </li>

        <li class="nav-item mt-3">
          <h6
            class="ps-4 ms-2 text-uppercase text-xs text-dark font-weight-bolder opacity-5"
          >
            Account pages
          </h6>
        </li>

        <li class="nav-item">
          <a class="nav-link text-dark" href="#">
            <i class="material-symbols-rounded opacity-5">person</i>
            <span class="nav-link-text ms-1">Profile</span>
          </a>
        </li>

      </ul>
    </div>

    <div class="sidenav-footer position-absolute w-100 bottom-0">
      <div class="mx-3">
        <a class="btn btn-outline-dark mt-4 w-100" href="#">Documentation</a>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
</script>
