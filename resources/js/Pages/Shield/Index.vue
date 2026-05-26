<template>
    <MainLayout>
        <div class="container-fluid py-4 bg-gray-100 min-vh-100">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="font-weight-bolder mb-0">Nimbus Shield</h3>
                    <p class="text-sm mb-0 text-secondary">Advanced real-time protection & firewall management.</p>
                </div>
                <div class="d-flex gap-2">
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
                                    <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'bg-white shadow text-dark' : 'text-secondary'" class="btn btn-link btn-sm mb-0 px-4 py-2 border-radius-md text-capitalize font-weight-bold">
                                        <i class="material-symbols-rounded text-sm me-1">settings</i> Settings
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6 text-end">
                            <div v-if="activeTab === 'threats'" class="input-group input-group-sm w-50 ms-auto">
                                <span class="input-group-text text-body border-0 bg-gray-100"><i class="material-symbols-rounded text-sm">search</i></span>
                                <input v-model="searchQuery" type="text" class="form-control border-0 bg-gray-100 px-2" placeholder="Search threats...">
                            </div>
                            <div v-if="activeTab === 'firewall'" class="d-flex justify-content-end gap-2">
                                <button @click="showAddRuleModal = true" class="btn btn-dark btn-sm mb-0 shadow-sm">
                                    <i class="material-symbols-rounded text-sm me-1">add</i> New Rule
                                </button>
                                <button @click="toggleFirewall" class="btn btn-sm mb-0 shadow-sm" :class="stats.firewall_status === 'Active' ? 'btn-outline-danger' : 'btn-success'">
                                    {{ stats.firewall_status === 'Active' ? 'Disable UFW' : 'Enable UFW' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0 pt-3">
                    <!-- Threats Tab -->
                    <div v-if="activeTab === 'threats'">
                        <div v-if="loading" class="text-center py-6">
                            <div class="spinner-border text-dark" role="status"></div>
                            <p class="text-xs text-secondary mt-2">Loading system logs...</p>
                        </div>
                        <div v-else-if="filteredThreats.length === 0" class="text-center py-6">
                            <div class="mb-3">
                                <i class="material-symbols-rounded text-success" style="font-size: 64px;">verified_user</i>
                            </div>
                            <h6 class="text-dark">No Threats Found</h6>
                            <p class="text-xs text-secondary">Your system is clean and all scans returned positive results.</p>
                        </div>
                        <div v-else class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-4">Security Threat</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Detection Type</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Time</th>
                                        <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Action Taken</th>
                                        <th class="text-secondary opacity-7"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="threat in filteredThreats" :key="threat.id" class="hover-bg-gray">
                                        <td class="ps-4">
                                            <div class="d-flex py-1">
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-0 text-sm font-weight-bold">{{ threat.file_path }}</h6>
                                                    <p class="text-xs text-secondary mb-0 text-wrap max-width-300">{{ threat.details }}</p>
                                                </div>
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
                                            <span class="badge badge-sm rounded-pill" :class="getStatusBadgeClass(threat.status)">
                                                {{ threat.status }}
                                            </span>
                                        </td>
                                        <td class="align-middle text-right px-4">
                                            <div class="d-flex gap-2 justify-content-end">
                                                <button v-if="threat.status === 'detected'" @click="quarantineThreat(threat.id)" class="btn btn-link text-warning text-gradient p-0 mb-0" title="Quarantine">
                                                    <i class="material-symbols-rounded">inventory_2</i>
                                                </button>
                                                <button v-if="threat.status === 'quarantined'" @click="restoreThreat(threat.id)" class="btn btn-link text-success text-gradient p-0 mb-0" title="Restore File">
                                                    <i class="material-symbols-rounded">restore_page</i>
                                                </button>
                                                <button @click="confirmDeleteThreat(threat)" class="btn btn-link text-danger text-gradient p-0 mb-0" title="Delete">
                                                    <i class="material-symbols-rounded">delete</i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
                                                <span class="text-xs text-secondary">Server time: {{ new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</span>
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

const loading = ref(true)
const scanning = ref(false)
const threats = ref([])
const stats = ref({
    active_threats: 0,
    quarantined: 0,
    last_scan: 'Never',
    firewall_status: 'Checking...',
    scan_status: 'idle',
    tools_installed: { all: true }, // Default to true to avoid flicker
    install_status: 'idle',
    auto_scan_enabled: false,
    auto_scan_time: '03:00',
    auto_quarantine: false,
    email_alerts: false,
    alert_emails: ''
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
            alert_emails: stats.value.alert_emails
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
const newRule = ref({
    port: '',
    action: 'allow',
    proto: 'tcp'
})

const searchQuery = ref('')
const showToast = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
let statusInterval = null

const loadStatus = async () => {
    try {
        const response = await axios.get('/shield/status')
        if (response.data.success) {
            threats.value = response.data.threats
            stats.value = response.data.stats
            
            // Sync scanning state with backend
            scanning.value = stats.value.scan_status === 'running'
            
            if (scanning.value) {
                startPolling()
            } else {
                stopPolling()
            }
        }
    } catch (error) {
        // Only show error if not polling
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
        // We'll need a backend method for this, I'll add it to ShieldController later or use index
        // For now let's assume we use 'ufw delete [index]'
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
    }
})

const startPolling = () => {
    if (statusInterval) return
    statusInterval = setInterval(loadStatus, 5000) // Poll every 5 seconds
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

const quarantineThreat = async (id) => {
    if (!confirm('Are you sure you want to quarantine this file? It will be moved to a secure location and made inaccessible.')) return
    try {
        const response = await axios.post('/shield/quarantine', { id })
        if (response.data.success) {
            showNotification('File quarantined', 'success')
            await loadStatus()
        }
    } catch (error) {
        showNotification('Quarantine failed', 'danger')
    }
}

const restoreThreat = async (id) => {
    if (!confirm('Are you sure you want to restore this file to its original location? Only do this if you are certain it is safe.')) return
    try {
        const response = await axios.post('/shield/restore', { id })
        if (response.data.success) {
            showNotification('File restored successfully', 'success')
            await loadStatus()
        }
    } catch (error) {
        showNotification(error.response?.data?.error || 'Restore failed', 'danger')
    }
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

const filteredThreats = computed(() => {
    if (!searchQuery.value) return threats.value
    const q = searchQuery.value.toLowerCase()
    return threats.value.filter(t => 
        t.file_path.toLowerCase().includes(q) || 
        t.type.toLowerCase().includes(q)
    )
})

const getStatusBadgeClass = (status) => {
    switch(status) {
        case 'detected': return 'border-danger text-danger'
        case 'quarantined': return 'border-warning text-warning'
        case 'deleted': return 'border-secondary text-secondary'
        default: return 'border-secondary text-secondary'
    }
}

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    }).format(date);
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
