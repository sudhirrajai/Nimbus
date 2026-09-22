<template>
    <MainLayout>
        <div class="container-fluid py-4 bg-gray-100 min-vh-100">
            <!-- Toast Notification -->
            <div v-if="toast.show" class="position-fixed top-3 end-3 z-index-modal" style="z-index: 1060;">
                <div class="toast show align-items-center text-white border-0 shadow-lg" :class="'bg-gradient-' + toast.type" role="alert">
                    <div class="d-flex">
                        <div class="toast-body d-flex align-items-center">
                            <i class="material-symbols-rounded me-2 text-md">{{ toast.icon }}</i>
                            <span>{{ toast.message }}</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toast.show = false"></button>
                    </div>
                </div>
            </div>

            <!-- Header Section -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h3 class="font-weight-bolder mb-0">Redis Manager</h3>
                        <span v-if="isInstalled && status === 'running'" class="badge badge-sm bg-gradient-success d-flex align-items-center gap-1">
                            <span class="pulse-dot"></span> Active
                        </span>
                        <span v-else-if="isInstalled" class="badge badge-sm bg-gradient-danger">Stopped</span>
                        <span v-else class="badge badge-sm bg-gradient-warning">Not Installed</span>
                    </div>
                    <p class="text-sm mb-0 text-secondary">High-speed in-memory data store, cache accelerator, and queue engine.</p>
                </div>

                <!-- Global Actions -->
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button v-if="!isInstalled" @click="installRedis" :disabled="installing" class="btn bg-gradient-primary btn-sm mb-0 shadow-sm d-flex align-items-center gap-1">
                        <span v-if="installing" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="material-symbols-rounded text-sm">download</i>
                        {{ installing ? 'Installing Redis...' : '1-Click Install Redis' }}
                    </button>

                    <template v-else>
                        <button @click="fetchStatus" :disabled="loadingStatus" class="btn btn-outline-dark btn-sm mb-0 shadow-sm d-flex align-items-center gap-1" title="Refresh metrics">
                            <i class="material-symbols-rounded text-sm" :class="{ 'spin-anim': loadingStatus }">sync</i> Refresh
                        </button>
                        <button v-if="status !== 'running'" @click="handleServiceAction('start')" :disabled="actionLoading" class="btn bg-gradient-success btn-sm mb-0 shadow-sm d-flex align-items-center gap-1">
                            <i class="material-symbols-rounded text-sm">play_arrow</i> Start Service
                        </button>
                        <button v-if="status === 'running'" @click="handleServiceAction('restart')" :disabled="actionLoading" class="btn bg-gradient-dark btn-sm mb-0 shadow-sm d-flex align-items-center gap-1">
                            <i class="material-symbols-rounded text-sm">restart_alt</i> Restart
                        </button>
                        <button v-if="status === 'running'" @click="handleServiceAction('stop')" :disabled="actionLoading" class="btn btn-outline-danger btn-sm mb-0 shadow-sm d-flex align-items-center gap-1">
                            <i class="material-symbols-rounded text-sm">stop</i> Stop
                        </button>
                    </template>
                </div>
            </div>

            <!-- NOT INSTALLED CALLOUT BANNER -->
            <div v-if="!isInstalled" class="card shadow-sm border-radius-lg border-0 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-white position-relative">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="icon icon-shape bg-gradient-primary shadow-primary text-center border-radius-md" style="width: 54px; height: 54px; display: grid; place-items: center;">
                                    <i class="material-symbols-rounded text-white" style="font-size: 32px;">memory</i>
                                </div>
                                <div>
                                    <h5 class="font-weight-bolder mb-1">Supercharge Your Apps with Redis</h5>
                                    <p class="text-sm text-secondary mb-0">Accelerate page loading times up to 10x by caching database queries, user sessions, and queue workers.</p>
                                </div>
                            </div>
                            <div class="row g-3 pt-2">
                                <div class="col-sm-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-symbols-rounded text-success text-sm">check_circle</i>
                                        <span class="text-xs font-weight-bold text-dark">Automated redis-server</span>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-symbols-rounded text-success text-sm">check_circle</i>
                                        <span class="text-xs font-weight-bold text-dark">Multi-PHP 8.1 - 8.4 Extensions</span>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-symbols-rounded text-success text-sm">check_circle</i>
                                        <span class="text-xs font-weight-bold text-dark">Secured Localhost Binding</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                            <button @click="installRedis" :disabled="installing" class="btn bg-gradient-primary btn-lg mb-0 shadow-md px-4">
                                <span v-if="installing" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="material-symbols-rounded me-2">bolt</i>
                                {{ installing ? 'Installing Stack...' : 'Install Redis Now' }}
                            </button>
                        </div>
                    </div>

                    <!-- Installation Log Output -->
                    <div v-if="installLog" class="mt-4 p-3 bg-dark text-white border-radius-md" style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                        <pre class="mb-0 text-white" style="white-space: pre-wrap;">{{ installLog }}</pre>
                    </div>
                </div>
            </div>

            <!-- MAIN REDIS DASHBOARD (When Installed) -->
            <template v-if="isInstalled">
                <!-- Metrics Grid -->
                <div class="row g-3 mb-4">
                    <!-- Memory Usage Card -->
                    <div class="col-xl-3 col-sm-6">
                        <div class="card shadow-sm border-radius-lg border-0 h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs mb-1 text-uppercase font-weight-bold text-secondary">Memory Allocation</p>
                                        <h5 class="font-weight-bolder mb-0 text-dark">
                                            {{ info.memory?.used_human || '0 B' }}
                                            <span class="text-xs font-weight-normal text-secondary">/ {{ info.memory?.max_human || 'Unlimited' }}</span>
                                        </h5>
                                    </div>
                                    <div class="icon icon-shape bg-gradient-info shadow-info text-center border-radius-md" style="width: 44px; height: 44px; display: grid; place-items: center;">
                                        <i class="material-symbols-rounded text-white" style="font-size: 24px;">pie_chart</i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-xxs font-weight-bold text-secondary">Usage</span>
                                        <span class="text-xxs font-weight-bolder" :class="memoryUsageClass">{{ info.memory?.percentage || 0 }}%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar" :class="memoryBarClass" role="progressbar" :style="{ width: (info.memory?.percentage || 0) + '%' }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hit Rate Ratio -->
                    <div class="col-xl-3 col-sm-6">
                        <div class="card shadow-sm border-radius-lg border-0 h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs mb-1 text-uppercase font-weight-bold text-secondary">Cache Hit Ratio</p>
                                        <h5 class="font-weight-bolder mb-0 text-success">
                                            {{ info.stats?.hit_rate || 100 }}%
                                        </h5>
                                        <p class="text-xxs text-secondary mb-0 mt-1">
                                            <span class="text-success font-weight-bold">{{ formatNumber(info.stats?.hits || 0) }}</span> hits vs 
                                            <span class="text-secondary">{{ formatNumber(info.stats?.misses || 0) }}</span> misses
                                        </p>
                                    </div>
                                    <div class="icon icon-shape bg-gradient-success shadow-success text-center border-radius-md" style="width: 44px; height: 44px; display: grid; place-items: center;">
                                        <i class="material-symbols-rounded text-white" style="font-size: 24px;">trending_up</i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-gradient-success" role="progressbar" :style="{ width: (info.stats?.hit_rate || 100) + '%' }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Operations & Commands -->
                    <div class="col-xl-3 col-sm-6">
                        <div class="card shadow-sm border-radius-lg border-0 h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs mb-1 text-uppercase font-weight-bold text-secondary">Throughput (Ops/sec)</p>
                                        <h5 class="font-weight-bolder mb-0 text-dark">
                                            {{ info.stats?.ops_per_sec || 0 }} <span class="text-xs font-weight-normal text-secondary">ops/s</span>
                                        </h5>
                                        <p class="text-xxs text-secondary mb-0 mt-1">
                                            Total processed: <b>{{ formatNumber(info.stats?.total_commands || 0) }}</b>
                                        </p>
                                    </div>
                                    <div class="icon icon-shape bg-gradient-warning shadow-warning text-center border-radius-md" style="width: 44px; height: 44px; display: grid; place-items: center;">
                                        <i class="material-symbols-rounded text-white" style="font-size: 24px;">speed</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Clients & Keys -->
                    <div class="col-xl-3 col-sm-6">
                        <div class="card shadow-sm border-radius-lg border-0 h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-xs mb-1 text-uppercase font-weight-bold text-secondary">Connected Clients</p>
                                        <h5 class="font-weight-bolder mb-0 text-dark">
                                            {{ info.clients?.connected || 0 }}
                                        </h5>
                                        <p class="text-xxs text-secondary mb-0 mt-1">
                                            Keyspace: <b>{{ formatNumber(info.total_keys || 0) }}</b> total keys
                                        </p>
                                    </div>
                                    <div class="icon icon-shape bg-gradient-dark shadow-dark text-center border-radius-md" style="width: 44px; height: 44px; display: grid; place-items: center;">
                                        <i class="material-symbols-rounded text-white" style="font-size: 24px;">hub</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="nav-wrapper position-relative end-0 mb-4">
                    <ul class="nav nav-pills nav-fill p-1 bg-white border-radius-lg shadow-sm" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link mb-0 px-0 py-2 cursor-pointer font-weight-bold" :class="{ 'active bg-gradient-dark text-white shadow': activeTab === 'overview' }" @click="activeTab = 'overview'">
                                <i class="material-symbols-rounded text-sm me-1">dashboard</i> Overview & Health
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link mb-0 px-0 py-2 cursor-pointer font-weight-bold" :class="{ 'active bg-gradient-dark text-white shadow': activeTab === 'memory' }" @click="activeTab = 'memory'">
                                <i class="material-symbols-rounded text-sm me-1">memory</i> Memory & Eviction
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link mb-0 px-0 py-2 cursor-pointer font-weight-bold" :class="{ 'active bg-gradient-dark text-white shadow': activeTab === 'security' }" @click="activeTab = 'security'">
                                <i class="material-symbols-rounded text-sm me-1">shield</i> Security & Password
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link mb-0 px-0 py-2 cursor-pointer font-weight-bold" :class="{ 'active bg-gradient-dark text-white shadow': activeTab === 'keys' }" @click="onKeysTabClick">
                                <i class="material-symbols-rounded text-sm me-1">key</i> Key Browser & Flush
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link mb-0 px-0 py-2 cursor-pointer font-weight-bold" :class="{ 'active bg-gradient-dark text-white shadow': activeTab === 'slowlog' }" @click="onSlowlogTabClick">
                                <i class="material-symbols-rounded text-sm me-1">history</i> SlowLog & Clients
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- ════ TAB 1: OVERVIEW & HEALTH ════ -->
                <div v-if="activeTab === 'overview'" class="row g-4">
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-radius-lg border-0">
                            <div class="card-header pb-0 p-3 bg-white">
                                <h6 class="mb-0 font-weight-bolder text-dark">Server Instance Details</h6>
                            </div>
                            <div class="card-body p-3">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                        <span class="text-xs text-secondary font-weight-bold">Redis Version</span>
                                        <span class="text-xs text-dark font-weight-bolder">{{ info.version }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                        <span class="text-xs text-secondary font-weight-bold">Running Mode</span>
                                        <span class="text-xs text-dark font-weight-bolder text-capitalize">{{ info.mode }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                        <span class="text-xs text-secondary font-weight-bold">Process ID (PID)</span>
                                        <span class="text-xs text-dark font-weight-bolder">{{ info.pid || 'N/A' }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                        <span class="text-xs text-secondary font-weight-bold">TCP Port</span>
                                        <span class="text-xs text-dark font-weight-bolder">{{ info.port || 6379 }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                        <span class="text-xs text-secondary font-weight-bold">System Uptime</span>
                                        <span class="text-xs text-dark font-weight-bolder">{{ info.uptime_human || '0s' }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                        <span class="text-xs text-secondary font-weight-bold">Peak Memory Reached</span>
                                        <span class="text-xs text-dark font-weight-bolder">{{ info.memory?.peak_human || '0 B' }}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                                        <span class="text-xs text-secondary font-weight-bold">Fragmentation Ratio</span>
                                        <span class="text-xs text-dark font-weight-bolder">{{ info.memory?.fragmentation_ratio || 1.0 }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <!-- Keyspace Databases -->
                        <div class="card shadow-sm border-radius-lg border-0 mb-4">
                            <div class="card-header pb-0 p-3 bg-white">
                                <h6 class="mb-0 font-weight-bolder text-dark">Active Keyspace Databases</h6>
                            </div>
                            <div class="card-body p-3">
                                <div v-if="!info.keyspace || Object.keys(info.keyspace).length === 0" class="text-center py-4">
                                    <i class="material-symbols-rounded text-secondary" style="font-size: 36px;">database</i>
                                    <p class="text-xs text-secondary mb-0 mt-2">No keys stored yet in any database.</p>
                                </div>
                                <div v-else class="table-responsive">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 px-2">DB</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 px-2">Keys</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 px-2">Expires</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 px-2 text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(dbData, dbKey) in info.keyspace" :key="dbKey">
                                                <td class="px-2 py-2">
                                                    <span class="badge badge-sm bg-gradient-dark font-weight-bold">{{ dbKey }}</span>
                                                </td>
                                                <td class="px-2 py-2 text-xs font-weight-bold text-dark">{{ dbData.keys }}</td>
                                                <td class="px-2 py-2 text-xs text-secondary">{{ dbData.expires }}</td>
                                                <td class="px-2 py-2 text-end">
                                                    <button @click="quickBrowseDb(dbData.db)" class="btn btn-link text-primary text-xs p-0 mb-0 font-weight-bold me-2">Browse</button>
                                                    <button @click="confirmFlushDb(dbData.db)" class="btn btn-link text-danger text-xs p-0 mb-0 font-weight-bold">Flush</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions Card -->
                        <div class="card shadow-sm border-radius-lg border-0">
                            <div class="card-header pb-0 p-3 bg-white">
                                <h6 class="mb-0 font-weight-bolder text-dark">Quick Maintenance</h6>
                            </div>
                            <div class="card-body p-3 d-flex flex-column gap-2">
                                <button @click="openFlushPatternModal('laravel_cache:*')" class="btn btn-outline-dark btn-sm mb-0 text-start d-flex justify-content-between align-items-center">
                                    <span>Flush Laravel Query Cache <code class="text-xxs text-primary ms-1">laravel_cache:*</code></span>
                                    <i class="material-symbols-rounded text-sm">cleaning_services</i>
                                </button>
                                <button @click="confirmFlushAll" class="btn btn-outline-danger btn-sm mb-0 text-start d-flex justify-content-between align-items-center">
                                    <span>Flush Entire Redis <code class="text-xxs text-danger ms-1">FLUSHALL</code></span>
                                    <i class="material-symbols-rounded text-sm">delete_forever</i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════ TAB 2: MEMORY ALLOCATION & EVICTION ════ -->
                <div v-if="activeTab === 'memory'" class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-radius-lg border-0">
                            <div class="card-header p-3 bg-white border-bottom">
                                <h6 class="mb-0 font-weight-bolder text-dark">Memory Limit & Eviction Policy</h6>
                                <p class="text-xs text-secondary mb-0">Control maximum RAM consumption and what happens when memory fills up.</p>
                            </div>
                            <div class="card-body p-4">
                                <!-- Memory Presets -->
                                <div class="mb-4">
                                    <label class="form-label text-xs font-weight-bolder text-uppercase text-secondary">Quick Memory Limit Presets</label>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <button v-for="preset in memoryPresets" :key="preset.val" 
                                                @click="memoryForm.maxmemory = preset.val" 
                                                type="button"
                                                class="btn btn-sm mb-0" 
                                                :class="memoryForm.maxmemory === preset.val ? 'btn-dark' : 'btn-outline-dark'">
                                            {{ preset.label }}
                                        </button>
                                    </div>
                                    <div class="input-group input-group-outline mb-2">
                                        <input v-model="memoryForm.maxmemory" type="text" class="form-control" placeholder="e.g. 256mb, 512mb, 1gb, 2gb, or 0 for unlimited">
                                    </div>
                                    <span class="text-xxs text-secondary">Set to <code>0</code> for unlimited (not recommended on production VPS). Applied immediately without restarting Redis.</span>
                                </div>

                                <hr class="horizontal dark my-4">

                                <!-- Eviction Policy -->
                                <div class="mb-4">
                                    <label class="form-label text-xs font-weight-bolder text-uppercase text-secondary">Maxmemory Eviction Policy</label>
                                    <div class="row g-2">
                                        <div v-for="policy in evictionPolicies" :key="policy.id" class="col-md-6">
                                            <div class="p-3 border border-radius-md cursor-pointer h-100 transition-all"
                                                 :class="{ 'border-primary bg-gray-50 shadow-sm': memoryForm.maxmemory_policy === policy.id }"
                                                 @click="memoryForm.maxmemory_policy = policy.id">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <span class="text-xs font-weight-bold font-monospace text-dark">{{ policy.id }}</span>
                                                    <span v-if="policy.recommended" class="badge badge-sm bg-gradient-success text-xxs">Recommended</span>
                                                </div>
                                                <p class="text-xxs text-secondary mb-0">{{ policy.desc }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button @click="saveMemoryConfig" :disabled="savingConfig" class="btn bg-gradient-dark mb-0 shadow-sm px-4">
                                        <span v-if="savingConfig" class="spinner-border spinner-border-sm me-2"></span>
                                        {{ savingConfig ? 'Saving Settings...' : 'Save & Apply Instantly' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════ TAB 3: SECURITY & NETWORK ════ -->
                <div v-if="activeTab === 'security'" class="row justify-content-center">
                    <div class="col-lg-8">
                        <!-- Security Audit Card -->
                        <div class="card shadow-sm border-radius-lg border-0 mb-4">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="icon icon-shape text-center border-radius-md" :class="isLocalhostBound ? 'bg-gradient-success text-white' : 'bg-gradient-danger text-white'" style="width: 44px; height: 44px; display: grid; place-items: center;">
                                        <i class="material-symbols-rounded" style="font-size: 24px;">{{ isLocalhostBound ? 'verified_user' : 'warning' }}</i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-weight-bolder text-dark">{{ isLocalhostBound ? 'Secure Binding Active' : 'Warning: Public Interface Detected' }}</h6>
                                        <p class="text-xs text-secondary mb-0">
                                            {{ isLocalhostBound ? 'Redis is bound to local loopback (127.0.0.1). External internet traffic cannot reach your port.' : 'Redis is bound to non-local interfaces. Ensure strong password protection is active!' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Password Configuration -->
                        <div class="card shadow-sm border-radius-lg border-0 mb-4">
                            <div class="card-header p-3 bg-white border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 font-weight-bolder text-dark">Password Authentication (requirepass)</h6>
                                        <p class="text-xs text-secondary mb-0">Require clients to authenticate with AUTH before processing commands.</p>
                                    </div>
                                    <span class="badge badge-sm" :class="config.has_password ? 'bg-gradient-success' : 'bg-gradient-secondary'">
                                        {{ config.has_password ? 'Protected' : 'No Password' }}
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <label class="form-label text-xs font-weight-bolder text-uppercase text-secondary">Redis Password</label>
                                    <div class="input-group input-group-outline mb-2">
                                        <input v-model="securityForm.password" :type="showPassword ? 'text' : 'password'" class="form-control" placeholder="Leave empty to disable password requirement">
                                        <button class="btn btn-outline-secondary mb-0 px-3" type="button" @click="showPassword = !showPassword">
                                            <i class="material-symbols-rounded text-sm">{{ showPassword ? 'visibility_off' : 'visibility' }}</i>
                                        </button>
                                        <button class="btn btn-outline-dark mb-0 px-3" type="button" @click="generatePassword" title="Generate strong random password">
                                            <i class="material-symbols-rounded text-sm me-1">shuffle</i> Generate
                                        </button>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-xxs text-secondary">Stored in <code>/etc/redis/redis.conf</code> and applied live.</span>
                                        <button v-if="securityForm.password" @click="copyPassword" type="button" class="btn btn-link text-primary text-xxs p-0 mb-0 font-weight-bold">
                                            <i class="material-symbols-rounded text-xxs me-1">content_copy</i> Copy Password
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button v-if="config.has_password" @click="disablePassword" :disabled="savingPassword" class="btn btn-outline-danger btn-sm mb-0">
                                        Disable Password
                                    </button>
                                    <button @click="savePassword" :disabled="savingPassword || !securityForm.password" class="btn bg-gradient-dark btn-sm mb-0 shadow-sm px-4">
                                        <span v-if="savingPassword" class="spinner-border spinner-border-sm me-2"></span>
                                        {{ savingPassword ? 'Saving...' : 'Set Password' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Network & Protected Mode -->
                        <div class="card shadow-sm border-radius-lg border-0">
                            <div class="card-header p-3 bg-white border-bottom">
                                <h6 class="mb-0 font-weight-bolder text-dark">Network & Interface Binding</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="mb-3">
                                    <label class="form-label text-xs font-weight-bolder text-uppercase text-secondary">Bind Addresses</label>
                                    <div class="input-group input-group-outline mb-1">
                                        <input v-model="networkForm.bind" type="text" class="form-control" placeholder="127.0.0.1 -::1">
                                    </div>
                                    <span class="text-xxs text-secondary">Keep <code>127.0.0.1 -::1</code> for safety unless using a private VPC mesh.</span>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label text-xs font-weight-bolder text-uppercase text-secondary">Protected Mode</label>
                                    <select v-model="networkForm.protected_mode" class="form-select border px-2 py-2 text-xs border-radius-md">
                                        <option value="yes">Yes (Recommended - reject external clients without password)</option>
                                        <option value="no">No (Allow all interface connections)</option>
                                    </select>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button @click="saveNetworkConfig" :disabled="savingNetwork" class="btn bg-gradient-dark btn-sm mb-0 shadow-sm px-4">
                                        <span v-if="savingNetwork" class="spinner-border spinner-border-sm me-2"></span>
                                        {{ savingNetwork ? 'Updating...' : 'Save Network Rules' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════ TAB 4: INTERACTIVE KEY BROWSER ════ -->
                <div v-if="activeTab === 'keys'" class="card shadow-sm border-radius-lg border-0">
                    <div class="card-header p-3 bg-white border-bottom">
                        <div class="row g-2 align-items-center">
                            <!-- Database Picker -->
                            <div class="col-md-2">
                                <label class="form-label text-xxs font-weight-bolder text-uppercase text-secondary mb-1">Database</label>
                                <select v-model="keyBrowser.db" @change="searchKeys" class="form-select border px-2 py-1 text-xs border-radius-md font-weight-bold">
                                    <option v-for="i in 16" :key="i-1" :value="i-1">db{{ i-1 }}</option>
                                </select>
                            </div>

                            <!-- Search Pattern -->
                            <div class="col-md-6">
                                <label class="form-label text-xxs font-weight-bolder text-uppercase text-secondary mb-1">Search Key Pattern</label>
                                <div class="input-group input-group-outline">
                                    <input v-model="keyBrowser.pattern" @keyup.enter="searchKeys" type="text" class="form-control" placeholder="e.g. * or laravel_cache:* or session:*">
                                    <button @click="searchKeys" :disabled="loadingKeys" class="btn btn-dark mb-0 px-3" type="button">
                                        <i class="material-symbols-rounded text-sm">search</i>
                                    </button>
                                </div>
                            </div>

                            <!-- Flush Pattern / Batch Flush -->
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <label class="form-label text-xxs font-weight-bolder text-uppercase text-secondary mb-1 d-none d-md-block">&nbsp;</label>
                                <div class="d-flex gap-2 justify-content-md-end">
                                    <button @click="openFlushPatternModal(keyBrowser.pattern !== '*' ? keyBrowser.pattern : 'laravel_cache:*')" class="btn btn-outline-warning btn-sm mb-0">
                                        <i class="material-symbols-rounded text-xs me-1">filter_alt</i> Flush Prefix
                                    </button>
                                    <button @click="confirmFlushDb(keyBrowser.db)" class="btn btn-outline-danger btn-sm mb-0">
                                        <i class="material-symbols-rounded text-xs me-1">delete_sweep</i> Flush db{{ keyBrowser.db }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Suggestions -->
                        <div class="d-flex flex-wrap gap-1 align-items-center mt-2">
                            <span class="text-xxs text-secondary me-1">Quick Filters:</span>
                            <span @click="setSearchPattern('*')" class="badge bg-light text-dark cursor-pointer text-xxs hover-scale">* (All)</span>
                            <span @click="setSearchPattern('laravel_cache:*')" class="badge bg-light text-dark cursor-pointer text-xxs hover-scale">laravel_cache:*</span>
                            <span @click="setSearchPattern('session:*')" class="badge bg-light text-dark cursor-pointer text-xxs hover-scale">session:*</span>
                            <span @click="setSearchPattern('horizon:*')" class="badge bg-light text-dark cursor-pointer text-xxs hover-scale">horizon:*</span>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div v-if="loadingKeys" class="text-center py-5">
                            <div class="spinner-border text-dark" role="status"></div>
                            <p class="text-xs text-secondary mt-2 mb-0">Scanning keyspace...</p>
                        </div>

                        <div v-else-if="keysList.length === 0" class="text-center py-5">
                            <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">search_off</i>
                            <p class="text-sm font-weight-bold text-dark mt-2 mb-0">No matching keys found in db{{ keyBrowser.db }}</p>
                            <span class="text-xs text-secondary">Try searching with a broader pattern like <code>*</code></span>
                        </div>

                        <div v-else class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-4">Key Name</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Type</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">TTL</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Memory Size</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in keysList" :key="item.key" class="cursor-pointer hover-bg-gray" @click="viewKeyDetails(item.key)">
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center">
                                                <i class="material-symbols-rounded text-sm text-secondary me-2">vpn_key</i>
                                                <span class="text-xs font-weight-bold font-monospace text-dark text-truncate" style="max-width: 380px;" :title="item.key">
                                                    {{ item.key }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-sm font-weight-bold" :class="getKeyTypeBadge(item.type)">{{ item.type }}</span>
                                        </td>
                                        <td>
                                            <span class="text-xs text-secondary">{{ item.ttl_human }}</span>
                                        </td>
                                        <td>
                                            <span class="text-xs text-secondary font-monospace">{{ item.memory_human }}</span>
                                        </td>
                                        <td class="text-end pe-4" @click.stop>
                                            <button @click="viewKeyDetails(item.key)" class="btn btn-link text-primary text-xs p-0 mb-0 font-weight-bold me-3">View</button>
                                            <button @click="deleteSingleKey(item.key)" class="btn btn-link text-danger text-xs p-0 mb-0 font-weight-bold">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ════ TAB 5: SLOWLOG & CLIENTS ════ -->
                <div v-if="activeTab === 'slowlog'" class="row g-4">
                    <!-- SlowLog -->
                    <div class="col-lg-7">
                        <div class="card shadow-sm border-radius-lg border-0">
                            <div class="card-header p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 font-weight-bolder text-dark">Redis SlowLog Queries</h6>
                                    <p class="text-xs text-secondary mb-0">Operations exceeding execution threshold.</p>
                                </div>
                                <button @click="fetchSlowLog" :disabled="loadingSlowLog" class="btn btn-outline-dark btn-sm mb-0 p-2">
                                    <i class="material-symbols-rounded text-sm">sync</i>
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div v-if="loadingSlowLog" class="text-center py-4">
                                    <div class="spinner-border text-dark spinner-border-sm" role="status"></div>
                                </div>
                                <div v-else-if="slowlogEntries.length === 0" class="text-center py-4 text-secondary">
                                    <i class="material-symbols-rounded" style="font-size: 32px;">speed</i>
                                    <p class="text-xs mb-0 mt-1">No slow operations recorded. Performance is optimal!</p>
                                </div>
                                <div v-else class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-3">Duration</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Command</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-end pe-3">Timestamp</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(entry, idx) in slowlogEntries" :key="idx">
                                                <td class="ps-3">
                                                    <span class="badge badge-sm bg-gradient-danger text-xxs">{{ entry.duration_ms }} ms</span>
                                                </td>
                                                <td>
                                                    <code class="text-xs text-dark text-break font-monospace">{{ entry.command }}</code>
                                                </td>
                                                <td class="text-end pe-3 text-xxs text-secondary">{{ entry.timestamp }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connected Clients -->
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-radius-lg border-0">
                            <div class="card-header p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 font-weight-bolder text-dark">Active Client Connections</h6>
                                    <p class="text-xs text-secondary mb-0">Currently connected sockets.</p>
                                </div>
                                <button @click="fetchClients" :disabled="loadingClients" class="btn btn-outline-dark btn-sm mb-0 p-2">
                                    <i class="material-symbols-rounded text-sm">sync</i>
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div v-if="loadingClients" class="text-center py-4">
                                    <div class="spinner-border text-dark spinner-border-sm" role="status"></div>
                                </div>
                                <div v-else-if="clientList.length === 0" class="text-center py-4 text-secondary">
                                    <p class="text-xs mb-0">No active client connections.</p>
                                </div>
                                <div v-else class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table align-items-center mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-3">Address</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Age</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Idle</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-end pe-3">Command</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="client in clientList" :key="client.id">
                                                <td class="ps-3 font-monospace text-xs text-dark">{{ client.addr }}</td>
                                                <td class="text-xxs text-secondary">{{ client.age }}</td>
                                                <td class="text-xxs text-secondary">{{ client.idle }}</td>
                                                <td class="text-end pe-3 text-xs font-monospace font-weight-bold text-primary">{{ client.cmd }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ════ MODAL: VIEW KEY DETAILS ════ -->
            <div v-if="showKeyModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1055;">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-dark text-white border-0 py-3">
                            <h6 class="modal-title text-white font-weight-bold d-flex align-items-center gap-2">
                                <i class="material-symbols-rounded">vpn_key</i>
                                <span class="font-monospace text-truncate" style="max-width: 500px;">{{ activeKeyData?.key }}</span>
                            </h6>
                            <button type="button" class="btn-close btn-close-white" @click="showKeyModal = false"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div v-if="loadingKeyDetails" class="text-center py-4">
                                <div class="spinner-border text-dark" role="status"></div>
                            </div>
                            <template v-else-if="activeKeyData">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge badge-sm" :class="getKeyTypeBadge(activeKeyData.type)">{{ activeKeyData.type }}</span>
                                    <span class="badge badge-sm bg-gradient-secondary">TTL: {{ activeKeyData.ttl_human }}</span>
                                    <span v-if="activeKeyData.is_json" class="badge badge-sm bg-gradient-info">Parsed JSON</span>
                                </div>
                                <div class="position-relative">
                                    <pre class="bg-dark text-white p-3 border-radius-md mb-0" style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 13px;">{{ activeKeyData.value }}</pre>
                                    <button @click="copyKeyValue" class="btn btn-sm btn-outline-white position-absolute top-2 end-2 mb-0 py-1 px-2 text-xxs" title="Copy Content">
                                        <i class="material-symbols-rounded text-xxs me-1">content_copy</i> Copy
                                    </button>
                                </div>
                            </template>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-between">
                            <button @click="deleteSingleKey(activeKeyData?.key, true)" class="btn btn-outline-danger btn-sm mb-0">
                                <i class="material-symbols-rounded text-sm me-1">delete</i> Delete Key
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm mb-0" @click="showKeyModal = false">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════ MODAL: FLUSH BY PREFIX ════ -->
            <div v-if="showFlushPatternModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1055;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-warning text-white border-0 py-3">
                            <h6 class="modal-title text-white font-weight-bold d-flex align-items-center gap-2">
                                <i class="material-symbols-rounded">filter_alt</i>
                                Flush Cache by Pattern
                            </h6>
                            <button type="button" class="btn-close btn-close-white" @click="showFlushPatternModal = false"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="text-xs text-secondary mb-3">
                                Only keys matching this pattern will be removed from <b>db{{ keyBrowser.db }}</b>. Your session and queue data outside this pattern will stay safe.
                            </p>
                            <div class="mb-3">
                                <label class="form-label text-xs font-weight-bolder text-uppercase text-secondary">Pattern to Flush</label>
                                <div class="input-group input-group-outline">
                                    <input v-model="flushPatternInput" type="text" class="form-control" placeholder="e.g. laravel_cache:*">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm mb-0" @click="showFlushPatternModal = false">Cancel</button>
                            <button @click="executeFlushPattern" :disabled="flushingPattern || !flushPatternInput" class="btn bg-gradient-warning btn-sm mb-0 shadow-sm">
                                <span v-if="flushingPattern" class="spinner-border spinner-border-sm me-1"></span>
                                {{ flushingPattern ? 'Flushing...' : 'Flush Matching Keys' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════ MODAL: FLUSH DATABASE CONFIRMATION ════ -->
            <div v-if="showFlushDbModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1055;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-danger text-white border-0 py-3">
                            <h6 class="modal-title text-white font-weight-bold d-flex align-items-center gap-2">
                                <i class="material-symbols-rounded">warning</i>
                                Confirm Database Wipe
                            </h6>
                            <button type="button" class="btn-close btn-close-white" @click="showFlushDbModal = false"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="mb-3">
                                <i class="material-symbols-rounded text-danger" style="font-size: 54px;">delete_forever</i>
                            </div>
                            <h6 class="font-weight-bolder mb-2">
                                {{ flushDbTarget === 'all' ? 'Flush ALL Redis Databases?' : `Flush db${flushDbTarget} completely?` }}
                            </h6>
                            <p class="text-xs text-secondary mb-0">
                                This will permanently remove all cached keys{{ flushDbTarget === 'all' ? ' across all databases' : ` in database db${flushDbTarget}` }}.
                            </p>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm mb-0 px-4" @click="showFlushDbModal = false">Cancel</button>
                            <button @click="executeFlushDb" :disabled="flushingDb" class="btn btn-danger btn-sm mb-0 shadow-sm px-4">
                                <span v-if="flushingDb" class="spinner-border spinner-border-sm me-1"></span>
                                {{ flushingDb ? 'Wiping...' : 'Yes, Flush Database' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    is_installed: Boolean,
    status: String,
    is_enabled: Boolean,
    initial_info: Object,
    initial_config: Object,
});

// State
const isInstalled = ref(props.is_installed ?? false);
const status = ref(props.status ?? 'not_installed');
const isEnabled = ref(props.is_enabled ?? false);
const info = ref(props.initial_info || {});
const config = ref(props.initial_config || {});

const activeTab = ref('overview');
const loadingStatus = ref(false);
const actionLoading = ref(false);
const installing = ref(false);
const installLog = ref('');

// Forms
const memoryForm = reactive({
    maxmemory: config.value.maxmemory || '256mb',
    maxmemory_policy: config.value.maxmemory_policy || 'allkeys-lru'
});

const securityForm = reactive({
    password: config.value.password || ''
});
const showPassword = ref(false);

const networkForm = reactive({
    bind: config.value.bind || '127.0.0.1 -::1',
    protected_mode: config.value.protected_mode || 'yes'
});

const savingConfig = ref(false);
const savingPassword = ref(false);
const savingNetwork = ref(false);

// Key Browser State
const keyBrowser = reactive({
    db: 0,
    pattern: '*'
});
const keysList = ref([]);
const loadingKeys = ref(false);
const showKeyModal = ref(false);
const activeKeyData = ref(null);
const loadingKeyDetails = ref(false);

// Flush Modals State
const showFlushPatternModal = ref(false);
const flushPatternInput = ref('laravel_cache:*');
const flushingPattern = ref(false);

const showFlushDbModal = ref(false);
const flushDbTarget = ref(0);
const flushingDb = ref(false);

// Slowlog & Clients State
const slowlogEntries = ref([]);
const loadingSlowLog = ref(false);
const clientList = ref([]);
const loadingClients = ref(false);

// Toast
const toast = reactive({
    show: false,
    message: '',
    type: 'success',
    icon: 'check'
});

const showToast = (message, type = 'success', icon = 'check') => {
    toast.message = message;
    toast.type = type;
    toast.icon = icon;
    toast.show = true;
    setTimeout(() => { toast.show = false; }, 4000);
};

// Presets
const memoryPresets = [
    { label: '128 MB', val: '128mb' },
    { label: '256 MB', val: '256mb' },
    { label: '512 MB', val: '512mb' },
    { label: '1 GB', val: '1gb' },
    { label: '2 GB', val: '2gb' },
    { label: '4 GB', val: '4gb' },
    { label: 'Unlimited', val: '0' },
];

const evictionPolicies = [
    { id: 'allkeys-lru', desc: 'Evict least recently used keys regardless of expiration. Best for general Laravel caching.', recommended: true },
    { id: 'volatile-lru', desc: 'Evict least recently used keys that have an expiration set.', recommended: false },
    { id: 'allkeys-lfu', desc: 'Evict least frequently used keys across the entire keyspace.', recommended: false },
    { id: 'volatile-lfu', desc: 'Evict least frequently used keys that have an expiration set.', recommended: false },
    { id: 'noeviction', desc: 'Never evict data. Returns errors on memory saturation. Ideal for pure queues/sessions.', recommended: false },
    { id: 'volatile-ttl', desc: 'Evict keys with the shortest remaining time-to-live (TTL).', recommended: false },
];

// Computed
const memoryUsageClass = computed(() => {
    const pct = info.value.memory?.percentage || 0;
    if (pct > 85) return 'text-danger';
    if (pct > 70) return 'text-warning';
    return 'text-success';
});

const memoryBarClass = computed(() => {
    const pct = info.value.memory?.percentage || 0;
    if (pct > 85) return 'bg-gradient-danger';
    if (pct > 70) return 'bg-gradient-warning';
    return 'bg-gradient-info';
});

const isLocalhostBound = computed(() => {
    const bind = networkForm.bind.toLowerCase();
    return bind.includes('127.0.0.1') && !bind.includes('0.0.0.0');
});

// Methods
const formatNumber = (num) => {
    return new Intl.NumberFormat().format(num);
};

const getKeyTypeBadge = (type) => {
    switch (type) {
        case 'string': return 'bg-gradient-info';
        case 'hash': return 'bg-gradient-primary';
        case 'list': return 'bg-gradient-success';
        case 'set': return 'bg-gradient-warning';
        case 'zset': return 'bg-gradient-dark';
        default: return 'bg-secondary';
    }
};

const fetchStatus = async () => {
    loadingStatus.value = true;
    try {
        const res = await axios.get('/redis/status');
        isInstalled.value = res.data.is_installed;
        status.value = res.data.status;
        isEnabled.value = res.data.is_enabled;
        info.value = res.data.info || {};
        config.value = res.data.config || {};

        memoryForm.maxmemory = config.value.maxmemory || '256mb';
        memoryForm.maxmemory_policy = config.value.maxmemory_policy || 'allkeys-lru';
        securityForm.password = config.value.password || '';
        networkForm.bind = config.value.bind || '127.0.0.1 -::1';
        networkForm.protected_mode = config.value.protected_mode || 'yes';
    } catch (e) {
        showToast('Failed to refresh Redis status', 'danger', 'error');
    } finally {
        loadingStatus.value = false;
    }
};

const handleServiceAction = async (action) => {
    actionLoading.value = true;
    try {
        const res = await axios.post(`/redis/service/${action}`);
        showToast(res.data.message, 'success');
        await fetchStatus();
    } catch (e) {
        showToast(e.response?.data?.message || `Failed to ${action} Redis`, 'danger', 'error');
    } finally {
        actionLoading.value = false;
    }
};

const installRedis = async () => {
    installing.value = true;
    installLog.value = 'Starting installation of redis-server, redis-tools, and PHP extensions...\n';
    try {
        const res = await axios.post('/redis/install');
        installLog.value += res.data.log || res.data.message;
        showToast('Redis installed and configured successfully!', 'success');
        await fetchStatus();
    } catch (e) {
        installLog.value += '\n[ERROR] ' + (e.response?.data?.message || 'Installation failed.');
        showToast('Installation failed. Inspect log output.', 'danger', 'error');
    } finally {
        installing.value = false;
    }
};

const saveMemoryConfig = async () => {
    savingConfig.value = true;
    try {
        const res = await axios.post('/redis/config', memoryForm);
        showToast('Memory limits & eviction policy updated!', 'success');
        await fetchStatus();
    } catch (e) {
        showToast(e.response?.data?.message || 'Failed to update memory config', 'danger', 'error');
    } finally {
        savingConfig.value = false;
    }
};

const savePassword = async () => {
    savingPassword.value = true;
    try {
        const res = await axios.post('/redis/security/password', { password: securityForm.password });
        showToast('Redis password updated successfully!', 'success');
        await fetchStatus();
    } catch (e) {
        showToast(e.response?.data?.message || 'Failed to save password', 'danger', 'error');
    } finally {
        savingPassword.value = false;
    }
};

const disablePassword = async () => {
    if (!confirm('Are you sure you want to remove password authentication from Redis?')) return;
    savingPassword.value = true;
    try {
        await axios.post('/redis/security/password', { password: '' });
        securityForm.password = '';
        showToast('Redis password disabled.', 'warning');
        await fetchStatus();
    } catch (e) {
        showToast('Failed to disable password', 'danger', 'error');
    } finally {
        savingPassword.value = false;
    }
};

const generatePassword = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+';
    let pass = '';
    for (let i = 0; i < 32; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    securityForm.password = pass;
    showPassword.value = true;
};

const copyPassword = () => {
    navigator.clipboard.writeText(securityForm.password);
    showToast('Password copied to clipboard!', 'info', 'content_copy');
};

const saveNetworkConfig = async () => {
    savingNetwork.value = true;
    try {
        await axios.post('/redis/config', networkForm);
        showToast('Network binding settings saved!', 'success');
        await fetchStatus();
    } catch (e) {
        showToast('Failed to update network settings', 'danger', 'error');
    } finally {
        savingNetwork.value = false;
    }
};

// Key Browser
const onKeysTabClick = () => {
    activeTab.value = 'keys';
    searchKeys();
};

const searchKeys = async () => {
    loadingKeys.value = true;
    try {
        const res = await axios.get('/redis/keys', {
            params: { pattern: keyBrowser.pattern, db: keyBrowser.db }
        });
        keysList.value = res.data.keys || [];
    } catch (e) {
        showToast('Failed to load keys', 'danger', 'error');
    } finally {
        loadingKeys.value = false;
    }
};

const setSearchPattern = (pattern) => {
    keyBrowser.pattern = pattern;
    searchKeys();
};

const quickBrowseDb = (dbIndex) => {
    keyBrowser.db = dbIndex;
    keyBrowser.pattern = '*';
    activeTab.value = 'keys';
    searchKeys();
};

const viewKeyDetails = async (key) => {
    showKeyModal.value = true;
    loadingKeyDetails.value = true;
    activeKeyData.value = null;
    try {
        const res = await axios.get('/redis/keys/view', {
            params: { key, db: keyBrowser.db }
        });
        activeKeyData.value = res.data;
    } catch (e) {
        showToast('Could not load key value', 'danger', 'error');
    } finally {
        loadingKeyDetails.value = false;
    }
};

const copyKeyValue = () => {
    if (activeKeyData.value?.value) {
        navigator.clipboard.writeText(activeKeyData.value.value);
        showToast('Key content copied!', 'info', 'content_copy');
    }
};

const deleteSingleKey = async (key, closeDetailsModal = false) => {
    if (!confirm(`Delete key '${key}'?`)) return;
    try {
        await axios.delete('/redis/keys', {
            data: { key, db: keyBrowser.db }
        });
        showToast(`Key '${key}' deleted!`, 'success');
        if (closeDetailsModal) {
            showKeyModal.value = false;
        }
        searchKeys();
        fetchStatus();
    } catch (e) {
        showToast('Failed to delete key', 'danger', 'error');
    }
};

// Flush Operations
const openFlushPatternModal = (suggestedPattern) => {
    flushPatternInput.value = suggestedPattern;
    showFlushPatternModal.value = true;
};

const executeFlushPattern = async () => {
    flushingPattern.value = true;
    try {
        const res = await axios.post('/redis/flush-pattern', {
            pattern: flushPatternInput.value,
            db: keyBrowser.db
        });
        showToast(res.data.message, 'success');
        showFlushPatternModal.value = false;
        searchKeys();
        fetchStatus();
    } catch (e) {
        showToast(e.response?.data?.message || 'Flush failed', 'danger', 'error');
    } finally {
        flushingPattern.value = false;
    }
};

const confirmFlushDb = (dbIndex) => {
    flushDbTarget.value = dbIndex;
    showFlushDbModal.value = true;
};

const confirmFlushAll = () => {
    flushDbTarget.value = 'all';
    showFlushDbModal.value = true;
};

const executeFlushDb = async () => {
    flushingDb.value = true;
    try {
        const isAll = flushDbTarget.value === 'all';
        const res = await axios.post('/redis/flush-db', {
            db: isAll ? 0 : flushDbTarget.value,
            all: isAll
        });
        showToast(res.data.message, 'success');
        showFlushDbModal.value = false;
        searchKeys();
        fetchStatus();
    } catch (e) {
        showToast('Flush failed', 'danger', 'error');
    } finally {
        flushingDb.value = false;
    }
};

// Slowlog & Clients
const onSlowlogTabClick = () => {
    activeTab.value = 'slowlog';
    fetchSlowLog();
    fetchClients();
};

const fetchSlowLog = async () => {
    loadingSlowLog.value = true;
    try {
        const res = await axios.get('/redis/slowlog');
        slowlogEntries.value = res.data.slowlog || [];
    } catch (e) {
        // Ignore
    } finally {
        loadingSlowLog.value = false;
    }
};

const fetchClients = async () => {
    loadingClients.value = true;
    try {
        const res = await axios.get('/redis/clients');
        clientList.value = res.data.clients || [];
    } catch (e) {
        // Ignore
    } finally {
        loadingClients.value = false;
    }
};

onMounted(() => {
    if (isInstalled.value && status.value === 'running') {
        // Periodic subtle poll every 30s
        setInterval(() => {
            if (activeTab.value === 'overview') {
                fetchStatus();
            }
        }, 30000);
    }
});
</script>

<style scoped>
.pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #fff;
    display: inline-block;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.4); opacity: 1; }
    100% { transform: scale(0.9); opacity: 0.7; }
}

.spin-anim {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}

.hover-bg-gray:hover {
    background-color: #f8fafc;
}

.hover-scale {
    transition: transform 0.15s ease-in-out;
}
.hover-scale:hover {
    transform: translateY(-1px);
}
</style>
