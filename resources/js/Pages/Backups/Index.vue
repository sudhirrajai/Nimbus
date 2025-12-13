<template>
    <MainLayout>
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <div class="icon icon-shape bg-gradient-primary shadow-primary text-center border-radius-xl mx-auto mb-4"
                                style="width: 80px; height: 80px;">
                                <i class="material-symbols-rounded text-white"
                                    style="font-size: 40px; line-height: 80px;">cloud_upload</i>
                            </div>
                            <h3 class="mb-2">Backups</h3>
                            <h5 class="text-secondary mb-4">Coming Soon</h5>
                            <p class="text-muted mb-4">
                                Automated backup system for your databases and files.<br>
                                Schedule backups, store to cloud, and restore with one click.
                            </p>
                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <div class="text-center">
                                    <i class="material-symbols-rounded text-primary mb-2"
                                        style="font-size: 32px;">schedule</i>
                                    <p class="text-sm mb-0 fw-bold">Scheduled Backups</p>
                                </div>
                                <div class="text-center">
                                    <i class="material-symbols-rounded text-success mb-2"
                                        style="font-size: 32px;">cloud</i>
                                    <p class="text-sm mb-0 fw-bold">Cloud Storage</p>
                                </div>
                                <div class="text-center">
                                    <i class="material-symbols-rounded text-info mb-2"
                                        style="font-size: 32px;">restore</i>
                                    <p class="text-sm mb-0 fw-bold">One-Click Restore</p>
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
