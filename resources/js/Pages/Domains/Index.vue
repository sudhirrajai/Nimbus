<template>
  <MainLayout>
    <Head title="Domains" />
    <div class="container-fluid py-4">

      <!-- Page Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <h4 class="font-weight-bolder mb-0">Domain Management</h4>
              <p class="mb-0 text-sm">Manage your hosted domains and websites</p>
            </div>
            <button v-if="isRootOrAdmin" class="btn bg-gradient-dark mb-0 d-flex align-items-center gap-1" @click="openAddModal">
              <i class="material-symbols-rounded text-sm">add</i>
              Add Domain
            </button>
          </div>
        </div>
      </div>

      <!-- Alert Messages -->
      <div class="row" v-if="alert.show">
        <div class="col-12">
          <div :class="`alert alert-${alert.type} alert-dismissible fade show`" role="alert">
            <span class="alert-icon"><i class="material-symbols-rounded">{{ alert.type === 'success' ? 'check_circle' : 'error' }}</i></span>
            <span class="alert-text"><strong>{{ alert.type === 'success' ? 'Success!' : 'Error!' }}</strong> {{ alert.message }}</span>
            <button type="button" class="btn-close" @click="alert.show = false"></button>
          </div>
        </div>
      </div>

      <!-- Domain Table Card -->
      <div class="row">
        <div class="col-12">
          <div class="card shadow-sm border">
            <!-- Card Header: Title, View Switcher & Search Controls -->
            <div class="card-header pb-3 pt-3 border-bottom">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                
                <!-- Left: Title & View Tabs -->
                <div class="d-flex flex-wrap align-items-center gap-3">
                  <h6 class="mb-0 font-weight-bolder text-dark">Your Domains</h6>
                  
                  <!-- Segmented View Mode Switcher -->
                  <div class="domain-view-toggle d-flex p-1 bg-gray-100 rounded-3">
                    <button 
                      type="button" 
                      class="btn btn-sm mb-0 px-3 py-1 text-xs font-weight-bold rounded-2 transition-all d-flex align-items-center border-0"
                      :class="activeTab === 'grouped' ? 'bg-white text-dark shadow-xs active-view-btn' : 'text-secondary bg-transparent'"
                      @click="switchTab('grouped')"
                    >
                      <i class="material-symbols-rounded text-sm me-1">account_tree</i>
                      Grouped View
                      <span class="badge ms-2" :class="activeTab === 'grouped' ? 'bg-dark text-white' : 'bg-secondary text-white'" style="font-size: 0.65rem;">
                        {{ domainGroups.length }}
                      </span>
                    </button>
                    
                    <button 
                      type="button" 
                      class="btn btn-sm mb-0 px-3 py-1 text-xs font-weight-bold rounded-2 transition-all d-flex align-items-center border-0"
                      :class="activeTab === 'flat' ? 'bg-white text-dark shadow-xs active-view-btn' : 'text-secondary bg-transparent'"
                      @click="switchTab('flat')"
                    >
                      <i class="material-symbols-rounded text-sm me-1">table_rows</i>
                      All Domains
                      <span class="badge ms-2" :class="activeTab === 'flat' ? 'bg-dark text-white' : 'bg-secondary text-white'" style="font-size: 0.65rem;">
                        {{ domains.length }}
                      </span>
                    </button>
                  </div>
                </div>

                <!-- Right: Expand All & Search -->
                <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                  <!-- Expand All / Collapse All (Grouped View only) -->
                  <button 
                    v-if="activeTab === 'grouped' && hasAnySubdomains" 
                    type="button" 
                    class="btn btn-outline-secondary btn-sm mb-0 px-2 py-1 text-xs d-flex align-items-center gap-1"
                    @click="toggleExpandAll"
                    :title="allExpanded ? 'Collapse all subdomains' : 'Expand all subdomains'"
                  >
                    <i class="material-symbols-rounded text-xs">{{ allExpanded ? 'unfold_less' : 'unfold_more' }}</i>
                    {{ allExpanded ? 'Collapse All' : 'Expand All' }}
                  </button>

                  <!-- Search input -->
                  <div class="input-group input-group-sm" style="min-width: 180px; max-width: 240px;">
                    <span class="input-group-text text-body"><i class="material-symbols-rounded text-sm">search</i></span>
                    <input 
                      v-model="searchQuery" 
                      type="text" 
                      class="form-control" 
                      placeholder="Search domains..."
                      @input="currentPage = 1"
                    >
                    <button v-if="searchQuery" class="btn btn-link text-secondary p-0 px-2 mb-0" type="button" @click="searchQuery = ''">
                      <i class="material-symbols-rounded text-xs">close</i>
                    </button>
                  </div>
                </div>

              </div>
            </div>

            <!-- Table Body -->
            <div class="card-body px-0 pt-0 pb-2">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="min-width: 260px;">
                        Domain Name
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Status
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Storage
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2" style="min-width: 200px;">
                        Document Root
                      </th>
                      <th v-if="isRoot" class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Created At
                      </th>
                      <th v-if="isRoot" class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                        Created By
                      </th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="min-width: 180px;">
                        Actions
                      </th>
                    </tr>
                  </thead>

                  <tbody>
                    <!-- ========================================== -->
                    <!-- TAB 1: GROUPED / STACKED VIEW (DEFAULT)   -->
                    <!-- ========================================== -->
                    <template v-if="activeTab === 'grouped' && !loading">
                      <template v-for="group in paginatedGroups" :key="group.main.name">
                        <!-- Main Domain Row -->
                        <tr 
                          :class="{ 'opacity-6': !group.main.is_active, 'group-parent-expanded': isExpanded(group.main.name) && group.subdomains.length > 0 }" 
                          class="domain-row parent-row"
                        >
                          <td>
                            <div class="d-flex align-items-center px-3 py-2">
                              <!-- Expand/Collapse Chevron Button -->
                              <button
                                v-if="group.subdomains.length > 0"
                                type="button"
                                class="btn btn-link p-0 me-2 mb-0 expand-chevron-btn"
                                :class="{ 'expanded': isExpanded(group.main.name) }"
                                @click="toggleExpand(group.main.name)"
                                :title="isExpanded(group.main.name) ? 'Collapse subdomains' : 'Expand subdomains'"
                              >
                                <i class="material-symbols-rounded">chevron_right</i>
                              </button>
                              <span v-else class="expand-placeholder me-2"></span>

                              <!-- Domain Info & Badges -->
                              <div class="d-flex flex-column justify-content-center">
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                  <h6 class="mb-0 text-sm font-weight-bold text-dark">
                                    {{ group.main.name }}
                                  </h6>
                                  
                                  <span v-if="group.main.php_version" class="badge bg-light text-dark ms-1 text-xxs font-weight-bold border" style="padding: 2px 6px;">
                                    PHP {{ group.main.php_version }}
                                  </span>

                                  <!-- Subdomain Count Pill Badge -->
                                  <button
                                    v-if="group.subdomains.length > 0"
                                    type="button"
                                    class="badge subdomain-pill-btn border-0 ms-1"
                                    :class="isExpanded(group.main.name) ? 'subdomain-pill-active' : ''"
                                    @click="toggleExpand(group.main.name)"
                                    :title="isExpanded(group.main.name) ? 'Click to collapse subdomains' : 'Click to view subdomains'"
                                  >
                                    <i class="material-symbols-rounded text-xxs me-1">account_tree</i>
                                    {{ group.subdomains.length }} {{ group.subdomains.length === 1 ? 'subdomain' : 'subdomains' }}
                                  </button>

                                  <i v-if="group.main.is_active === false" class="material-symbols-rounded text-xs text-warning ms-1" title="DNS not pointing to this server">warning</i>
                                  <i v-if="group.main.is_active === null" class="spinner-border spinner-border-sm ms-1" style="width: 10px; height: 10px; border-width: 1px;"></i>
                                </div>
                                <p class="text-xs text-secondary mb-0 opacity-7">/var/www/{{ group.main.name }}</p>
                              </div>
                            </div>
                          </td>

                          <!-- Status -->
                          <td>
                            <span v-if="group.main.is_active === null" class="status-pill opacity-5">
                              <span class="spinner-border spinner-border-sm me-2" style="width: 10px; height: 10px; border-width: 1px;"></span>
                              Checking...
                            </span>
                            <span v-else-if="group.main.is_active" class="status-pill status-active">
                              <span class="pill-dot"></span>
                              Active
                            </span>
                            <span v-else class="status-pill status-configuring" :title="`Point A record to ${group.main.server_ip || serverIp}`">
                              <span class="pill-dot"></span>
                              Configuring
                            </span>
                          </td>

                          <!-- Storage -->
                          <td>
                            <span v-if="group.main.storage === null" class="text-xs text-muted">
                              <span class="spinner-border spinner-border-sm" style="width: 10px; height: 10px; border-width: 1px;"></span>
                            </span>
                            <span v-else class="text-xs font-weight-bold text-dark">{{ group.main.storage }}</span>
                          </td>

                          <!-- Document Root -->
                          <td>
                            <div class="d-flex align-items-center">
                              <span class="text-xs text-secondary mb-0 me-2 text-truncate" style="max-width: 220px;" :title="group.main.document_root || ('/var/www/' + group.main.name)">
                                {{ group.main.document_root || ('/var/www/' + group.main.name) }}
                              </span>
                              <button v-if="isRootOrAdmin" class="btn btn-link text-secondary p-0 mb-0 btn-edit-root" @click="openRootModal(group.main)" title="Change document root">
                                <i class="material-symbols-rounded text-xs">edit</i>
                              </button>
                            </div>
                          </td>

                          <!-- Created At -->
                          <td v-if="isRoot">
                            <span class="text-xs font-weight-bold text-dark">{{ group.main.created_at || 'Unknown' }}</span>
                          </td>

                          <!-- Created By -->
                          <td v-if="isRoot">
                            <span class="text-xs font-weight-bold text-dark">{{ group.main.created_by || 'System' }}</span>
                          </td>

                          <!-- Actions -->
                          <td class="align-middle text-center">
                            <div class="d-flex justify-content-center gap-2">
                              <button 
                                class="action-btn btn-view" 
                                @click="viewWebsite(group.main.name)"
                                title="View website"
                              >
                                <i class="material-symbols-rounded">visibility</i>
                              </button>
                              <button 
                                v-if="isRootOrAdmin || hasPerm('files')"
                                class="action-btn btn-folder" 
                                @click="openFileManager(group.main.name)"
                                title="File Manager"
                              >
                                <i class="material-symbols-rounded">folder</i>
                              </button>
                              <button 
                                v-if="isRootOrAdmin"
                                class="action-btn btn-php" 
                                @click="openPhpVersionModal(group.main)"
                                title="Switch PHP Version"
                              >
                                <i class="material-symbols-rounded">published_with_changes</i>
                              </button>
                              <button 
                                v-if="isRootOrAdmin"
                                class="action-btn btn-edit" 
                                @click="openEditModal(group.main.name)"
                                title="Edit domain"
                              >
                                <i class="material-symbols-rounded">edit</i>
                              </button>
                              <button 
                                v-if="isRootOrAdmin"
                                class="action-btn btn-delete" 
                                @click="confirmDelete(group.main.name)"
                                title="Delete domain"
                              >
                                <i class="material-symbols-rounded">delete</i>
                              </button>
                            </div>
                          </td>
                        </tr>

                        <!-- Subdomain Rows (Nested Under Parent) -->
                        <template v-if="isExpanded(group.main.name) && group.subdomains.length > 0">
                          <tr 
                            v-for="(sub, subIdx) in group.subdomains" 
                            :key="sub.name"
                            class="domain-row subdomain-row"
                            :class="{ 'opacity-6': !sub.is_active, 'last-subdomain-row': subIdx === group.subdomains.length - 1 }"
                          >
                            <td>
                              <div class="d-flex align-items-center py-2 ps-4 pe-3 position-relative">
                                <!-- Tree Line Connector -->
                                <div class="tree-guide-branch" :class="{ 'is-last': subIdx === group.subdomains.length - 1 }"></div>
                                
                                <div class="d-flex flex-column justify-content-center ms-3">
                                  <div class="d-flex align-items-center flex-wrap gap-1">
                                    <span class="subdomain-badge-tag">SUB</span>
                                    <h6 class="mb-0 text-sm font-weight-bold text-dark">
                                      {{ sub.name }}
                                    </h6>
                                    <span v-if="sub.php_version" class="badge bg-light text-dark text-xxs font-weight-bold border" style="padding: 2px 6px;">
                                      PHP {{ sub.php_version }}
                                    </span>
                                    <i v-if="sub.is_active === false" class="material-symbols-rounded text-xs text-warning ms-1" title="DNS not pointing to this server">warning</i>
                                    <i v-if="sub.is_active === null" class="spinner-border spinner-border-sm ms-1" style="width: 10px; height: 10px; border-width: 1px;"></i>
                                  </div>
                                  <p class="text-xs text-secondary mb-0 opacity-7">/var/www/{{ sub.name }}</p>
                                </div>
                              </div>
                            </td>

                            <!-- Subdomain Status -->
                            <td>
                              <span v-if="sub.is_active === null" class="status-pill opacity-5">
                                <span class="spinner-border spinner-border-sm me-2" style="width: 10px; height: 10px; border-width: 1px;"></span>
                                Checking...
                              </span>
                              <span v-else-if="sub.is_active" class="status-pill status-active">
                                <span class="pill-dot"></span>
                                Active
                              </span>
                              <span v-else class="status-pill status-configuring" :title="`Point A record to ${sub.server_ip || serverIp}`">
                                <span class="pill-dot"></span>
                                Configuring
                              </span>
                            </td>

                            <!-- Subdomain Storage -->
                            <td>
                              <span v-if="sub.storage === null" class="text-xs text-muted">
                                <span class="spinner-border spinner-border-sm" style="width: 10px; height: 10px; border-width: 1px;"></span>
                              </span>
                              <span v-else class="text-xs font-weight-bold text-dark">{{ sub.storage }}</span>
                            </td>

                            <!-- Subdomain Document Root -->
                            <td>
                              <div class="d-flex align-items-center">
                                <span class="text-xs text-secondary mb-0 me-2 text-truncate" style="max-width: 220px;" :title="sub.document_root || ('/var/www/' + sub.name)">
                                  {{ sub.document_root || ('/var/www/' + sub.name) }}
                                </span>
                                <button v-if="isRootOrAdmin" class="btn btn-link text-secondary p-0 mb-0 btn-edit-root" @click="openRootModal(sub)" title="Change document root">
                                  <i class="material-symbols-rounded text-xs">edit</i>
                                </button>
                              </div>
                            </td>

                            <!-- Subdomain Created At -->
                            <td v-if="isRoot">
                              <span class="text-xs font-weight-bold text-dark">{{ sub.created_at || 'Unknown' }}</span>
                            </td>

                            <!-- Subdomain Created By -->
                            <td v-if="isRoot">
                              <span class="text-xs font-weight-bold text-dark">{{ sub.created_by || 'System' }}</span>
                            </td>

                            <!-- Subdomain Actions -->
                            <td class="align-middle text-center">
                              <div class="d-flex justify-content-center gap-2">
                                <button 
                                  class="action-btn btn-view" 
                                  @click="viewWebsite(sub.name)"
                                  title="View website"
                                >
                                  <i class="material-symbols-rounded">visibility</i>
                                </button>
                                <button 
                                  v-if="isRootOrAdmin || hasPerm('files')"
                                  class="action-btn btn-folder" 
                                  @click="openFileManager(sub.name)"
                                  title="File Manager"
                                >
                                  <i class="material-symbols-rounded">folder</i>
                                </button>
                                <button 
                                  v-if="isRootOrAdmin"
                                  class="action-btn btn-php" 
                                  @click="openPhpVersionModal(sub)"
                                  title="Switch PHP Version"
                                >
                                  <i class="material-symbols-rounded">published_with_changes</i>
                                </button>
                                <button 
                                  v-if="isRootOrAdmin"
                                  class="action-btn btn-edit" 
                                  @click="openEditModal(sub.name)"
                                  title="Edit domain"
                                >
                                  <i class="material-symbols-rounded">edit</i>
                                </button>
                                <button 
                                  v-if="isRootOrAdmin"
                                  class="action-btn btn-delete" 
                                  @click="confirmDelete(sub.name)"
                                  title="Delete domain"
                                >
                                  <i class="material-symbols-rounded">delete</i>
                                </button>
                              </div>
                            </td>
                          </tr>
                        </template>
                      </template>
                    </template>

                    <!-- ========================================== -->
                    <!-- TAB 2: FLAT / ALL DOMAINS VIEW             -->
                    <!-- ========================================== -->
                    <template v-if="activeTab === 'flat' && !loading">
                      <tr v-for="domain in paginatedFlatDomains" :key="domain.name" :class="{ 'opacity-6': !domain.is_active }" class="domain-row">
                        <td>
                          <div class="d-flex px-3 py-2">
                            <div class="d-flex flex-column justify-content-center">
                              <div class="d-flex align-items-center flex-wrap gap-1">
                                <h6 class="mb-0 text-sm font-weight-bold">
                                  {{ domain.name }}
                                </h6>
                                <span v-if="domain.php_version" class="badge bg-light text-dark ms-2 text-xxs font-weight-bold border" style="padding: 2px 6px;">
                                  PHP {{ domain.php_version }}
                                </span>
                                <i v-if="domain.is_active === false" class="material-symbols-rounded text-xs text-warning ms-1" title="DNS not pointing to this server">warning</i>
                                <i v-if="domain.is_active === null" class="spinner-border spinner-border-sm ms-1" style="width: 10px; height: 10px; border-width: 1px;"></i>
                              </div>
                              <p class="text-xs text-secondary mb-0 opacity-7">/var/www/{{ domain.name }}</p>
                            </div>
                          </div>
                        </td>
                        <td>
                          <span v-if="domain.is_active === null" class="status-pill opacity-5">
                            <span class="spinner-border spinner-border-sm me-2" style="width: 10px; height: 10px; border-width: 1px;"></span>
                            Checking...
                          </span>
                          <span v-else-if="domain.is_active" class="status-pill status-active">
                            <span class="pill-dot"></span>
                            Active
                          </span>
                          <span v-else class="status-pill status-configuring" :title="`Point A record to ${domain.server_ip || serverIp}`">
                            <span class="pill-dot"></span>
                            Configuring
                          </span>
                        </td>
                        <td>
                          <span v-if="domain.storage === null" class="text-xs text-muted">
                            <span class="spinner-border spinner-border-sm" style="width: 10px; height: 10px; border-width: 1px;"></span>
                          </span>
                          <span v-else class="text-xs font-weight-bold text-dark">{{ domain.storage }}</span>
                        </td>
                        <td>
                          <div class="d-flex align-items-center">
                            <span class="text-xs text-secondary mb-0 me-2 text-truncate" style="max-width: 220px;" :title="domain.document_root || ('/var/www/' + domain.name)">
                              {{ domain.document_root || ('/var/www/' + domain.name) }}
                            </span>
                            <button v-if="isRootOrAdmin" class="btn btn-link text-secondary p-0 mb-0 btn-edit-root" @click="openRootModal(domain)" title="Change document root">
                              <i class="material-symbols-rounded text-xs">edit</i>
                            </button>
                          </div>
                        </td>
                        <td v-if="isRoot">
                          <span class="text-xs font-weight-bold text-dark">{{ domain.created_at || 'Unknown' }}</span>
                        </td>
                        <td v-if="isRoot">
                          <span class="text-xs font-weight-bold text-dark">{{ domain.created_by || 'System' }}</span>
                        </td>
                        <td class="align-middle text-center">
                          <div class="d-flex justify-content-center gap-2">
                            <button 
                              class="action-btn btn-view" 
                              @click="viewWebsite(domain.name)"
                              title="View website"
                            >
                              <i class="material-symbols-rounded">visibility</i>
                            </button>
                            <button 
                              v-if="isRootOrAdmin || hasPerm('files')"
                              class="action-btn btn-folder" 
                              @click="openFileManager(domain.name)"
                              title="File Manager"
                            >
                              <i class="material-symbols-rounded">folder</i>
                            </button>
                            <button 
                              v-if="isRootOrAdmin"
                              class="action-btn btn-php" 
                              @click="openPhpVersionModal(domain)"
                              title="Switch PHP Version"
                            >
                              <i class="material-symbols-rounded">published_with_changes</i>
                            </button>
                            <button 
                              v-if="isRootOrAdmin"
                              class="action-btn btn-edit" 
                              @click="openEditModal(domain.name)"
                              title="Edit domain"
                            >
                              <i class="material-symbols-rounded">edit</i>
                            </button>
                            <button 
                              v-if="isRootOrAdmin"
                              class="action-btn btn-delete" 
                              @click="confirmDelete(domain.name)"
                              title="Delete domain"
                            >
                              <i class="material-symbols-rounded">delete</i>
                            </button>
                          </div>
                        </td>
                      </tr>
                    </template>

                    <!-- Empty State -->
                    <tr v-if="!loading && ((activeTab === 'grouped' && filteredGroups.length === 0) || (activeTab === 'flat' && filteredFlatDomains.length === 0))">
                      <td :colspan="isRoot ? 7 : 5" class="text-center py-5">
                        <div class="empty-state">
                          <i class="material-symbols-rounded text-secondary opacity-3" style="font-size: 64px;">language</i>
                          <p class="text-secondary mt-3">
                            {{ searchQuery ? `No domains found matching "${searchQuery}".` : 'No domains found. Add your first domain to get started.' }}
                          </p>
                          <button v-if="searchQuery" class="btn btn-outline-secondary btn-sm" @click="searchQuery = ''">
                            Clear search
                          </button>
                        </div>
                      </td>
                    </tr>

                    <!-- Loading Spinner -->
                    <tr v-if="loading">
                      <td :colspan="isRoot ? 7 : 5" class="text-center py-5">
                        <div class="spinner-border text-dark" role="status" style="width: 3rem; height: 3rem;">
                          <span class="visually-hidden">Loading...</span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Pagination Footer -->
              <div v-if="totalItemCount > itemsPerPage" class="d-flex flex-wrap justify-content-between align-items-center p-3 border-top gap-2">
                <div class="text-xs text-secondary">
                  Showing {{ paginationStart + 1 }} to {{ Math.min(paginationEnd, totalItemCount) }} of {{ totalItemCount }} {{ activeTab === 'grouped' ? 'domain groups' : 'domains' }}
                </div>
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button class="page-link" @click="currentPage--" aria-label="Previous" :disabled="currentPage === 1">
                      <i class="material-symbols-rounded text-xs">chevron_left</i>
                    </button>
                  </li>
                  <li v-for="page in totalPages" :key="page" class="page-item" :class="{ active: currentPage === page }">
                    <button class="page-link" @click="currentPage = page">{{ page }}</button>
                  </li>
                  <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button class="page-link" @click="currentPage++" aria-label="Next" :disabled="currentPage === totalPages">
                      <i class="material-symbols-rounded text-xs">chevron_right</i>
                    </button>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add/Edit Domain Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">
                {{ isEdit ? "Edit Domain" : "Add New Domain" }}
              </h5>
              <button type="button" class="btn-close" @click="closeModal" :disabled="submitting"></button>
            </div>

            <div class="modal-body">
              <!-- DNS Tip -->
              <div v-if="!isEdit" class="alert alert-info py-2 mb-3 text-white">
                <div class="d-flex align-items-center">
                  <i class="material-symbols-rounded me-2 text-sm">info</i>
                  <small>
                    <strong>Tip:</strong> Point your domain's <strong>A record</strong> to 
                    <code class="text-white bg-dark px-1 rounded">{{ serverIp || 'fetching...' }}</code> 
                    before adding it here.
                  </small>
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="form-control-label">Domain Name</label>
                <div class="input-group input-group-outline" :class="{ 'is-invalid': validationError }">
                  <input 
                    type="text" 
                    v-model="domainInput" 
                    class="form-control"
                    :class="{ 'is-invalid': validationError }"
                    placeholder="example.com or sub.example.com"
                    @input="clearValidationError"
                    @keyup.enter="isEdit ? updateDomain() : saveDomain()"
                    :disabled="submitting"
                  >
                </div>
                <div class="invalid-feedback d-block" v-if="validationError">
                  {{ validationError }}
                </div>
                <small class="text-muted text-xs">
                  Tip: Subdomains (like <code>blog.example.com</code>) will automatically nest under their parent domain in Grouped View.
                </small>
              </div>

              <!-- PHP Version selection for new domains -->
              <div v-if="!isEdit" class="form-group mb-3">
                <label class="form-control-label">PHP Version</label>
                <select v-model="createPhpVersion" class="form-select" :disabled="submitting">
                  <option value="8.4">PHP 8.4</option>
                  <option value="8.3">PHP 8.3</option>
                  <option value="8.2">PHP 8.2 (Recommended)</option>
                  <option value="8.1">PHP 8.1</option>
                  <option value="8.0">PHP 8.0</option>
                  <option value="7.4">PHP 7.4</option>
                </select>
              </div>

            </div>

            <div class="modal-footer">
              <button 
                class="btn btn-outline-secondary mb-0" 
                @click="closeModal"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button 
                class="btn bg-gradient-dark mb-0" 
                @click="isEdit ? updateDomain() : saveDomain()"
                :disabled="submitting || !domainInput.trim()"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEdit ? "Update Domain" : "Create Domain" }}
              </button>
            </div>

          </div>
        </div>
      </div>

      <!-- PHP Version Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showPhpModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">
                Switch PHP Version
              </h5>
              <button type="button" class="btn-close" @click="closePhpModal" :disabled="submitting"></button>
            </div>

            <div class="modal-body">
              <p class="text-sm text-secondary mb-3">
                Select the PHP version for <strong>{{ selectedDomainName }}</strong>. The Nginx configuration will be updated and reloaded automatically.
              </p>

              <div v-if="loadingPhpVersions" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-dark" role="status"></div>
                <p class="text-xs text-secondary mt-2 mb-0">Detecting installed PHP versions...</p>
              </div>

              <div v-else>
                <div class="form-group mb-3">
                  <label class="form-control-label">Current Version: <strong class="text-dark">PHP {{ currentDomainPhpVersion }}</strong></label>
                  <select v-model="selectedPhpVersion" class="form-select" :disabled="submitting">
                    <option 
                      v-for="v in availablePhpVersions" 
                      :key="v.version" 
                      :value="v.version"
                    >
                      PHP {{ v.version }} {{ v.installed ? '(Installed)' : '(Will be installed)' }}
                    </option>
                  </select>
                </div>

                <div class="alert alert-info py-2 mb-0 text-white">
                  <div class="d-flex align-items-center">
                    <i class="material-symbols-rounded me-2 text-sm">info</i>
                    <small>
                      Switching PHP versions takes effect instantly with zero downtime.
                    </small>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button 
                class="btn btn-outline-secondary mb-0" 
                @click="closePhpModal"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button 
                class="btn bg-gradient-dark mb-0" 
                @click="updatePhpVersion"
                :disabled="submitting || loadingPhpVersions || selectedPhpVersion === currentDomainPhpVersion"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                Switch Version
              </button>
            </div>

          </div>
        </div>
      </div>

      <!-- Document Root Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showRootModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder">
                Change Document Root
              </h5>
              <button type="button" class="btn-close" @click="closeRootModal" :disabled="submitting"></button>
            </div>

            <div class="modal-body">
              <div class="alert alert-info py-2 mb-3 text-white">
                <div class="d-flex align-items-center">
                  <i class="material-symbols-rounded me-2 text-sm">info</i>
                  <small>
                    This changes where Nginx looks for your website files. It must be inside your domain's folder.
                  </small>
                </div>
              </div>

              <div class="form-group">
                <label class="form-control-label">Document Root Path</label>
                <div class="input-group input-group-outline" :class="{ 'is-invalid': rootValidationError }">
                  <input 
                    type="text" 
                    v-model="rootInput" 
                    class="form-control"
                    :class="{ 'is-invalid': rootValidationError }"
                    placeholder="/var/www/example.com/public"
                    @input="rootValidationError = ''"
                    @keyup.enter="updateDocumentRoot()"
                    :disabled="submitting"
                  >
                </div>
                <div class="invalid-feedback d-block" v-if="rootValidationError">
                  {{ rootValidationError }}
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button 
                class="btn btn-outline-secondary mb-0" 
                @click="closeRootModal"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button 
                class="btn bg-gradient-dark mb-0" 
                @click="updateDocumentRoot()"
                :disabled="submitting || !rootInput.trim()"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                Save
              </button>
            </div>

          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div class="modal fade show" tabindex="-1" style="display:block" v-if="showDeleteModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

            <div class="modal-header">
              <h5 class="modal-title font-weight-bolder text-danger">
                <i class="material-symbols-rounded me-1">warning</i>
                Confirm Deletion
              </h5>
              <button type="button" class="btn-close" @click="showDeleteModal = false" :disabled="submitting"></button>
            </div>

            <div class="modal-body">
              <p class="mb-0">
                Are you sure you want to delete <strong>{{ domainToDelete }}</strong>?
              </p>
              <p class="text-sm text-danger mb-0 mt-2">
                <i class="material-symbols-rounded text-sm me-1">info</i>
                This will permanently delete the domain folder and all its contents. This action cannot be undone.
              </p>
            </div>

            <div class="modal-footer">
              <button 
                class="btn btn-outline-secondary mb-0" 
                @click="showDeleteModal = false"
                :disabled="submitting"
              >
                Cancel
              </button>
              <button 
                class="btn bg-gradient-danger mb-0" 
                @click="deleteDomain"
                :disabled="submitting"
              >
                <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status"></span>
                Delete Domain
              </button>
            </div>

          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { Head, router, usePage } from '@inertiajs/vue3'

const page = usePage()
const userRole = computed(() => page.props.auth?.user?.role || 'user')
const isRoot = computed(() => page.props.auth?.user?.is_root || userRole.value === 'root')
const isRootOrAdmin = computed(() => isRoot.value || userRole.value === 'admin')
const userPermissions = computed(() => page.props.auth?.user?.permissions || [])
const hasPerm = (perm) => isRoot.value || userPermissions.value.includes(perm)

// Main State
const domains = ref([])
const activeTab = ref('grouped') // 'grouped' (default) | 'flat'
const expandedDomains = ref(new Set())
const loadedDetails = ref(new Set())
const searchQuery = ref("")
const currentPage = ref(1)
const itemsPerPage = ref(10)
const serverIp = ref("")
const loading = ref(false)
const submitting = ref(false)

// Modals State
const showModal = ref(false)
const showDeleteModal = ref(false)
const showRootModal = ref(false)
const showPhpModal = ref(false)
const isEdit = ref(false)
const domainInput = ref("")
const createPhpVersion = ref("8.2")
const rootInput = ref("")
const oldDomain = ref("")
const domainToDelete = ref("")
const validationError = ref("")
const rootValidationError = ref("")

const selectedDomainName = ref("")
const currentDomainPhpVersion = ref("")
const selectedPhpVersion = ref("")
const availablePhpVersions = ref([])
const loadingPhpVersions = ref(false)

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

onMounted(() => {
  loadDomains()
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => {
    alert.value.show = false
  }, 5000)
}

// ==========================================
// GROUPING ENGINE & HIERARCHY LOGIC
// ==========================================

/**
 * Groups all domains into { main: domainObj, subdomains: [domainObj, ...] }
 * A domain D is classified as a subdomain of P if D.name ends with '.' + P.name.
 * If multiple match, the root ancestor (shortest domain name) is selected so all
 * subdomains are neatly stacked under their primary root domain.
 */
const domainGroups = computed(() => {
  const all = [...domains.value]
  if (all.length === 0) return []

  // Sort by domain name length ascending so root domains come first
  const sortedByLength = [...all].sort((a, b) => a.name.length - b.name.length)
  
  const groupsMap = new Map() // key: main domain name, value: { main, subdomains: [] }

  for (const domain of sortedByLength) {
    const dName = domain.name.toLowerCase().trim()
    let matchedParentKey = null

    // Check if domain is a subdomain of any existing group root
    for (const [parentName] of groupsMap.entries()) {
      if (dName.endsWith('.' + parentName.toLowerCase())) {
        matchedParentKey = parentName
        break
      }
    }

    if (matchedParentKey) {
      groupsMap.get(matchedParentKey).subdomains.push(domain)
    } else {
      // It's a top-level / main domain
      groupsMap.set(domain.name, {
        main: domain,
        subdomains: []
      })
    }
  }

  return Array.from(groupsMap.values())
})

/**
 * Filtered groups according to search query.
 * If search matches a subdomain, the main domain is displayed and auto-expanded!
 */
const filteredGroups = computed(() => {
  if (!searchQuery.value.trim()) return domainGroups.value
  const q = searchQuery.value.toLowerCase().trim()

  const result = []
  for (const group of domainGroups.value) {
    const mainMatches = group.main.name.toLowerCase().includes(q)
    const matchingSubdomains = group.subdomains.filter(s => s.name.toLowerCase().includes(q))

    if (mainMatches) {
      // Main domain matches query: keep all subdomains
      result.push(group)
    } else if (matchingSubdomains.length > 0) {
      // Subdomain matches: show group with matching subdomains
      result.push({
        main: group.main,
        subdomains: matchingSubdomains,
        hasAutoExpandedMatch: true
      })
    }
  }
  return result
})

// Flat view filtered list
const filteredFlatDomains = computed(() => {
  if (!searchQuery.value.trim()) return domains.value
  const q = searchQuery.value.toLowerCase().trim()
  return domains.value.filter(domain => domain.name.toLowerCase().includes(q))
})

// Expand / Collapse State helpers
const isExpanded = (domainName) => {
  // If search query is active, auto-expand any matching groups
  if (searchQuery.value.trim()) {
    return true
  }
  return expandedDomains.value.has(domainName)
}

const toggleExpand = (domainName) => {
  const next = new Set(expandedDomains.value)
  if (next.has(domainName)) {
    next.delete(domainName)
  } else {
    next.add(domainName)
  }
  expandedDomains.value = next
}

const hasAnySubdomains = computed(() => {
  return filteredGroups.value.some(g => g.subdomains.length > 0)
})

const allExpanded = computed(() => {
  const withSubs = filteredGroups.value.filter(g => g.subdomains.length > 0)
  if (withSubs.length === 0) return false
  return withSubs.every(g => expandedDomains.value.has(g.main.name))
})

const toggleExpandAll = () => {
  if (allExpanded.value) {
    expandedDomains.value = new Set()
  } else {
    const next = new Set()
    for (const g of filteredGroups.value) {
      if (g.subdomains.length > 0) {
        next.add(g.main.name)
      }
    }
    expandedDomains.value = next
  }
}

const switchTab = (tab) => {
  activeTab.value = tab
  currentPage.value = 1
}

// Pagination computations
const totalItemCount = computed(() => {
  return activeTab.value === 'grouped' ? filteredGroups.value.length : filteredFlatDomains.value.length
})

const totalPages = computed(() => {
  return Math.ceil(totalItemCount.value / itemsPerPage.value) || 1
})

const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage.value)
const paginationEnd = computed(() => currentPage.value * itemsPerPage.value)

const paginatedGroups = computed(() => {
  return filteredGroups.value.slice(paginationStart.value, paginationEnd.value)
})

const paginatedFlatDomains = computed(() => {
  return filteredFlatDomains.value.slice(paginationStart.value, paginationEnd.value)
})

// ==========================================
// FAST PARALLEL DETAILS FETCHER
// ==========================================

const loadDomains = async () => {
  try {
    loading.value = true
    const res = await axios.get('/domains/api')
    domains.value = res.data.domains || []
    serverIp.value = res.data.server_ip || ""
    
    // Concurrently fetch storage, DNS, and PHP details in background pool
    fetchDetailsInParallel()
  } catch (error) {
    showAlert('danger', 'Failed to load domains')
    console.error(error)
  } finally {
    loading.value = false
  }
}

const fetchDetailsInParallel = async () => {
  const toFetch = domains.value.filter(d => !loadedDetails.value.has(d.name))
  if (toFetch.length === 0) return

  const concurrency = 4
  let currentIndex = 0

  const worker = async () => {
    while (currentIndex < toFetch.length) {
      const idx = currentIndex++
      const domain = toFetch[idx]
      if (!domain) break

      try {
        const res = await axios.get(`/domains/api/${domain.name}/details`)
        const domainIndex = domains.value.findIndex(d => d.name === domain.name)
        if (domainIndex !== -1) {
          domains.value[domainIndex] = {
            ...domains.value[domainIndex],
            storage: res.data.storage,
            is_active: res.data.is_active,
            server_ip: res.data.server_ip,
            php_version: res.data.php_version
          }
        }
        loadedDetails.value.add(domain.name)
      } catch (err) {
        console.error(`Failed to load details for ${domain.name}`, err)
        const domainIndex = domains.value.findIndex(d => d.name === domain.name)
        if (domainIndex !== -1) {
          domains.value[domainIndex] = {
            ...domains.value[domainIndex],
            storage: '?',
            is_active: false,
            php_version: '8.2'
          }
        }
        loadedDetails.value.add(domain.name)
      }
    }
  }

  const workers = []
  for (let i = 0; i < Math.min(concurrency, toFetch.length); i++) {
    workers.push(worker())
  }
  await Promise.all(workers)
}

// ==========================================
// DOMAIN ACTIONS & MODALS
// ==========================================

const validateDomain = (domain) => {
  const trimmed = domain.trim()
  if (!trimmed) {
    return "Domain name is required"
  }
  const domainRegex = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i
  if (!domainRegex.test(trimmed)) {
    return "Please enter a valid domain name (e.g., example.com or sub.example.com)"
  }
  if (trimmed.length > 253) {
    return "Domain name is too long (max 253 characters)"
  }
  return null
}

const clearValidationError = () => {
  validationError.value = ""
}

const openAddModal = () => {
  domainInput.value = ""
  createPhpVersion.value = "8.2"
  validationError.value = ""
  isEdit.value = false
  showModal.value = true
}

const openEditModal = (domain) => {
  isEdit.value = true
  oldDomain.value = domain
  domainInput.value = domain
  validationError.value = ""
  showModal.value = true
}

const viewWebsite = (domain) => {
  const url = `http://${domain}`
  window.open(url, '_blank')
}

const openFileManager = (domain) => {
  router.visit(`/file-manager/${domain}`)
}

const saveDomain = async () => {
  const error = validateDomain(domainInput.value)
  if (error) {
    validationError.value = error
    return
  }

  try {
    submitting.value = true
    await axios.post('/domains', { 
      domain: domainInput.value.trim().toLowerCase(),
      php_version: createPhpVersion.value
    })
    showAlert('success', `Domain "${domainInput.value}" has been added successfully`)
    closeModal()
    loadDomains()
  } catch (error) {
    if (error.response?.status === 409) {
      validationError.value = "This domain already exists"
    } else if (error.response?.data?.error) {
      validationError.value = error.response.data.error
    } else {
      showAlert('danger', 'Failed to add domain. Please try again.')
    }
  } finally {
    submitting.value = false
  }
}

const updateDomain = async () => {
  const error = validateDomain(domainInput.value)
  if (error) {
    validationError.value = error
    return
  }

  try {
    submitting.value = true
    await axios.put(`/domains/${oldDomain.value}`, { 
      domain: domainInput.value.trim().toLowerCase() 
    })
    showAlert('success', `Domain has been updated successfully`)
    closeModal()
    loadDomains()
  } catch (error) {
    if (error.response?.status === 409) {
      validationError.value = "This domain already exists"
    } else if (error.response?.status === 404) {
      validationError.value = "Original domain not found"
    } else if (error.response?.data?.error) {
      validationError.value = error.response.data.error
    } else {
      showAlert('danger', 'Failed to update domain. Please try again.')
    }
  } finally {
    submitting.value = false
  }
}

const confirmDelete = (domain) => {
  domainToDelete.value = domain
  showDeleteModal.value = true
}

const deleteDomain = async () => {
  try {
    submitting.value = true
    await axios.delete(`/domains/${domainToDelete.value}`)
    showAlert('success', `Domain "${domainToDelete.value}" has been deleted`)
    showDeleteModal.value = false
    loadDomains()
  } catch (error) {
    showAlert('danger', 'Failed to delete domain. Please try again.')
  } finally {
    submitting.value = false
  }
}

const updateDocumentRoot = async () => {
  if (!rootInput.value.trim()) {
    rootValidationError.value = "Document root is required"
    return
  }

  try {
    submitting.value = true
    await axios.put(`/domains/${oldDomain.value}/root`, { 
      document_root: rootInput.value.trim()
    })
    showAlert('success', `Document root has been updated successfully`)
    closeRootModal()
    loadDomains()
  } catch (error) {
    if (error.response?.data?.error) {
      rootValidationError.value = error.response.data.error
    } else {
      showAlert('danger', 'Failed to update document root. Please try again.')
    }
  } finally {
    submitting.value = false
  }
}

const openRootModal = (domain) => {
  oldDomain.value = domain.name
  rootInput.value = domain.document_root || `/var/www/${domain.name}`
  rootValidationError.value = ""
  showRootModal.value = true
}

const closeRootModal = () => {
  showRootModal.value = false
  rootInput.value = ""
  rootValidationError.value = ""
}

const openPhpVersionModal = async (domain) => {
  selectedDomainName.value = domain.name
  currentDomainPhpVersion.value = domain.php_version || '8.2'
  selectedPhpVersion.value = domain.php_version || '8.2'
  showPhpModal.value = true
  
  try {
    loadingPhpVersions.value = true
    const res = await axios.get('/php/versions')
    availablePhpVersions.value = res.data.versions || []
  } catch (err) {
    console.error('Failed to fetch PHP versions', err)
    availablePhpVersions.value = [
      { version: '7.4', installed: false },
      { version: '8.0', installed: false },
      { version: '8.1', installed: false },
      { version: '8.2', installed: true },
      { version: '8.3', installed: true },
      { version: '8.4', installed: false }
    ]
  } finally {
    loadingPhpVersions.value = false
  }
}

const closePhpModal = () => {
  showPhpModal.value = false
  selectedDomainName.value = ""
  currentDomainPhpVersion.value = ""
  selectedPhpVersion.value = ""
}

const updatePhpVersion = async () => {
  try {
    submitting.value = true
    const res = await axios.put(`/domains/${selectedDomainName.value}/php-version`, {
      php_version: selectedPhpVersion.value
    })
    showAlert('success', res.data.message || `Switched PHP version successfully to ${selectedPhpVersion.value}`)
    closePhpModal()
    loadDomains()
  } catch (err) {
    const errorMsg = err.response?.data?.error || 'Failed to update PHP version'
    showAlert('danger', errorMsg)
  } finally {
    submitting.value = false
  }
}

const closeModal = () => {
  showModal.value = false
  domainInput.value = ""
  createPhpVersion.value = "8.2"
  validationError.value = ""
}
</script>

<style scoped>
/* Row Transition */
.domain-row {
  transition: background-color 0.15s ease;
}
.domain-row:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

/* Parent Row State */
.group-parent-expanded {
  background-color: rgba(99, 102, 241, 0.025);
  border-bottom: 1px solid #edf2f7;
}

/* Subdomain Row Nested Styling */
.subdomain-row {
  background-color: #f8fafc;
  border-top: 1px dashed #e2e8f0;
}
.subdomain-row:hover {
  background-color: #f1f5f9;
}
.last-subdomain-row {
  border-bottom: 2px solid #e2e8f0;
}

/* Tree Guide Branch */
.tree-guide-branch {
  position: absolute;
  left: 20px;
  top: -12px;
  width: 14px;
  height: 28px;
  border-left: 2px dashed #94a3b8;
  border-bottom: 2px dashed #94a3b8;
  border-bottom-left-radius: 6px;
}
.tree-guide-branch.is-last {
  height: 24px;
}

/* Subdomain Indicator Tag */
.subdomain-badge-tag {
  font-size: 0.62rem;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 4px;
  background-color: #e0e7ff;
  color: #4338ca;
  letter-spacing: 0.5px;
}

/* Expand Chevron Button */
.expand-chevron-btn {
  width: 26px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.2s ease;
  cursor: pointer;
  color: #64748b;
}
.expand-chevron-btn:hover {
  background-color: #e2e8f0;
  color: #1e293b;
}
.expand-chevron-btn.expanded {
  transform: rotate(90deg);
  color: #0f172a;
}
.expand-placeholder {
  width: 26px;
  display: inline-block;
}

/* Subdomain Pill on Parent Row */
.subdomain-pill-btn {
  display: inline-flex;
  align-items: center;
  padding: 3px 8px;
  font-size: 0.68rem;
  font-weight: 700;
  border-radius: 12px;
  background-color: #e2e8f0;
  color: #334155;
  transition: all 0.2s ease;
  cursor: pointer;
}
.subdomain-pill-btn:hover,
.subdomain-pill-active {
  background-color: #e0e7ff;
  color: #4338ca;
  box-shadow: 0 1px 3px rgba(67, 56, 202, 0.15);
}

/* View Switcher Active State */
.active-view-btn {
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08) !important;
}

/* Status Pill Design */
.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 100px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.pill-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 8px;
}

.status-active {
  background-color: #e6fffa;
  color: #047857;
}
.status-active .pill-dot {
  background-color: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.status-configuring {
  background-color: #fffbeb;
  color: #92400e;
}
.status-configuring .pill-dot {
  background-color: #f59e0b;
  box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
}

/* Action Buttons */
.action-btn {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  background: transparent;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  color: #67748e;
}

.action-btn i {
  font-size: 1.15rem !important;
}

.action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.btn-view:hover {
  background-color: #e0f2fe;
  color: #0ea5e9;
}

.btn-folder:hover {
  background-color: #f0fdf4;
  color: #22c55e;
}

.btn-php:hover {
  background-color: #f5f3ff;
  color: #8b5cf6;
}

.btn-edit:hover {
  background-color: #f8fafc;
  color: #64748b;
}

.btn-delete:hover {
  background-color: #fef2f2;
  color: #ef4444;
}

.btn-edit-root {
  opacity: 0.35;
  transition: opacity 0.2s;
}
.domain-row:hover .btn-edit-root {
  opacity: 1;
}

.empty-state {
  padding: 40px 0;
}
</style>
