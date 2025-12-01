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
              <div class="d-flex flex-wrap gap-2">
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
                      :key="item.name"
                      @contextmenu.prevent="openContextMenu($event, item)"
                      class="file-row"
                    >
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
                      <td colspan="5" class="text-center py-5">
                        <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">folder_open</i>
                        <p class="text-secondary mb-0">This folder is empty</p>
                      </td>
                    </tr>

                    <tr v-if="loading">
                      <td colspan="5" class="text-center py-5">
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
        :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
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
      <div class="modal fade show" style="display:block" v-if="showCreateFileModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Create New File</h5>
              <button type="button" class="btn-close" @click="showCreateFileModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>File Name</label>
                <input 
                  v-model="newFileName" 
                  type="text" 
                  class="form-control" 
                  placeholder="example.php"
                  @keyup.enter="createFile"
                />
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showCreateFileModal = false">Cancel</button>
              <button class="btn bg-gradient-primary" @click="createFile" :disabled="!newFileName">Create</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Directory Modal -->
      <div class="modal fade show" style="display:block" v-if="showCreateDirModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Create New Folder</h5>
              <button type="button" class="btn-close" @click="showCreateDirModal = false"></button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Folder Name</label>
                <input 
                  v-model="newDirName" 
                  type="text" 
                  class="form-control" 
                  placeholder="folder-name"
                  @keyup.enter="createDirectory"
                />
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" @click="showCreateDirModal = false">Cancel</button>
              <button class="btn bg-gradient-info" @click="createDirectory" :disabled="!newDirName">Create</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Rename Modal -->
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
      <div class="modal fade show" style="display:block" v-if="showEditorModal">
        <div class="modal-dialog modal-xl modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Editing: {{ editingFile }}</h5>
              <button type="button" class="btn-close" @click="closeEditor"></button>
            </div>
            <div class="modal-body">
              <textarea 
                v-model="fileContent" 
                class="form-control font-monospace" 
                rows="20"
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
import { ref, onMounted } from 'vue'
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

const showCreateFileModal = ref(false)
const showCreateDirModal = ref(false)
const showRenameModal = ref(false)
const showDeleteModal = ref(false)
const showEditorModal = ref(false)
const showPermissionsModal = ref(false)

const newFileName = ref('')
const newDirName = ref('')
const renameName = ref('')
const selectedItem = ref(null)
const editingFile = ref('')
const fileContent = ref('')
const fileInput = ref(null)
const newPermissions = ref('')
const recursivePermissions = ref(false)

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
      path: currentPath.value,
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
      path: currentPath.value
    })
    items.value = response.data.items
    breadcrumbs.value = response.data.breadcrumbs
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
      path: currentPath.value,
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
      path: currentPath.value,
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
      path: currentPath.value,
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
      path: currentPath.value,
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

  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('path', currentPath.value)

    await axios.post(`/file-manager/${props.domain}/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    showAlert('success', 'File uploaded successfully')
    loadFiles()
  } catch (error) {
    showAlert('danger', error.response?.data?.error || 'Failed to upload file')
  } finally {
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
</script>

<style scoped>
.modal {
  background: rgba(0, 0, 0, 0.5);
}

.modal-content {
  border: none;
  border-radius: 1rem;
}

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

.context-menu {
  position: fixed;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  z-index: 9999;
  min-width: 180px;
  padding: 4px 0;
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
</style>