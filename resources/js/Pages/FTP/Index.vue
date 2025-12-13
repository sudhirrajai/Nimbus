<template>
    <MainLayout>
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <div class="icon icon-shape bg-gradient-info shadow-info text-center border-radius-xl mx-auto mb-4"
                                style="width: 80px; height: 80px;">
                                <i class="material-symbols-rounded text-white"
                                    style="font-size: 40px; line-height: 80px;">folder_shared</i>
                            </div>
                            <h3 class="mb-2">FTP Accounts</h3>
                            <h5 class="text-secondary mb-4">Coming Soon</h5>
                            <p class="text-muted mb-4">
                                Create and manage FTP accounts for your domains.<br>
                                Secure file access with isolated user permissions.
                            </p>
                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <div class="text-center">
                                    <i class="material-symbols-rounded text-primary mb-2"
                                        style="font-size: 32px;">person_add</i>
                                    <p class="text-sm mb-0 fw-bold">Create Users</p>
                                </div>
                                <div class="text-center">
                                    <i class="material-symbols-rounded text-success mb-2"
                                        style="font-size: 32px;">lock</i>
                                    <p class="text-sm mb-0 fw-bold">Secure Access</p>
                                </div>
                                <div class="text-center">
                                    <i class="material-symbols-rounded text-info mb-2"
                                        style="font-size: 32px;">folder</i>
                                    <p class="text-sm mb-0 fw-bold">Directory Limits</p>
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
