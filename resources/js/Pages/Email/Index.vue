<template>
  <MainLayout>
    <div class="container-fluid py-4">
      <div class="row justify-content-center">
        <div class="col-md-8">
          <div class="card text-center py-5">
            <div class="card-body">
              <div class="icon icon-shape bg-gradient-primary shadow-primary text-center border-radius-xl mx-auto mb-4"
                style="width: 80px; height: 80px;">
                <i class="material-symbols-rounded text-white" style="font-size: 40px; line-height: 80px;">email</i>
              </div>
              <h3 class="mb-2">Email Management</h3>
              <h5 class="text-secondary mb-4">Coming Soon</h5>
              <p class="text-muted mb-4">
                Complete email server management for your domains.<br>
                Create accounts, set up forwarders, and access webmail.
              </p>
              <div class="d-flex justify-content-center gap-5 flex-wrap">
                <div class="text-center">
                  <i class="material-symbols-rounded text-primary mb-2" style="font-size: 32px;">person</i>
                  <p class="text-sm mb-0 fw-bold">Email Accounts</p>
                </div>
                <div class="text-center">
                  <i class="material-symbols-rounded text-success mb-2" style="font-size: 32px;">dns</i>
                  <p class="text-sm mb-0 fw-bold">Custom Domains</p>
                </div>
                <div class="text-center">
                  <i class="material-symbols-rounded text-info mb-2" style="font-size: 32px;">forward_to_inbox</i>
                  <p class="text-sm mb-0 fw-bold">Mail Forwarders</p>
                </div>
              </div>
              <hr class="my-4">
              <p class="text-muted text-sm">
                <i class="material-symbols-rounded text-warning me-1">construction</i>
                We're working hard to bring you this feature. Stay tuned!
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
</script>
