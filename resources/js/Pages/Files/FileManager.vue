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
      <div class="row" v-if="alert.show">
        <div class="col-12">
          <div :class="`alert alert-${alert.type} alert-dismissible fade show`">
            <span class="alert-icon">
              <i class="material-symbols-rounded">{{ alert.type === 'success' ? 'check_circle' : 'error' }}</i>
            </span>
            <span class="alert-text">{{ alert.message }}</span>
            <button type="button" class="btn-close" @click="alert.show = false"></button>
          </div>
        </div>
      </div>

      <!-- Toolbar -->
      <div class="row mb-3">
        <div class="col-12">
          <div class="card toolbar-card">
            <div class="card-body p-3">
              <!-- Row 1: Primary Actions + Search -->
              <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Create group -->
                <div class="btn-group" role="group">
                  <button class="btn btn-sm bg-gradient-dark mb-0" @click="showCreateFileModal = true" title="New File">
                    <i class="material-symbols-rounded text-sm me-1">note_add</i>
                    New File
                  </button>
                  <button class="btn btn-sm bg-gradient-dark mb-0" @click="showCreateDirModal = true" title="New Folder">
                    <i class="material-symbols-rounded text-sm me-1">create_new_folder</i>
                    New Folder
                  </button>
                </div>

                <!-- Upload -->
                <button class="btn btn-sm bg-gradient-primary mb-0" @click="triggerUpload">
                  <i class="material-symbols-rounded text-sm me-1">upload</i>
                  Upload
                </button>
                <input ref="fileInput" type="file" style="display:none" @change="handleFileUpload" />

                <!-- Terminal -->
                <button class="btn btn-sm bg-gradient-success mb-0" @click="webTerminalRef?.openTerminal()" title="Open Web Terminal">
                  <i class="material-symbols-rounded text-sm me-1">terminal</i>
                  Terminal
                </button>

                <!-- Navigation -->
                <div class="toolbar-divider"></div>
                <button v-if="currentPath" class="btn btn-sm btn-outline-dark mb-0" @click="goUpOneLevel">
                  <i class="material-symbols-rounded text-sm me-1">arrow_upward</i>
                  Up
                </button>
                <button class="btn btn-sm btn-outline-dark mb-0" @click="loadFiles" :disabled="loading">
                  <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin-animation': loading }">refresh</i>
                  Refresh
                </button>

                <!-- Search -->
                <div class="ms-auto d-flex align-items-center gap-2">
                  <div class="input-group input-group-sm toolbar-search">
                    <span class="input-group-text bg-transparent border-end-0">
                      <i class="material-symbols-rounded text-sm text-secondary">search</i>
                    </span>
                    <input v-model="searchQuery" type="text" class="form-control border-start-0 ps-0"
                      placeholder="Search files and folders..." />
                  </div>
                  <div class="form-check form-switch mb-0 ms-1">
                    <input class="form-check-input" type="checkbox" id="showHiddenToggle" v-model="showHidden"
                      @change="loadFiles">
                    <label class="form-check-label text-xs text-nowrap" for="showHiddenToggle">
                      Hidden
                    </label>
                  </div>
                </div>
              </div>

              <!-- Row 2: Bulk actions (only shown when items are selected) -->
              <div v-if="hasSelected" class="d-flex flex-wrap align-items-center gap-2 mt-2 pt-2 bulk-actions-bar">
                <span class="badge bg-gradient-primary me-1">{{ selectedItems.length }} selected</span>
                <button class="btn btn-sm btn-outline-danger mb-0" @click="bulkDelete">
                  <i class="material-symbols-rounded text-sm me-1">delete</i>
                  Delete
                </button>
                <button class="btn btn-sm btn-outline-secondary mb-0" @click="bulkZip">
                  <i class="material-symbols-rounded text-sm me-1">folder_zip</i>
                  Zip
                </button>
                <button class="btn btn-sm btn-outline-info mb-0" @click="bulkCopyMove('copy')">
                  <i class="material-symbols-rounded text-sm me-1">content_copy</i>
                  Copy
                </button>
                <button class="btn btn-sm btn-outline-warning mb-0" @click="bulkCopyMove('move')">
                  <i class="material-symbols-rounded text-sm me-1">drive_file_move</i>
                  Move
                </button>
                <button class="btn btn-sm btn-link text-secondary mb-0 ms-auto" @click="selectedItems = []; allSelected = false">
                  Clear selection
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Breadcrumbs -->
      <div class="row mb-3">
        <div class="col-12">
          <div class="breadcrumb-bar d-flex align-items-center">
            <i class="material-symbols-rounded text-sm text-secondary me-2">folder_open</i>
            <nav aria-label="breadcrumb" class="flex-grow-1">
              <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-0">
                <li v-for="(crumb, index) in breadcrumbs" :key="index" class="breadcrumb-item"
                  :class="{ active: index === breadcrumbs.length - 1 }">
                  <a v-if="index < breadcrumbs.length - 1" href="#" @click.prevent="navigateTo(crumb.path)"
                    class="text-dark">
                    {{ crumb.name }}
                  </a>
                  <span v-else>{{ crumb.name }}</span>
                </li>
              </ol>
            </nav>
            <button class="btn btn-sm btn-link text-secondary mb-0 p-0" @click="toggleSelectAll" title="Select All">
              <i class="material-symbols-rounded text-sm">{{ allSelected ? 'deselect' : 'select_all' }}</i>
            </button>
          </div>
        </div>
      </div>

      <!-- Git Panel -->
      <div class="row mb-3">
        <div class="col-12">
          <div class="card">
            <div class="card-body p-3">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <h6 class="mb-1">Git Repository</h6>
                  <p class="text-sm text-secondary mb-0">
                    Manage the nearest Git repository for the current folder.
                  </p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                  <span v-if="gitTokenExists" class="badge bg-gradient-success me-1">
                    <i class="material-symbols-rounded text-xs me-1">key</i> Token Set
                  </span>
                  <button class="btn btn-sm btn-outline-dark mb-0" @click="showGitTokenForm = !showGitTokenForm">
                    <i class="material-symbols-rounded text-sm me-1">key</i>
                    {{ showGitTokenForm ? 'Hide' : 'Configure Token' }}
                  </button>
                  <button class="btn btn-sm btn-outline-secondary mb-0" @click="loadGitStatus" :disabled="gitLoading">
                    <i class="material-symbols-rounded text-sm me-1">refresh</i>
                    Refresh Git
                  </button>
                </div>
              </div>

              <!-- Git Token Configuration -->
              <div v-if="showGitTokenForm" class="git-token-section mb-3">
                <div class="d-flex align-items-start gap-3">
                  <div class="flex-grow-1">
                    <label class="form-label text-sm mb-1 fw-bold">Personal Access Token</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light">
                        <i class="material-symbols-rounded text-sm text-secondary">key</i>
                      </span>
                      <input v-model="gitTokenInput" :type="showTokenText ? 'text' : 'password'" class="form-control"
                        placeholder="ghp_xxxx... or gitlab token" />
                      <button class="btn btn-outline-secondary mb-0" @click="showTokenText = !showTokenText" type="button">
                        <i class="material-symbols-rounded text-sm">{{ showTokenText ? 'visibility_off' : 'visibility' }}</i>
                      </button>
                      <button class="btn bg-gradient-primary mb-0" @click="saveGitToken" :disabled="!gitTokenInput.trim() || gitTokenSaving">
                        <span v-if="gitTokenSaving" class="spinner-border spinner-border-sm me-1"></span>
                        Save Token
                      </button>
                    </div>
                    <small class="text-muted mt-1 d-block">
                      <i class="material-symbols-rounded text-xs align-middle me-1">info</i>
                      Used for Git Pull/Push over HTTPS. Token is stored securely and never displayed.
                      <a href="https://github.com/settings/tokens" target="_blank" class="text-primary">Generate on GitHub</a>
                    </small>
                  </div>
                </div>
              </div>

              <div v-if="gitLoading" class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
              </div>

              <div v-else-if="gitInfo.available">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                  <span class="badge bg-gradient-dark">Branch: {{ gitInfo.branch || 'detached' }}</span>
                  <span class="badge" :class="gitInfo.dirty ? 'bg-gradient-warning' : 'bg-gradient-success'">
                    {{ gitInfo.dirty ? 'Working Tree Dirty' : 'Working Tree Clean' }}
                  </span>
                  <span class="text-sm text-secondary">
                    Repo Root: /var/www/{{ domain }}{{ gitInfo.repoRoot ? '/' + gitInfo.repoRoot : '' }}
                  </span>
                </div>

                <div class="row g-3 mb-3">
                  <div class="col-lg-4">
                    <label class="form-label text-sm mb-1">Commit Message</label>
                    <div class="input-group">
                      <input v-model="gitCommitMessage" type="text" class="form-control" placeholder="Describe your changes">
                      <button class="btn bg-gradient-primary mb-0" @click="runGitCommit" :disabled="gitActionLoading || !gitCommitMessage.trim()">
                        Commit
                      </button>
                    </div>
                  </div>
                  <div class="col-lg-4">
                    <label class="form-label text-sm mb-1">Switch Branch</label>
                    <div class="input-group">
                      <select v-model="gitSelectedBranch" class="form-select">
                        <option value="">Select branch</option>
                        <option v-for="branch in gitInfo.branches" :key="branch" :value="branch">
                          {{ branch }}
                        </option>
                      </select>
                      <button class="btn bg-gradient-info mb-0" @click="runGitSwitchBranch" :disabled="gitActionLoading || !gitSelectedBranch">
                        Switch
                      </button>
                    </div>
                  </div>
                  <div class="col-lg-4">
                    <label class="form-label text-sm mb-1">Create Stash</label>
                    <div class="input-group">
                      <input v-model="gitStashMessage" type="text" class="form-control" placeholder="Optional stash message">
                      <button class="btn bg-gradient-secondary mb-0" @click="runGitStash" :disabled="gitActionLoading">
                        Stash
                      </button>
                    </div>
                  </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                  <button class="btn btn-sm btn-outline-success mb-0" @click="performGitAction('pull')" :disabled="gitActionLoading">
                    <i class="material-symbols-rounded text-sm me-1">south</i>
                    Pull
                  </button>
                  <button class="btn btn-sm btn-outline-primary mb-0" @click="performGitAction('push')" :disabled="gitActionLoading">
                    <i class="material-symbols-rounded text-sm me-1">north</i>
                    Push
                  </button>
                  <button class="btn btn-sm btn-outline-dark mb-0" @click="performGitAction('stash_pop')" :disabled="gitActionLoading || gitInfo.stashes.length === 0">
                    <i class="material-symbols-rounded text-sm me-1">inventory_2</i>
                    Stash Pop Latest
                  </button>
                  <span v-if="gitActionLoading" class="text-sm text-secondary d-inline-flex align-items-center">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Running Git action...
                  </span>
                </div>

                <div class="row g-3">
                  <div class="col-lg-6">
                    <label class="form-label text-sm mb-1">Git Status</label>
                    <pre class="git-console mb-0">{{ gitInfo.statusLines.length ? gitInfo.statusLines.join('\n') : 'Working tree clean' }}</pre>
                  </div>
                  <div class="col-lg-6">
                    <label class="form-label text-sm mb-1">Stashes</label>
                    <pre class="git-console mb-0">{{ gitInfo.stashes.length ? gitInfo.stashes.map(stash => `${stash.ref} ${stash.message}`).join('\n') : 'No stashes found' }}</pre>
                  </div>
                </div>

                <div v-if="gitLastOutput" class="mt-3">
                  <label class="form-label text-sm mb-1">Last Git Output</label>
                  <pre class="git-console mb-0">{{ gitLastOutput }}</pre>
                </div>
              </div>

              <div v-else class="text-sm text-secondary">
                {{ gitInfo.message || 'No Git repository found in this folder or its parent folders.' }}
            <div class="d-flex align-items-center gap-2">
              <span class="text-xxs text-secondary font-weight-bold text-uppercase">{{ items.length }} items</span>
              <button class="btn btn-link text-secondary mb-0 p-1" @click="toggleSelectAll">
                <i class="material-symbols-rounded text-sm">{{ allSelected ? 'deselect' : 'select_all' }}</i>
              </button>
            </div>
          </div>

          <!-- File List Area -->
          <div class="glass-card p-0 overflow-hidden">
            <div class="table-responsive">
              <table class="table align-items-center mb-0">
                <thead class="bg-gray-100 opacity-7">
                  <tr>
                    <th style="width:40px" class="text-uppercase text-secondary text-xxs font-weight-bolder"></th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Name</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Size</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder">Modified</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder text-center">Actions</th>
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
                        <div class="file-icon-box me-3" :class="item.type === 'directory' ? 'bg-light-warning' : 'bg-light-info'">
                          <i class="material-symbols-rounded" :class="item.type === 'directory' ? 'text-warning' : 'text-info'">
                            {{ item.type === 'directory' ? 'folder' : 'description' }}
                          </i>
                        </div>
                        <div class="d-flex flex-column">
                          <a v-if="item.type === 'directory'" href="#" @click.prevent="openDirectory(item.name)" class="text-sm font-weight-bold text-dark mb-0">
                            {{ item.name }}
                          </a>
                          <span v-else class="text-sm font-weight-bold text-dark mb-0">{{ item.name }}</span>
                          <span class="text-xxs text-secondary">{{ item.permissions }}</span>
                        </div>
                      </div>
                    </td>
                    <td><span class="text-xs font-weight-bold">{{ item.type === 'directory' ? '--' : item.sizeFormatted }}</span></td>
                    <td><span class="text-xs text-secondary">{{ item.modified }}</span></td>
                    <td class="text-center">
                      <div class="d-flex justify-content-center gap-1">
                        <button v-if="item.type === 'file' && item.editable" class="action-btn-circle" @click="editFile(item.name)" title="Edit">
                          <i class="material-symbols-rounded text-sm">edit_note</i>
                        </button>
                        <button v-if="item.type === 'file'" class="action-btn-circle" @click="downloadFile(item.name)" title="Download">
                          <i class="material-symbols-rounded text-sm">download</i>
                        </button>
                        <button class="action-btn-circle" @click="openContextMenu($event, item)" title="More">
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
              <div class="empty-state-icon mb-3">
                <i class="material-symbols-rounded">folder_open</i>
              </div>
              <h6 class="text-secondary">Empty Directory</h6>
              <p class="text-xs text-muted">Upload or create files to get started</p>
              <button class="btn btn-sm bg-gradient-primary mt-2" @click="triggerUpload">Upload Files</button>
            </div>

            <!-- Pagination -->
            <div v-if="filteredItems.length > itemsPerPage" class="d-flex justify-content-between align-items-center p-3 border-top bg-gray-50">
              <span class="text-xxs text-secondary font-weight-bold">Page {{ currentPage }} of {{ totalPages }}</span>
              <ul class="pagination pagination-primary pagination-xs mb-0">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                  <button class="page-link" @click="currentPage--"><i class="material-symbols-rounded">chevron_left</i></button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                  <button class="page-link" @click="currentPage++"><i class="material-symbols-rounded">chevron_right</i></button>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Git Panel (Moved to bottom or hidden until needed) -->
      <div id="git-panel" class="row mt-4">

      <!-- Context Menu -->
      <div v-if="contextMenu.show" class="context-menu" :style="contextMenuStyle" @click.stop>
        <div v-if="contextMenu.item.type === 'file' && contextMenu.item.editable" class="context-menu-item"
          @click="editFile(contextMenu.item.name); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">edit_note</i>
          Edit
        </div>
        <div v-if="contextMenu.item.type === 'file'" class="context-menu-item"
          @click="downloadFile(contextMenu.item.name); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">download</i>
          Download
        </div>
        <div class="context-menu-item" @click="openRenameModal(contextMenu.item); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">label</i>
          Rename
        </div>
        <div class="context-menu-item" @click="openCopyMoveModal(contextMenu.item, 'copy'); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">content_copy</i>
          Copy
        </div>
        <div class="context-menu-item" @click="openCopyMoveModal(contextMenu.item, 'move'); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">drive_file_move</i>
          Move
        </div>
        <div class="context-menu-item" @click="openPermissionsModal(contextMenu.item); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">shield</i>
          Permissions
        </div>
        <div class="context-menu-item" @click="zipItem(contextMenu.item); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">folder_zip</i>
          Create ZIP
        </div>
        <div v-if="contextMenu.item.type === 'file' && isArchive(contextMenu.item.name)" class="context-menu-item"
          @click="openExtractModal(contextMenu.item); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">unarchive</i>
          Extract Here
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item text-danger" @click="confirmDelete(contextMenu.item); closeContextMenu()">
          <i class="material-symbols-rounded text-sm me-2">delete</i>
          Delete
        </div>
      </div>

      <!-- Permissions Modal -->
      <div class="modal-backdrop fade show" v-if="showPermissionsModal" @click="showPermissionsModal = false"></div>
      <div class="modal fade show" style="display:block" v-if="showPermissionsModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Change Permissions: {{ selectedItem?.name }}</h5>
              <button type="button" class="btn-close" @click="showPermissionsModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label class="form-label">Permission Mode</label>
                <input v-model="newPermissions" type="text" class="form-control" placeholder="0755" maxlength="4"
                  pattern="[0-7]{3,4}" />
                <small class="text-muted d-block mt-1">
                  Common: 644 (files), 755 (folders), 777 (full access)
                </small>
              </div>
              <div class="form-check mt-3" v-if="selectedItem?.type === 'directory'">
                <input class="form-check-input" type="checkbox" v-model="recursivePermissions" id="recursiveCheck">
                <label class="form-check-label" for="recursiveCheck">
                  Apply recursively to all files and folders inside
                </label>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showPermissionsModal = false">Cancel</button>
              <button class="btn bg-gradient-primary" @click="changePermissions">Apply</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create File Modal -->
      <div class="modal-backdrop fade show" v-if="showCreateFileModal" @click="showCreateFileModal = false"></div>
      <div class="modal fade show" style="display:block" v-if="showCreateFileModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content create-modal">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center">
                <div class="modal-icon bg-gradient-primary">
                  <i class="material-symbols-rounded">note_add</i>
                </div>
                <div class="ms-3">
                  <h5 class="modal-title mb-0">Create New File</h5>
                  <p class="text-sm text-secondary mb-0">Add a new file to current directory</p>
                </div>
              </div>
              <button type="button" class="btn-close" @click="showCreateFileModal = false"></button>
            </div>
            <div class="modal-body pt-4">
              <div class="form-group mb-0">
                <label class="form-label fw-bold text-dark">File Name <span class="text-danger">*</span></label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="material-symbols-rounded text-primary">description</i>
                  </span>
                  <input ref="createFileInput" v-model="newFileName" type="text"
                    class="form-control form-control-lg ps-0 border-start-0"
                    placeholder="Enter file name with extension (e.g., index.html)" @keyup.enter="createFile" />
                </div>
                <small class="text-muted mt-2 d-block">
                  <i class="material-symbols-rounded text-sm align-middle me-1">info</i>
                  Include the file extension like .php, .html, .css, .js, etc.
                </small>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button class="btn btn-outline-secondary" @click="showCreateFileModal = false">
                <i class="material-symbols-rounded text-sm me-1">close</i>
                Cancel
              </button>
              <button class="btn bg-gradient-primary" @click="createFile" :disabled="!newFileName">
                <i class="material-symbols-rounded text-sm me-1">add</i>
                Create File
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Directory Modal -->
      <div class="modal-backdrop fade show" v-if="showCreateDirModal" @click="showCreateDirModal = false"></div>
      <div class="modal fade show" style="display:block" v-if="showCreateDirModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content create-modal">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center">
                <div class="modal-icon bg-gradient-info">
                  <i class="material-symbols-rounded">create_new_folder</i>
                </div>
                <div class="ms-3">
                  <h5 class="modal-title mb-0">Create New Folder</h5>
                  <p class="text-sm text-secondary mb-0">Add a new folder to current directory</p>
                </div>
              </div>
              <button type="button" class="btn-close" @click="showCreateDirModal = false"></button>
            </div>
            <div class="modal-body pt-4">
              <div class="form-group mb-0">
                <label class="form-label fw-bold text-dark">Folder Name <span class="text-danger">*</span></label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="material-symbols-rounded text-info">folder</i>
                  </span>
                  <input ref="createFolderInput" v-model="newDirName" type="text"
                    class="form-control form-control-lg ps-0 border-start-0"
                    placeholder="Enter folder name (e.g., images, assets)" @keyup.enter="createDirectory" />
                </div>
                <small class="text-muted mt-2 d-block">
                  <i class="material-symbols-rounded text-sm align-middle me-1">info</i>
                  Use lowercase letters, numbers, and hyphens for best compatibility.
                </small>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button class="btn btn-outline-secondary" @click="showCreateDirModal = false">
                <i class="material-symbols-rounded text-sm me-1">close</i>
                Cancel
              </button>
              <button class="btn bg-gradient-info" @click="createDirectory" :disabled="!newDirName">
                <i class="material-symbols-rounded text-sm me-1">add</i>
                Create Folder
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload Progress Modal -->
      <div class="modal-backdrop fade show" v-if="uploading"></div>
      <div class="modal fade show" style="display:block" v-if="uploading">
        <div class="modal-dialog modal-dialog-centered modal-sm">
          <div class="modal-content">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center">
                <div class="modal-icon bg-gradient-success">
                  <i class="material-symbols-rounded">cloud_upload</i>
                </div>
                <div class="ms-3">
                  <h5 class="modal-title mb-0">Uploading</h5>
                  <p class="text-sm text-secondary mb-0">Please wait...</p>
                </div>
              </div>
            </div>
            <div class="modal-body pt-3 pb-4">
              <p class="text-sm text-dark mb-3 text-truncate fw-medium">
                <i class="material-symbols-rounded text-sm align-middle me-1 text-secondary">description</i>
                {{ uploadFileName }}
              </p>
              <div class="progress mb-2" style="height: 8px;">
                <div class="progress-bar bg-gradient-success" role="progressbar"
                  :style="{ width: uploadProgress + '%' }"></div>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-xs text-secondary">{{ uploadProgress }}% complete</span>
                <span class="text-xs text-success fw-bold" v-if="uploadProgress === 100">
                  <i class="material-symbols-rounded text-xs align-middle">check_circle</i> Done
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Copy/Move Modal -->
      <div class="modal-backdrop fade show" v-if="showCopyMoveModal" @click="closeCopyMoveModal"></div>
      <div class="modal fade show" style="display:block" v-if="showCopyMoveModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content copy-move-modal">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center">
                <div :class="['modal-icon', copyMoveAction === 'copy' ? 'bg-gradient-info' : 'bg-gradient-warning']">
                  <i class="material-symbols-rounded">{{ copyMoveAction === 'copy' ? 'content_copy' : 'drive_file_move'
                    }}</i>
                </div>
                <div class="ms-3">
                  <h5 class="modal-title mb-0">{{ copyMoveAction === 'copy' ? 'Copy' : 'Move' }} Item</h5>
                  <p class="text-sm text-secondary mb-0">Select destination for {{ copyMoveItem?.name }}</p>
                </div>
              </div>
              <button type="button" class="btn-close" @click="closeCopyMoveModal"></button>
            </div>
            <div class="modal-body pt-4">
              <!-- Source Info -->
              <div class="source-info-card mb-4">
                <div class="d-flex align-items-center">
                  <i class="material-symbols-rounded text-lg"
                    :class="isBulkCopyMove ? 'text-primary' : (copyMoveItem?.type === 'directory' ? 'text-warning' : 'text-info')">
                    {{ isBulkCopyMove ? 'folder_copy' : (copyMoveItem?.type === 'directory' ? 'folder' : 'description')
                    }}
                  </i>
                  <div class="ms-3">
                    <p class="mb-0 fw-bold">{{ copyMoveItem?.name }}</p>
                    <p class="mb-0 text-xs text-secondary">From: {{ currentPath || 'Root' }}</p>
                  </div>
                </div>
              </div>

              <!-- Destination Input -->
              <div class="form-group mb-0">
                <label class="form-label fw-bold text-dark">
                  Destination Path <span class="text-danger">*</span>
                </label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="material-symbols-rounded"
                      :class="copyMoveAction === 'copy' ? 'text-info' : 'text-warning'">folder_open</i>
                  </span>
                  <input ref="copyMoveInput" v-model="copyMoveDestination" type="text"
                    class="form-control form-control-lg ps-0 border-start-0"
                    placeholder="Leave empty for root, or enter path like 'images/icons'"
                    @keyup.enter="executeCopyMove" />
                </div>
                <small class="text-muted mt-2 d-block">
                  <i class="material-symbols-rounded text-sm align-middle me-1">info</i>
                  Enter relative path within the domain. Leave empty to {{ copyMoveAction }} to root.
                </small>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button class="btn btn-outline-secondary" @click="closeCopyMoveModal">
                <i class="material-symbols-rounded text-sm me-1">close</i>
                Cancel
              </button>
              <button :class="['btn', copyMoveAction === 'copy' ? 'bg-gradient-info' : 'bg-gradient-warning']"
                @click="executeCopyMove" :disabled="copyMoveProcessing">
                <span v-if="copyMoveProcessing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">{{ copyMoveAction === 'copy' ? 'content_copy' :
                  'drive_file_move' }}</i>
                {{ copyMoveAction === 'copy' ? 'Copy Here' : 'Move Here' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Rename Modal -->
      <div class="modal-backdrop fade show" v-if="showRenameModal" @click="showRenameModal = false"></div>
      <div class="modal fade show" style="display:block" v-if="showRenameModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Rename {{ selectedItem?.name }}</h5>
              <button type="button" class="btn-close" @click="showRenameModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>New Name</label>
                <input v-model="renameName" type="text" class="form-control" @keyup.enter="renameItem" />
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showRenameModal = false">Cancel</button>
              <button class="btn bg-gradient-primary" @click="renameItem" :disabled="!renameName">Rename</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div class="modal-backdrop fade show" v-if="showDeleteModal" @click="showDeleteModal = false"></div>
      <div class="modal fade show" style="display:block" v-if="showDeleteModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center">
                <div class="modal-icon bg-gradient-danger">
                  <i class="material-symbols-rounded">delete_forever</i>
                </div>
                <div class="ms-3">
                  <h5 class="modal-title mb-0">Confirm Deletion</h5>
                  <p class="text-sm text-secondary mb-0">This action cannot be undone</p>
                </div>
              </div>
              <button type="button" class="btn-close" @click="showDeleteModal = false"></button>
            </div>
            <div class="modal-body pt-4">
              <div v-if="isBulkDelete" class="alert alert-light border-0 mb-0 py-3">
                <p class="mb-0 text-dark">
                  Are you sure you want to delete <span class="fw-bold text-danger">{{ selectedItems.length }}
                    items</span>?
                </p>
              </div>
              <div v-else class="alert alert-light border-0 mb-0 py-3">
                <p class="mb-0 text-dark text-break">
                  Are you sure you want to delete <span class="fw-bold text-danger">{{ selectedItem?.name }}</span>?
                </p>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button class="btn btn-outline-secondary" @click="showDeleteModal = false" :disabled="deleteProcessing">
                <i class="material-symbols-rounded text-sm me-1">close</i>
                Cancel
              </button>
              <button class="btn bg-gradient-danger" @click="executeDelete" :disabled="deleteProcessing">
                <span v-if="deleteProcessing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">delete</i>
                {{ isBulkDelete ? 'Delete All' : 'Delete Item' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Extract Modal -->
      <div class="modal-backdrop fade show" v-if="showExtractModal" @click="showExtractModal = false"></div>
      <div class="modal fade show" style="display:block" v-if="showExtractModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header border-0 pb-0">
              <div class="d-flex align-items-center">
                <div class="modal-icon bg-gradient-success">
                  <i class="material-symbols-rounded">unarchive</i>
                </div>
                <div class="ms-3">
                  <h5 class="modal-title mb-0">Extract Archive</h5>
                  <p class="text-sm text-secondary mb-0">{{ extractItem?.name }}</p>
                </div>
              </div>
              <button type="button" class="btn-close" @click="showExtractModal = false"></button>
            </div>
            <div class="modal-body pt-4">
              <div class="form-group mb-0">
                <label class="form-label fw-bold text-dark">Destination Path (optional)</label>
                <div class="input-group input-group-lg">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="material-symbols-rounded text-success">folder_open</i>
                  </span>
                  <input v-model="extractDestination" type="text"
                    class="form-control form-control-lg ps-0 border-start-0"
                    placeholder="Leave empty for same directory" @keyup.enter="executeExtract" />
                </div>
                <small class="text-muted mt-2 d-block">
                  <i class="material-symbols-rounded text-sm align-middle me-1">info</i>
                  Leave empty to extract to the current directory, or enter a relative path.
                </small>
              </div>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button class="btn btn-outline-secondary" @click="showExtractModal = false">
                <i class="material-symbols-rounded text-sm me-1">close</i>
                Cancel
              </button>
              <button class="btn bg-gradient-success" @click="executeExtract" :disabled="extractProcessing">
                <span v-if="extractProcessing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">unarchive</i>
                Extract
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- File Editor Modal -->
      <div class="modal-backdrop fade show" v-if="showEditorModal" @click="closeEditor"></div>
      <div class="modal fade show d-block" v-if="showEditorModal">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90vw;">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Editing: {{ editingFile }}</h5>
              <button type="button" class="btn-close" @click="closeEditor"></button>
            </div>
            <div class="modal-body">
              <textarea v-model="fileContent" class="form-control font-monospace" rows="25"
                style="font-size: 13px;"></textarea>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="closeEditor">Cancel</button>
              <button class="btn bg-gradient-success" @click="saveFile" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                Save Changes
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Upload Drop Zone Overlay -->
      <div v-if="isDragging" class="upload-drop-zone-overlay">
        <div class="drop-zone-content">
          <div class="drop-zone-icon-box mb-3">
            <i class="material-symbols-rounded">upload_file</i>
          </div>
          <h3 class="text-white mb-2">Drop files to upload</h3>
          <p class="text-white opacity-8">Uploading to: <span class="fw-bold">/var/www/{{ domain }}{{ currentPath ? '/' +
              currentPath : '' }}</span></p>
        </div>
      </div>

    </div>

    <!-- Web Terminal -->
    <WebTerminal
      ref="webTerminalRef"
      :domain="domain"
      :currentPath="currentPath"
      @refresh-files="loadFiles"
    />
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import WebTerminal from '@/Components/WebTerminal.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'

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
const dragCounter = ref(0) // Used to handle drag enter/leave events correctly on nested elements

const selectedItems = ref([]) // array of { name, type }
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
const showTerminal = ref(false)

const newFileName = ref('')
const newDirName = ref('')
const renameName = ref('')
const selectedItem = ref(null)
const editingFile = ref('')
const fileContent = ref('')
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

// Template refs for autofocus
const createFileInput = ref(null)
const createFolderInput = ref(null)

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

const handleDoubleClick = (item) => {
  if (item.type === 'directory') {
    openDirectory(item.name)
  } else if (item.type === 'file' && item.editable) {
    editFile(item.name)
  }
}

onMounted(() => {
  loadFiles()
  checkGitToken()
  window.addEventListener('keydown', handleKeyboardShortcuts)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyboardShortcuts)
})

const handleKeyboardShortcuts = (e) => {
  // Don't trigger shortcuts when typing in inputs, textareas, or selects
  const tag = e.target.tagName.toLowerCase()
  if (tag === 'input' || tag === 'textarea' || tag === 'select') return

  // Ctrl+A — Select All / Deselect All
  if (e.ctrlKey && e.key === 'a') {
    e.preventDefault()
    toggleSelectAll()
    return
  }

  // Ctrl+C — Copy Selected
  if (e.ctrlKey && e.key === 'c' && hasSelected.value) {
    e.preventDefault()
    bulkCopyMove('copy')
    return
  }

  // Ctrl+X — Move Selected
  if (e.ctrlKey && e.key === 'x' && hasSelected.value) {
    e.preventDefault()
    bulkCopyMove('move')
    return
  }

  // Delete — Delete Selected
  if (e.key === 'Delete' && hasSelected.value) {
    e.preventDefault()
    bulkDelete()
    return
  }

  // F5 — Refresh
  if (e.key === 'F5') {
    e.preventDefault()
    loadFiles()
    return
  }

  // Backspace — Go Up One Level
  if (e.key === 'Backspace' && currentPath.value) {
    e.preventDefault()
    goUpOneLevel()
    return
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
    showAlert('success', 'Git token saved successfully. Pull/Push should now work.')
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
    showAlert('success', 'Permissions changed successfully')
    showPermissionsModal.value = false
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to change permissions')
  }
}

const loadFiles = async () => {
  try {
    loading.value = true
    const response = await axios.post(`/file-manager/${props.domain}/list`, {
      path: currentPath.value || '',
      showHidden: showHidden.value
    })
    items.value = response.data.items
    breadcrumbs.value = response.data.breadcrumbs
    // reset selection because list changed
    selectedItems.value = []
    allSelected.value = false
    await loadGitStatus()
  } catch (error) {
    showAlert('danger', 'Failed to load files')
    console.error(error)
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
    gitInfo.value = {
      available: false,
      message: error.response?.data?.error || 'Failed to load Git status.',
      repoRoot: '',
      branch: '',
      branches: [],
      statusLines: [],
      stashes: [],
      dirty: false
    }
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

    gitLastOutput.value = response.data.output || response.data.message || 'Git action completed successfully.'
    showAlert('success', response.data.message || 'Git action completed successfully.')

    if (action === 'commit') {
      gitCommitMessage.value = ''
    }
    if (action === 'stash') {
      gitStashMessage.value = ''
    }

    await loadFiles()
  } catch (error) {
    gitLastOutput.value = error.response?.data?.error || 'Git action failed.'
    showAlert('danger', gitLastOutput.value)
  } finally {
    gitActionLoading.value = false
  }
}

const runGitCommit = async () => {
  if (!gitCommitMessage.value.trim()) return
  await performGitAction('commit', { message: gitCommitMessage.value.trim() })
}

const runGitSwitchBranch = async () => {
  if (!gitSelectedBranch.value) return
  await performGitAction('switch_branch', { branch: gitSelectedBranch.value })
}

const runGitStash = async () => {
  await performGitAction('stash', { message: gitStashMessage.value.trim() })
}

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

const goBack = () => {
  router.visit('/domains')
}

const createFile = async () => {
  try {
    await axios.post(`/file-manager/${props.domain}/create-file`, {
      path: currentPath.value || '',
      name: newFileName.value
    })
    showAlert('success', 'File created successfully')
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
    showAlert('success', 'Folder created successfully')
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
      // Bulk delete logic
      await axios.post(`/file-manager/${props.domain}/delete-multiple`, {
        path: currentPath.value || '',
        items: getSelectedItems()
      })
      showAlert('success', 'Selected items deleted successfully')
      selectedItems.value = []
    } else {
      // Single delete logic
      await axios.post(`/file-manager/${props.domain}/delete`, {
        path: currentPath.value || '',
        name: selectedItem.value.name
      })
      showAlert('success', `${selectedItem.value.name} deleted successfully`)
    }
    showDeleteModal.value = false
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to delete item(s)')
  } finally {
    deleteProcessing.value = false
  }
}

const editFile = async (name) => {
  try {
    const filePath = currentPath.value ? `${currentPath.value}/${name}` : name
    const response = await axios.post(`/file-manager/${props.domain}/read`, {
      path: filePath
    })
    fileContent.value = response.data.content
    editingFile.value = name
    showEditorModal.value = true
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to read file')
  }
}

const saveFile = async () => {
  try {
    saving.value = true
    const filePath = currentPath.value ? `${currentPath.value}/${editingFile.value}` : editingFile.value
    await axios.post(`/file-manager/${props.domain}/save`, {
      path: filePath,
      content: fileContent.value
    })
    showAlert('success', 'File saved successfully')
    closeEditor()
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to save file')
  } finally {
    saving.value = false
  }
}

const closeEditor = () => {
  showEditorModal.value = false
  editingFile.value = ''
  fileContent.value = ''
}

const triggerUpload = () => {
  fileInput.value.click()
}

const handleDragEnter = (e) => {
  dragCounter.value++
  if (e.dataTransfer.types.includes('Files')) {
    isDragging.value = true
  }
}

const handleDragOver = (e) => {
  // Required to allow drop
  if (e.dataTransfer.types.includes('Files')) {
    isDragging.value = true
  }
}

const handleDragLeave = (e) => {
  dragCounter.value--
  if (dragCounter.value === 0) {
    isDragging.value = false
  }
}

const handleDrop = async (e) => {
  isDragging.value = false
  dragCounter.value = 0
  
  const files = e.dataTransfer.files
  if (files.length > 0) {
    await processFilesUpload(files)
  }
}

const handleFileUpload = async (event) => {
  const files = event.target.files
  if (!files || files.length === 0) return
  
  await processFilesUpload(files)
  event.target.value = ''
}

const processFilesUpload = async (files) => {
  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    
    // Check file size client-side (unlimited now via chunking, but let's keep a sane max like 5GB)
    const maxSize = 5000 * 1024 * 1024 // 5GB in bytes
    if (file.size > maxSize) {
      showAlert('danger', `File "${file.name}" too large. Maximum size is 5GB.`)
      continue
    }

    // Initialize upload progress state
    uploading.value = true
    uploadProgress.value = 0
    uploadFileName.value = file.name

    try {
      const chunkSize = 5 * 1024 * 1024; // 5MB chunks
      const totalChunks = Math.ceil(file.size / chunkSize);

      for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        const start = chunkIndex * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData()
        formData.append('file', chunk)
        formData.append('path', currentPath.value || '')
        formData.append('isChunk', 'true')
        formData.append('chunkIndex', chunkIndex)
        formData.append('totalChunks', totalChunks)
        formData.append('originalName', file.name)

        await axios.post(`/file-manager/${props.domain}/upload`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
          timeout: 600000, // 10 minute timeout per chunk just in case
          onUploadProgress: (progressEvent) => {
            const loadedBytes = (chunkIndex * chunkSize) + progressEvent.loaded;
            const percentCompleted = Math.round((loadedBytes * 100) / file.size);
            uploadProgress.value = Math.min(percentCompleted, 100);
          }
        })
      }

      if (i === files.length - 1) {
        showAlert('success', files.length > 1 ? `Successfully uploaded ${files.length} files` : 'File uploaded successfully')
        loadFiles()
      }
    } catch (error) {
      let errorMessage = `Failed to upload "${file.name}"`

      if (error.response) {
        if (error.response.status === 413) {
          errorMessage = 'Chunk too large. Please check server upload limits.'
        } else if (error.response.status === 422) {
          errorMessage = error.response.data?.message || 'Validation failed'
        } else {
          errorMessage = error.response.data?.error || errorMessage
        }
      }
      
      showAlert('danger', errorMessage)
      break // Stop processing further files on error
    } finally {
      if (i === files.length - 1) {
        uploading.value = false
        uploadProgress.value = 0
        uploadFileName.value = ''
      }
    }
  }
}

const downloadFile = async (name) => {
  try {
    const filePath = currentPath.value ? `${currentPath.value}/${name}` : name
    window.location.href = `/file-manager/${props.domain}/download?path=${encodeURIComponent(filePath)}`
  } catch (error) {
    showAlert('danger', 'Failed to download file')
  }
}

/* =========================
   Selection / Bulk actions
   ========================= */

const isSelected = (item) => {
  return selectedItems.value.some(si => si.name === item.name && si.type === item.type)
}

const toggleSelectItem = (item, event) => {
  const already = isSelected(item)
  if (already) {
    selectedItems.value = selectedItems.value.filter(si => !(si.name === item.name && si.type === item.type))
  } else {
    selectedItems.value.push({ name: item.name, type: item.type })
  }
  allSelected.value = selectedItems.value.length === items.value.length && items.value.length > 0
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedItems.value = []
    allSelected.value = false
  } else {
    selectedItems.value = items.value.map(i => ({ name: i.name, type: i.type }))
    allSelected.value = true
  }
}

const getSelectedItems = () => selectedItems.value.map(i => i.name)

// Bulk delete opens the premium confirmation modal
const bulkDelete = () => {
  if (!hasSelected.value) return
  isBulkDelete.value = true
  showDeleteModal.value = true
}

// Bulk ZIP
const bulkZip = async () => {
  if (!hasSelected.value) return
  const zipName = prompt('Enter zip file name (without .zip):', 'archive')
  if (!zipName) return

  try {
    await axios.post(`/file-manager/${props.domain}/zip`, {
      path: currentPath.value || '',
      items: getSelectedItems(),
      zipName: zipName.endsWith('.zip') ? zipName : zipName + '.zip'
    })
    showAlert('success', 'ZIP archive created')
    selectedItems.value = []
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to create ZIP')
  }
}

// Check if file is an extractable archive
const isArchive = (filename) => {
  const lower = filename.toLowerCase()
  return lower.endsWith('.zip') || lower.endsWith('.tar') || lower.endsWith('.tar.gz') || lower.endsWith('.tgz')
}

// Open extract modal
const openExtractModal = (item) => {
  extractItem.value = item
  extractDestination.value = '' // Empty means same directory
  extractProcessing.value = false
  showExtractModal.value = true
}

// Execute extraction
const executeExtract = async () => {
  if (!extractItem.value) return

  try {
    extractProcessing.value = true
    await axios.post(`/file-manager/${props.domain}/extract`, {
      path: currentPath.value || '',
      name: extractItem.value.name,
      destination: extractDestination.value
    })
    showAlert('success', 'Archive extracted successfully')
    showExtractModal.value = false
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to extract archive')
  } finally {
    extractProcessing.value = false
  }
}

// Bulk Copy/Move: opens the modal for bulk operations
const bulkCopyMove = (action) => {
  if (!hasSelected.value) return

  // Set bulk mode
  isBulkCopyMove.value = true
  bulkCopyMoveItems.value = [...selectedItems.value]
  copyMoveItem.value = {
    name: `${selectedItems.value.length} items`,
    type: 'multiple'
  }
  copyMoveAction.value = action
  copyMoveDestination.value = currentPath.value || ''
  copyMoveProcessing.value = false
  showCopyMoveModal.value = true

  setTimeout(() => {
    copyMoveInput.value?.focus()
    copyMoveInput.value?.select()
  }, 100)
}

/* =========================
   Helper modal for copy/move single item from context menu
   ========================= */
const openCopyMoveModal = (item, action) => {
  isBulkCopyMove.value = false
  bulkCopyMoveItems.value = []
  copyMoveItem.value = item
  copyMoveAction.value = action
  copyMoveDestination.value = currentPath.value || ''
  copyMoveProcessing.value = false
  showCopyMoveModal.value = true
  // Focus input after modal opens
  setTimeout(() => {
    copyMoveInput.value?.focus()
    copyMoveInput.value?.select()
  }, 100)
}

const closeCopyMoveModal = () => {
  showCopyMoveModal.value = false
  copyMoveItem.value = null
  copyMoveDestination.value = ''
  copyMoveProcessing.value = false
  isBulkCopyMove.value = false
  bulkCopyMoveItems.value = []
}

const executeCopyMove = async () => {
  copyMoveProcessing.value = true

  try {
    if (isBulkCopyMove.value) {
      // Bulk operation
      for (const it of bulkCopyMoveItems.value) {
        await axios.post(`/file-manager/${props.domain}/${copyMoveAction.value}`, {
          sourcePath: currentPath.value || '',
          name: it.name,
          destinationPath: copyMoveDestination.value || ''
        })
      }
      showAlert('success', `${copyMoveAction.value === 'copy' ? 'Copied' : 'Moved'} ${bulkCopyMoveItems.value.length} items`)
      selectedItems.value = []
    } else {
      // Single item operation
      if (!copyMoveItem.value) return
      await axios.post(`/file-manager/${props.domain}/${copyMoveAction.value}`, {
        sourcePath: currentPath.value || '',
        name: copyMoveItem.value.name,
        destinationPath: copyMoveDestination.value || ''
      })
      showAlert('success', `${copyMoveAction.value === 'copy' ? 'Copied' : 'Moved'} ${copyMoveItem.value.name}`)
    }
    closeCopyMoveModal()
    loadFiles()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || `Failed to ${copyMoveAction.value}`)
  } finally {
    copyMoveProcessing.value = false
  }
}

const scrollToGit = () => {
  const el = document.getElementById('git-panel')
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
  }
}

</script>

<style scoped>
/* Glassmorphism Card */
.glass-card {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px) saturate(180%);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 1rem;
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
}

/* Sidebar Nav Pills */
.nav-pill-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 16px;
  border: none;
  background: transparent;
  border-radius: 10px;
  color: #67748e;
  font-size: 14px;
  transition: all 0.2s ease;
  margin-bottom: 4px;
  text-align: left;
}

.nav-pill-btn i {
  font-size: 18px;
  opacity: 0.8;
}

.nav-pill-btn:hover {
  background: rgba(0, 0, 0, 0.04);
  color: #344767;
}

.nav-pill-btn.active {
  background: #fff;
  color: #344767;
  font-weight: 600;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Modern Search */
.glass-search {
  background: rgba(0, 0, 0, 0.05);
  border-radius: 10px;
  padding: 0 8px;
  width: 240px;
}

.glass-search .form-control:focus {
  box-shadow: none;
}

/* File Row Modern */
.file-row-modern {
  transition: all 0.2s ease;
  border-bottom: 1px solid rgba(0, 0, 0, 0.03);
}

.file-row-modern:hover {
  background: rgba(94, 114, 228, 0.04) !important;
  transform: scale(1.002);
}

.file-row-modern.selected {
  background: rgba(94, 114, 228, 0.08) !important;
}

.file-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-light-warning { background: rgba(251, 207, 51, 0.15); }
.bg-light-info { background: rgba(17, 205, 239, 0.15); }

/* Action Buttons */
.action-btn-circle {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: #67748e;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.action-btn-circle:hover {
  background: white;
  color: #5e72e4;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Bulk Actions Overlay */
.bulk-actions-overlay {
  animation: slideInUp 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes slideInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Empty State */
.empty-state-icon {
  width: 80px;
  height: 80px;
  background: #f8f9fa;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
}

.empty-state-icon i {
  font-size: 40px;
  color: #adb5bd;
}

/* Refresh spin animation */
.spin-animation {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Git Console */
.git-console {
  background: #1e1e2f;
  color: #a9b7c6;
  padding: 1rem;
  border-radius: 12px;
  font-family: 'Fira Code', monospace;
  font-size: 13px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Context Menu */
.context-menu {
  position: fixed;
  z-index: 9999;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(15px);
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  min-width: 200px;
  padding: 8px;
  border: 1px solid rgba(255, 255, 255, 0.5);
}

.context-menu-item {
  padding: 10px 14px;
  border-radius: 8px;
  transition: all 0.15s ease;
}

.context-menu-item:hover {
  background: #5e72e4;
  color: white;
}
</style>

