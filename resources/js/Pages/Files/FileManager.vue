<template>
  <MainLayout>
    <Head title="File Manager" />
    <div class="container-fluid py-4" @click="closeContextMenu" @dragenter.prevent="handleDragEnter"
      @dragover.prevent="handleDragOver" @dragleave.prevent="handleDragLeave" @drop.prevent="handleDrop">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="glass-card d-flex justify-content-between align-items-center p-3">
            <div class="d-flex align-items-center">
              <div class="icon-shape icon-md bg-gradient-primary shadow-primary text-center border-radius-xl me-3">
                <i class="material-symbols-rounded opacity-10">folder_open</i>
              </div>
              <div>
                <h4 class="font-weight-bolder mb-0">File Manager</h4>
                <p class="mb-0 text-sm text-secondary">
                  <span class="text-primary font-weight-bold">{{ domain }}</span> 
                  <span class="mx-2 text-lighter">/</span> 
                  <span class="text-muted">/var/www/{{ domain }}</span>
                  <span v-if="currentPath" class="text-dark font-weight-bold">/{{ currentPath }}</span>
                </p>
              </div>
            </div>
            <button class="btn btn-link text-secondary mb-0" @click="goBack">
              <i class="material-symbols-rounded text-sm me-1">arrow_back</i>
              Back to Domains
            </button>
          </div>
        </div>
      </div>

      <!-- Alert -->
      <transition name="fade">
        <div class="row" v-if="alert.show">
          <div class="col-12">
            <div :class="`alert alert-${alert.type} alert-dismissible fade show border-radius-lg shadow-sm mb-4`">
              <span class="alert-icon">
                <i class="material-symbols-rounded">{{ alert.type === 'success' ? 'check_circle' : 'error' }}</i>
              </span>
              <span class="alert-text ms-2 font-weight-bold text-white">{{ alert.message }}</span>
              <button type="button" class="btn-close" @click="alert.show = false"></button>
            </div>
          </div>
        </div>
      </transition>

      <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3 col-md-4 mb-4">
          <div class="glass-card h-100 p-3">
            <h6 class="text-uppercase text-xxs font-weight-bolder opacity-7 mb-3">Quick Navigation</h6>
            <div class="nav-pills-container">
              <button class="nav-pill-btn" :class="{ active: !currentPath }" @click="navigateTo('')">
                <i class="material-symbols-rounded">home</i>
                <span>Root Directory</span>
              </button>
              <button v-for="(crumb, index) in breadcrumbs.slice(0, -1)" :key="index" class="nav-pill-btn" @click="navigateTo(crumb.path)">
                <i class="material-symbols-rounded">subdirectory_arrow_right</i>
                <span>{{ crumb.name }}</span>
              </button>
            </div>

            <hr class="horizontal dark my-4">

            <h6 class="text-uppercase text-xxs font-weight-bolder opacity-7 mb-3">Git Repository</h6>
            <div v-if="gitInfo.available" class="git-status-card p-3 border-radius-lg bg-gray-100 mb-3 shadow-sm border border-white">
              <div class="d-flex align-items-center mb-2">
                <i class="material-symbols-rounded text-sm me-2" :class="gitInfo.dirty ? 'text-warning' : 'text-success'">
                  {{ gitInfo.dirty ? 'warning' : 'check_circle' }}
                </i>
                <span class="text-xs font-weight-bold text-dark">{{ gitInfo.branch }}</span>
              </div>
              <div class="progress progress-xs mb-0" style="height: 4px;">
                <div class="progress-bar" :class="gitInfo.dirty ? 'bg-warning' : 'bg-success'" role="progressbar" :style="{ width: gitInfo.dirty ? '70%' : '100%' }"></div>
              </div>
              <div class="mt-2 d-flex justify-content-between align-items-center">
                <span class="text-xxs text-secondary">Status: {{ gitInfo.dirty ? 'Modified' : 'Clean' }}</span>
                <button class="btn btn-link text-primary text-xxs p-0 mb-0 font-weight-bold" @click="scrollToGit">Manage</button>
              </div>
            </div>
            <div v-else class="text-center py-4 bg-gray-100 border-radius-lg mb-3 border border-dashed">
              <i class="material-symbols-rounded text-secondary opacity-5 fs-2 mb-2">account_tree</i>
              <p class="text-xs text-secondary mb-0">No repository detected</p>
            </div>
          </div>
        </div>

        <!-- Main Content Area -->
        <div class="col-lg-9 col-md-8">
          <!-- Toolbar -->
          <div class="glass-card mb-3 p-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <div class="btn-group shadow-sm border-radius-lg overflow-hidden">
                <button class="btn btn-sm bg-white mb-0 border-end" @click="showCreateFileModal = true">
                  <i class="material-symbols-rounded text-primary text-sm me-1">note_add</i>
                  New File
                </button>
                <button class="btn btn-sm bg-white mb-0" @click="showCreateDirModal = true">
                  <i class="material-symbols-rounded text-info text-sm me-1">create_new_folder</i>
                  Folder
                </button>
              </div>

              <button class="btn btn-sm bg-gradient-primary mb-0 shadow-sm" @click="triggerUpload">
                <i class="material-symbols-rounded text-sm me-1">upload</i>
                Upload
              </button>
              <input ref="fileInput" type="file" style="display:none" @change="handleFileUpload" multiple />

              <button class="btn btn-sm bg-white mb-0 shadow-sm text-dark" @click="webTerminalRef?.openTerminal()">
                <i class="material-symbols-rounded text-sm me-1 text-success">terminal</i>
                Terminal
              </button>

              <div class="ms-auto d-flex align-items-center gap-3">
                <div class="search-wrapper-premium shadow-sm">
                  <div class="search-type-selector">
                    <i class="material-symbols-rounded text-sm">filter_list</i>
                    <select v-model="searchType" class="form-select border-0 bg-transparent text-xxs font-weight-bold">
                      <option value="filename">Name</option>
                      <option value="content">Content</option>
                    </select>
                  </div>
                  <div class="vr bg-gray-300 my-2" style="width: 1px;"></div>
                  <div class="search-input-box">
                    <i class="material-symbols-rounded text-sm text-secondary">search</i>
                    <input v-model="searchQuery" type="text" class="form-control border-0 bg-transparent ps-1" 
                      placeholder="Search files..." @keyup.enter="handleSearch" />
                    <button v-if="searchQuery" class="btn btn-link p-0 m-0 me-2" @click="searchQuery = ''; isSearching = false; loadFiles()">
                      <i class="material-symbols-rounded text-sm text-secondary">close</i>
                    </button>
                  </div>
                </div>
                
                <div class="d-flex align-items-center gap-3 bg-gray-100 px-3 py-1 border-radius-lg border">
                  <div class="form-check form-switch mb-0 p-0 d-flex align-items-center gap-2">
                    <input class="form-check-input ms-0" type="checkbox" id="deepSearchToggle" v-model="deepSearch">
                    <label class="form-check-label text-xxs text-dark font-weight-bold mb-0 cursor-pointer" for="deepSearchToggle">In-depth</label>
                  </div>
                  <div class="vr bg-gray-300" style="height: 15px;"></div>
                  <div class="form-check form-switch mb-0 p-0 d-flex align-items-center gap-2">
                    <input class="form-check-input ms-0" type="checkbox" id="showHiddenToggle" v-model="showHidden" @change="loadFiles">
                    <label class="form-check-label text-xxs text-dark font-weight-bold mb-0 cursor-pointer" for="showHiddenToggle">Hidden</label>
                  </div>
                </div>

                <button class="btn btn-icon-only btn-rounded bg-white mb-0 shadow-sm border" @click="loadFiles" :disabled="loading">
                  <i class="material-symbols-rounded text-lg text-dark" :class="{ 'spin-animation': loading }">refresh</i>
                </button>
              </div>
            </div>

            <!-- Bulk actions overlay -->
            <transition name="slide-up">
              <div v-if="hasSelected" class="bulk-actions-overlay mt-3 p-2 border-radius-lg bg-gradient-dark d-flex align-items-center gap-2 shadow-lg">
                <span class="text-white text-xs font-weight-bold ms-3 me-auto">
                  <i class="material-symbols-rounded text-xs me-1">check_circle</i>
                  {{ selectedItems.length }} selected
                </span>
                <button class="btn btn-xs btn-link text-white mb-0" @click="bulkCopyMove('copy')">Copy</button>
                <button class="btn btn-xs btn-link text-white mb-0" @click="bulkCopyMove('move')">Move</button>
                <button class="btn btn-xs btn-link text-white mb-0" @click="bulkZip">Zip</button>
                <button class="btn btn-xs btn-link text-danger mb-0" @click="bulkDelete">Delete</button>
                <div class="vr bg-white opacity-2 mx-2" style="height: 20px;"></div>
                <button class="btn btn-xs btn-link text-white mb-0 opacity-7" @click="selectedItems = []; allSelected = false">Cancel</button>
              </div>
            </transition>
          </div>

          <!-- Breadcrumbs bar -->
          <div class="glass-card mb-3 p-2 px-3 d-flex align-items-center shadow-sm">
            <nav aria-label="breadcrumb" class="flex-grow-1">
              <ol class="breadcrumb bg-transparent mb-0 p-0">
                <li class="breadcrumb-item text-xs">
                  <a href="#" @click.prevent="navigateTo('')" class="text-secondary">
                    <i class="material-symbols-rounded text-xs">home</i>
                  </a>
                </li>
                <li v-for="(crumb, index) in breadcrumbs" :key="index" class="breadcrumb-item text-xs" :class="{ active: index === breadcrumbs.length - 1 }">
                  <a v-if="index < breadcrumbs.length - 1" href="#" @click.prevent="navigateTo(crumb.path)" class="text-secondary">{{ crumb.name }}</a>
                  <span v-else class="text-dark font-weight-bold">{{ crumb.name }}</span>
                </li>
              </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
              <span class="text-xxs text-secondary font-weight-bolder text-uppercase ls-1">{{ items.length }} items</span>
              <button class="btn btn-link text-secondary mb-0 p-1" @click="toggleSelectAll" :title="allSelected ? 'Deselect All' : 'Select All'">
                <i class="material-symbols-rounded text-lg">{{ allSelected ? 'deselect' : 'select_all' }}</i>
              </button>
            </div>
          </div>

          <!-- File List Area -->
          <div class="glass-card p-0 overflow-hidden mb-4 shadow-sm border-0">
            <div class="table-responsive">
              <table class="table align-items-center mb-0">
                <thead class="bg-gray-50">
                  <tr>
                    <th style="width:48px" class="ps-3"></th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Name</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Size</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Modified</th>
                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in paginatedItems" :key="item.name + item.type"
                    @contextmenu.prevent="openContextMenu($event, item)" 
                    @dblclick="handleDoubleClick(item)"
                    class="file-row-modern"
                    :class="{ 'selected': isSelected(item), 'opacity-5': item.hidden }">
                    <td class="ps-3">
                      <div class="form-check mb-0">
                        <input type="checkbox" class="form-check-input" :checked="isSelected(item)" @change="toggleSelectItem(item, $event)">
                      </div>
                    </td>
                    <td>
                      <div class="d-flex px-2 py-2 align-items-center">
                        <div class="file-icon-box me-3 shadow-sm" :class="item.type === 'directory' ? 'bg-light-warning' : 'bg-light-info'">
                          <i class="material-symbols-rounded" :class="item.type === 'directory' ? 'text-warning' : 'text-info'">
                            {{ item.type === 'directory' ? 'folder' : (item.matchType === 'content' ? 'find_in_page' : 'description') }}
                          </i>
                        </div>
                        <div class="d-flex flex-column">
                          <a v-if="item.type === 'directory'" href="#" @click.prevent="openDirectory(item.name)" class="text-sm font-weight-bold text-dark mb-0">
                            {{ item.name }}
                          </a>
                          <span v-else class="text-sm font-weight-bold text-dark mb-0">{{ item.name }}</span>
                          <div v-if="isSearching && item.path" class="d-flex align-items-center gap-2">
                            <span class="text-xxs text-primary font-weight-bold">{{ item.path }}</span>
                            <button class="btn btn-link p-0 m-0 text-xxs text-info" @click.stop="jumpToPath(item.path)" title="Go to folder">
                              <i class="material-symbols-rounded text-xs">open_in_new</i>
                            </button>
                          </div>
                          <span v-else class="text-xxs text-secondary">{{ item.permissions }}</span>
                        </div>
                      </div>
                    </td>
                    <td><span class="text-xs font-weight-bold text-dark">{{ item.type === 'directory' ? '--' : item.sizeFormatted }}</span></td>
                    <td><span class="text-xs text-secondary">{{ item.modified }}</span></td>
                    <td class="text-center">
                      <div class="d-flex justify-content-center gap-2">
                        <button v-if="item.type === 'file' && item.editable" class="action-btn-circle" @click="editFile(item.name)" title="Edit">
                          <i class="material-symbols-rounded text-sm">edit_note</i>
                        </button>
                        <button v-if="item.type === 'file'" class="action-btn-circle" @click="downloadFile(item.name)" title="Download">
                          <i class="material-symbols-rounded text-sm">download</i>
                        </button>
                <button class="action-btn-circle" @click.stop="openContextMenu($event, item)" title="More options">
                  <i class="material-symbols-rounded text-sm">more_vert</i>
                </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <!-- Empty State -->
            <div v-if="items.length === 0 && !loading" class="text-center py-7">
              <div class="empty-state-icon mb-3 shadow-sm">
                <i class="material-symbols-rounded">folder_open</i>
              </div>
              <h6 class="text-dark font-weight-bold">Empty Directory</h6>
              <p class="text-xs text-secondary mb-3">Upload or create files to get started with your project</p>
              <button class="btn btn-sm bg-gradient-primary px-4 border-radius-lg" @click="triggerUpload">Upload Files</button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center py-5">
              <div class="spinner-border text-primary border-3" role="status"></div>
              <p class="text-xs text-secondary mt-3 font-weight-bold">Fetching files...</p>
            </div>

            <!-- Pagination -->
            <div v-if="filteredItems.length > itemsPerPage" class="d-flex justify-content-between align-items-center p-3 border-top bg-gray-50 border-radius-bottom-lg">
              <span class="text-xxs text-secondary font-weight-bold">Page {{ currentPage }} of {{ totalPages }}</span>
              <ul class="pagination pagination-primary pagination-xs mb-0">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                  <button class="page-link shadow-none" @click="currentPage--"><i class="material-symbols-rounded">chevron_left</i></button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                  <button class="page-link shadow-none" @click="currentPage++"><i class="material-symbols-rounded">chevron_right</i></button>
                </li>
              </ul>
            </div>
          </div>

          <!-- Git Management Panel -->
          <div id="git-panel" class="glass-card p-4 shadow-lg mb-4">
            <div class="d-flex justify-content-between align-items-start mb-4">
              <div>
                <h5 class="mb-1 font-weight-bolder">Git Repository Control</h5>
                <p class="text-sm text-secondary mb-0">Manage versioning, branches, and deployment flows.</p>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-dark mb-0 border-radius-lg" @click="showGitTokenForm = !showGitTokenForm">
                  <i class="material-symbols-rounded text-sm me-1">key</i>
                  {{ showGitTokenForm ? 'Hide Token' : 'Auth Token' }}
                </button>
                <button class="btn btn-sm bg-gradient-dark mb-0 border-radius-lg" @click="loadGitStatus" :disabled="gitLoading">
                  <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin-animation': gitLoading }">refresh</i>
                  Sync Status
                </button>
              </div>
            </div>

            <!-- Token Form -->
            <transition name="fade">
              <div v-if="showGitTokenForm" class="bg-gray-100 p-3 border-radius-lg mb-4 border border-white shadow-inner">
                <label class="form-label text-xs font-weight-bolder text-uppercase opacity-7">Personal Access Token (GitHub/GitLab)</label>
                <div class="input-group input-group-outline bg-white border-radius-lg overflow-hidden">
                  <input v-model="gitTokenInput" :type="showTokenText ? 'text' : 'password'" class="form-control border-0 ps-3" placeholder="ghp_xxxx..." />
                  <button class="btn btn-link text-dark mb-0 px-3 border-start" @click="showTokenText = !showTokenText">
                    <i class="material-symbols-rounded text-sm">{{ showTokenText ? 'visibility_off' : 'visibility' }}</i>
                  </button>
                  <button class="btn bg-gradient-primary mb-0 border-radius-0 px-4" @click="saveGitToken" :disabled="gitTokenSaving">
                    <span v-if="gitTokenSaving" class="spinner-border spinner-border-sm"></span>
                    <span v-else>Save</span>
                  </button>
                </div>
                <small class="text-muted mt-2 d-block">
                  <i class="material-symbols-rounded text-xs align-middle me-1">info</i>
                  Required for authenticated operations like Pull and Push over HTTPS.
                </small>
              </div>
            </transition>

            <div v-if="gitInfo.available">
              <div class="row g-3 mb-4">
                <div class="col-md-4">
                  <div class="p-3 bg-white border-radius-lg shadow-sm border">
                    <p class="text-xs font-weight-bolder text-uppercase opacity-5 mb-1">Branch</p>
                    <h6 class="mb-0 text-primary font-weight-bold">
                      <i class="material-symbols-rounded text-sm me-1">account_tree</i>
                      {{ gitInfo.branch }}
                    </h6>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="p-3 bg-white border-radius-lg shadow-sm border">
                    <p class="text-xs font-weight-bolder text-uppercase opacity-5 mb-1">Tree Status</p>
                    <h6 class="mb-0 font-weight-bold" :class="gitInfo.dirty ? 'text-warning' : 'text-success'">
                      <i class="material-symbols-rounded text-sm me-1">{{ gitInfo.dirty ? 'warning' : 'check_circle' }}</i>
                      {{ gitInfo.dirty ? 'Dirty' : 'Clean' }}
                    </h6>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="p-3 bg-white border-radius-lg shadow-sm border">
                    <p class="text-xs font-weight-bolder text-uppercase opacity-5 mb-1">Root Path</p>
                    <h6 class="mb-0 text-xs text-truncate text-dark font-weight-bold" :title="gitInfo.repoRoot">
                      <i class="material-symbols-rounded text-sm me-1 text-secondary">folder_shared</i>
                      /var/www/{{ domain }}{{ gitInfo.repoRoot ? '/' + gitInfo.repoRoot : '' }}
                    </h6>
                  </div>
                </div>
              </div>

              <div class="row mb-4">
                <div class="col-lg-6">
                  <div class="form-group mb-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase opacity-7">Commit Changes</label>
                    <div class="input-group input-group-outline bg-white border-radius-lg overflow-hidden border shadow-sm">
                      <input v-model="gitCommitMessage" type="text" class="form-control border-0 ps-3" placeholder="Message..." />
                      <button class="btn bg-gradient-primary mb-0 border-radius-0 px-4" @click="runGitCommit" :disabled="!gitCommitMessage.trim() || gitActionLoading">
                        Commit
                      </button>
                    </div>
                  </div>
                  <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-sm btn-outline-success mb-0 border-radius-lg px-3" @click="performGitAction('pull')" :disabled="gitActionLoading">
                      <i class="material-symbols-rounded text-sm me-1">south</i> Pull
                    </button>
                    <button class="btn btn-sm btn-outline-primary mb-0 border-radius-lg px-3" @click="performGitAction('push')" :disabled="gitActionLoading">
                      <i class="material-symbols-rounded text-sm me-1">north</i> Push
                    </button>
                  </div>
                </div>
                <div class="col-lg-6">
                  <div class="form-group mb-3">
                    <label class="form-label text-xs font-weight-bold text-uppercase opacity-7">Switch Branch</label>
                    <div class="input-group input-group-outline bg-white border-radius-lg overflow-hidden border shadow-sm">
                      <select v-model="gitSelectedBranch" class="form-select border-0 ps-3">
                        <option v-for="branch in gitInfo.branches" :key="branch" :value="branch">{{ branch }}</option>
                      </select>
                      <button class="btn bg-gradient-info mb-0 border-radius-0 px-4" @click="runGitSwitchBranch" :disabled="gitActionLoading">
                        Switch
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="git-console-container mt-4">
                <p class="text-xs font-weight-bold text-uppercase opacity-7 mb-2">Live Git Status</p>
                <pre class="git-console shadow-inner">{{ gitInfo.statusLines.join('\n') || 'Working tree clean' }}</pre>
                <transition name="fade">
                  <div v-if="gitLastOutput" class="mt-3">
                    <p class="text-xs font-weight-bold text-uppercase opacity-7 mb-2 text-info">Last Action Output</p>
                    <pre class="git-console border-info text-info shadow-inner" style="background: rgba(17, 205, 239, 0.05);">{{ gitLastOutput }}</pre>
                  </div>
                </transition>
              </div>
            </div>
            <div v-else class="text-center py-5 bg-gray-50 border-radius-lg border border-dashed">
              <i class="material-symbols-rounded text-secondary opacity-3 fs-1 mb-3">account_tree</i>
              <h5 class="text-dark font-weight-bold">No Git Repository Detected</h5>
              <p class="text-sm text-secondary px-5">This directory or its parent folders are not initialized with Git. Use the terminal to <code>git init</code> if needed.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Modals and Overlays -->

      <!-- Context Menu -->
      <transition name="fade">
        <div v-if="contextMenu.show" class="context-menu" :style="contextMenuStyle" @click.stop>
          <div v-if="contextMenu.item.type === 'file' && contextMenu.item.editable" class="context-menu-item" @click="editFile(contextMenu.item.name); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">edit_note</i> Edit File
          </div>
          <div v-if="contextMenu.item.type === 'file'" class="context-menu-item" @click="downloadFile(contextMenu.item.name); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">download</i> Download
          </div>
          <div class="context-menu-item" @click="openRenameModal(contextMenu.item); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">label</i> Rename
          </div>
          <div class="context-menu-item" @click="openCopyMoveModal(contextMenu.item, 'copy'); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">content_copy</i> Copy to...
          </div>
          <div class="context-menu-item" @click="openCopyMoveModal(contextMenu.item, 'move'); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">drive_file_move</i> Move to...
          </div>
          <div class="context-menu-item" @click="openPermissionsModal(contextMenu.item); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">shield</i> Permissions
          </div>
          <div v-if="itemIsArchive(contextMenu.item)" class="context-menu-item" @click="openExtractModal(contextMenu.item); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">unarchive</i> Extract Here
          </div>
          <div class="context-menu-divider"></div>
          <div class="context-menu-item text-danger" @click="confirmDelete(contextMenu.item); closeContextMenu()">
            <i class="material-symbols-rounded text-sm me-2">delete</i> Delete
          </div>
        </div>
      </transition>

      <!-- Permissions Modal -->
      <div class="modal-backdrop fade show" v-if="showPermissionsModal" @click="showPermissionsModal = false"></div>
      <div class="modal fade show d-block" v-if="showPermissionsModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="glass-card modal-content border-0 shadow-2xl">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title font-weight-bolder">Change Permissions</h5>
              <button type="button" class="btn-close" @click="showPermissionsModal = false"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm text-secondary mb-3">Item: <span class="text-dark font-weight-bold">{{ selectedItem?.name }}</span></p>
              <div class="form-group mb-3">
                <label class="form-label text-xs font-weight-bold">Mode (e.g., 0755)</label>
                <input v-model="newPermissions" type="text" class="form-control form-control-lg border ps-3" placeholder="0755" maxlength="4" />
                <small class="text-muted mt-2 d-block">0755 is standard for folders, 0644 for files.</small>
              </div>
              <div v-if="selectedItem?.type === 'directory'" class="form-check ps-0">
                <div class="form-check form-switch ps-0">
                  <input class="form-check-input ms-0" type="checkbox" v-model="recursivePermissions" id="recursiveCheck">
                  <label class="form-check-label ms-4 text-sm text-dark font-weight-bold" for="recursiveCheck">Apply recursively to contents</label>
                </div>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button class="btn btn-link text-secondary mb-0" @click="showPermissionsModal = false">Cancel</button>
              <button class="btn bg-gradient-primary mb-0 border-radius-lg" @click="changePermissions">Update</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Rename Modal -->
      <div class="modal-backdrop fade show" v-if="showRenameModal" @click="showRenameModal = false"></div>
      <div class="modal fade show d-block" v-if="showRenameModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="glass-card modal-content border-0">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title font-weight-bolder">Rename Item</h5>
              <button type="button" class="btn-close" @click="showRenameModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-0">
                <label class="form-label text-xs font-weight-bold">New Name</label>
                <input v-model="renameName" type="text" class="form-control form-control-lg border ps-3" @keyup.enter="renameItem" />
              </div>
            </div>
            <div class="modal-footer border-0">
              <button class="btn btn-link text-secondary mb-0" @click="showRenameModal = false">Cancel</button>
              <button class="btn bg-gradient-primary mb-0" @click="renameItem" :disabled="!renameName.trim()">Rename</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Modal -->
      <div class="modal-backdrop fade show" v-if="showDeleteModal" @click="showDeleteModal = false"></div>
      <div class="modal fade show d-block" v-if="showDeleteModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="glass-card modal-content border-0 shadow-2xl">
            <div class="modal-body text-center py-5">
              <div class="modal-icon bg-gradient-danger mx-auto mb-4 shadow-danger">
                <i class="material-symbols-rounded text-white" style="font-size: 32px;">delete_forever</i>
              </div>
              <h4 class="font-weight-bolder">Confirm Deletion</h4>
              <p class="text-secondary px-4">
                {{ isBulkDelete ? `Are you sure you want to permanently delete ${selectedItems.length} selected items?` : `Delete "${selectedItem?.name}" permanently? This cannot be undone.` }}
              </p>
              <div class="d-flex justify-content-center gap-3 mt-4">
                <button class="btn btn-link text-secondary mb-0 font-weight-bold" @click="showDeleteModal = false">Cancel</button>
                <button class="btn bg-gradient-danger mb-0 border-radius-lg px-4 shadow-danger" @click="executeDelete" :disabled="deleteProcessing">
                  <span v-if="deleteProcessing" class="spinner-border spinner-border-sm me-2"></span>
                  Delete Forever
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Copy/Move Modal -->
      <div class="modal-backdrop fade show" v-if="showCopyMoveModal" @click="closeCopyMoveModal"></div>
      <div class="modal fade show d-block" v-if="showCopyMoveModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="glass-card modal-content border-0">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title font-weight-bolder">{{ copyMoveAction === 'copy' ? 'Copy' : 'Move' }} Item(s)</h5>
              <button type="button" class="btn-close" @click="closeCopyMoveModal"></button>
            </div>
            <div class="modal-body">
              <p class="text-sm text-secondary mb-3">Target: <span class="text-dark font-weight-bold">{{ copyMoveItem?.name }}</span></p>
              <div class="form-group mb-0">
                <label class="form-label text-xs font-weight-bold">Destination Path (relative to domain root)</label>
                <div class="input-group input-group-outline bg-white border border-radius-lg overflow-hidden">
                  <span class="input-group-text border-0 bg-light"><i class="material-symbols-rounded text-sm">folder</i></span>
                  <input ref="copyMoveInput" v-model="copyMoveDestination" type="text" class="form-control border-0 ps-2" placeholder="e.g., public/images" @keyup.enter="executeCopyMove" />
                </div>
                <small class="text-muted mt-2 d-block">Leave empty to {{ copyMoveAction }} to the website root directory.</small>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button class="btn btn-link text-secondary mb-0" @click="closeCopyMoveModal">Cancel</button>
              <button :class="['btn', copyMoveAction === 'copy' ? 'bg-gradient-info' : 'bg-gradient-warning', 'mb-0']" @click="executeCopyMove" :disabled="copyMoveProcessing">
                <span v-if="copyMoveProcessing" class="spinner-border spinner-border-sm me-2"></span>
                {{ copyMoveAction === 'copy' ? 'Copy Now' : 'Move Now' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create File/Folder -->
      <div v-if="showCreateFileModal" class="modal-backdrop fade show" @click="showCreateFileModal = false"></div>
      <div v-if="showCreateFileModal" class="modal fade show d-block">
        <div class="modal-dialog modal-dialog-centered">
          <div class="glass-card modal-content border-0">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title font-weight-bolder">New File</h5>
              <button type="button" class="btn-close" @click="showCreateFileModal = false"></button>
            </div>
            <div class="modal-body pt-3">
              <div class="input-group input-group-outline bg-white border border-radius-lg overflow-hidden mb-2">
                <span class="input-group-text border-0 bg-light"><i class="material-symbols-rounded text-sm text-primary">description</i></span>
                <input ref="createFileInput" v-model="newFileName" type="text" class="form-control border-0 ps-2" placeholder="filename.php" @keyup.enter="createFile" />
              </div>
              <small class="text-muted">Enter the name including extension.</small>
            </div>
            <div class="modal-footer border-0">
              <button class="btn bg-gradient-primary w-100 mb-0 border-radius-lg" @click="createFile" :disabled="!newFileName.trim()">Create File</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showCreateDirModal" class="modal-backdrop fade show" @click="showCreateDirModal = false"></div>
      <div v-if="showCreateDirModal" class="modal fade show d-block">
        <div class="modal-dialog modal-dialog-centered">
          <div class="glass-card modal-content border-0">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title font-weight-bolder">New Folder</h5>
              <button type="button" class="btn-close" @click="showCreateDirModal = false"></button>
            </div>
            <div class="modal-body pt-3">
              <div class="input-group input-group-outline bg-white border border-radius-lg overflow-hidden mb-2">
                <span class="input-group-text border-0 bg-light"><i class="material-symbols-rounded text-sm text-info">create_new_folder</i></span>
                <input ref="createFolderInput" v-model="newDirName" type="text" class="form-control border-0 ps-2" placeholder="folder-name" @keyup.enter="createDirectory" />
              </div>
              <small class="text-muted">Folder name (no spaces recommended).</small>
            </div>
            <div class="modal-footer border-0">
              <button class="btn bg-gradient-info w-100 mb-0 border-radius-lg" @click="createDirectory" :disabled="!newDirName.trim()">Create Folder</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Fullscreen Ace Editor -->
      <transition name="fade">
        <div v-if="showEditorModal" class="fullscreen-editor-overlay">
          <div class="editor-header px-4 py-3 bg-dark d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <i class="material-symbols-rounded text-white me-2">edit_note</i>
              <span class="text-white font-weight-bold">{{ editingFile }}</span>
              <span class="badge badge-sm bg-primary ms-3">{{ detectedMode }}</span>
            </div>
            <div class="d-flex gap-2">
              <button v-if="detectedMode === 'json'" class="btn btn-sm btn-outline-info mb-0" @click="formatContent">
                <i class="material-symbols-rounded text-sm me-1">format_align_left</i>
                Format
              </button>
              <button class="btn btn-sm btn-success mb-0" @click="saveFile" :disabled="saving">
                <i class="material-symbols-rounded text-sm me-1">{{ saving ? 'sync' : 'save' }}</i>
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
              <button class="btn btn-sm btn-outline-light mb-0" @click="closeEditor">
                <i class="material-symbols-rounded text-sm me-1">close</i>
                Close
              </button>
            </div>
          </div>
          <div id="ace-editor-container" class="flex-grow-1"></div>
        </div>
      </transition>

      <!-- Uploading Modal -->
      <div v-if="uploading" class="modal-backdrop fade show"></div>
      <div v-if="uploading" class="modal fade show d-block">
        <div class="modal-dialog modal-sm modal-dialog-centered">
          <div class="glass-card modal-content border-0 text-center p-4">
            <div class="spinner-border text-primary mx-auto mb-3" role="status"></div>
            <h6 class="font-weight-bolder">Uploading Files</h6>
            <p class="text-xs text-secondary mb-3">{{ uploadFileName }}</p>
            <div class="progress mb-2" style="height: 6px;">
              <div class="progress-bar bg-gradient-primary" :style="{ width: uploadProgress + '%' }"></div>
            </div>
            <span class="text-xxs font-weight-bold text-dark">{{ uploadProgress }}%</span>
          </div>
        </div>
      </div>

      <!-- Drop Zone Overlay -->
      <transition name="fade">
        <div v-if="isDragging" class="upload-drop-zone-overlay">
          <div class="drop-zone-content">
            <div class="drop-zone-icon-box mb-4 shadow-lg scale-up">
              <i class="material-symbols-rounded text-white" style="font-size: 48px;">upload_file</i>
            </div>
            <h2 class="text-white font-weight-bolder">Release to Upload</h2>
            <p class="text-white opacity-8">Dropping into: <span class="font-weight-bold">/{{ currentPath || 'root' }}</span></p>
          </div>
        </div>
      </transition>

    </div>

    <!-- Web Terminal -->
    <WebTerminal ref="webTerminalRef" :domain="domain" :currentPath="currentPath" @refresh-files="loadFiles" />
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import WebTerminal from '@/Components/WebTerminal.vue'
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue'
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'
import ace from 'ace-builds'

// Import Ace components
import 'ace-builds/src-noconflict/mode-php'
import 'ace-builds/src-noconflict/mode-javascript'
import 'ace-builds/src-noconflict/mode-html'
import 'ace-builds/src-noconflict/mode-css'
import 'ace-builds/src-noconflict/mode-json'
import 'ace-builds/src-noconflict/mode-sh'
import 'ace-builds/src-noconflict/mode-sql'
import 'ace-builds/src-noconflict/mode-python'
import 'ace-builds/src-noconflict/mode-markdown'
import 'ace-builds/src-noconflict/mode-yaml'
import 'ace-builds/src-noconflict/theme-monokai'
import 'ace-builds/src-noconflict/ext-language_tools'

const props = defineProps({
  domain: String,
  initialPath: String
})

const webTerminalRef = ref(null)
const items = ref([])
const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(15)
const currentPath = ref(props.initialPath || '')
const breadcrumbs = ref([])
const loading = ref(false)
const saving = ref(false)
const showHidden = ref(false)
const deepSearch = ref(false)
const searchType = ref('filename')
const isSearching = ref(false)
const searchResults = ref([])
const gitLoading = ref(false)
const gitActionLoading = ref(false)
const gitCommitMessage = ref('')
const gitStashMessage = ref('')
const gitSelectedBranch = ref('')
const gitLastOutput = ref('')
const gitInfo = ref({
  available: false,
  message: '',
  repoRoot: '',
  branch: '',
  branches: [],
  statusLines: [],
  stashes: [],
  dirty: false
})

// Git token state
const showGitTokenForm = ref(false)
const gitTokenInput = ref('')
const gitTokenSaving = ref(false)
const gitTokenExists = ref(false)
const showTokenText = ref(false)

// drag and drop state
const isDragging = ref(false)
const dragCounter = ref(0) 

const selectedItems = ref([]) 
const allSelected = ref(false)

const showCreateFileModal = ref(false)
const showCreateDirModal = ref(false)
const showRenameModal = ref(false)
const showDeleteModal = ref(false)
const isBulkDelete = ref(false)
const deleteProcessing = ref(false)
const showEditorModal = ref(false)
const showPermissionsModal = ref(false)
const showCopyMoveModal = ref(false)

const newFileName = ref('')
const newDirName = ref('')
const renameName = ref('')
const selectedItem = ref(null)
const editingFile = ref('')
const fileContent = ref('')
const detectedMode = ref('text')
let aceEditor = null
const fileInput = ref(null)
const newPermissions = ref('')
const recursivePermissions = ref(false)

// Upload progress state
const uploading = ref(false)
const uploadProgress = ref(0)
const uploadFileName = ref('')

// Copy/Move modal state
const copyMoveItem = ref(null)
const copyMoveAction = ref('copy')
const copyMoveDestination = ref('')
const copyMoveProcessing = ref(false)
const copyMoveInput = ref(null)
const isBulkCopyMove = ref(false)
const bulkCopyMoveItems = ref([])

// Extract modal state
const showExtractModal = ref(false)
const extractItem = ref(null)
const extractDestination = ref('')
const extractProcessing = ref(false)

const contextMenu = ref({
  show: false,
  x: 0,
  y: 0,
  item: null
})

const alert = ref({
  show: false,
  type: 'success',
  message: ''
})

// Computed
const hasSelected = computed(() => selectedItems.value.length > 0)

const filteredItems = computed(() => {
  if (isSearching.value) return searchResults.value
  
  if (!searchQuery.value) return items.value
  const q = searchQuery.value.toLowerCase()
  return items.value.filter(item => 
    item.name.toLowerCase().includes(q) || 
    (item.extension && item.extension.toLowerCase().includes(q))
  )
})

const totalPages = computed(() => Math.ceil(filteredItems.value.length / itemsPerPage.value))
const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage.value)
const paginationEnd = computed(() => currentPage.value * itemsPerPage.value)

const paginatedItems = computed(() => {
  return filteredItems.value.slice(paginationStart.value, paginationEnd.value)
})

const contextMenuStyle = computed(() => {
  if (!contextMenu.value.show) return {}

  const menuWidth = 200
  const menuHeight = 350
  const windowWidth = window.innerWidth
  const windowHeight = window.innerHeight

  let x = contextMenu.value.x
  let y = contextMenu.value.y

  if (x + menuWidth > windowWidth) {
    x = windowWidth - menuWidth - 10
  }
  if (y + menuHeight > windowHeight) {
    y = windowHeight - menuHeight - 10
  }

  return {
    top: `${y}px`,
    left: `${x}px`
  }
})

onMounted(() => {
  loadFiles()
  checkGitToken()
  window.addEventListener('keydown', handleKeyboardShortcuts)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyboardShortcuts)
})

const handleKeyboardShortcuts = (e) => {
  const tag = e.target.tagName.toLowerCase()
  if (tag === 'input' || tag === 'textarea' || tag === 'select') return

  if (e.ctrlKey && e.key === 'a') {
    e.preventDefault()
    toggleSelectAll()
  }

  if (e.key === 'Delete' && hasSelected.value) {
    e.preventDefault()
    bulkDelete()
  }

  if (e.key === 'F5') {
    e.preventDefault()
    loadFiles()
  }

  if (e.key === 'Backspace' && currentPath.value) {
    e.preventDefault()
    goUpOneLevel()
  }
}

// Git Token Management
const checkGitToken = async () => {
  try {
    const response = await axios.get(`/file-manager/${props.domain}/git/token`)
    gitTokenExists.value = response.data.hasToken
  } catch (error) {
    gitTokenExists.value = false
  }
}

const saveGitToken = async () => {
  if (!gitTokenInput.value.trim()) return
  try {
    gitTokenSaving.value = true
    await axios.post(`/file-manager/${props.domain}/git/token`, {
      token: gitTokenInput.value.trim()
    })
    showAlert('success', 'Git token saved successfully.')
    gitTokenInput.value = ''
    gitTokenExists.value = true
    showGitTokenForm.value = false
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to save git token')
  } finally {
    gitTokenSaving.value = false
  }
}

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const openContextMenu = (event, item) => {
  contextMenu.value = {
    show: true,
    x: event.clientX,
    y: event.clientY,
    item: item
  }
}

const closeContextMenu = () => {
  contextMenu.value.show = false
}

const openPermissionsModal = (item) => {
  selectedItem.value = item
  newPermissions.value = item.permissions
  recursivePermissions.value = false
  showPermissionsModal.value = true
}

const changePermissions = async () => {
  try {
    await axios.post(`/file-manager/${props.domain}/chmod`, {
      path: currentPath.value || '',
      name: selectedItem.value.name,
      permissions: newPermissions.value,
      recursive: recursivePermissions.value
    })
    showAlert('success', 'Permissions updated')
    showPermissionsModal.value = false
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to update permissions')
  }
}

const loadFiles = async () => {
  try {
    loading.value = true
    isSearching.value = false
    searchResults.value = []
    const response = await axios.post(`/file-manager/${props.domain}/list`, {
      path: currentPath.value || '',
      showHidden: showHidden.value
    })
    items.value = response.data.items
    breadcrumbs.value = response.data.breadcrumbs
    selectedItems.value = []
    allSelected.value = false
    await loadGitStatus()
  } catch (error) {
    showAlert('danger', 'Failed to load files')
  } finally {
    loading.value = false
  }
}

const handleSearch = async () => {
  if (!searchQuery.value || searchQuery.value.length < 2) {
    if (isSearching.value) loadFiles()
    return
  }

  if (!deepSearch.value) {
    isSearching.value = false
    return
  }

  try {
    loading.value = true
    isSearching.value = true
    const response = await axios.post(`/file-manager/${props.domain}/search`, {
      query: searchQuery.value,
      path: currentPath.value || '',
      type: searchType.value
    })
    searchResults.value = response.data.results
    currentPage.value = 1
  } catch (error) {
    showAlert('danger', 'Search failed')
  } finally {
    loading.value = false
  }
}

const loadGitStatus = async () => {
  try {
    gitLoading.value = true
    const response = await axios.post(`/file-manager/${props.domain}/git/status`, {
      path: currentPath.value || ''
    })
    gitInfo.value = {
      available: response.data.available ?? false,
      message: response.data.message || '',
      repoRoot: response.data.repoRoot || '',
      branch: response.data.branch || '',
      branches: response.data.branches || [],
      statusLines: response.data.statusLines || [],
      stashes: response.data.stashes || [],
      dirty: response.data.dirty || false
    }

    if (gitInfo.value.branch && !gitSelectedBranch.value) {
      gitSelectedBranch.value = gitInfo.value.branch
    }
  } catch (error) {
    gitInfo.value.available = false
  } finally {
    gitLoading.value = false
  }
}

const performGitAction = async (action, payload = {}) => {
  try {
    gitActionLoading.value = true
    const response = await axios.post(`/file-manager/${props.domain}/git/action`, {
      path: currentPath.value || '',
      action,
      ...payload
    })

    gitLastOutput.value = response.data.output || response.data.message
    showAlert('success', response.data.message || 'Action completed')

    if (action === 'commit') gitCommitMessage.value = ''
    
    await loadFiles()
  } catch (error) {
    gitLastOutput.value = error.response?.data?.error || 'Action failed'
    showAlert('danger', gitLastOutput.value)
  } finally {
    gitActionLoading.value = false
  }
}

const runGitCommit = () => performGitAction('commit', { message: gitCommitMessage.value.trim() })
const runGitSwitchBranch = () => performGitAction('switch_branch', { branch: gitSelectedBranch.value })

const navigateTo = (path) => {
  currentPath.value = path
  loadFiles()
}

const openDirectory = (name) => {
  currentPath.value = currentPath.value ? `${currentPath.value}/${name}` : name
  loadFiles()
}

const goUpOneLevel = () => {
  if (!currentPath.value) return
  const pathParts = currentPath.value.split('/').filter(Boolean)
  pathParts.pop()
  currentPath.value = pathParts.join('/')
  loadFiles()
}

const goBack = () => router.visit('/domains')

const createFile = async () => {
  try {
    await axios.post(`/file-manager/${props.domain}/create-file`, {
      path: currentPath.value || '',
      name: newFileName.value
    })
    showAlert('success', 'File created')
    showCreateFileModal.value = false
    newFileName.value = ''
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to create file')
  }
}

const createDirectory = async () => {
  try {
    await axios.post(`/file-manager/${props.domain}/create-directory`, {
      path: currentPath.value || '',
      name: newDirName.value
    })
    showAlert('success', 'Folder created')
    showCreateDirModal.value = false
    newDirName.value = ''
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to create folder')
  }
}

const openRenameModal = (item) => {
  selectedItem.value = item
  renameName.value = item.name
  showRenameModal.value = true
}

const renameItem = async () => {
  try {
    await axios.post(`/file-manager/${props.domain}/rename`, {
      path: currentPath.value || '',
      oldName: selectedItem.value.name,
      newName: renameName.value
    })
    showAlert('success', 'Renamed successfully')
    showRenameModal.value = false
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to rename')
  }
}

const confirmDelete = (item) => {
  isBulkDelete.value = false
  selectedItem.value = item
  showDeleteModal.value = true
}

const executeDelete = async () => {
  try {
    deleteProcessing.value = true
    if (isBulkDelete.value) {
      await axios.post(`/file-manager/${props.domain}/delete-multiple`, {
        path: currentPath.value || '',
        items: selectedItems.value.map(i => i.name)
      })
      showAlert('success', 'Items deleted')
      selectedItems.value = []
    } else {
      await axios.post(`/file-manager/${props.domain}/delete`, {
        path: currentPath.value || '',
        name: selectedItem.value.name
      })
      showAlert('success', 'Item deleted')
    }
    showDeleteModal.value = false
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to delete')
  } finally {
    deleteProcessing.value = false
  }
}

const editFile = async (name) => {
  try {
    const filePath = currentPath.value ? `${currentPath.value}/${name}` : name
    const response = await axios.post(`/file-manager/${props.domain}/read`, { path: filePath })
    fileContent.value = response.data.content
    editingFile.value = name
    showEditorModal.value = true
    
    // Auto-detect language
    const ext = name.split('.').pop().toLowerCase()
    const modes = {
      'php': 'php', 'js': 'javascript', 'html': 'html', 'css': 'css', 
      'json': 'json', 'sh': 'sh', 'sql': 'sql', 'py': 'python',
      'md': 'markdown', 'xml': 'xml', 'yaml': 'yaml', 'yml': 'yaml'
    }
    detectedMode.value = modes[ext] || 'text'

    nextTick(() => {
      initAceEditor()
    })
  } catch (error) {
    const msg = error.response?.data?.error || 'Failed to read file'
    showAlert('danger', msg)
  }
}

const initAceEditor = () => {
  if (aceEditor) aceEditor.destroy()
  
  aceEditor = ace.edit("ace-editor-container")
  aceEditor.setTheme("ace/theme/monokai")
  aceEditor.session.setMode(`ace/mode/${detectedMode.value}`)
  aceEditor.setValue(fileContent.value, -1)
  
  // Options
  aceEditor.setOptions({
    fontSize: "14px",
    enableBasicAutocompletion: true,
    enableLiveAutocompletion: true,
    showPrintMargin: false,
    scrollPastEnd: 0.5,
    wrap: true
  })

  aceEditor.on('change', () => {
    fileContent.value = aceEditor.getValue()
  })
}

const saveFile = async () => {
  try {
    saving.value = true
    const filePath = currentPath.value ? `${currentPath.value}/${editingFile.value}` : editingFile.value
    await axios.post(`/file-manager/${props.domain}/save`, { path: filePath, content: fileContent.value })
    showAlert('success', 'File saved')
    // We don't closeEditor here anymore, just keep editing
    loadFiles()
  } catch (error) {
    showAlert('danger', 'Failed to save file')
  } finally {
    saving.value = false
  }
}

const closeEditor = () => {
  if (aceEditor) {
    aceEditor.destroy()
    aceEditor = null
  }
  showEditorModal.value = false
  editingFile.value = ''
  fileContent.value = ''
  document.body.style.overflow = '' // Restore scroll
}

const formatContent = () => {
  if (detectedMode.value === 'json') {
    try {
      const obj = JSON.parse(fileContent.value)
      const formatted = JSON.stringify(obj, null, 2)
      aceEditor.setValue(formatted, -1)
      fileContent.value = formatted
    } catch (e) {
      showAlert('danger', 'Invalid JSON content')
    }
  }
}

const jumpToPath = (path) => {
  const parts = path.split('/')
  parts.pop() // Remove filename
  currentPath.value = parts.join('/')
  loadFiles()
}

watch(showEditorModal, (val) => {
  if (val) document.body.style.overflow = 'hidden'
  else document.body.style.overflow = ''
})

const triggerUpload = () => fileInput.value.click()

const handleFileUpload = (e) => processFilesUpload(e.target.files)
const handleDrop = (e) => {
  isDragging.value = false
  processFilesUpload(e.dataTransfer.files)
}

const processFilesUpload = async (files) => {
  if (!files.length) return
  uploading.value = true
  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    uploadFileName.value = file.name
    const formData = new FormData()
    formData.append('file', file)
    formData.append('path', currentPath.value || '')
    try {
      await axios.post(`/file-manager/${props.domain}/upload`, formData, {
        onUploadProgress: (p) => uploadProgress.value = Math.round((p.loaded * 100) / p.total)
      })
    } catch (err) {
      showAlert('danger', `Upload failed: ${file.name}`)
    }
  }
  uploading.value = false
  loadFiles()
}

const handleDragEnter = () => { isDragging.value = true; dragCounter.value++ }
const handleDragLeave = () => { dragCounter.value--; if (dragCounter.value === 0) isDragging.value = false }
const handleDragOver = (e) => { e.preventDefault(); isDragging.value = true }

const downloadFile = (name) => {
  const filePath = currentPath.value ? `${currentPath.value}/${name}` : name
  window.location.href = `/file-manager/${props.domain}/download?path=${encodeURIComponent(filePath)}`
}

const isSelected = (item) => selectedItems.value.some(si => si.name === item.name)
const toggleSelectItem = (item) => {
  const idx = selectedItems.value.findIndex(si => si.name === item.name)
  if (idx > -1) selectedItems.value.splice(idx, 1)
  else selectedItems.value.push({ name: item.name, type: item.type })
}

const toggleSelectAll = () => {
  if (allSelected.value) selectedItems.value = []
  else selectedItems.value = items.value.map(i => ({ name: i.name, type: i.type }))
  allSelected.value = !allSelected.value
}

const bulkDelete = () => { isBulkDelete.value = true; showDeleteModal.value = true }

const bulkZip = async () => {
  const name = prompt('Zip name:', 'archive.zip')
  if (!name) return
  try {
    await axios.post(`/file-manager/${props.domain}/zip`, {
      path: currentPath.value || '',
      items: selectedItems.value.map(i => i.name),
      zipName: name.endsWith('.zip') ? name : name + '.zip'
    })
    showAlert('success', 'Zip created')
    selectedItems.value = []
    loadFiles()
  } catch (err) { showAlert('danger', 'Zip failed') }
}

const bulkCopyMove = (action) => {
  copyMoveAction.value = action
  copyMoveItem.value = { name: `${selectedItems.value.length} items` }
  isBulkCopyMove.value = true
  bulkCopyMoveItems.value = [...selectedItems.value]
  copyMoveDestination.value = currentPath.value || ''
  showCopyMoveModal.value = true
}

const openCopyMoveModal = (item, action) => {
  copyMoveAction.value = action
  copyMoveItem.value = item
  isBulkCopyMove.value = false
  copyMoveDestination.value = currentPath.value || ''
  showCopyMoveModal.value = true
}

const closeCopyMoveModal = () => showCopyMoveModal.value = false

const executeCopyMove = async () => {
  copyMoveProcessing.value = true
  try {
    const itemsToProcess = isBulkCopyMove.value ? bulkCopyMoveItems.value : [copyMoveItem.value]
    for (const it of itemsToProcess) {
      await axios.post(`/file-manager/${props.domain}/${copyMoveAction.value}`, {
        sourcePath: currentPath.value || '',
        name: it.name,
        destinationPath: copyMoveDestination.value || ''
      })
    }
    showAlert('success', 'Action completed')
    closeCopyMoveModal()
    loadFiles()
  } catch (err) { showAlert('danger', 'Action failed') }
  finally { copyMoveProcessing.value = false }
}

const itemIsArchive = (item) => item?.name.toLowerCase().endsWith('.zip')
const openExtractModal = (item) => {
  extractItem.value = item
  showExtractModal.value = true
}

const executeExtract = async () => {
  extractProcessing.value = true
  try {
    await axios.post(`/file-manager/${props.domain}/extract`, {
      path: currentPath.value || '',
      name: extractItem.value.name,
      destination: extractDestination.value
    })
    showAlert('success', 'Extracted')
    showExtractModal.value = false
    loadFiles()
  } catch (err) { showAlert('danger', 'Extraction failed') }
  finally { extractProcessing.value = false }
}

const handleDoubleClick = (item) => {
  if (item.type === 'directory') openDirectory(item.name)
  else if (item.editable) editFile(item.name)
}

const scrollToGit = () => document.getElementById('git-panel')?.scrollIntoView({ behavior: 'smooth' })

</script>

<style scoped>
.glass-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(8px) saturate(160%);
  border: 1px solid rgba(255, 255, 255, 0.4);
  border-radius: 1rem;
  box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.1);
  will-change: transform, opacity;
}

.nav-pill-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 16px;
  border: none;
  background: transparent;
  border-radius: 12px;
  color: #67748e;
  font-size: 14px;
  transition: all 0.2s ease;
  margin-bottom: 4px;
  text-align: left;
}

.nav-pill-btn:hover { background: rgba(0, 0, 0, 0.04); color: #344767; }
.nav-pill-btn.active { background: #fff; color: #5e72e4; font-weight: 700; shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }

.glass-search { background: rgba(0, 0, 0, 0.05); border-radius: 10px; width: 220px; }
.file-row-modern { 
  transition: background-color 0.2s ease, transform 0.2s ease; 
  border-bottom: 1px solid rgba(0,0,0,0.03); 
  will-change: background-color, transform;
  cursor: pointer;
  user-select: none;
}
.file-row-modern:hover { background: rgba(94, 114, 228, 0.05); transform: translateX(2px); }
.file-row-modern.selected { background: rgba(94, 114, 228, 0.08); }

.search-wrapper-premium {
  display: flex;
  align-items: center;
  background: #fff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 12px;
  padding: 4px 12px;
  min-width: 350px;
  transition: all 0.3s ease;
}

.search-wrapper-premium:focus-within {
  border-color: #5e72e4;
  box-shadow: 0 0 0 3px rgba(94, 114, 228, 0.1);
}

.search-type-selector {
  display: flex;
  align-items: center;
  gap: 4px;
}

.search-type-selector select {
  padding: 4px 20px 4px 4px;
  width: auto;
  font-size: 11px;
  color: #344767;
}

.search-input-box {
  display: flex;
  align-items: center;
  flex-grow: 1;
  gap: 8px;
}

.search-input-box input {
  font-size: 13px;
  height: 32px;
}

.btn-icon-only.btn-rounded {
  border-radius: 50% !important;
  width: 38px;
  height: 38px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-icon-box { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.bg-light-warning { background: rgba(251, 207, 51, 0.15); }
.bg-light-info { background: rgba(17, 205, 239, 0.15); }

.action-btn-circle {
  width: 30px; height: 30px; border-radius: 50%; border: none; background: transparent;
  color: #67748e; display: flex; align-items: center; justify-content: center; transition: all 0.2s;
}
.action-btn-circle:hover { 
  background: white; 
  color: #5e72e4; 
  box-shadow: 0 4px 10px rgba(0,0,0,0.08); 
  transform: translateY(-1px);
}

.bulk-actions-overlay { position: sticky; bottom: 10px; z-index: 100; border-radius: 12px; }

.git-console {
  background: #1e1e2f; color: #a9b7c6; padding: 1rem; border-radius: 12px;
  font-family: 'Fira Code', monospace; font-size: 12px; border: 1px solid rgba(255,255,255,0.1);
}

.context-menu {
  position: fixed; z-index: 3000; background: rgba(255, 255, 255, 0.98);
  backdrop-filter: none; border-radius: 12px; box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
  min-width: 200px; padding: 8px; border: 1px solid rgba(0, 0, 0, 0.05);
}
.context-menu-item { padding: 8px 12px; border-radius: 8px; font-size: 13px; cursor: pointer; display: flex; align-items: center; }
.context-menu-item:hover { background: #5e72e4; color: white; }
.context-menu-divider { height: 1px; background: rgba(0,0,0,0.05); margin: 4px 0; }

.fullscreen-editor-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999 !important;
  background: #1e1e1e; display: flex; flex-direction: column;
  width: 100vw; height: 100vh;
}

.editor-header {
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  min-height: 60px;
}

.spin-animation { animation: rotate 2s linear infinite; }
@keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-up-enter-active, .slide-up-leave-active { transition: all 0.3s ease-out; }
.slide-up-enter-from { transform: translateY(20px); opacity: 0; }
.slide-up-leave-to { transform: translateY(20px); opacity: 0; }

.upload-drop-zone-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 2000;
  background: rgba(94, 114, 228, 0.9); backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center; text-align: center;
}
.drop-zone-icon-box {
  width: 100px; height: 100px; background: rgba(255,255,255,0.2);
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
}
</style>
