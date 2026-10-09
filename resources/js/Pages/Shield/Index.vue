<template>
    <MainLayout>
        <div class="container-fluid py-4 bg-gray-100 min-vh-100">
            <!-- Header Section -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h3 class="font-weight-bolder mb-0">Nimbus Shield</h3>
                    <p class="text-sm mb-0 text-secondary">Advanced real-time protection & firewall management.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div v-if="scanning" class="d-flex align-items-center me-3">
                        <div class="spinner-grow text-primary spinner-grow-sm me-2" role="status"></div>
                        <span class="text-xs font-weight-bold text-primary">System Scan in Progress...</span>
                    </div>
                    <button v-if="!scanning" @click="startScan('/var/www')" class="btn btn-dark btn-sm mb-0 shadow-sm">
                        <i class="material-symbols-rounded text-sm me-1">search</i> Quick Scan
                    </button>
                    <button v-if="!scanning" @click="startScan('/usr/local/nimbus')" class="btn btn-outline-dark btn-sm mb-0 shadow-sm">
                        Full System Scan
                    </button>
                    <button v-if="scanning" @click="stopScan" class="btn btn-danger btn-sm mb-0 shadow-sm">
                        <i class="material-symbols-rounded text-sm me-1">stop</i> Stop
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="row mb-4">
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card shadow-sm border-radius-lg overflow-hidden">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold text-secondary">Active Threats</p>
                                        <h5 class="font-weight-bolder mb-0" :class="stats.active_threats > 0 ? 'text-danger' : 'text-success'">
                                            {{ stats.active_threats }}
                                            <span class="text-xs font-weight-normal text-secondary ms-1">detected</span>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-danger shadow-danger text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">warning</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card shadow-sm border-radius-lg overflow-hidden">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold text-secondary">Firewall Status</p>
                                        <h5 class="font-weight-bolder mb-0" :class="stats.firewall_status === 'Active' ? 'text-success' : 'text-danger'">
                                            {{ stats.firewall_status }}
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-success shadow-success text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">local_fire_department</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <div class="card shadow-sm border-radius-lg overflow-hidden">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold text-secondary">Quarantined</p>
                                        <h5 class="font-weight-bolder mb-0 text-dark">
                                            {{ stats.quarantined }}
                                            <span class="text-xs font-weight-normal text-secondary ms-1">files isolated</span>
                                        </h5>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-dark shadow-dark text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">inventory_2</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <div class="card shadow-sm border-radius-lg overflow-hidden">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-capitalize font-weight-bold text-secondary">Last Scan</p>
                                        <h6 class="font-weight-bolder mb-0 text-xs mt-1">
                                            {{ stats.last_scan }}
                                        </h6>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-primary shadow-primary text-center border-radius-md">
                                        <i class="material-symbols-rounded opacity-10">history</i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Installation Status Banner -->
            <div v-if="!stats.tools_installed.all || stats.install_status === 'installing'" class="card mb-4 border-0 shadow-sm bg-gradient-info">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="icon icon-shape bg-white text-info text-center border-radius-md me-3">
                            <i class="material-symbols-rounded opacity-10">{{ stats.install_status === 'installing' ? 'hourglass_top' : 'security' }}</i>
                        </div>
                        <div>
                            <h6 class="text-white mb-0">{{ stats.install_status === 'installing' ? 'Setting up Protection...' : 'Security Tools Missing' }}</h6>
                            <p class="text-white text-xs opacity-8 mb-0">
                                {{ stats.install_status === 'installing' ? 'We are configuring ClamAV and Maldet for real-time monitoring.' : 'Install ClamAV and Maldet to enable advanced threat detection.' }}
                            </p>
                        </div>
                    </div>
                    <button v-if="stats.install_status !== 'installing'" @click="installTools" class="btn btn-white btn-sm mb-0">
                        Configure Now
                    </button>
                    <div v-else class="text-white text-xs font-weight-bold">
                        Please wait...
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="card shadow-sm border-radius-xl">
                <div class="card-header pb-0 p-3 bg-white border-radius-xl">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <ul class="nav nav-pills p-1 bg-gray-100 border-radius-lg w-fit-content" role="tablist">
                                <li class="nav-item">
                                    <button @click="activeTab = 'threats'" :class="activeTab === 'threats' ? 'bg-white shadow text-dark' : 'text-secondary'" class="btn btn-link btn-sm mb-0 px-4 py-2 border-radius-md text-capitalize font-weight-bold">
                                        <i class="material-symbols-rounded text-sm me-1">verified_user</i> Malware Log
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button @click="activeTab = 'firewall'" :class="activeTab === 'firewall' ? 'bg-white shadow text-dark' : 'text-secondary'" class="btn btn-link btn-sm mb-0 px-4 py-2 border-radius-md text-capitalize font-weight-bold">
                                        <i class="material-symbols-rounded text-sm me-1">local_fire_department</i> Firewall
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button @click="activeTab = 'fail2ban'" :class="activeTab === 'fail2ban' ? 'bg-white shadow text-dark' : 'text-secondary'" class="btn btn-link btn-sm mb-0 px-4 py-2 border-radius-md text-capitalize font-weight-bold">
                                        <i class="material-symbols-rounded text-sm me-1">gavel</i> Fail2Ban
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'bg-white shadow text-dark' : 'text-secondary'" class="btn btn-link btn-sm mb-0 px-4 py-2 border-radius-md text-capitalize font-weight-bold">
                                        <i class="material-symbols-rounded text-sm me-1">settings</i> Settings
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6 text-end">
                            <div v-if="activeTab === 'threats'" class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                                <button v-if="selectedThreatIds.length > 0" @click="openBulkSelectedRestoreModal" class="btn btn-sm btn-success mb-0 shadow-sm d-flex align-items-center">
                                    <i class="material-symbols-rounded text-sm me-1">restore_page</i>
                                    Recover Selected ({{ selectedThreatIds.length }})
                                </button>
                                <button v-if="stats.quarantined > 0" @click="openBulkAllRestoreModal" class="btn btn-sm btn-outline-success mb-0 shadow-sm d-flex align-items-center">
                                    <i class="material-symbols-rounded text-sm me-1">inventory_2</i>
                                    Restore All Quarantined ({{ stats.quarantined }})
                                </button>
                                <div class="input-group input-group-sm w-auto">
                                    <span class="input-group-text text-body border-0 bg-gray-100"><i class="material-symbols-rounded text-sm">search</i></span>
                                    <input v-model="searchQuery" @input="currentPage = 1" type="text" class="form-control border-0 bg-gray-100 px-2" placeholder="Search threats, paths...">
                                    <button v-if="searchQuery" class="btn btn-link text-secondary p-0 px-2 mb-0" @click="searchQuery = ''; currentPage = 1">
                                        <i class="material-symbols-rounded text-xs">close</i>
                                    </button>
                                </div>
                            </div>
                            <div v-if="activeTab === 'firewall'" class="d-flex justify-content-end gap-2">
                                <button @click="showAddRuleModal = true" class="btn btn-dark btn-sm mb-0 shadow-sm">
                                    <i class="material-symbols-rounded text-sm me-1">add</i> New Rule
                                </button>
                                <button @click="toggleFirewall" class="btn btn-sm mb-0 shadow-sm" :class="stats.firewall_status === 'Active' ? 'btn-outline-danger' : 'btn-success'">
                                    {{ stats.firewall_status === 'Active' ? 'Disable UFW' : 'Enable UFW' }}
                                </button>
                            </div>
                            <div v-if="activeTab === 'fail2ban'" class="d-flex justify-content-end gap-2">
                                <button v-if="fail2ban.installed && fail2ban.active" @click="showAddBanModal = true" class="btn btn-dark btn-sm mb-0 shadow-sm">
                                    <i class="material-symbols-rounded text-sm me-1">add</i> Ban IP
                                </button>
                                <button v-if="fail2ban.installed" @click="toggleFail2Ban" class="btn btn-sm mb-0 shadow-sm" :class="fail2ban.active ? 'btn-outline-danger' : 'btn-success'">
                                    {{ fail2ban.active ? 'Disable Fail2Ban' : 'Enable Fail2Ban' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0 pt-3">
                    <!-- Threats Tab -->
                    <div v-if="activeTab === 'threats'">
                        <!-- Threat Filter Chips -->
                        <div class="px-3 pb-3 d-flex align-items-center gap-2 flex-wrap border-bottom">
                            <span class="text-xs text-secondary font-weight-bold me-1">Filter:</span>
                            <button @click="setStatusFilter('all')" class="btn btn-xs mb-0 border-radius-lg" :class="statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary'">
                                All ({{ threats.length }})
                            </button>
                            <button @click="setStatusFilter('detected')" class="btn btn-xs mb-0 border-radius-lg" :class="statusFilter === 'detected' ? 'btn-danger' : 'btn-outline-danger'">
                                <i class="material-symbols-rounded text-xxs me-1">warning</i> Active Detected ({{ activeCount }})
                            </button>
                            <button @click="setStatusFilter('quarantined')" class="btn btn-xs mb-0 border-radius-lg" :class="statusFilter === 'quarantined' ? 'btn-warning text-white' : 'btn-outline-warning'">
                                <i class="material-symbols-rounded text-xxs me-1">inventory_2</i> Quarantined ({{ quarantinedCount }})
                            </button>
                            <button @click="setStatusFilter('ignored')" class="btn btn-xs mb-0 border-radius-lg" :class="statusFilter === 'ignored' ? 'btn-success text-white' : 'btn-outline-success'">
                                <i class="material-symbols-rounded text-xxs me-1">verified</i> Ignored / Safe ({{ ignoredCount }})
                            </button>
                        </div>

                        <!-- Bulk Actions Toolbar when items are selected -->
                        <div v-if="selectedThreatIds.length > 0" class="alert bg-gray-100 border border-success d-flex flex-wrap align-items-center justify-content-between p-2 px-3 mx-3 my-2 border-radius-lg">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-dark">{{ selectedThreatIds.length }} files selected</span>
                                <span v-if="selectedQuarantinedCount > 0" class="text-xs text-dark font-weight-bold">
                                    ({{ selectedQuarantinedCount }} quarantined)
                                </span>
                                <button v-if="quarantinedCount > selectedQuarantinedCount" @click="selectAllQuarantined" class="btn btn-link text-primary p-0 mb-0 text-xs">
                                    + Select all {{ quarantinedCount }} quarantined
                                </button>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <button @click="openBulkSelectedRestoreModal" class="btn btn-xs btn-success mb-0 d-flex align-items-center shadow-sm">
                                    <i class="material-symbols-rounded text-xs me-1">restore_page</i>
                                    Recover Selected Files ({{ selectedThreatIds.length }})
                                </button>
                                <button @click="clearSelection" class="btn btn-link text-secondary p-0 px-2 mb-0 text-xs">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        <div v-if="loading" class="text-center py-6">
                            <div class="spinner-border text-dark" role="status"></div>
                            <p class="text-xs text-secondary mt-2">Loading system logs...</p>
                        </div>
                        <div v-else-if="filteredThreats.length === 0" class="text-center py-6">
                            <div class="mb-3">
                                <i class="material-symbols-rounded text-success" style="font-size: 64px;">verified_user</i>
                            </div>
                            <h6 class="text-dark">No Threats Found</h6>
                            <p class="text-xs text-secondary">
                                {{ searchQuery ? 'No threats match your search query.' : (statusFilter !== 'all' ? `No threats in '${statusFilter}' status.` : 'Your system is clean and all scans returned positive results.') }}
                            </p>
                            <button v-if="searchQuery || statusFilter !== 'all'" @click="searchQuery = ''; setStatusFilter('all')" class="btn btn-sm btn-outline-dark mt-2">
                                Clear Filters
                            </button>
                        </div>
                        <div v-else>
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="ps-3 text-center" style="width: 45px;">
                                                <div class="form-check p-0 m-0 d-flex justify-content-center align-items-center">
                                                    <input 
                                                        type="checkbox" 
                                                        class="form-check-input cursor-pointer m-0" 
                                                        :checked="isAllPageSelected" 
                                                        :indeterminate.prop="isSomePageSelected"
                                                        @change="toggleSelectAllPage" 
                                                        title="Select all on this page"
                                                    >
                                                </div>
                                            </th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Security Threat & File Path</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Detection Type</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Time</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Status</th>
                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 pe-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="threat in paginatedThreats" :key="threat.id" class="hover-bg-gray" :class="{ 'bg-light': selectedThreatIds.includes(threat.id) }">
                                            <td class="ps-3 text-center" style="width: 45px;">
                                                <div class="form-check p-0 m-0 d-flex justify-content-center align-items-center">
                                                    <input 
                                                        type="checkbox" 
                                                        :value="threat.id" 
                                                        v-model="selectedThreatIds" 
                                                        class="form-check-input cursor-pointer m-0"
                                                    >
                                                </div>
                                            </td>
                                            <td class="ps-2">
                                                <div class="d-flex flex-column py-1">
                                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                                        <span class="text-sm font-weight-bold text-dark font-monospace">{{ getFileName(threat.file_path) }}</span>
                                                        <span class="text-xxs text-secondary font-monospace bg-gray-100 px-2 py-0 border-radius-sm">{{ getDirName(threat.file_path) }}</span>
                                                        <button @click.stop="copyPath(threat.file_path)" class="btn btn-link text-secondary p-0 mb-0 ms-1" title="Copy Full Path">
                                                            <i class="material-symbols-rounded text-xs">content_copy</i>
                                                        </button>
                                                    </div>
                                                    <p class="text-xs text-secondary mb-0 text-wrap max-width-500 mt-1">
                                                        {{ threat.details }}
                                                    </p>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="material-symbols-rounded text-sm me-1" :class="threat.type.includes('ClamAV') ? 'text-primary' : 'text-danger'">{{ threat.type.includes('ClamAV') ? 'coronavirus' : 'code' }}</i>
                                                    <span class="text-xs font-weight-bold">{{ threat.type }}</span>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs">{{ formatDate(threat.detected_at) }}</span>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="badge badge-sm rounded-pill border" :class="getStatusBadgeClass(threat.status)">
                                                    {{ threat.status }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-center px-4">
                                                <div class="d-flex gap-2 justify-content-center align-items-center">
                                                    <!-- View File Preview -->
                                                    <button @click="openFilePreview(threat)" class="btn btn-link text-info text-gradient p-0 mb-0" title="View / Inspect File">
                                                        <i class="material-symbols-rounded">visibility</i>
                                                    </button>
                                                    <!-- Open in File Editor -->
                                                    <button @click="openInFileEditor(threat)" class="btn btn-link text-primary text-gradient p-0 mb-0" title="Open in File Editor">
                                                        <i class="material-symbols-rounded">edit_document</i>
                                                    </button>
                                                    <!-- Restore File (if quarantined) -->
                                                    <button v-if="threat.status === 'quarantined'" @click="confirmRestoreThreat(threat)" class="btn btn-link text-success text-gradient p-0 mb-0" title="Restore File to Website">
                                                        <i class="material-symbols-rounded">restore_page</i>
                                                    </button>
                                                    <!-- Quarantine File (if detected) -->
                                                    <button v-if="threat.status === 'detected'" @click="confirmQuarantineThreat(threat)" class="btn btn-link text-warning text-gradient p-0 mb-0" title="Quarantine File">
                                                        <i class="material-symbols-rounded">inventory_2</i>
                                                    </button>
                                                    <!-- Ignore / Whitelist (if not ignored) -->
                                                    <button v-if="threat.status !== 'ignored'" @click="confirmIgnoreThreat(threat)" class="btn btn-link text-dark text-gradient p-0 mb-0" title="Mark as Safe / Ignore (Never quarantine)">
                                                        <i class="material-symbols-rounded">verified</i>
                                                    </button>
                                                    <!-- Unignore -->
                                                    <button v-if="threat.status === 'ignored'" @click="unignoreThreat(threat)" class="btn btn-link text-secondary text-gradient p-0 mb-0" title="Reset to Detected">
                                                        <i class="material-symbols-rounded">restart_alt</i>
                                                    </button>
                                                    <!-- Delete File Permanently -->
                                                    <button @click="confirmDeleteThreat(threat)" class="btn btn-link text-danger text-gradient p-0 mb-0" title="Delete Permanently">
                                                        <i class="material-symbols-rounded">delete</i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination Controls -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-top gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-xs text-secondary">
                                        Showing <b class="text-dark">{{ (currentPage - 1) * perPage + 1 }}</b> to <b class="text-dark">{{ Math.min(currentPage * perPage, filteredThreats.length) }}</b> of <b class="text-dark">{{ filteredThreats.length }}</b> entries
                                    </span>
                                    <div class="d-flex align-items-center gap-1 ms-3">
                                        <span class="text-xs text-secondary">Per page:</span>
                                        <select v-model="perPage" @change="currentPage = 1" class="form-select form-select-sm py-1 px-2 border border-radius-md text-xs w-auto">
                                            <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div v-if="totalPages > 1" class="pagination-wrapper d-flex align-items-center gap-1">
                                    <button @click="goToPage(1)" :disabled="currentPage === 1" class="btn btn-xs btn-outline-secondary mb-0 border-radius-md px-2" title="First Page">
                                        <i class="material-symbols-rounded text-xxs">first_page</i>
                                    </button>
                                    <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="btn btn-xs btn-outline-secondary mb-0 border-radius-md px-2" title="Previous Page">
                                        <i class="material-symbols-rounded text-xxs">chevron_left</i>
                                    </button>
                                    
                                    <template v-for="p in visiblePages" :key="p">
                                        <button v-if="p !== '...'" @click="goToPage(p)" class="btn btn-xs mb-0 border-radius-md px-2" :class="currentPage === p ? 'btn-dark' : 'btn-outline-secondary'">
                                            {{ p }}
                                        </button>
                                        <span v-else class="text-xs text-secondary px-1">...</span>
                                    </template>

                                    <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="btn btn-xs btn-outline-secondary mb-0 border-radius-md px-2" title="Next Page">
                                        <i class="material-symbols-rounded text-xxs">chevron_right</i>
                                    </button>
                                    <button @click="goToPage(totalPages)" :disabled="currentPage === totalPages" class="btn btn-xs btn-outline-secondary mb-0 border-radius-md px-2" title="Last Page">
                                        <i class="material-symbols-rounded text-xxs">last_page</i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Firewall Tab -->
                    <div v-if="activeTab === 'firewall'" class="card my-4">
                        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                            <div class="bg-gradient-dark shadow-dark border-radius-lg pt-4 pb-3 d-flex justify-content-between align-items-center px-3">
                                <h6 class="text-white text-capitalize ps-3">Active Firewall Rules (UFW)</h6>
                                <div class="d-flex gap-2">
                                    <button @click="toggleFirewall" class="btn btn-sm mb-0" :class="stats.firewall_status === 'Active' ? 'btn-danger' : 'btn-success'">
                                        {{ stats.firewall_status === 'Active' ? 'Disable Firewall' : 'Enable Firewall' }}
                                    </button>
                                    <button @click="showAddRuleModal = true" class="btn btn-sm btn-primary mb-0">
                                        <i class="material-symbols-rounded text-sm me-1">add</i> Add Rule
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div v-if="loadingRules" class="text-center py-5">
                                <div class="spinner-border text-dark" role="status"></div>
                            </div>
                            <div v-else-if="firewallRules.length === 0" class="text-center py-5">
                                <i class="material-symbols-rounded text-secondary mb-2" style="font-size: 48px;">policy</i>
                                <p class="text-secondary">No custom firewall rules found. Default policies are active.</p>
                            </div>
                            <div v-else class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">#</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">To (Port/Service)</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Action</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">From (Source)</th>
                                            <th class="text-secondary opacity-7"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="rule in firewallRules" :key="rule.index">
                                            <td class="ps-4"><span class="text-xs font-weight-bold">{{ rule.index }}</span></td>
                                            <td><span class="text-sm">{{ rule.to }}</span></td>
                                            <td>
                                                <span class="badge badge-sm" :class="rule.action.toUpperCase() === 'ALLOW' ? 'bg-gradient-success' : 'bg-gradient-danger'">
                                                    {{ rule.action }}
                                                </span>
                                            </td>
                                            <td><span class="text-xs">{{ rule.from }}</span></td>
                                            <td class="align-middle text-right px-3">
                                                <button @click="removeRule(rule.index)" class="btn btn-link text-danger text-gradient px-3 mb-0">
                                                    <i class="material-symbols-rounded text-sm me-2">delete</i>Remove
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Fail2Ban Tab -->
                    <div v-if="activeTab === 'fail2ban'" class="p-4">
                        <!-- If Fail2Ban is not installed -->
                        <div v-if="!fail2ban.installed" class="text-center py-6">
                            <div class="mb-3">
                                <i class="material-symbols-rounded text-secondary" style="font-size: 64px;">gavel</i>
                            </div>
                            <h5 class="text-dark font-weight-bolder">Fail2Ban is not installed</h5>
                            <p class="text-sm text-secondary max-width-500 mx-auto mb-4">
                                Fail2Ban automatically protects your server against brute-force attacks by monitoring system logs (SSH, Nginx, Mail Server) and temporarily or permanently banning offending IP addresses.
                            </p>
                            <div>
                                <button v-if="fail2ban.install_status !== 'installing'" @click="installFail2Ban" class="btn btn-dark btn-sm px-4 shadow-sm mb-0">
                                    <i class="material-symbols-rounded text-sm me-1">download</i> Install Fail2Ban
                                </button>
                                <div v-else class="d-flex align-items-center justify-content-center">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    <span class="text-sm font-weight-bold text-primary">Installing Fail2Ban in background... Please wait.</span>
                                </div>
                            </div>
                        </div>

                        <!-- If Fail2Ban is installed -->
                        <div v-else>
                            <!-- Summary Cards -->
                            <div class="row mb-4">
                                <div class="col-md-4 col-sm-6 mb-3">
                                    <div class="card border shadow-none mb-0 h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <p class="text-xs font-weight-bold text-secondary text-uppercase mb-0">Fail2Ban Service</p>
                                                    <h6 class="font-weight-bolder mb-0" :class="fail2ban.active ? 'text-success' : 'text-danger'">
                                                        {{ fail2ban.active ? 'Active & Running' : 'Stopped / Inactive' }}
                                                    </h6>
                                                </div>
                                                <div class="form-check form-switch ps-0 mb-0">
                                                    <input class="form-check-input ms-auto" type="checkbox" :checked="fail2ban.active" @change="toggleFail2Ban">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6 mb-3">
                                    <div class="card border shadow-none mb-0 h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <p class="text-xs font-weight-bold text-secondary text-uppercase mb-0">Active Jails</p>
                                                    <h6 class="font-weight-bolder mb-0 text-dark">
                                                        {{ fail2ban.jails.length }} monitored services
                                                    </h6>
                                                </div>
                                                <div class="icon icon-shape bg-gradient-info text-center border-radius-md">
                                                    <i class="material-symbols-rounded text-white" style="font-size: 20px;">shield</i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-12 mb-3">
                                    <div class="card border shadow-none mb-0 h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <p class="text-xs font-weight-bold text-secondary text-uppercase mb-0">Total Banned IPs</p>
                                                    <h6 class="font-weight-bolder mb-0 text-dark">
                                                        {{ fail2ban.banned_ips.length }} IPs blocked
                                                    </h6>
                                                </div>
                                                <div class="icon icon-shape bg-gradient-danger text-center border-radius-md">
                                                    <i class="material-symbols-rounded text-white" style="font-size: 20px;">block</i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Jails Overview -->
                            <div class="row">
                                <div class="col-lg-4 mb-4">
                                    <div class="card border shadow-none">
                                        <div class="card-header pb-0 p-3 bg-white">
                                            <h6 class="mb-0 font-weight-bold text-dark">Monitored Jails</h6>
                                            <p class="text-xxs text-secondary mb-0">Individual jail status & failed attempts.</p>
                                        </div>
                                        <div class="card-body p-3">
                                            <div v-for="jail in fail2ban.jails" :key="jail.name" class="d-flex justify-content-between align-items-center p-2 border-bottom hover-bg-gray border-radius-md mb-2">
                                                <div>
                                                    <span class="badge bg-light text-dark border me-2">{{ jail.name }}</span>
                                                    <span class="text-xxs text-secondary">Failed: {{ jail.total_failed }}</span>
                                                </div>
                                                <span class="badge badge-sm" :class="jail.currently_banned > 0 ? 'bg-gradient-danger' : 'bg-gradient-secondary'">
                                                    {{ jail.currently_banned }} banned
                                                </span>
                                            </div>
                                            <div v-if="fail2ban.jails.length === 0" class="text-center py-4 text-secondary text-xs">
                                                No active jails detected.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-8">
                                    <div class="card border shadow-none">
                                        <div class="card-header pb-0 p-3 bg-white d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 font-weight-bold text-dark">Banned IP Addresses</h6>
                                                <p class="text-xxs text-secondary mb-0">Currently blocked IPs in iptables/UFW by Fail2Ban.</p>
                                            </div>
                                            <button @click="showAddBanModal = true" class="btn btn-dark btn-xs mb-0">
                                                <i class="material-symbols-rounded text-xs me-1">add</i> Ban IP Manually
                                            </button>
                                        </div>
                                        <div class="card-body p-0 pt-3">
                                            <div class="table-responsive">
                                                <table class="table align-items-center mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-3">IP Address</th>
                                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Jail</th>
                                                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr v-for="item in fail2ban.banned_ips" :key="item.ip + '-' + item.jail">
                                                            <td class="ps-3"><span class="text-sm font-weight-bold text-dark">{{ item.ip }}</span></td>
                                                            <td><span class="badge bg-light text-dark border">{{ item.jail }}</span></td>
                                                            <td class="text-center">
                                                                <button @click="unbanIp(item.ip, item.jail)" class="btn btn-link text-success text-gradient px-3 mb-0 py-1" title="Unban IP">
                                                                    <i class="material-symbols-rounded text-sm me-1">check_circle</i> Unban
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr v-if="fail2ban.banned_ips.length === 0">
                                                            <td colspan="3" class="text-center py-4 text-secondary text-xs">
                                                                No IPs are currently banned.
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div v-if="activeTab === 'settings'" class="p-4">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card border shadow-none">
                                    <div class="card-header pb-0 p-3 bg-white">
                                        <h6 class="mb-0">Automated Protection Settings</h6>
                                        <p class="text-xs text-secondary mb-0">Configure when and how Nimbus Shield should scan your server automatically.</p>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center justify-content-between mb-4">
                                            <div>
                                                <h6 class="text-sm font-weight-bold mb-0">Daily Auto-Scan</h6>
                                                <p class="text-xs text-secondary mb-0">Automatically scan all websites for malware every day.</p>
                                            </div>
                                            <div class="form-check form-switch ps-0">
                                                <input class="form-check-input ms-auto" type="checkbox" v-model="stats.auto_scan_enabled">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4" :class="{ 'opacity-5': !stats.auto_scan_enabled }">
                                            <label class="text-sm font-weight-bold mb-1 d-block">Preferred Scan Time (24h format)</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="time" v-model="stats.auto_scan_time" class="form-control border px-2 w-25" :disabled="!stats.auto_scan_enabled">
                                                <span class="text-xs text-secondary">Server time: {{ formatTime(new Date()) }}</span>
                                            </div>
                                            <p class="text-xs text-secondary mt-2">
                                                <i class="material-symbols-rounded text-xs me-1">info</i>
                                                We recommend scheduling scans during low-traffic periods (e.g., 03:00 AM).
                                            </p>
                                        </div>

                                        <hr class="horizontal dark my-4">

                                        <div class="d-flex align-items-center justify-content-between mb-4">
                                            <div>
                                                <h6 class="text-sm font-weight-bold mb-0">Auto-Quarantine Threats</h6>
                                                <p class="text-xs text-secondary mb-0">Automatically isolate suspicious files immediately during scans.</p>
                                            </div>
                                            <div class="form-check form-switch ps-0">
                                                <input class="form-check-input ms-auto" type="checkbox" v-model="stats.auto_quarantine">
                                            </div>
                                        </div>

                                        <hr class="horizontal dark my-4">

                                        <!-- Restored & Ignored Files Protection -->
                                        <div class="mb-4">
                                            <h6 class="text-sm font-weight-bold mb-1">Restored & Ignored Files Protection</h6>
                                            <p class="text-xs text-secondary mb-3">Choose how the scanner handles files you previously restored or marked as safe / ignored.</p>
                                            <div class="bg-gray-100 p-3 border-radius-lg border">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" id="policyFlag" value="flag_only" v-model="stats.ignored_policy">
                                                    <label class="form-check-label text-xs text-dark font-weight-bold mb-0 cursor-pointer" for="policyFlag">
                                                        <i class="material-symbols-rounded text-success text-xs me-1">verified</i> Only Flag in Scan Logs (Never Re-Quarantine) <span class="badge bg-gradient-success text-xxs ms-1">Recommended</span>
                                                        <span class="d-block text-secondary font-weight-normal text-xxs mt-1">If a harmless file was restored or ignored, future scans will log it as safe and will NEVER move it to quarantine again.</span>
                                                    </label>
                                                </div>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" id="policySkip" value="skip" v-model="stats.ignored_policy">
                                                    <label class="form-check-label text-xs text-dark font-weight-bold mb-0 cursor-pointer" for="policySkip">
                                                        <i class="material-symbols-rounded text-info text-xs me-1">visibility_off</i> Skip Completely in Future Scans
                                                        <span class="d-block text-secondary font-weight-normal text-xxs mt-1">Restored and ignored files will be completely skipped and won't appear in future threat reports.</span>
                                                    </label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="radio" id="policyQuarantine" value="quarantine" v-model="stats.ignored_policy">
                                                    <label class="form-check-label text-xs text-dark font-weight-bold mb-0 cursor-pointer" for="policyQuarantine">
                                                        <i class="material-symbols-rounded text-danger text-xs me-1">replay</i> Aggressive (Re-Quarantine All Matches)
                                                        <span class="d-block text-secondary font-weight-normal text-xxs mt-1">Files matching patterns will be quarantined again on every scan, even if previously restored.</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="horizontal dark my-4">

                                        <!-- Custom Excluded Paths -->
                                        <div class="mb-4">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <h6 class="text-sm font-weight-bold mb-0">Excluded Paths & Directories</h6>
                                                <span class="text-xxs text-secondary">One pattern per line</span>
                                            </div>
                                            <p class="text-xs text-secondary mb-2">Paths or directory names to exclude from malware scans (e.g. <code>graphify-out</code>, <code>.npm</code>, <code>composer.phar</code>).</p>
                                            <textarea v-model="stats.excluded_paths" rows="3" class="form-control border px-2 font-monospace text-xs" placeholder="graphify-out&#10;.npm&#10;.cache&#10;cache&#10;composer.phar"></textarea>
                                        </div>

                                        <hr class="horizontal dark my-4">

                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div>
                                                <h6 class="text-sm font-weight-bold mb-0">Email Notifications</h6>
                                                <p class="text-xs text-secondary mb-0">Send an encrypted report when a scan starts and finishes.</p>
                                            </div>
                                            <div class="form-check form-switch ps-0">
                                                <input class="form-check-input ms-auto" type="checkbox" v-model="stats.email_alerts">
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4" :class="{ 'opacity-5': !stats.email_alerts }">
                                            <label class="text-sm font-weight-bold mb-1 d-block">Alert Recipients (Comma separated)</label>
                                            <input type="text" v-model="stats.alert_emails" class="form-control border px-2 w-100" placeholder="admin@domain.com, security@domain.com" :disabled="!stats.email_alerts">
                                        </div>

                                        <hr class="horizontal dark my-4">
                                        
                                        <div class="d-flex justify-content-end">
                                            <button @click="saveSecuritySettings" class="btn btn-dark btn-sm mb-0 px-4" :disabled="savingSettings">
                                                <span v-if="savingSettings" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                                {{ savingSettings ? 'Saving...' : 'Save Settings' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card bg-gray-100 border-0 shadow-none h-100">
                                    <div class="card-body p-3">
                                        <h6 class="text-sm font-weight-bold mb-3">Scheduling Tips</h6>
                                        <div class="d-flex mb-3">
                                            <i class="material-symbols-rounded text-primary me-2">timer</i>
                                            <p class="text-xs text-secondary mb-0"><b>Off-peak hours:</b> Most malware injections happen at night. A 3:00 AM scan is ideal.</p>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <i class="material-symbols-rounded text-success me-2">bolt</i>
                                            <p class="text-xs text-secondary mb-0"><b>Resources:</b> Auto-scans run with low-priority settings so they won't slow down your sites.</p>
                                        </div>
                                        <div class="d-flex mb-3">
                                            <i class="material-symbols-rounded text-info me-2">notification_important</i>
                                            <p class="text-xs text-secondary mb-0"><b>Cron Job:</b> Ensure your system cron is active for these settings to take effect.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Threat Confirmation Modal -->
            <div v-if="showDeleteThreatModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1050;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-danger border-0">
                            <h5 class="modal-title text-white">
                                <i class="material-symbols-rounded me-2">warning</i>
                                Confirm Permanent Deletion
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showDeleteThreatModal = false"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="mb-4">
                                <i class="material-symbols-rounded text-danger" style="font-size: 64px;">delete_forever</i>
                            </div>
                            <h5 class="mb-3">Are you sure?</h5>
                            <p class="text-secondary mb-0">You are about to permanently delete:</p>
                            <code class="d-block bg-light p-2 my-3 border-radius-md text-break text-danger">{{ threatToDelete?.file_path }}</code>
                            <p class="text-sm text-muted">
                                <i class="material-symbols-rounded text-xs me-1">info</i>
                                This action cannot be undone. The file will be removed from the server immediately.
                            </p>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary mb-0 px-4" @click="showDeleteThreatModal = false">Cancel</button>
                            <button type="button" class="btn btn-danger mb-0 px-4" @click="executeDeleteThreat" :disabled="deletingThreat">
                                <span v-if="deletingThreat" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                {{ deletingThreat ? 'Deleting...' : 'Delete Permanently' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quarantine Threat Confirmation Modal -->
            <div v-if="showQuarantineModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1050;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-warning border-0">
                            <h5 class="modal-title text-white">
                                <i class="material-symbols-rounded me-2">warning</i>
                                Confirm Quarantine
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showQuarantineModal = false"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="mb-4">
                                <i class="material-symbols-rounded text-warning" style="font-size: 64px;">inventory_2</i>
                            </div>
                            <h5 class="mb-3">Isolate this file?</h5>
                            <p class="text-secondary mb-0">You are about to quarantine:</p>
                            <code class="d-block bg-light p-2 my-3 border-radius-md text-break text-warning">{{ threatToQuarantine?.file_path }}</code>
                            <p class="text-sm text-muted">
                                <i class="material-symbols-rounded text-xs me-1">info</i>
                                The file will be moved to a secure location on the server and made inaccessible.
                            </p>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary mb-0 px-4" @click="showQuarantineModal = false">Cancel</button>
                            <button type="button" class="btn btn-warning text-white mb-0 px-4" @click="executeQuarantineThreat" :disabled="quarantiningThreat">
                                <span v-if="quarantiningThreat" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                {{ quarantiningThreat ? 'Quarantining...' : 'Quarantine File' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Restore Threat Confirmation Modal -->
            <div v-if="showRestoreModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1050;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-success border-0">
                            <h5 class="modal-title text-white">
                                <i class="material-symbols-rounded me-2">security</i>
                                Confirm Restoration
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showRestoreModal = false"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="mb-4">
                                <i class="material-symbols-rounded text-success" style="font-size: 64px;">restore_page</i>
                            </div>
                            <h5 class="mb-3">Restore this file?</h5>
                            <p class="text-secondary mb-0">You are about to restore:</p>
                            <code class="d-block bg-light p-2 my-3 border-radius-md text-break text-success">{{ threatToRestore?.file_path }}</code>
                            <p class="text-sm text-muted">
                                <i class="material-symbols-rounded text-xs me-1">info</i>
                                The file will be returned to its original path and marked as ignored. Only do this if you are sure it is safe.
                            </p>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary mb-0 px-4" @click="showRestoreModal = false">Cancel</button>
                            <button type="button" class="btn btn-success mb-0 px-4" @click="executeRestoreThreat" :disabled="restoringThreat">
                                <span v-if="restoringThreat" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                {{ restoringThreat ? 'Restoring...' : 'Restore File' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Rule Modal -->
            <div v-if="showAddRuleModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5)">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Firewall Rule</h5>
                            <button type="button" class="btn-close" @click="showAddRuleModal = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Port / Service</label>
                                <input v-model="newRule.port" type="text" class="form-control border px-2" placeholder="e.g. 80, 443, 3306">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Action</label>
                                <select v-model="newRule.action" class="form-control border px-2">
                                    <option value="allow">Allow</option>
                                    <option value="deny">Deny</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Protocol</label>
                                <select v-model="newRule.proto" class="form-control border px-2">
                                    <option value="tcp">TCP</option>
                                    <option value="udp">UDP</option>
                                    <option value="any">Any</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="showAddRuleModal = false">Cancel</button>
                            <button type="button" class="btn btn-primary" @click="addRule">Add Rule</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Ban IP Modal -->
            <div v-if="showAddBanModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1050;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header">
                            <h5 class="modal-title font-weight-bold">Ban IP Address Manually</h5>
                            <button type="button" class="btn-close" @click="showAddBanModal = false"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label text-xs font-weight-bolder">IP Address</label>
                                <div class="input-group input-group-outline">
                                    <input v-model="newBan.ip" type="text" class="form-control" placeholder="e.g. 192.168.1.100">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-xs font-weight-bolder">Active Jail</label>
                                <div class="input-group input-group-outline">
                                    <select v-model="newBan.jail" class="form-control">
                                        <option v-for="jail in fail2ban.jails" :key="jail.name" :value="jail.name">{{ jail.name }}</option>
                                        <option v-if="fail2ban.jails.length === 0" value="sshd">sshd</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary mb-0 px-4" @click="showAddBanModal = false">Cancel</button>
                            <button type="button" class="btn btn-danger mb-0 px-4" @click="banIp" :disabled="banningIp">
                                <span v-if="banningIp" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Ban IP
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inspect File / Preview Modal -->
            <div v-if="showPreviewModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.6); z-index: 1060;">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content border-radius-lg shadow-2xl border-0 overflow-hidden">
                        <div class="modal-header bg-gradient-dark border-0 p-3">
                            <div class="d-flex align-items-center">
                                <div class="icon icon-shape bg-white text-dark text-center border-radius-md me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="material-symbols-rounded text-sm">visibility</i>
                                </div>
                                <div>
                                    <h6 class="modal-title text-white mb-0 font-weight-bolder">Inspect Security Threat</h6>
                                    <p class="text-white text-xxs opacity-8 mb-0 font-monospace">{{ previewData?.file_path || threatToPreview?.file_path }}</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span v-if="previewData?.is_quarantined" class="badge bg-gradient-warning text-xxs">
                                    <i class="material-symbols-rounded text-xxs me-1">inventory_2</i> Quarantined
                                </span>
                                <span v-else class="badge bg-gradient-danger text-xxs">
                                    <i class="material-symbols-rounded text-xxs me-1">warning</i> Active on Disk
                                </span>
                                <button type="button" class="btn-close btn-close-white ms-2" @click="showPreviewModal = false"></button>
                            </div>
                        </div>

                        <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                            <div v-if="loadingPreview" class="text-center py-6">
                                <div class="spinner-border text-dark" role="status"></div>
                                <p class="text-xs text-secondary mt-2">Loading file contents securely from server...</p>
                            </div>
                            <div v-else-if="previewData">
                                <!-- Details Callout -->
                                <div class="alert bg-gray-100 border p-3 mb-3 border-radius-lg d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-danger text-xxs">{{ previewData.threat?.type }}</span>
                                            <span class="text-xs font-weight-bold text-dark">{{ previewData.threat?.details }}</span>
                                        </div>
                                        <p class="text-xxs text-secondary mb-0 font-monospace">
                                            Path: {{ previewData.file_path }}
                                        </p>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-light text-dark border text-xxs font-monospace">{{ previewData.lines }} lines</span>
                                        <span class="badge bg-light text-dark border text-xxs font-monospace">{{ formatBytes(previewData.size) }}</span>
                                        <button @click="copyPath(previewData.file_path)" class="btn btn-xs btn-outline-dark mb-0">
                                            <i class="material-symbols-rounded text-xxs me-1">content_copy</i> Copy Path
                                        </button>
                                    </div>
                                </div>

                                <div v-if="previewData.is_quarantined" class="alert bg-warning-subtle text-warning-emphasis border border-warning p-2 px-3 mb-3 border-radius-md text-xs d-flex align-items-center">
                                    <i class="material-symbols-rounded text-sm me-2">inventory_2</i>
                                    <span>This file is currently in quarantine at <code>{{ previewData.quarantined_path }}</code> with chmod 000. It cannot be executed by websites until restored.</span>
                                </div>

                                <!-- Code Viewer Container -->
                                <div class="code-preview-container bg-dark text-white border-radius-lg p-3 font-monospace text-xs" style="max-height: 480px; overflow: auto; line-height: 1.5; white-space: pre;">
                                    <code>{{ previewData.content }}</code>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer border-0 p-3 bg-light d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-2">
                                <button v-if="previewData?.threat?.status === 'quarantined'" @click="executeRestoreFromPreview" class="btn btn-sm btn-success mb-0">
                                    <i class="material-symbols-rounded text-sm me-1">restore_page</i> Restore File
                                </button>
                                <button v-if="previewData?.threat?.status !== 'ignored'" @click="executeIgnoreFromPreview" class="btn btn-sm btn-outline-dark mb-0">
                                    <i class="material-symbols-rounded text-sm me-1">verified</i> Mark Safe / Ignore
                                </button>
                                <button @click="executeDeleteFromPreview" class="btn btn-sm btn-outline-danger mb-0">
                                    <i class="material-symbols-rounded text-sm me-1">delete</i> Delete
                                </button>
                            </div>
                            <div class="d-flex gap-2">
                                <button @click="showPreviewModal = false" class="btn btn-sm btn-outline-secondary mb-0 px-3">Close</button>
                                <button @click="openInFileEditor(previewData?.threat || threatToPreview)" class="btn btn-sm btn-primary mb-0 px-3">
                                    <i class="material-symbols-rounded text-sm me-1">edit_document</i> Open in File Editor
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Restore Confirmation Modal -->
            <div v-if="showBulkRestoreModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1055;">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-success border-0">
                            <h5 class="modal-title text-white font-weight-bold">
                                <i class="material-symbols-rounded me-2">restore_page</i>
                                {{ bulkRestoreMode === 'selected' ? `Recover ${selectedThreatIds.length} Selected File(s)` : 'Restore All Quarantined Files' }}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showBulkRestoreModal = false"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="mb-3">
                                <i class="material-symbols-rounded text-success" style="font-size: 64px;">published_with_changes</i>
                            </div>
                            <h5 class="mb-2">
                                {{ bulkRestoreMode === 'selected' ? `Recover ${selectedThreatIds.length} Selected File(s) to Websites?` : `Restore ${stats.quarantined} Files to Websites?` }}
                            </h5>
                            <p class="text-sm text-secondary mb-3">
                                {{ bulkRestoreMode === 'selected' 
                                    ? `This will safely recover the ${selectedThreatIds.length} selected files back to their original project directories, set proper permissions (644, www-data:www-data), and mark them as Safe / Ignored so future automated scans will NOT re-quarantine them.`
                                    : 'This will safely return all currently quarantined files back to their original project directories, set proper permissions (644, www-data:www-data), and mark them as Safe / Ignored so future automated scans will NOT re-quarantine them.'
                                }}
                            </p>

                            <!-- Selected files preview list -->
                            <div v-if="bulkRestoreMode === 'selected' && selectedThreats.length > 0" class="text-start mb-3">
                                <label class="text-xs font-weight-bold text-secondary mb-1">Selected Files to Recover ({{ selectedThreats.length }}):</label>
                                <div class="bg-gray-100 p-2 border-radius-md border overflow-auto" style="max-height: 180px;">
                                    <div v-for="st in selectedThreats" :key="st.id" class="d-flex align-items-center justify-content-between py-1 px-2 border-bottom border-light">
                                        <div class="text-truncate me-2" style="max-width: 85%;">
                                            <span class="text-xs font-monospace font-weight-bold text-dark">{{ getFileName(st.file_path) }}</span>
                                            <span class="text-xxs font-monospace text-secondary d-block text-truncate">{{ st.file_path }}</span>
                                        </div>
                                        <span class="badge badge-sm rounded-pill border" :class="getStatusBadgeClass(st.status)">
                                            {{ st.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="alert bg-gray-100 p-2 text-xxs text-secondary border-radius-md mb-0 text-start">
                                <i class="material-symbols-rounded text-xxs me-1 align-middle text-primary">info</i>
                                Essential project scripts (e.g., WordPress files, composer.phar, AST caches) will immediately resume working.
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary mb-0 px-4" @click="showBulkRestoreModal = false">Cancel</button>
                            <button type="button" class="btn btn-success mb-0 px-4" @click="executeBulkRestore" :disabled="restoringBulk">
                                <span v-if="restoringBulk" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                {{ restoringBulk ? 'Recovering...' : (bulkRestoreMode === 'selected' ? `Recover ${selectedThreatIds.length} Selected` : 'Restore All Now') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ignore / Whitelist Confirmation Modal -->
            <div v-if="showIgnoreModal" class="modal fade show d-block" style="background: rgba(0,0,0,0.5); z-index: 1050;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-radius-lg shadow-lg border-0">
                        <div class="modal-header bg-gradient-dark border-0">
                            <h5 class="modal-title text-white font-weight-bold">
                                <i class="material-symbols-rounded me-2">verified</i>
                                Mark Threat as Safe / Ignored
                            </h5>
                            <button type="button" class="btn-close btn-close-white" @click="showIgnoreModal = false"></button>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div class="mb-3">
                                <i class="material-symbols-rounded text-primary" style="font-size: 64px;">shield_lock</i>
                            </div>
                            <h5 class="mb-2">Whitelist this file?</h5>
                            <p class="text-xs text-secondary mb-2">You are marking the following file as safe / harmless:</p>
                            <code class="d-block bg-light p-2 mb-3 border-radius-md text-break text-dark text-xxs font-monospace">{{ threatToIgnore?.file_path }}</code>
                            <p class="text-xs text-muted mb-0">
                                This file will be marked as <b>Ignored</b> and protected from future auto-quarantines. If it is currently quarantined, it will also be automatically restored to your website.
                            </p>
                        </div>
                        <div class="modal-footer border-0 p-3 justify-content-center">
                            <button type="button" class="btn btn-outline-secondary mb-0 px-4" @click="showIgnoreModal = false">Cancel</button>
                            <button type="button" class="btn btn-dark mb-0 px-4" @click="executeIgnoreThreat" :disabled="ignoringThreat">
                                <span v-if="ignoringThreat" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                {{ ignoringThreat ? 'Processing...' : 'Confirm & Whitelist' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notifications -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div v-if="showToast" class="toast show align-items-center text-white border-0" :class="'bg-' + toastType" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        {{ toastMessage }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="showToast = false"></button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { formatDate, formatTime } from '@/utils/date';

const loading = ref(true)
const scanning = ref(false)
const threats = ref([])
const stats = ref({
    active_threats: 0,
    quarantined: 0,
    ignored: 0,
    last_scan: 'Never',
    firewall_status: 'Checking...',
    scan_status: 'idle',
    tools_installed: { all: true }, // Default to true to avoid flicker
    install_status: 'idle',
    auto_scan_enabled: false,
    auto_scan_time: '03:00',
    auto_quarantine: false,
    email_alerts: false,
    alert_emails: '',
    ignored_policy: 'flag_only',
    excluded_paths: "graphify-out\n.npm\n.cache\ncache\ncomposer.phar"
})

const savingSettings = ref(false)
const saveSecuritySettings = async () => {
    savingSettings.value = true
    try {
        const response = await axios.post('/shield/settings', {
            auto_scan_enabled: stats.value.auto_scan_enabled,
            auto_scan_time: stats.value.auto_scan_time,
            auto_quarantine: stats.value.auto_quarantine,
            email_alerts: stats.value.email_alerts,
            alert_emails: stats.value.alert_emails,
            ignored_policy: stats.value.ignored_policy,
            excluded_paths: stats.value.excluded_paths
        })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
        }
    } catch (error) {
        showNotification('Failed to save settings', 'danger')
    } finally {
        savingSettings.value = false
    }
}

const activeTab = ref('threats')
const loadingRules = ref(false)
const firewallRules = ref([])
const showAddRuleModal = ref(false)
const showDeleteThreatModal = ref(false)
const threatToDelete = ref(null)
const deletingThreat = ref(false)

const showQuarantineModal = ref(false)
const threatToQuarantine = ref(null)
const quarantiningThreat = ref(false)

const showRestoreModal = ref(false)
const threatToRestore = ref(null)
const restoringThreat = ref(false)

const showBulkRestoreModal = ref(false)
const bulkRestoreMode = ref('all') // 'all' or 'selected'
const restoringBulk = ref(false)
const selectedThreatIds = ref([])

const showIgnoreModal = ref(false)
const threatToIgnore = ref(null)
const ignoringThreat = ref(false)

const showPreviewModal = ref(false)
const loadingPreview = ref(false)
const previewData = ref(null)
const threatToPreview = ref(null)

const newRule = ref({
    port: '',
    action: 'allow',
    proto: 'tcp'
})

// Search, Filtering & Pagination
const searchQuery = ref('')
const statusFilter = ref('all') // 'all', 'detected', 'quarantined', 'ignored'
const currentPage = ref(1)
const perPage = ref(15)
const perPageOptions = [10, 15, 25, 50, 100]

const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
let statusInterval = null

const fail2ban = ref({
    installed: false,
    active: false,
    jails: [],
    banned_ips: [],
    install_status: 'idle'
})
const showAddBanModal = ref(false)
const banningIp = ref(false)
const newBan = ref({
    ip: '',
    jail: ''
})

const loadStatus = async () => {
    try {
        const response = await axios.get('/shield/status')
        if (response.data.success) {
            threats.value = response.data.threats
            stats.value = response.data.stats
            
            // Sync scanning state with backend
            scanning.value = stats.value.scan_status === 'running'
            
            if (scanning.value || fail2ban.value.install_status === 'installing') {
                startPolling()
            } else {
                stopPolling()
            }
        }
        if (activeTab.value === 'fail2ban') {
            await loadFail2BanStatus()
        }
    } catch (error) {
        if (!statusInterval) {
            showNotification('Failed to load status', 'danger')
        }
    } finally {
        loading.value = false
    }
}

const loadFirewallRules = async () => {
    loadingRules.value = true
    try {
        const response = await axios.get('/shield/firewall/rules')
        if (response.data.success) {
            firewallRules.value = response.data.rules
            stats.value.firewall_status = response.data.status
        }
    } catch (error) {
        showNotification('Failed to load firewall rules', 'danger')
    } finally {
        loadingRules.value = false
    }
}

const toggleFirewall = async () => {
    const enable = stats.value.firewall_status !== 'Active'
    try {
        const response = await axios.post('/shield/firewall/toggle', { enable })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            loadFirewallRules()
        }
    } catch (error) {
        showNotification('Failed to toggle firewall', 'danger')
    }
}

const addRule = async () => {
    try {
        const response = await axios.post('/shield/firewall/add', newRule.value)
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            showAddRuleModal.value = false
            newRule.value = { port: '', action: 'allow', proto: 'tcp' }
            loadFirewallRules()
        }
    } catch (error) {
        showNotification('Failed to add rule', 'danger')
    }
}

const removeRule = async (index) => {
    if (!confirm('Are you sure you want to remove this firewall rule?')) return
    try {
        const response = await axios.post('/shield/firewall/delete', { index })
        if (response.data.success) {
            showNotification('Rule removed', 'success')
            loadFirewallRules()
        }
    } catch (error) {
        showNotification('Failed to remove rule', 'danger')
    }
}

// Watch activeTab to load rules
import { watch } from 'vue';
watch(activeTab, (newTab) => {
    if (newTab === 'firewall') {
        loadFirewallRules()
    } else if (newTab === 'fail2ban') {
        loadFail2BanStatus()
    }
})

const loadingFail2Ban = ref(false)
const loadFail2BanStatus = async () => {
    loadingFail2Ban.value = true
    try {
        const response = await axios.get('/shield/fail2ban')
        if (response.data.success) {
            fail2ban.value = {
                installed: response.data.installed,
                active: response.data.active,
                jails: response.data.jails,
                banned_ips: response.data.banned_ips,
                install_status: response.data.install_status
            }
            if (fail2ban.value.install_status === 'installing') {
                startPolling()
            } else if (!scanning.value && statusInterval) {
                stopPolling()
            }
        }
    } catch (error) {
        if (!statusInterval) {
            showNotification('Failed to load Fail2Ban status', 'danger')
        }
    } finally {
        loadingFail2Ban.value = false
    }
}

const toggleFail2Ban = async () => {
    const enable = !fail2ban.value.active
    try {
        const response = await axios.post('/shield/fail2ban/toggle', { enable })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            loadFail2BanStatus()
        }
    } catch (error) {
        showNotification('Failed to toggle Fail2Ban', 'danger')
    }
}

const installFail2Ban = async () => {
    try {
        const response = await axios.post('/shield/fail2ban/install')
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            fail2ban.value.install_status = 'installing'
            startPolling()
        }
    } catch (error) {
        showNotification('Failed to start Fail2Ban installation', 'danger')
    }
}

const unbanIp = async (ip, jail) => {
    if (!confirm(`Are you sure you want to unban IP ${ip} from jail ${jail}?`)) return
    try {
        const response = await axios.post('/shield/fail2ban/unban', { ip, jail })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            loadFail2BanStatus()
        }
    } catch (error) {
        showNotification('Failed to unban IP', 'danger')
    }
}

const banIp = async () => {
    if (!newBan.value.ip) {
        showNotification('IP Address is required', 'warning')
        return
    }
    if (!newBan.value.jail) {
        newBan.value.jail = fail2ban.value.jails[0]?.name || 'sshd'
    }
    banningIp.value = true
    try {
        const response = await axios.post('/shield/fail2ban/ban', newBan.value)
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            showAddBanModal.value = false
            newBan.value = { ip: '', jail: '' }
            loadFail2BanStatus()
        }
    } catch (error) {
        showNotification(error.response?.data?.error || 'Failed to ban IP', 'danger')
    } finally {
        banningIp.value = false
    }
}

const startPolling = () => {
    if (statusInterval) return
    statusInterval = setInterval(loadStatus, 5000)
}

const installTools = async () => {
    try {
        const response = await axios.post('/shield/install-tools')
        if (response.data.success) {
            showNotification('Installation started. You can close this page; it will continue in the background.', 'success')
            stats.value.install_status = 'installing'
            startPolling()
        }
    } catch (error) {
        showNotification('Failed to start installation', 'danger')
    }
}

const stopPolling = () => {
    if (statusInterval) {
        clearInterval(statusInterval)
        statusInterval = null
    }
}

const startScan = async (path) => {
    if (scanning.value) return
    
    scanning.value = true
    showNotification('Starting scan in ' + path + '...', 'info')
    
    try {
        const response = await axios.post('/shield/scan', { path })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            startPolling()
        }
    } catch (error) {
        if (error.response?.status === 409) {
            showNotification('A scan is already running. Monitoring progress...', 'info')
            startPolling()
        } else {
            showNotification('Scan failed: ' + (error.response?.data?.error || error.message), 'danger')
            scanning.value = false
        }
    }
}

const stopScan = async () => {
    try {
        const response = await axios.post('/shield/stop')
        if (response.data.success) {
            showNotification('Scan stopped/reset', 'info')
            scanning.value = false
            stopPolling()
            await loadStatus()
        }
    } catch (error) {
        showNotification('Failed to stop scan', 'danger')
    }
}

const confirmQuarantineThreat = (threat) => {
    threatToQuarantine.value = threat
    showQuarantineModal.value = true
}

const executeQuarantineThreat = async () => {
    if (!threatToQuarantine.value) return
    quarantiningThreat.value = true
    try {
        const response = await axios.post('/shield/quarantine', { id: threatToQuarantine.value.id })
        if (response.data.success) {
            showNotification('File quarantined successfully', 'success')
            showQuarantineModal.value = false
            await loadStatus()
        }
    } catch (error) {
        showNotification('Quarantine failed: ' + (error.response?.data?.error || error.message), 'danger')
    } finally {
        quarantiningThreat.value = false
    }
}

const confirmRestoreThreat = (threat) => {
    threatToRestore.value = threat
    showRestoreModal.value = true
}

const executeRestoreThreat = async () => {
    if (!threatToRestore.value) return
    restoringThreat.value = true
    try {
        const response = await axios.post('/shield/restore', { id: threatToRestore.value.id })
        if (response.data.success) {
            showNotification('File restored successfully', 'success')
            showRestoreModal.value = false
            await loadStatus()
        }
    } catch (error) {
        showNotification(error.response?.data?.error || 'Restore failed', 'danger')
    } finally {
        restoringThreat.value = false
    }
}

const confirmIgnoreThreat = (threat) => {
    threatToIgnore.value = threat
    showIgnoreModal.value = true
}

const executeIgnoreThreat = async () => {
    if (!threatToIgnore.value) return
    ignoringThreat.value = true
    try {
        const response = await axios.post('/shield/ignore', { id: threatToIgnore.value.id })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            showIgnoreModal.value = false
            await loadStatus()
        }
    } catch (error) {
        showNotification(error.response?.data?.error || 'Failed to ignore threat', 'danger')
    } finally {
        ignoringThreat.value = false
    }
}

const unignoreThreat = async (threat) => {
    if (!threat) return
    try {
        const response = await axios.post('/shield/unignore', { id: threat.id })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            await loadStatus()
        }
    } catch (error) {
        showNotification('Failed to unignore threat', 'danger')
    }
}

const openBulkSelectedRestoreModal = () => {
    if (selectedThreatIds.value.length === 0) return
    bulkRestoreMode.value = 'selected'
    showBulkRestoreModal.value = true
}

const openBulkAllRestoreModal = () => {
    bulkRestoreMode.value = 'all'
    showBulkRestoreModal.value = true
}

const toggleSelectAllPage = () => {
    const pageIds = paginatedThreats.value.map(t => t.id)
    if (isAllPageSelected.value) {
        selectedThreatIds.value = selectedThreatIds.value.filter(id => !pageIds.includes(id))
    } else {
        const newSet = new Set([...selectedThreatIds.value, ...pageIds])
        selectedThreatIds.value = Array.from(newSet)
    }
}

const selectAllQuarantined = () => {
    const qIds = threats.value.filter(t => t.status === 'quarantined').map(t => t.id)
    selectedThreatIds.value = Array.from(new Set([...selectedThreatIds.value, ...qIds]))
}

const clearSelection = () => {
    selectedThreatIds.value = []
}

const executeBulkRestore = async () => {
    restoringBulk.value = true
    try {
        const payload = bulkRestoreMode.value === 'selected' ? { ids: selectedThreatIds.value } : {}
        const response = await axios.post('/shield/bulk-restore', payload)
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            showBulkRestoreModal.value = false
            selectedThreatIds.value = []
            await loadStatus()
        }
    } catch (error) {
        showNotification(error.response?.data?.error || 'Bulk restore failed', 'danger')
    } finally {
        restoringBulk.value = false
    }
}

// File Inspection & Preview Modal
const openFilePreview = async (threat) => {
    threatToPreview.value = threat
    showPreviewModal.value = true
    loadingPreview.value = true
    previewData.value = null
    try {
        const response = await axios.post('/shield/file-preview', { id: threat.id })
        if (response.data.success) {
            previewData.value = response.data
        }
    } catch (error) {
        showNotification(error.response?.data?.error || 'Failed to load file preview', 'danger')
        showPreviewModal.value = false
    } finally {
        loadingPreview.value = false
    }
}

const executeRestoreFromPreview = async () => {
    if (!previewData.value?.threat) return
    try {
        const response = await axios.post('/shield/restore', { id: previewData.value.threat.id })
        if (response.data.success) {
            showNotification('File restored successfully', 'success')
            showPreviewModal.value = false
            await loadStatus()
        }
    } catch (error) {
        showNotification(error.response?.data?.error || 'Restore failed', 'danger')
    }
}

const executeIgnoreFromPreview = async () => {
    if (!previewData.value?.threat) return
    try {
        const response = await axios.post('/shield/ignore', { id: previewData.value.threat.id })
        if (response.data.success) {
            showNotification(response.data.message, 'success')
            showPreviewModal.value = false
            await loadStatus()
        }
    } catch (error) {
        showNotification('Failed to ignore threat', 'danger')
    }
}

const executeDeleteFromPreview = async () => {
    if (!previewData.value?.threat) return
    if (!confirm('Permanently delete this file from server?')) return
    try {
        const response = await axios.post('/shield/delete', { id: previewData.value.threat.id })
        if (response.data.success) {
            showNotification('File deleted permanently', 'success')
            showPreviewModal.value = false
            await loadStatus()
        }
    } catch (error) {
        showNotification('Delete failed', 'danger')
    }
}

// Open File in File Manager / Editor
const openInFileEditor = (threat) => {
    if (!threat || !threat.file_path) return
    const filePath = threat.file_path

    if (threat.status === 'quarantined') {
        if (confirm('This file is currently in quarantine (inaccessible). Would you like to restore it first so you can view and edit it in the File Editor?')) {
            axios.post('/shield/restore', { id: threat.id }).then(res => {
                if (res.data.success) {
                    showNotification('File restored. Opening File Editor...', 'success')
                    loadStatus().then(() => {
                        navigateToEditor(filePath)
                    })
                }
            }).catch(err => {
                showNotification(err.response?.data?.error || 'Restore failed', 'danger')
            })
            return
        }
    }

    navigateToEditor(filePath)
}

const navigateToEditor = (fullPath) => {
    // Check if in /var/www/<domain>/...
    const match = fullPath.match(/^\/var\/www\/([^\/]+)(?:\/(.*))?$/)
    if (match) {
        const domain = match[1]
        const subPath = match[2] || ''
        const parts = subPath.split('/')
        const fileName = parts.pop() || ''
        const dirPath = parts.join('/')

        const url = `/file-manager/${encodeURIComponent(domain)}?path=${encodeURIComponent(dirPath)}&file=${encodeURIComponent(fileName)}`
        window.open(url, '_blank')
    } else {
        // Root scope
        const parts = fullPath.split('/')
        const fileName = parts.pop() || ''
        const dirPath = parts.join('/')
        const url = `/file-manager/root?path=${encodeURIComponent(dirPath)}&file=${encodeURIComponent(fileName)}`
        window.open(url, '_blank')
    }
}

const copyPath = (path) => {
    if (!path) return
    navigator.clipboard.writeText(path).then(() => {
        showNotification('File path copied to clipboard!', 'info')
    }).catch(() => {
        showNotification('Could not copy path', 'warning')
    })
}

const confirmDeleteThreat = (threat) => {
    threatToDelete.value = threat
    showDeleteThreatModal.value = true
}

const executeDeleteThreat = async () => {
    if (!threatToDelete.value) return
    deletingThreat.value = true
    try {
        const response = await axios.post('/shield/delete', { id: threatToDelete.value.id })
        if (response.data.success) {
            showNotification('File deleted permanently', 'success')
            showDeleteThreatModal.value = false
            await loadStatus()
        }
    } catch (error) {
        showNotification('Delete failed', 'danger')
    } finally {
        deletingThreat.value = false
    }
}

// Helpers for paths
const getFileName = (path) => {
    if (!path) return ''
    return path.split('/').pop() || path
}

const getDirName = (path) => {
    if (!path) return ''
    const parts = path.split('/')
    parts.pop()
    return parts.join('/') || '/'
}

const formatBytes = (bytes, decimals = 2) => {
    if (!bytes || bytes === 0) return '0 B'
    const k = 1024
    const dm = decimals < 0 ? 0 : decimals
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

// Filtering & Pagination computed
const setStatusFilter = (val) => {
    statusFilter.value = val
    currentPage.value = 1
}

const activeCount = computed(() => threats.value.filter(t => t.status === 'detected').length)
const quarantinedCount = computed(() => threats.value.filter(t => t.status === 'quarantined').length)
const ignoredCount = computed(() => threats.value.filter(t => t.status === 'ignored').length)

const selectedThreats = computed(() => threats.value.filter(t => selectedThreatIds.value.includes(t.id)))
const selectedQuarantinedCount = computed(() => selectedThreats.value.filter(t => t.status === 'quarantined').length)
const isAllPageSelected = computed(() => paginatedThreats.value.length > 0 && paginatedThreats.value.every(t => selectedThreatIds.value.includes(t.id)))
const isSomePageSelected = computed(() => paginatedThreats.value.some(t => selectedThreatIds.value.includes(t.id)) && !isAllPageSelected.value)

const filteredThreats = computed(() => {
    let list = threats.value
    if (statusFilter.value !== 'all') {
        list = list.filter(t => t.status === statusFilter.value)
    }
    if (searchQuery.value && searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        list = list.filter(t => 
            (t.file_path && t.file_path.toLowerCase().includes(q)) || 
            (t.type && t.type.toLowerCase().includes(q)) ||
            (t.details && t.details.toLowerCase().includes(q))
        )
    }
    return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredThreats.value.length / perPage.value)))

const paginatedThreats = computed(() => {
    const start = (currentPage.value - 1) * perPage.value
    return filteredThreats.value.slice(start, start + perPage.value)
})

const visiblePages = computed(() => {
    const total = totalPages.value
    const cur = currentPage.value
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1)
    }
    if (cur <= 4) {
        return [1, 2, 3, 4, 5, '...', total]
    }
    if (cur >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total]
    }
    return [1, '...', cur - 1, cur, cur + 1, '...', total]
})

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
    }
}

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'detected': return 'border-danger text-danger bg-danger-subtle'
        case 'quarantined': return 'border-warning text-warning bg-warning-subtle'
        case 'deleted': return 'border-secondary text-secondary'
        case 'ignored': return 'border-success text-success bg-success-subtle'
        default: return 'border-secondary text-secondary'
    }
}

const showNotification = (message, type = 'success') => {
    toastMessage.value = message
    toastType.value = type
    showToast.value = true
    setTimeout(() => { showToast.value = false }, 4000)
}

onMounted(() => {
    loadStatus()
})

import { onUnmounted } from 'vue';
onUnmounted(() => {
    stopPolling()
})
</script>

<style scoped>
.w-fit-content {
    width: fit-content;
}
.hover-bg-gray:hover {
    background-color: #f8f9fa !important;
    transition: background-color 0.2s ease;
}
.max-width-300 {
    max-width: 300px;
}
.avatar-xxs {
    width: 24px;
    height: 24px;
}
.bg-gray-100 {
    background-color: #f8f9fa !important;
}
.badge {
    text-transform: uppercase;
    font-weight: 700;
}
.rounded-pill {
    border-radius: 50rem !important;
}
.py-6 {
    padding-top: 4rem !important;
    padding-bottom: 4rem !important;
}
</style>
