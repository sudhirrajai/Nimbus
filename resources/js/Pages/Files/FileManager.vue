<template>
  <MainLayout>
    <div class="container-fluid py-4" @click="closeContextMenu">

      <!-- Header -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="font-weight-bolder mb-0">File Manager</h4>
              <p class="mb-0 text-sm">{{ domain }} - /var/www/{{ domain }}{{ currentPath ? '/' + currentPath : '' }}</p>
            </div>
            <button class="btn btn-outline-secondary mb-0" @click="goBack">
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
          <div class="card">
            <div class="card-body p-3">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-sm bg-gradient-primary mb-0" @click="showCreateFileModal = true">
                  <i class="material-symbols-rounded text-sm me-1">note_add</i>
                  New File
                </button>
                <button class="btn btn-sm bg-gradient-info mb-0" @click="showCreateDirModal = true">
                  <i class="material-symbols-rounded text-sm me-1">create_new_folder</i>
                  New Folder
                </button>
                <button class="btn btn-sm bg-gradient-success mb-0" @click="triggerUpload">
                  <i class="material-symbols-rounded text-sm me-1">upload</i>
                  Upload File
                </button>
                <input 
                  ref="fileInput" 
                  type="file" 
                  style="display:none" 
                  @change="handleFileUpload"
                />
                <button 
                  v-if="currentPath" 
                  class="btn btn-sm bg-gradient-warning mb-0" 
                  @click="goUpOneLevel"
                >
                  <i class="material-symbols-rounded text-sm me-1">arrow_upward</i>
                  Up One Level
                </button>
                <button class="btn btn-sm btn-outline-secondary mb-0" @click="loadFiles" :disabled="loading">
                  <i class="material-symbols-rounded text-sm me-1">refresh</i>
                  Refresh
                </button>

                <!-- Bulk actions -->
                <div class="btn-group ms-2" role="group">
                  <button class="btn btn-sm btn-outline-primary" @click="toggleSelectAll">
                    <i class="material-symbols-rounded text-sm me-1">select_all</i>
                    {{ allSelected ? 'Unselect All' : 'Select All' }}
                  </button>
                  <button class="btn btn-sm btn-outline-danger" :disabled="!hasSelected" @click="bulkDelete">
                    <i class="material-symbols-rounded text-sm me-1">delete</i>
                    Delete Selected
                  </button>
                  <button class="btn btn-sm btn-outline-secondary" :disabled="!hasSelected" @click="bulkZip">
                    <i class="material-symbols-rounded text-sm me-1">folder_zip</i>
                    Zip Selected
                  </button>
                  <button class="btn btn-sm btn-outline-info" :disabled="!hasSelected" @click="bulkCopyMove('copy')">
                    <i class="material-symbols-rounded text-sm me-1">content_copy</i>
                    Copy Selected
                  </button>
                  <button class="btn btn-sm btn-outline-warning" :disabled="!hasSelected" @click="bulkCopyMove('move')">
                    <i class="material-symbols-rounded text-sm me-1">drive_file_move</i>
                    Move Selected
                  </button>
                </div>

                <div class="ms-auto form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="showHiddenToggle"
                    v-model="showHidden"
                    @change="loadFiles"
                  >
                  <label class="form-check-label text-sm" for="showHiddenToggle">
                    Show Hidden Files
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Breadcrumbs -->
      <div class="row mb-3">
        <div class="col-12">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent mb-0 pb-0">
              <li 
                v-for="(crumb, index) in breadcrumbs" 
                :key="index"
                class="breadcrumb-item"
                :class="{ active: index === breadcrumbs.length - 1 }"
              >
                <a 
                  v-if="index < breadcrumbs.length - 1"
                  href="#" 
                  @click.prevent="navigateTo(crumb.path)"
                  class="text-dark"
                >
                  {{ crumb.name }}
                </a>
                <span v-else>{{ crumb.name }}</span>
              </li>
            </ol>
          </nav>
        </div>
      </div>

      <!-- File List -->
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table align-items-center mb-0">
                  <thead>
                    <tr>
                      <th style="width:40px" class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                        <!-- placeholder for checkbox column -->
                      </th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Name</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Size</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Modified</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Permissions</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr 
                      v-for="item in items" 
                      :key="item.name + item.type"
                      @contextmenu.prevent="openContextMenu($event, item)"
                      class="file-row"
                      :class="{ 'text-muted': item.hidden }"
                    >
                      <td>
                        <input type="checkbox" class="form-check-input" :checked="isSelected(item)" @change="toggleSelectItem(item, $event)">
                      </td>

                      <td>
                        <div class="d-flex px-2 py-1 align-items-center">
                          <div class="me-3">
                            <i 
                              class="material-symbols-rounded" 
                              :class="item.type === 'directory' ? 'text-warning' : 'text-info'"
                            >
                              {{ item.type === 'directory' ? 'folder' : 'description' }}
                            </i>
                          </div>
                          <div>
                            <a 
                              v-if="item.type === 'directory'"
                              href="#" 
                              @click.prevent="openDirectory(item.name)"
                              class="text-sm font-weight-bold mb-0"
                            >
                              {{ item.name }}
                            </a>
                            <span v-else class="text-sm font-weight-bold mb-0">
                              {{ item.name }}
                            </span>
                            <p class="text-xs text-secondary mb-0" v-if="item.extension">
                              {{ item.extension.toUpperCase() }} file
                            </p>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="text-xs">{{ item.type === 'directory' ? item.size : item.sizeFormatted }}</span>
                      </td>
                      <td>
                        <span class="text-xs">{{ item.modified }}</span>
                      </td>
                      <td>
                        <span 
                          class="badge badge-sm bg-gradient-secondary cursor-pointer"
                          @click="openPermissionsModal(item)"
                          title="Click to change permissions"
                        >
                          {{ item.permissions }}
                        </span>
                      </td>
                      <td class="align-middle text-center">
                        <button 
                          v-if="item.type === 'file' && item.editable"
                          class="btn btn-link text-primary mb-0 px-2"
                          @click="editFile(item.name)"
                          title="Edit"
                        >
                          <i class="material-symbols-rounded text-sm">edit_note</i>
                        </button>
                        <button 
                          v-if="item.type === 'file'"
                          class="btn btn-link text-success mb-0 px-2"
                          @click="downloadFile(item.name)"
                          title="Download"
                        >
                          <i class="material-symbols-rounded text-sm">download</i>
                        </button>
                        <button 
                          class="btn btn-link text-warning mb-0 px-2"
                          @click="openRenameModal(item)"
                          title="Rename"
                        >
                          <i class="material-symbols-rounded text-sm">label</i>
                        </button>
                        <button 
                          class="btn btn-link text-danger mb-0 px-2"
                          @click="confirmDelete(item)"
                          title="Delete"
                        >
                          <i class="material-symbols-rounded text-sm">delete</i>
                        </button>
                      </td>
                    </tr>

                    <tr v-if="items.length === 0 && !loading">
                      <td colspan="6" class="text-center py-5">
                        <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">folder_open</i>
                        <p class="text-secondary mb-0">This folder is empty</p>
                      </td>
                    </tr>

                    <tr v-if="loading">
                      <td colspan="6" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                          <span class="visually-hidden">Loading...</span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Context Menu -->
      <div 
        v-if="contextMenu.show" 
        class="context-menu"
        :style="contextMenuStyle"
        @click.stop
      >
        <div 
          v-if="contextMenu.item.type === 'file' && contextMenu.item.editable"
          class="context-menu-item"
          @click="editFile(contextMenu.item.name); closeContextMenu()"
        >
          <i class="material-symbols-rounded text-sm me-2">edit_note</i>
          Edit
        </div>
        <div 
          v-if="contextMenu.item.type === 'file'"
          class="context-menu-item"
          @click="downloadFile(contextMenu.item.name); closeContextMenu()"
        >
          <i class="material-symbols-rounded text-sm me-2">download</i>
          Download
        </div>
        <div 
          class="context-menu-item"
          @click="openRenameModal(contextMenu.item); closeContextMenu()"
        >
          <i class="material-symbols-rounded text-sm me-2">label</i>
          Rename
        </div>
        <div 
          class="context-menu-item"
          @click="openCopyMoveModal(contextMenu.item, 'copy'); closeContextMenu()"
        >
          <i class="material-symbols-rounded text-sm me-2">content_copy</i>
          Copy
        </div>
        <div 
          class="context-menu-item"
          @click="openCopyMoveModal(contextMenu.item, 'move'); closeContextMenu()"
        >
          <i class="material-symbols-rounded text-sm me-2">drive_file_move</i>
          Move
        </div>
        <div 
          class="context-menu-item"
          @click="openPermissionsModal(contextMenu.item); closeContextMenu()"
        >
          <i class="material-symbols-rounded text-sm me-2">shield</i>
          Permissions
        </div>
        <div 
          class="context-menu-item"
          @click="zipItem(contextMenu.item); closeContextMenu()"
        >
          <i class="material-symbols-rounded text-sm me-2">folder_zip</i>
          Create ZIP
        </div>
        <div class="context-menu-divider"></div>
        <div 
          class="context-menu-item text-danger"
          @click="confirmDelete(contextMenu.item); closeContextMenu()"
        >
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
                <input 
                  v-model="newPermissions" 
                  type="text" 
                  class="form-control" 
                  placeholder="0755"
                  maxlength="4"
                  pattern="[0-7]{3,4}"
                />
                <small class="text-muted d-block mt-1">
                  Common: 644 (files), 755 (folders), 777 (full access)
                </small>
              </div>
              <div class="form-check mt-3" v-if="selectedItem?.type === 'directory'">
                <input 
                  class="form-check-input" 
                  type="checkbox" 
                  v-model="recursivePermissions"
                  id="recursiveCheck"
                >
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
                  <input 
                    ref="createFileInput"
                    v-model="newFileName" 
                    type="text" 
                    class="form-control form-control-lg ps-0 border-start-0" 
                    placeholder="Enter file name with extension (e.g., index.html)"
                    @keyup.enter="createFile"
                  />
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
                  <input 
                    ref="createFolderInput"
                    v-model="newDirName" 
                    type="text" 
                    class="form-control form-control-lg ps-0 border-start-0" 
                    placeholder="Enter folder name (e.g., images, assets)"
                    @keyup.enter="createDirectory"
                  />
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
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content upload-modal">
            <div class="modal-body text-center py-5">
              <div class="upload-icon-wrapper mb-4">
                <div class="upload-icon-circle">
                  <i class="material-symbols-rounded upload-icon-animate">cloud_upload</i>
                </div>
                <svg class="progress-ring" width="120" height="120">
                  <circle class="progress-ring-bg" cx="60" cy="60" r="54" />
                  <circle 
                    class="progress-ring-progress" 
                    cx="60" cy="60" r="54"
                    :style="{ strokeDashoffset: progressOffset }"
                  />
                </svg>
              </div>
              <h5 class="mb-2">Uploading File</h5>
              <p class="text-secondary text-sm mb-3 text-truncate px-4">{{ uploadFileName }}</p>
              <div class="progress-container mx-auto">
                <div class="progress">
                  <div 
                    class="progress-bar bg-gradient-primary progress-bar-striped progress-bar-animated" 
                    :style="{ width: uploadProgress + '%' }"
                  ></div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                  <span class="text-sm text-secondary">Progress</span>
                  <span class="text-sm fw-bold text-primary">{{ uploadProgress }}%</span>
                </div>
              </div>
              <p class="text-xs text-muted mt-3 mb-0">
                <i class="material-symbols-rounded text-sm align-middle me-1">info</i>
                Please don't close this window while uploading
              </p>
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
                  <i class="material-symbols-rounded">{{ copyMoveAction === 'copy' ? 'content_copy' : 'drive_file_move' }}</i>
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
                  <i class="material-symbols-rounded text-lg" :class="copyMoveItem?.type === 'directory' ? 'text-warning' : 'text-info'">
                    {{ copyMoveItem?.type === 'directory' ? 'folder' : 'description' }}
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
                    <i class="material-symbols-rounded" :class="copyMoveAction === 'copy' ? 'text-info' : 'text-warning'">folder_open</i>
                  </span>
                  <input 
                    ref="copyMoveInput"
                    v-model="copyMoveDestination" 
                    type="text" 
                    class="form-control form-control-lg ps-0 border-start-0" 
                    placeholder="Leave empty for root, or enter path like 'images/icons'"
                    @keyup.enter="executeCopyMove"
                  />
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
              <button 
                :class="['btn', copyMoveAction === 'copy' ? 'bg-gradient-info' : 'bg-gradient-warning']"
                @click="executeCopyMove"
                :disabled="copyMoveProcessing"
              >
                <span v-if="copyMoveProcessing" class="spinner-border spinner-border-sm me-2"></span>
                <i v-else class="material-symbols-rounded text-sm me-1">{{ copyMoveAction === 'copy' ? 'content_copy' : 'drive_file_move' }}</i>
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
                <input 
                  v-model="renameName" 
                  type="text" 
                  class="form-control"
                  @keyup.enter="renameItem"
                />
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
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title text-danger">Confirm Deletion</h5>
              <button type="button" class="btn-close" @click="showDeleteModal = false"></button>
            </div>
            <div class="modal-body">
              <p>Are you sure you want to delete <strong>{{ selectedItem?.name }}</strong>?</p>
              <p class="text-sm text-danger mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showDeleteModal = false">Cancel</button>
              <button class="btn bg-gradient-danger" @click="deleteItem">Delete</button>
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
              <textarea 
                v-model="fileContent" 
                class="form-control font-monospace" 
                rows="25"
                style="font-size: 13px;"
              ></textarea>
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

    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  domain: String,
  initialPath: String
})

const items = ref([])
const currentPath = ref(props.initialPath || '')
const breadcrumbs = ref([])
const loading = ref(false)
const saving = ref(false)
const showHidden = ref(false)

// selection management
const selectedItems = ref([]) // array of { name, type }
const allSelected = ref(false)

const showCreateFileModal = ref(false)
const showCreateDirModal = ref(false)
const showRenameModal = ref(false)
const showDeleteModal = ref(false)
const showEditorModal = ref(false)
const showPermissionsModal = ref(false)
const showCopyMoveModal = ref(false)

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

// Circular progress offset calculation
const progressOffset = computed(() => {
  const circumference = 2 * Math.PI * 54 // r=54
  return circumference - (uploadProgress.value / 100) * circumference
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
})

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const openContextMenu = (event, item) => {
  contextMenu.value = {
    show: true,
    x: event.pageX,
    y: event.pageY,
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
  } catch (error) {
    showAlert('danger', 'Failed to load files')
    console.error(error)
  } finally {
    loading.value = false
  }
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
  selectedItem.value = item
  showDeleteModal.value = true
}

const deleteItem = async () => {
  try {
    await axios.post(`/file-manager/${props.domain}/delete`, {
      path: currentPath.value || '',
      name: selectedItem.value.name
    })
    showAlert('success', 'Deleted successfully')
    showDeleteModal.value = false
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to delete')
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

const handleFileUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  // Initialize upload progress state
  uploading.value = true
  uploadProgress.value = 0
  uploadFileName.value = file.name

  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('path', currentPath.value || '')

    await axios.post(`/file-manager/${props.domain}/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
        uploadProgress.value = percentCompleted
      }
    })
    
    showAlert('success', 'File uploaded successfully')
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to upload file')
  } finally {
    uploading.value = false
    uploadProgress.value = 0
    uploadFileName.value = ''
    event.target.value = ''
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

// Bulk delete using existing delete-multiple endpoint
const bulkDelete = async () => {
  if (!hasSelected.value) return
  if (!confirm(`Delete ${selectedItems.value.length} items? This cannot be undone.`)) return

  try {
    await axios.post(`/file-manager/${props.domain}/delete-multiple`, {
      path: currentPath.value || '',
      items: getSelectedItems()
    })
    showAlert('success', 'Selected items deleted')
    selectedItems.value = []
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to delete selected items')
  }
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

// Bulk Copy/Move: we will loop selected items and call your existing copy/move endpoint per item.
// destinationPath is relative path string (can be empty for root)
const bulkCopyMove = async (action) => {
  if (!hasSelected.value) return
  const destination = prompt(`${action === 'copy' ? 'Copy' : 'Move'} selected items to (relative path inside current domain). Leave empty for root:`, currentPath.value || '')
  if (destination === null) return // user cancelled

  try {
    for (const it of selectedItems.value) {
      // call appropriate endpoint for each item
      await axios.post(`/file-manager/${props.domain}/${action}`, {
        sourcePath: currentPath.value || '',
        name: it.name,
        destinationPath: destination || ''
      })
    }
    showAlert('success', `${action === 'copy' ? 'Copied' : 'Moved'} ${selectedItems.value.length} items`)
    selectedItems.value = []
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || `Failed to ${action} selected items`)
  }
}

/* =========================
   Helper modal for copy/move single item from context menu
   ========================= */
const openCopyMoveModal = (item, action) => {
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
}

const executeCopyMove = async () => {
  if (!copyMoveItem.value) return
  
  copyMoveProcessing.value = true
  try {
    await axios.post(`/file-manager/${props.domain}/${copyMoveAction.value}`, {
      sourcePath: currentPath.value || '',
      name: copyMoveItem.value.name,
      destinationPath: copyMoveDestination.value || ''
    })
    showAlert('success', `${copyMoveAction.value === 'copy' ? 'Copied' : 'Moved'} ${copyMoveItem.value.name}`)
    closeCopyMoveModal()
    loadFiles()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || `Failed to ${copyMoveAction.value}`)
  } finally {
    copyMoveProcessing.value = false
  }
}

</script>

<style scoped>
.modal {
  background: rgba(0, 0, 0, 0.5);
  position: fixed;
  z-index: 20050; /* ensure above sidebar */
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.modal-backdrop {
  position: fixed;
  z-index: 20040; /* slightly below modal but above everything else */
}

/* Keep the modal-content style */
.modal-content {
  border: none;
  border-radius: 1rem;
  z-index: 20060;
}

/* make sure context menu still appears on top of everything */
.context-menu {
  position: fixed;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  z-index: 30000;
  min-width: 180px;
  padding: 4px 0;
}

/* rest of CSS mostly unchanged */
.gap-2 {
  gap: 0.5rem;
}

.breadcrumb-item + .breadcrumb-item::before {
  content: "/";
}

.breadcrumb-item a {
  text-decoration: none;
}

.breadcrumb-item a:hover {
  text-decoration: underline;
}

.btn-link {
  text-decoration: none;
}

.btn-link:hover i {
  transform: scale(1.1);
  transition: transform 0.2s;
}

textarea.font-monospace {
  font-family: 'Courier New', monospace;
  line-height: 1.5;
}

.file-row {
  cursor: pointer;
}

.file-row:hover {
  background-color: rgba(0, 0, 0, 0.02);
}

.cursor-pointer {
  cursor: pointer;
}

.context-menu-item {
  padding: 10px 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  font-size: 14px;
  transition: background-color 0.2s;
}

.context-menu-item:hover {
  background-color: rgba(0, 0, 0, 0.05);
}

.context-menu-divider {
  height: 1px;
  background-color: rgba(0, 0, 0, 0.1);
  margin: 4px 0;
}

/* Modal Icon Styles */
.modal-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.modal-icon i {
  font-size: 24px;
}

/* Create Modal Styles */
.create-modal .modal-header {
  padding: 24px 24px 0 24px;
}

.create-modal .modal-body {
  padding: 24px;
}

.create-modal .modal-footer {
  padding: 0 24px 24px 24px;
}

.create-modal .input-group-text {
  border-radius: 12px 0 0 12px;
}

.create-modal .form-control {
  border-radius: 0 12px 12px 0;
  background-color: #f8f9fa;
  border-color: #e9ecef;
  transition: all 0.3s ease;
}

.create-modal .form-control:focus {
  background-color: #fff;
  border-color: #7c3aed;
  box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.15);
}

/* Upload Progress Modal */
.upload-modal {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  overflow: hidden;
}

.upload-modal::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
  animation: shimmer 3s infinite linear;
}

@keyframes shimmer {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.upload-icon-wrapper {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto;
}

.upload-icon-circle {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
}

.upload-icon-animate {
  font-size: 36px;
  animation: uploadBounce 1.5s infinite ease-in-out;
}

@keyframes uploadBounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}

.progress-ring {
  position: absolute;
  top: 0;
  left: 0;
  transform: rotate(-90deg);
}

.progress-ring-bg {
  fill: none;
  stroke: rgba(255, 255, 255, 0.2);
  stroke-width: 8;
}

.progress-ring-progress {
  fill: none;
  stroke: rgba(255, 255, 255, 0.9);
  stroke-width: 8;
  stroke-linecap: round;
  stroke-dasharray: 339.292; /* 2 * PI * 54 */
  transition: stroke-dashoffset 0.3s ease;
}

.progress-container {
  width: 80%;
  max-width: 300px;
}

.upload-modal .progress {
  height: 10px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.2);
  overflow: hidden;
}

.upload-modal .progress-bar {
  background: linear-gradient(90deg, rgba(255,255,255,0.8), rgba(255,255,255,1));
  border-radius: 10px;
}

.upload-modal h5 {
  position: relative;
  z-index: 1;
}

.upload-modal p {
  position: relative;
  z-index: 1;
}

/* Copy/Move Modal */
.copy-move-modal .modal-header {
  padding: 24px 24px 0 24px;
}

.copy-move-modal .modal-body {
  padding: 24px;
}

.copy-move-modal .modal-footer {
  padding: 0 24px 24px 24px;
}

.source-info-card {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 12px;
  padding: 16px;
  border: 1px dashed #dee2e6;
}

.copy-move-modal .input-group-text {
  border-radius: 12px 0 0 12px;
}

.copy-move-modal .form-control {
  border-radius: 0 12px 12px 0;
  background-color: #f8f9fa;
  border-color: #e9ecef;
  transition: all 0.3s ease;
}

.copy-move-modal .form-control:focus {
  background-color: #fff;
  border-color: #7c3aed;
  box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.15);
}

/* Text utilities */
.text-lg {
  font-size: 32px !important;
}

/* Animation for modal appearance */
.modal.fade.show {
  animation: modalFadeIn 0.2s ease-out;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-dialog-centered {
  animation: modalSlideIn 0.25s ease-out;
}

@keyframes modalSlideIn {
  from {
    transform: translate(0, -20px);
    opacity: 0;
  }
  to {
    transform: translate(0, 0);
    opacity: 1;
  }
}
</style>
