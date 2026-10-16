<template>
    <MainLayout>
        <Head title="System Updates" />
        <div class="container-fluid py-3 py-md-4 px-2 px-sm-3">
            
            <!-- Header Hero Card with Mesh Glow -->
            <div class="row mb-3 mb-md-4">
                <div class="col-12">
                    <div class="card header-hero-card border-0 overflow-hidden" :class="hasUpdate ? 'hero-update' : 'hero-normal'">
                        <div class="hero-glow-orb"></div>
                        <div class="card-body p-3 p-sm-4 position-relative z-index-1">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <div class="hero-icon-box shadow-lg">
                                            <i class="material-symbols-rounded">system_update</i>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <h4 class="text-white mb-0 font-weight-bolder tracking-tight">Nimbus OTA Updates</h4>
                                                <span class="badge bg-white-15 text-white border border-white-20 text-xxs px-2 py-0.5 rounded-pill font-monospace">
                                                    Release Channel: Stable
                                                </span>
                                            </div>
                                            <span class="text-white-60 text-xs d-block mt-0.5">Automated, signed & safe over-the-air panel distributions</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Pills -->
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                        <div class="hero-pill-badge">
                                            <span class="hero-pill-label">CURRENT</span>
                                            <span class="hero-pill-val font-monospace">v{{ activeVersion }}</span>
                                        </div>

                                        <div v-if="hasUpdate" class="hero-pill-badge hero-pill-update animate-pulse">
                                            <span class="pulsing-dot-emerald"></span>
                                            <span class="hero-pill-val font-monospace font-weight-bold">v{{ latestVersion }} Ready</span>
                                        </div>
                                        <div v-else class="hero-pill-badge hero-pill-success">
                                            <span class="pulsing-dot-green"></span>
                                            <span class="hero-pill-val">Up to date</span>
                                        </div>

                                        <div v-if="updateSource" class="hero-pill-badge hero-pill-muted">
                                            <i class="material-symbols-rounded text-xxs me-1 text-white-50">cloud_done</i>
                                            <span class="hero-pill-val text-white-70 text-truncate" style="max-width: 260px;">{{ updateSource }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="w-100 w-md-auto d-flex flex-column flex-sm-row gap-2">
                                    <button class="btn btn-hero-check btn-sm mb-0 px-3 py-2.5 w-100 w-sm-auto d-flex align-items-center justify-content-center gap-2 shadow-sm"
                                        @click="checkUpdates"
                                        :disabled="checking || updating">
                                        <span v-if="checking" class="spinner-border spinner-border-sm" style="width: 14px; height: 14px;"></span>
                                        <i v-else class="material-symbols-rounded text-base">sync</i>
                                        <span class="font-weight-bold">{{ checking ? 'Checking Release Feed...' : 'Check for Updates' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Safe Update & Zero-Downtime Guarantee Banner -->
            <div class="row mb-3 mb-md-4">
                <div class="col-12">
                    <div class="card safe-banner-card border-0 shadow-sm overflow-hidden">
                        <div class="card-body p-3 p-sm-3.5 d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="shield-badge shadow-sm flex-shrink-0">
                                    <i class="material-symbols-rounded text-success">verified_user</i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                        <h6 class="mb-0 text-dark font-weight-bold text-sm">Safe Update & Zero-Downtime Rollback Guarantee</h6>
                                        <span class="badge bg-success-subtle text-success text-xxs font-weight-bold border border-success-subtle px-2 py-0.5 rounded-pill">
                                            ACTIVE PROTECTION
                                        </span>
                                    </div>
                                    <p class="text-xs text-secondary mb-0 line-height-relaxed">
                                        Every update triggers a pre-update snapshot of your installation and backs up your SQLite database. If any command or post-update health check fails, an automated rollback immediately restores the server without disrupting running services.
                                    </p>
                                </div>
                            </div>

                            <!-- Safety Micro-badges -->
                            <div class="d-flex flex-wrap gap-1.5 flex-shrink-0 align-self-stretch align-self-lg-center justify-content-start justify-content-lg-end">
                                <span class="safety-chip">
                                    <i class="material-symbols-rounded text-success text-xs">camera_alt</i>
                                    <span>Pre-update Snapshot</span>
                                </span>
                                <span class="safety-chip">
                                    <i class="material-symbols-rounded text-success text-xs">database</i>
                                    <span>DB Auto-Backup</span>
                                </span>
                                <span class="safety-chip">
                                    <i class="material-symbols-rounded text-success text-xs">history</i>
                                    <span>Instant Rollback</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row g-3 g-md-4">
                
                <!-- Left Column: Version Information & Specs -->
                <div class="col-12 col-lg-6 mb-2 mb-lg-0">
                    <div class="card h-100 border-0 shadow-sm modern-panel-card">
                        <div class="card-header pb-3 pt-3.5 px-3 px-sm-4 border-bottom bg-transparent d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="header-icon-pill bg-primary-subtle text-primary">
                                    <i class="material-symbols-rounded text-sm">info</i>
                                </div>
                                <h6 class="mb-0 text-dark font-weight-bold">Version Information</h6>
                            </div>
                            <span v-if="hasUpdate" class="badge bg-success-subtle text-success text-xxs font-weight-bold px-2 py-1 rounded-pill border border-success-subtle">
                                <i class="material-symbols-rounded text-xxs me-1">bolt</i> Update Ready
                            </span>
                            <span v-else class="badge bg-light text-secondary text-xxs font-weight-bold px-2 py-1 rounded-pill border">
                                <i class="material-symbols-rounded text-xxs me-1 text-success">check</i> Installed
                            </span>
                        </div>

                        <div class="card-body p-3 p-sm-4 d-flex flex-column">
                            
                            <!-- Version Metrics Grid -->
                            <div class="row g-2 g-sm-3 mb-3">
                                <!-- Current Version Box -->
                                <div class="col-6">
                                    <div class="version-stat-card current-stat-card p-3 rounded-3 h-100 d-flex flex-column justify-content-between shadow-xs">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-xxs text-uppercase text-secondary font-weight-bold">Installed Version</span>
                                            <span class="badge bg-white text-dark text-xxs border shadow-xs px-1.5 py-0.5 rounded">Local</span>
                                        </div>
                                        <div class="mt-2">
                                            <h3 class="mb-0 font-weight-bolder text-dark font-monospace tracking-tight">v{{ activeVersion }}</h3>
                                            <span class="text-xxs text-muted d-block mt-0.5">Active runtime release</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Latest Available Box -->
                                <div class="col-6">
                                    <div class="version-stat-card p-3 rounded-3 h-100 d-flex flex-column justify-content-between shadow-xs"
                                         :class="hasUpdate ? 'latest-stat-card-update' : 'latest-stat-card-normal'">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-xxs text-uppercase font-weight-bold"
                                                  :class="hasUpdate ? 'text-success' : 'text-secondary'">Latest Available</span>
                                            <span v-if="hasUpdate" class="badge bg-success text-white text-xxs shadow-xs px-1.5 py-0.5 rounded">New</span>
                                            <span v-else class="badge bg-white text-secondary text-xxs border shadow-xs px-1.5 py-0.5 rounded">Latest</span>
                                        </div>
                                        <div class="mt-2">
                                            <h3 class="mb-0 font-weight-bolder font-monospace tracking-tight" :class="hasUpdate ? 'text-success' : 'text-dark'">
                                                v{{ latestVersion }}
                                                <i v-if="hasUpdate" class="material-symbols-rounded text-sm align-middle">arrow_upward</i>
                                            </h3>
                                            <span class="text-xxs d-block mt-0.5" :class="hasUpdate ? 'text-success font-weight-medium' : 'text-muted'">
                                                {{ hasUpdate ? 'Ready for one-click install' : 'You are running latest' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Metadata Spec Micro-Cards Grid -->
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <div class="spec-mini-card p-2.5 rounded-3 border bg-gray-50 h-100 d-flex flex-column justify-content-center">
                                        <span class="text-xxs text-muted text-uppercase font-weight-bold d-block">Release Date</span>
                                        <div class="d-flex align-items-center gap-1.5 mt-1">
                                            <i class="material-symbols-rounded text-xs text-secondary">calendar_today</i>
                                            <span class="text-xs text-dark font-weight-bold text-truncate">{{ releaseDate ? formatDate(releaseDate) : 'Today' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="spec-mini-card p-2.5 rounded-3 border bg-gray-50 h-100 d-flex flex-column justify-content-center">
                                        <span class="text-xxs text-muted text-uppercase font-weight-bold d-block">Integrity Guard</span>
                                        <div class="d-flex align-items-center gap-1.5 mt-1 text-success">
                                            <i class="material-symbols-rounded text-xs">verified</i>
                                            <span class="text-xs font-weight-bold text-truncate">Verified</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="spec-mini-card p-2.5 rounded-3 border bg-gray-50 h-100 d-flex flex-column justify-content-center">
                                        <span class="text-xxs text-muted text-uppercase font-weight-bold d-block">Engine Stability</span>
                                        <div class="d-flex align-items-center gap-1.5 mt-1">
                                            <span class="status-indicator-dot bg-success"></span>
                                            <span class="text-xs text-dark font-weight-bold text-truncate">Production</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SHA-256 Checksum Container -->
                            <div v-if="sha256" class="checksum-container mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1.5">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <i class="material-symbols-rounded text-xs text-primary">fingerprint</i>
                                        <span class="text-xxs text-uppercase text-secondary font-weight-bolder">Package SHA-256 Checksum</span>
                                    </div>
                                    <button class="btn btn-copy-hash btn-xs mb-0 d-flex align-items-center gap-1"
                                            @click="copySha256" :title="copiedSha ? 'Copied!' : 'Copy checksum'">
                                        <i class="material-symbols-rounded text-xs" :class="copiedSha ? 'text-success' : 'text-primary'">
                                            {{ copiedSha ? 'done_all' : 'content_copy' }}
                                        </i>
                                        <span class="text-xxs font-weight-bold" :class="copiedSha ? 'text-success' : 'text-secondary'">
                                            {{ copiedSha ? 'Copied!' : 'Copy Hash' }}
                                        </span>
                                    </button>
                                </div>
                                <div class="hash-code-block px-3 py-2.5 rounded-3 border d-flex align-items-center justify-content-between gap-2 shadow-xs">
                                    <code class="hash-text text-truncate">{{ sha256 }}</code>
                                    <span class="badge bg-secondary text-white text-xxs px-2 py-0.5 rounded font-monospace flex-shrink-0">SHA-256</span>
                                </div>
                            </div>

                            <!-- Status Alert & Action Area -->
                            <div class="mt-auto pt-2">
                                <!-- When Update Available -->
                                <div v-if="hasUpdate">
                                    <div class="update-ready-banner p-3 rounded-3 mb-3 d-flex align-items-center gap-3">
                                        <div class="update-icon-circle bg-success text-white shadow-sm flex-shrink-0">
                                            <i class="material-symbols-rounded text-base">celebration</i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="text-xs font-weight-bold text-dark mb-0.5">Nimbus v{{ latestVersion }} is ready for deployment</h6>
                                            <p class="text-xxs text-secondary mb-0">Automated pre-backup, zero downtime installation and rollback configured.</p>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button class="btn bg-gradient-success btn-lg mb-0 d-flex align-items-center justify-content-center gap-2 shadow-success"
                                            @click="openUpdateConfirm" :disabled="updating">
                                            <span v-if="updating" class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="material-symbols-rounded text-lg">download</i>
                                            <span class="font-weight-bold">{{ updating ? 'Applying Safe Update Pipeline...' : 'Update Now (Safe Mode)' }}</span>
                                        </button>
                                        <a v-if="releaseUrl" :href="releaseUrl" target="_blank"
                                            class="btn btn-outline-secondary btn-sm mb-0 d-flex align-items-center justify-content-center gap-1.5">
                                            <i class="material-symbols-rounded text-sm">archive</i>
                                            <span>Download Release Archive</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- When Up To Date -->
                                <div v-else class="up-to-date-card p-3 rounded-3 border d-flex align-items-center justify-content-between gap-3 shadow-xs">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="check-icon-circle bg-success-subtle text-success flex-shrink-0">
                                            <i class="material-symbols-rounded text-base">check_circle</i>
                                        </div>
                                        <div>
                                            <h6 class="text-xs font-weight-bold text-dark mb-0.5">You are running the latest installed version</h6>
                                            <p class="text-xxs text-secondary mb-0">Your panel is fully patched, isolated, and up to date with all release channels.</p>
                                        </div>
                                    </div>
                                    <button class="btn btn-outline-dark btn-xs mb-0 d-flex align-items-center gap-1 flex-shrink-0 px-2.5 py-1.5"
                                            @click="checkUpdates" :disabled="checking">
                                        <i class="material-symbols-rounded text-xs">sync</i>
                                        <span>Refresh</span>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Right Column: Categorized Release Notes & Changelog -->
                <div class="col-12 col-lg-6 mb-2 mb-lg-0">
                    <div class="card h-100 border-0 shadow-sm modern-panel-card">
                        <div class="card-header pb-3 pt-3.5 px-3 px-sm-4 border-bottom bg-transparent d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="header-icon-pill bg-info-subtle text-info">
                                    <i class="material-symbols-rounded text-sm">format_list_bulleted</i>
                                </div>
                                <h6 class="mb-0 text-dark font-weight-bold">Release Notes & Changelog</h6>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-dark text-white text-xxs font-monospace px-2 py-1 rounded-pill">
                                    v{{ latestVersion }}
                                </span>
                                <span v-if="changelog.length > 0" class="badge bg-gray-200 text-dark text-xxs font-weight-bold px-2 py-1 rounded-pill">
                                    {{ changelog.length }} changes
                                </span>
                            </div>
                        </div>

                        <div class="card-body p-3 p-sm-4">
                            <!-- Changelog Item List -->
                            <div v-if="changelog.length > 0" class="changelog-stream d-flex flex-column gap-2.5">
                                <div v-for="(item, index) in changelog" :key="index"
                                     class="changelog-card p-3 rounded-3 border d-flex align-items-start gap-3">
                                    
                                    <!-- Category Pill & Icon -->
                                    <div class="category-icon-box flex-shrink-0" :class="`cat-${getCategory(item).color}`">
                                        <i class="material-symbols-rounded text-sm">{{ getCategory(item).icon }}</i>
                                    </div>

                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge text-xxs font-weight-bold px-2 py-0.5 rounded-pill"
                                                  :class="`bg-${getCategory(item).color}-subtle text-${getCategory(item).color} border border-${getCategory(item).color}-subtle`">
                                                {{ getCategory(item).label }}
                                            </span>
                                            <span class="text-xxs text-muted">Item #{{ index + 1 }}</span>
                                        </div>
                                        <p class="text-xs text-dark font-weight-medium line-height-relaxed mb-0">
                                            {{ item }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty Changelog State -->
                            <div v-else class="text-center text-muted py-5 d-flex flex-column align-items-center justify-content-center">
                                <div class="p-3.5 rounded-circle bg-gray-100 mb-2">
                                    <i class="material-symbols-rounded text-secondary" style="font-size: 36px;">description</i>
                                </div>
                                <h6 class="text-sm text-secondary font-weight-bold mb-1">No Changelog Entries Loaded</h6>
                                <p class="text-xs text-muted mb-3">Click "Check for Updates" above to query release notes from Roook.</p>
                                <button class="btn btn-sm btn-outline-dark mb-0 d-flex align-items-center gap-1"
                                        @click="checkUpdates" :disabled="checking">
                                    <i class="material-symbols-rounded text-xs">sync</i>
                                    <span>Query Release Feed</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Terminal / Execution Log & Pipeline Progress -->
            <div v-if="updating || updateLog" class="row mt-3 mt-md-4">
                <div class="col-12">
                    
                    <!-- Visual Pipeline Stepper -->
                    <div class="pipeline-progress-bar p-3 rounded-3 bg-white border mb-3 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2.5">
                            <span class="text-xs font-weight-bold text-dark d-flex align-items-center gap-1.5">
                                <i class="material-symbols-rounded text-sm text-primary">linear_scale</i>
                                Live Pipeline Stages
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <span v-if="updateComplete" class="badge bg-success text-white text-xxs px-2.5 py-1 rounded-pill font-weight-bold d-flex align-items-center gap-1">
                                    <i class="material-symbols-rounded text-xxs">check_circle</i> Pipeline Finished
                                </span>
                                <span v-else-if="updating" class="badge bg-warning-subtle text-warning border border-warning-subtle text-xxs px-2.5 py-1 rounded-pill font-weight-bold d-flex align-items-center gap-1">
                                    <span class="spinner-border spinner-border-sm" style="width: 10px; height: 10px;"></span>
                                    {{ currentStepBadgeText }}
                                </span>
                                <button v-if="updateComplete" class="btn btn-dark btn-xs mb-0 d-flex align-items-center gap-1 px-2 py-1" @click="reloadPage">
                                    <i class="material-symbols-rounded text-xxs">refresh</i>
                                    <span>Reload Panel</span>
                                </button>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div v-for="step in pipelineSteps" :key="step.id" class="col-6 col-md-3">
                                <div class="pipeline-step-box p-2.5 rounded-3 text-center border" :class="step.statusClass">
                                    <div class="d-flex align-items-center justify-content-center gap-1.5">
                                        <i class="material-symbols-rounded text-xs">{{ step.icon }}</i>
                                        <span class="text-xxs font-weight-bold text-truncate">{{ step.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Terminal Card -->
                    <div class="card border-0 shadow-lg overflow-hidden terminal-card">
                        <div class="card-header bg-dark text-white py-2.5 px-3 d-flex justify-content-between align-items-center border-bottom border-dark">
                            <div class="d-flex align-items-center gap-2">
                                <div class="terminal-dots d-flex gap-1.5">
                                    <span class="dot dot-red"></span>
                                    <span class="dot dot-yellow"></span>
                                    <span class="dot dot-green"></span>
                                </div>
                                <span class="font-monospace text-xs text-white-70 ms-2 d-none d-sm-inline">
                                    safe-update-pipeline.sh &bull; v{{ latestVersion }}
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <span v-if="updating" class="badge bg-warning text-dark text-xxs d-inline-flex align-items-center px-2 py-1 rounded-pill font-weight-bold">
                                    <span class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span>
                                    {{ currentStepBadgeText }}
                                </span>
                                <span v-else-if="updateFailed" class="badge bg-danger text-white text-xxs d-inline-flex align-items-center px-2 py-1 rounded-pill font-weight-bold">
                                    <i class="material-symbols-rounded text-xxs me-1">warning</i> Rolled Back
                                </span>
                                <span v-else-if="updateComplete" class="badge bg-success text-white text-xxs d-inline-flex align-items-center px-2 py-1 rounded-pill font-weight-bold">
                                    <i class="material-symbols-rounded text-xxs me-1">check</i> Update Complete
                                </span>
                                <button class="btn btn-link btn-xs text-white-70 p-0 mb-0 shadow-none d-flex align-items-center gap-1" @click="copyLog" title="Copy Log">
                                    <i class="material-symbols-rounded text-xs">content_copy</i>
                                    <span class="d-none d-sm-inline text-xxs">Copy Log</span>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0 bg-darker">
                            <pre ref="terminalPre" class="update-log m-0 p-3 text-light font-monospace text-xxs text-sm-xs"
                                 style="max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{ updateLog || 'Initializing safe update pipeline runner...' }}</pre>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Safe Update Confirmation Modal -->
            <div v-if="showConfirmModal" class="confirm-modal-wrapper">
                <div class="modal-backdrop fade show" @click="showConfirmModal = false"
                     style="background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(6px); z-index: 1060;"></div>
                <div class="modal fade show d-block" tabindex="-1" style="z-index: 1070;" @keydown.esc="showConfirmModal = false">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
                        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden bg-white">
                            
                            <!-- Modal Header / Visual Banner -->
                            <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex align-items-start justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="confirm-icon-box shadow-sm">
                                        <i class="material-symbols-rounded text-success" style="font-size: 26px;">system_update_alt</i>
                                    </div>
                                    <div>
                                        <h5 class="modal-title font-weight-bolder text-dark mb-0.5" style="letter-spacing: -0.02em;">Ready to Update?</h5>
                                        <span class="text-xs text-muted">Safe OTA Deployment Pipeline</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-close text-secondary" @click="showConfirmModal = false"></button>
                            </div>

                            <!-- Modal Body -->
                            <div class="modal-body px-4 py-3">
                                
                                <!-- Version Transition Card -->
                                <div class="version-transition-card p-3 rounded-3 mb-3 border d-flex align-items-center justify-content-between">
                                    <div class="text-center flex-grow-1">
                                        <span class="text-xxs text-uppercase text-muted font-weight-bold d-block mb-0.5">Current</span>
                                        <span class="font-monospace font-weight-bold text-dark text-sm">v{{ activeVersion }}</span>
                                    </div>
                                    <div class="px-2 text-secondary d-flex align-items-center">
                                        <i class="material-symbols-rounded text-base text-success">arrow_forward</i>
                                    </div>
                                    <div class="text-center flex-grow-1">
                                        <span class="text-xxs text-uppercase text-success font-weight-bold d-block mb-0.5">Target</span>
                                        <span class="font-monospace font-weight-bolder text-success text-sm">v{{ latestVersion }}</span>
                                    </div>
                                </div>

                                <!-- Checklist of Safety Protections -->
                                <div class="safety-checklist d-flex flex-column gap-2 mb-3">
                                    <div class="d-flex align-items-center gap-2.5 text-xs text-secondary">
                                        <i class="material-symbols-rounded text-success text-sm flex-shrink-0">check_circle</i>
                                        <span><strong>Automatic Snapshot:</strong> Backs up whole panel & SQLite database</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2.5 text-xs text-secondary">
                                        <i class="material-symbols-rounded text-success text-sm flex-shrink-0">shield</i>
                                        <span><strong>Zero-Downtime:</strong> Running sites and isolated PHP pools continue serving</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2.5 text-xs text-secondary">
                                        <i class="material-symbols-rounded text-success text-sm flex-shrink-0">history</i>
                                        <span><strong>Safe Rollback:</strong> Instantly rolls back if post-update health check fails</span>
                                    </div>
                                </div>

                                <!-- Duration Notice -->
                                <div class="p-2.5 rounded-3 bg-warning-subtle text-warning-emphasis border border-warning-subtle d-flex align-items-center gap-2">
                                    <i class="material-symbols-rounded text-warning text-sm flex-shrink-0">timer</i>
                                    <span class="text-xxs">Pipeline takes ~30–60 seconds. Please do not close this window during the update.</span>
                                </div>

                            </div>

                            <!-- Modal Footer -->
                            <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light btn-sm mb-0 px-3 py-2 font-weight-bold" @click="showConfirmModal = false">
                                    Cancel
                                </button>
                                <button type="button" class="btn bg-gradient-success btn-sm mb-0 px-3 py-2 d-flex align-items-center gap-1.5 shadow-success font-weight-bold"
                                        @click="confirmAndUpdate" :disabled="updating">
                                    <span v-if="updating" class="spinner-border spinner-border-sm" style="width: 13px; height: 13px;"></span>
                                    <i v-else class="material-symbols-rounded text-sm">rocket_launch</i>
                                    <span>{{ updating ? 'Starting Pipeline...' : 'Start Safe Update' }}</span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Notifications -->
            <div class="position-fixed bottom-0 end-0 p-3 toast-container" style="z-index: 1080">
                <div class="toast align-items-center border-0 shadow-2xl rounded-3"
                    :class="toastType === 'success' ? 'bg-gradient-success text-white' : 'bg-gradient-danger text-white'"
                    :style="showToast ? 'display: block;' : 'display: none;'" role="alert">
                    <div class="d-flex p-1">
                        <div class="toast-body text-xs font-weight-medium d-flex align-items-center gap-2">
                            <i class="material-symbols-rounded text-sm">{{ toastType === 'success' ? 'check_circle' : 'error' }}</i>
                            <span>{{ toastMessage }}</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            @click="showToast = false"></button>
                    </div>
                </div>
            </div>

        </div>
    </MainLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import { formatDate } from '@/utils/date'

const props = defineProps({
    currentVersion: String
})

const activeVersion = ref(props.currentVersion)
const checking = ref(false)
const updating = ref(false)
const latestVersion = ref(props.currentVersion)
const changelog = ref([])
const commands = ref([])
const sha256 = ref(null)
const updateSource = ref('')
const releaseDate = ref(null)
const releaseUrl = ref(null)
const updateLog = ref('')
const updateComplete = ref(false)
const updateFailed = ref(false)
const terminalPre = ref(null)

let pollInterval = null

// Toast
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')

const hasUpdate = computed(() => {
    return latestVersion.value !== activeVersion.value &&
        compareVersions(latestVersion.value, activeVersion.value) > 0
})

onMounted(async () => {
    await checkUpdates()
    await pollUpdateStatus()
    if (updating.value) {
        pollInterval = setInterval(pollUpdateStatus, 1500)
    }
})

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval)
})

const checkUpdates = async () => {
    checking.value = true
    try {
        const response = await axios.get('/updates/check')
        if (response.data.success) {
            activeVersion.value = response.data.currentVersion || props.currentVersion
            latestVersion.value = response.data.latestVersion
            changelog.value = response.data.changelog || []
            commands.value = response.data.commands || []
            sha256.value = response.data.sha256 || null
            updateSource.value = response.data.source || 'Roook'
            releaseDate.value = response.data.releaseDate
            releaseUrl.value = response.data.releaseUrl
        }
    } catch (error) {
        notify('Failed to check for updates from Roook', 'error')
    } finally {
        checking.value = false
    }
}

const showConfirmModal = ref(false)

const openUpdateConfirm = () => {
    showConfirmModal.value = true
}

const confirmAndUpdate = async () => {
    showConfirmModal.value = false
    await startUpdate()
}

const startUpdate = async () => {
    updating.value = true
    updateLog.value = ''
    updateComplete.value = false
    updateFailed.value = false

    try {
        await axios.post('/updates/perform')
        if (pollInterval) clearInterval(pollInterval)
        pollInterval = setInterval(pollUpdateStatus, 1500)
    } catch (error) {
        notify('Failed to start update pipeline', 'error')
        updating.value = false
    }
}

const pollUpdateStatus = async () => {
    try {
        const response = await axios.get('/updates/status')
        if (response.data.log) {
            updateLog.value = response.data.log
            scrollToTerminalBottom()
        }

        const log = response.data.log || ''
        const isDone = response.data.status === 'done' || 
                       log.includes('Update completed successfully') ||
                       (log.includes('[Step 8/8]') && log.includes('health check passed'))

        const isFailed = response.data.status === 'failed' || 
                         log.includes('CRITICAL: UPDATE FAILED') || 
                         log.includes('AUTOMATIC ROLLBACK COMPLETE')

        if (response.data.status === 'running' && !isDone && !isFailed) {
            updating.value = true
        }

        if (isDone) {
            updating.value = false
            updateComplete.value = true
            updateFailed.value = false
            if (response.data.currentVersion) {
                activeVersion.value = response.data.currentVersion
            }
            if (pollInterval) {
                clearInterval(pollInterval)
                pollInterval = null
            }
            notify('Update completed successfully! All panel services online.', 'success')
            setTimeout(async () => {
                await checkUpdates()
            }, 1200)
        } else if (isFailed) {
            updating.value = false
            updateComplete.value = false
            updateFailed.value = true
            if (pollInterval) {
                clearInterval(pollInterval)
                pollInterval = null
            }
            notify('Update encountered an error and was safely rolled back. Your system remains online.', 'error')
        }
    } catch (error) {
        // Network reload blips during service restart are expected; keep polling
    }
}

const scrollToTerminalBottom = () => {
    nextTick(() => {
        if (terminalPre.value) {
            terminalPre.value.scrollTop = terminalPre.value.scrollHeight
        }
    })
}

const reloadPage = () => {
    window.location.reload()
}

// Visual Pipeline Stepper
const pipelineSteps = computed(() => {
    const log = updateLog.value || ''
    
    // Step 1: Snapshot Backup
    const s1Active = log.includes('[Step 1/8]')
    const s1Done = log.includes('[Step 2/8]') || log.includes('Snapshot safely secured')
    
    // Step 2: Download & Verify
    const s2Active = log.includes('[Step 2/8]') || log.includes('[Step 3/8]')
    const s2Done = log.includes('[Step 4/8]') || log.includes('Extracting release files')
    
    // Step 3: Extract, Sync & Migrations
    const s3Active = log.includes('[Step 4/8]') || log.includes('[Step 5/8]') || log.includes('[Step 6')
    const s3Done = log.includes('[Step 7/8]') || log.includes('Performing post-update health check')
    
    // Step 4: Health Check & Finalize
    const s4Active = log.includes('[Step 7/8]') || log.includes('[Step 8/8]')
    const s4Done = updateComplete.value || log.includes('Update completed successfully') || (log.includes('health check passed') && log.includes('[Step 8/8]'))

    return [
        {
            id: 1,
            name: '1. Backup Snapshot',
            icon: s1Done ? 'check_circle' : (s1Active ? 'sync' : 'camera_alt'),
            statusClass: s1Done ? 'step-completed' : (s1Active ? 'step-active' : 'step-pending')
        },
        {
            id: 2,
            name: '2. Download & Verify',
            icon: s2Done ? 'check_circle' : (s2Active ? 'sync' : 'cloud_download'),
            statusClass: s2Done ? 'step-completed' : (s2Active ? 'step-active' : 'step-pending')
        },
        {
            id: 3,
            name: '3. Sync & Migrations',
            icon: s3Done ? 'check_circle' : (s3Active ? 'sync' : 'integration_instructions'),
            statusClass: s3Done ? 'step-completed' : (s3Active ? 'step-active' : 'step-pending')
        },
        {
            id: 4,
            name: '4. Health & Finalize',
            icon: s4Done ? 'check_circle' : (s4Active ? 'sync' : 'verified_user'),
            statusClass: s4Done ? 'step-completed' : (s4Active ? 'step-active' : 'step-pending')
        }
    ]
})

const currentStepBadgeText = computed(() => {
    if (updateComplete.value) return 'Update Completed'
    if (updateFailed.value) return 'Rolled Back'
    if (!updating.value) return 'Ready'
    const log = updateLog.value || ''
    if (log.includes('[Step 8/8]')) return 'Finalizing & Versioning'
    if (log.includes('[Step 7/8]')) return 'Health Check'
    if (log.includes('[Step 6.')) {
        const matches = [...log.matchAll(/\[Step 6\.(\d+)\] Executing: ([^\n]+)/g)]
        if (matches && matches.length > 0) {
            const last = matches[matches.length - 1][2]
            return last.slice(0, 26)
        }
        return 'Manifest Commands'
    }
    if (log.includes('[Step 5/8]')) return 'Syncing Files'
    if (log.includes('[Step 4/8]')) return 'Extracting Files'
    if (log.includes('[Step 3/8]')) return 'Verifying SHA-256'
    if (log.includes('[Step 2/8]')) return 'Downloading Release'
    if (log.includes('[Step 1/8]')) return 'Creating Snapshot'
    return 'In Progress'
})

const compareVersions = (v1, v2) => {
    const parts1 = (v1 || '').split('.').map(Number)
    const parts2 = (v2 || '').split('.').map(Number)

    for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
        const p1 = parts1[i] || 0
        const p2 = parts2[i] || 0
        if (p1 > p2) return 1
        if (p1 < p2) return -1
    }
    return 0
}

const notify = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => showToast.value = false, 5000)
}

const copiedSha = ref(false)
const copySha256 = () => {
    if (!sha256.value) return
    navigator.clipboard.writeText(sha256.value)
    copiedSha.value = true
    notify('SHA-256 Checksum copied to clipboard', 'success')
    setTimeout(() => { copiedSha.value = false }, 2500)
}

const copyLog = () => {
    if (!updateLog.value) return
    navigator.clipboard.writeText(updateLog.value)
    notify('Execution log copied to clipboard', 'success')
}

// Categorize changelog entry
const getCategory = (text) => {
    const lower = (text || '').toLowerCase()
    if (lower.includes('security') || lower.includes('ftp') || lower.includes('permission') || lower.includes('guard') || lower.includes('isolation') || lower.includes('unauthorized') || lower.includes('scop')) {
        return { label: 'Security & Access', icon: 'shield_lock', color: 'danger' }
    }
    if (lower.includes('ui') || lower.includes('ux') || lower.includes('responsive') || lower.includes('mobile') || lower.includes('sidebar') || lower.includes('navbar') || lower.includes('glassmorphism') || lower.includes('drawer') || lower.includes('streamlined') || lower.includes('feed')) {
        return { label: 'UI / UX Design', icon: 'palette', color: 'primary' }
    }
    if (lower.includes('terminal') || lower.includes('hash') || lower.includes('checksum') || lower.includes('fast') || lower.includes('speed') || lower.includes('optimize') || lower.includes('performance') || lower.includes('execution')) {
        return { label: 'Engine & Tooling', icon: 'bolt', color: 'warning' }
    }
    return { label: 'Feature Release', icon: 'auto_awesome', color: 'success' }
}
</script>

<style scoped>
/* Hero Header Styling */
.header-hero-card {
    border-radius: 1.25rem;
    box-shadow: 0 12px 36px rgba(15, 23, 42, 0.16);
    position: relative;
}

.hero-normal {
    background: radial-gradient(circle at 10% 15%, #1e293b 0%, #0f172a 85%);
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
}

.hero-update {
    background: radial-gradient(circle at 15% 20%, #065f46 0%, #047857 35%, #0f172a 90%);
    border: 1px solid rgba(16, 185, 129, 0.25) !important;
}

.hero-glow-orb {
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
    pointer-events: none;
}

.hero-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
}

.hero-icon-box i {
    font-size: 26px;
}

/* Hero Pill Badges */
.hero-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.14);
    font-size: 0.72rem;
    color: #fff;
}

.hero-pill-label {
    text-transform: uppercase;
    font-size: 0.62rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.6);
    letter-spacing: 0.5px;
}

.hero-pill-val {
    font-weight: 600;
}

.hero-pill-success {
    background: rgba(16, 185, 129, 0.16);
    border-color: rgba(16, 185, 129, 0.35);
    color: #a7f3d0;
}

.hero-pill-update {
    background: rgba(5, 150, 105, 0.3);
    border-color: rgba(110, 231, 183, 0.4);
    color: #d1fae5;
}

.pulsing-dot-green {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background-color: #10b981;
    box-shadow: 0 0 8px #10b981;
    animation: pulse 2s infinite;
}

.pulsing-dot-emerald {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #34d399;
    box-shadow: 0 0 10px #34d399;
    animation: pulse 1.5s infinite;
}

/* Button Hero Check */
.btn-hero-check {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    transition: all 0.2s ease;
}

.btn-hero-check:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.22);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-1px);
}

/* Safe Banner Card */
.safe-banner-card {
    border-radius: 1rem;
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border: 1px solid #bbf7d0 !important;
}

.shield-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #dcfce7;
    border: 1px solid #86efac;
    display: flex;
    align-items: center;
    justify-content: center;
}

.shield-badge i {
    font-size: 26px;
}

.safety-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid #bbf7d0;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #166534;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

/* Modern Panel Card */
.modern-panel-card {
    border-radius: 1rem;
    background: #fff;
    border: 1px solid #e2e8f0 !important;
}

.header-icon-pill {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Stat Cards */
.version-stat-card {
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0;
}

.current-stat-card {
    background: #f8fafc;
}

.latest-stat-card-normal {
    background: #f8fafc;
}

.latest-stat-card-update {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-color: #86efac !important;
}

/* Spec Mini Cards */
.spec-mini-card {
    background: #f8fafc;
    border-color: #e2e8f0 !important;
    transition: all 0.15s ease;
}

.spec-mini-card:hover {
    border-color: #cbd5e1 !important;
}

.status-indicator-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

/* Checksum Container */
.hash-code-block {
    background: #0f172a;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #e2e8f0;
}

.hash-text {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.74rem;
    color: #38bdf8;
    letter-spacing: 0.4px;
}

.btn-copy-hash {
    background: transparent;
    border: none;
    padding: 2px 4px;
    cursor: pointer;
}

.btn-copy-hash:hover {
    opacity: 0.8;
}

/* Up to Date & Update Ready Callouts */
.up-to-date-card {
    background: #f8fafc;
    border-color: #e2e8f0 !important;
}

.check-icon-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.update-ready-banner {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #a7f3d0;
}

.update-icon-circle {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Changelog Stream */
.changelog-card {
    background: #fff;
    border-color: #e2e8f0 !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.changelog-card:hover {
    transform: translateX(4px);
    border-color: #cbd5e1 !important;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.category-icon-box {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cat-danger {
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fee2e2;
}

.cat-primary {
    background: #eff6ff;
    color: #3b82f6;
    border: 1px solid #dbeafe;
}

.cat-warning {
    background: #fffbeb;
    color: #f59e0b;
    border: 1px solid #fef3c7;
}

.cat-success {
    background: #f0fdf4;
    color: #10b981;
    border: 1px solid #dcfce7;
}

/* Visual Pipeline Progress Stepper */
.pipeline-progress-bar {
    border-color: #e2e8f0 !important;
}

.pipeline-step-box {
    transition: all 0.2s ease;
}

.step-pending {
    background: #f8fafc;
    border-color: #e2e8f0 !important;
    color: #94a3b8;
}

.step-active {
    background: #fffbeb;
    border-color: #fde68a !important;
    color: #d97706;
    box-shadow: 0 0 10px rgba(245, 158, 11, 0.2);
    animation: pulse 1.8s infinite;
}

.step-completed {
    background: #f0fdf4;
    border-color: #bbf7d0 !important;
    color: #166534;
}

/* Terminal Card */
.terminal-card {
    border-radius: 1rem;
}

.bg-darker {
    background-color: #0b0f19;
}

.terminal-dots .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.dot-red { background-color: #ef4444; }
.dot-yellow { background-color: #f59e0b; }
.dot-green { background-color: #10b981; }

.update-log::-webkit-scrollbar {
    width: 6px;
}

.update-log::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

/* General Utilities */
.text-white-60 { color: rgba(255, 255, 255, 0.6); }
.text-white-70 { color: rgba(255, 255, 255, 0.7); }
.bg-white-15 { background-color: rgba(255, 255, 255, 0.15); }
.border-white-20 { border-color: rgba(255, 255, 255, 0.2) !important; }

.shadow-xs { box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
.shadow-inner { box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.25); }

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.05); }
}

@media (max-width: 575.98px) {
    .header-hero-card .card-body {
        padding: 1.15rem 1rem !important;
    }

    .hero-icon-box {
        width: 40px;
        height: 40px;
    }

    .hero-icon-box i {
        font-size: 22px;
    }

    .shield-badge {
        width: 38px;
        height: 38px;
    }

    .shield-badge i {
        font-size: 22px;
    }
}

/* Confirmation Modal Enhancements */
.confirm-icon-box {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: #ecfdf5;
    border: 1px solid #d1fae5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.version-transition-card {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.confirm-modal-wrapper .modal-content {
    animation: modalScaleIn 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalScaleIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(8px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
</style>
