<template>
  <div class="searchable-select-wrapper position-relative" ref="dropdownRef">
    <!-- Trigger Button -->
    <div class="custom-select-trigger d-flex align-items-center justify-content-between p-2 px-3 border rounded-3 cursor-pointer transition-all"
         :class="{
           'is-open': isOpen,
           'is-invalid': required && !modelValue,
           'is-disabled': disabled
         }"
         @click="toggleDropdown">
      <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1">
        <i class="material-symbols-rounded select-icon text-sm flex-shrink-0">{{ icon }}</i>
        <div v-if="selectedItem" class="d-flex align-items-center gap-2 text-truncate">
          <span class="text-xs font-weight-bold select-label text-truncate">{{ selectedItem.label }}</span>
          <span v-if="selectedItem.subLabel" class="badge badge-xs select-sublabel flex-shrink-0">
            {{ selectedItem.subLabel }}
          </span>
        </div>
        <span v-else class="text-xs select-placeholder">{{ placeholder }}</span>
      </div>
      <i class="material-symbols-rounded select-chevron text-sm transition-transform flex-shrink-0 ms-2"
         :class="{ 'rotate-180': isOpen }">expand_more</i>
    </div>

    <!-- Dropdown Menu -->
    <div v-if="isOpen" class="custom-select-menu shadow-xl border rounded-3 position-absolute w-100 mt-1 py-2 z-index-modal">
      <!-- Search Box -->
      <div class="px-2 pb-2 border-bottom select-search-wrapper">
        <div class="input-group input-group-sm select-search-box rounded-2 px-2 py-1 align-items-center">
          <i class="material-symbols-rounded text-xs me-1 select-search-icon">search</i>
          <input type="text"
                 ref="searchInputRef"
                 v-model="searchQuery"
                 class="form-control form-control-sm border-0 bg-transparent p-0 text-xs select-search-input"
                 :placeholder="searchPlaceholder"
                 @click.stop>
          <i v-if="searchQuery"
             class="material-symbols-rounded text-xs cursor-pointer select-clear-icon"
             @click.stop="searchQuery = ''">close</i>
        </div>
      </div>

      <!-- Items List -->
      <div class="custom-select-list overflow-auto" style="max-height: 220px;">
        <div v-if="filteredItems.length === 0" class="text-center py-3 select-empty text-xs">
          <i class="material-symbols-rounded text-sm d-block mb-1">search_off</i>
          No matches found
        </div>
        <div v-for="item in filteredItems"
             :key="item.value"
             class="custom-select-item d-flex align-items-center justify-content-between px-3 py-2 cursor-pointer transition-all"
             :class="{ 'active-item': item.value === modelValue }"
             @click="selectItem(item)">
          <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
            <i class="material-symbols-rounded text-xs flex-shrink-0 item-icon"
               :class="item.value === modelValue ? 'text-primary' : ''">{{ item.icon || icon }}</i>
            <span class="text-xs text-truncate item-label" :class="item.value === modelValue ? 'font-weight-bold text-primary' : ''">
              {{ item.label }}
            </span>
            <span v-if="item.subLabel" class="badge badge-xs select-sublabel flex-shrink-0">
              {{ item.subLabel }}
            </span>
          </div>
          <i v-if="item.value === modelValue" class="material-symbols-rounded text-primary text-xs flex-shrink-0">check</i>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  items: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'Select an option...' },
  searchPlaceholder: { type: String, default: 'Search...' },
  icon: { type: String, default: 'category' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'change'])

const isOpen = ref(false)
const searchQuery = ref('')
const dropdownRef = ref(null)
const searchInputRef = ref(null)

const normalizedItems = computed(() => {
  return props.items.map(item => {
    if (typeof item === 'string') {
      return { label: item, value: item, subLabel: null, icon: props.icon }
    }
    return {
      label: item.label || item.domain || item.name || item.value,
      value: item.value !== undefined ? item.value : (item.domain || item.name || item),
      subLabel: item.subLabel || (item.associated_db ? `DB: ${item.associated_db}` : null),
      icon: item.icon || props.icon
    }
  })
})

const selectedItem = computed(() => {
  return normalizedItems.value.find(item => item.value === props.modelValue)
})

const filteredItems = computed(() => {
  if (!searchQuery.value) return normalizedItems.value
  const q = searchQuery.value.toLowerCase()
  return normalizedItems.value.filter(item => {
    const labelMatch = (item.label || '').toLowerCase().includes(q)
    const subMatch = (item.subLabel || '').toLowerCase().includes(q)
    return labelMatch || subMatch
  })
})

const toggleDropdown = () => {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    nextTick(() => {
      searchInputRef.value?.focus()
    })
  }
}

const selectItem = (item) => {
  emit('update:modelValue', item.value)
  emit('change', item.value)
  isOpen.value = false
  searchQuery.value = ''
}

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isOpen.value = false
  }
}

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isOpen.value) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})
</script>

<style scoped>
/* Base Light Mode Styles */
.custom-select-trigger {
  min-height: 42px;
  background-color: #ffffff;
  border-color: rgba(16, 185, 129, 0.28) !important;
  color: #1e293b;
  user-select: none;
  border-radius: 10px !important;
  transition: all 0.22s ease-in-out;
}

.custom-select-trigger:hover:not(.is-disabled) {
  border-color: #10b981 !important;
  background-color: rgba(16, 185, 129, 0.04);
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.12);
}

.custom-select-trigger.is-open {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.22) !important;
}

.custom-select-trigger.is-disabled {
  opacity: 0.6;
  cursor: not-allowed !important;
  background-color: #f1f5f9;
}

.select-icon {
  color: #10b981;
}

.select-label {
  color: #0f172a;
}

.select-placeholder {
  color: #64748b;
}

.select-chevron {
  color: #64748b;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.custom-select-menu {
  top: calc(100% + 4px);
  left: 0;
  background: #ffffff;
  border-color: rgba(16, 185, 129, 0.24) !important;
  border-radius: 12px !important;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12), 0 4px 12px rgba(16, 185, 129, 0.08) !important;
  z-index: 1060;
  animation: selectMenuDrop 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes selectMenuDrop {
  0% {
    opacity: 0;
    transform: translateY(-6px) scale(0.98);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.select-search-wrapper {
  border-color: rgba(16, 185, 129, 0.12) !important;
}

.select-search-box {
  background-color: #f8fafc;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.select-search-input {
  color: #0f172a;
  outline: none !important;
  box-shadow: none !important;
}

.select-search-icon,
.select-clear-icon {
  color: #10b981;
}

.select-empty {
  color: #64748b;
}

.custom-select-item {
  user-select: none;
  transition: all 0.18s ease;
  margin: 1px 4px;
  border-radius: 8px;
}

.item-icon {
  color: #10b981;
}

.item-label {
  color: #334155;
}

.custom-select-item:hover {
  background-color: rgba(16, 185, 129, 0.1);
  color: #065f46;
}

.custom-select-item:hover .item-label {
  color: #047857;
}

.custom-select-item.active-item {
  background-color: rgba(16, 185, 129, 0.18);
}

.custom-select-item.active-item .item-label {
  color: #047857 !important;
}

.rotate-180 {
  transform: rotate(180deg);
}

.badge-xs {
  font-size: 0.65rem !important;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 6px;
}

.select-sublabel {
  background-color: rgba(16, 185, 129, 0.12) !important;
  color: #059669 !important;
  border: 1px solid rgba(16, 185, 129, 0.25) !important;
}

/* Mini Scrollbar */
.custom-select-list::-webkit-scrollbar {
  width: 5px;
}
.custom-select-list::-webkit-scrollbar-track {
  background: transparent;
}
.custom-select-list::-webkit-scrollbar-thumb {
  background: rgba(16, 185, 129, 0.3);
  border-radius: 4px;
}
.custom-select-list::-webkit-scrollbar-thumb:hover {
  background: rgba(16, 185, 129, 0.5);
}

/* ==========================================================================
   DARK VERSION (Emerald Glass Theme)
   ========================================================================== */
:global(body.dark-version) .custom-select-trigger {
  background-color: rgba(12, 26, 21, 0.92) !important;
  border-color: rgba(16, 185, 129, 0.28) !important;
  color: #f8fafc !important;
}

:global(body.dark-version) .custom-select-trigger:hover:not(.is-disabled) {
  background-color: rgba(16, 34, 28, 0.98) !important;
  border-color: rgba(16, 185, 129, 0.55) !important;
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.16) !important;
}

:global(body.dark-version) .custom-select-trigger.is-open {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.25) !important;
}

:global(body.dark-version) .custom-select-trigger.is-disabled {
  background-color: rgba(15, 23, 42, 0.4) !important;
  opacity: 0.5;
}

:global(body.dark-version) .select-label {
  color: #f1f5f9 !important;
}

:global(body.dark-version) .select-placeholder {
  color: #94a3b8 !important;
}

:global(body.dark-version) .select-chevron {
  color: #94a3b8 !important;
}

:global(body.dark-version) .custom-select-menu {
  background: rgba(9, 21, 17, 0.98) !important;
  backdrop-filter: blur(24px) !important;
  -webkit-backdrop-filter: blur(24px) !important;
  border-color: rgba(16, 185, 129, 0.32) !important;
  box-shadow: 0 24px 55px rgba(0, 0, 0, 0.75), 0 0 25px rgba(16, 185, 129, 0.14) !important;
}

:global(body.dark-version) .select-search-wrapper {
  border-color: rgba(16, 185, 129, 0.15) !important;
}

:global(body.dark-version) .select-search-box {
  background-color: rgba(16, 34, 27, 0.95) !important;
  border: 1px solid rgba(16, 185, 129, 0.24) !important;
}

:global(body.dark-version) .select-search-input {
  color: #f8fafc !important;
}

:global(body.dark-version) .select-search-input::placeholder {
  color: #64748b !important;
}

:global(body.dark-version) .item-label {
  color: #e2e8f0 !important;
}

:global(body.dark-version) .select-empty {
  color: #64748b !important;
}

:global(body.dark-version) .custom-select-item:hover {
  background-color: rgba(16, 185, 129, 0.16) !important;
}

:global(body.dark-version) .custom-select-item:hover .item-label {
  color: #34d399 !important;
}

:global(body.dark-version) .custom-select-item.active-item {
  background-color: rgba(16, 185, 129, 0.24) !important;
}

:global(body.dark-version) .custom-select-item.active-item .item-label {
  color: #34d399 !important;
}

:global(body.dark-version) .select-sublabel {
  background-color: rgba(16, 185, 129, 0.16) !important;
  color: #34d399 !important;
  border-color: rgba(16, 185, 129, 0.3) !important;
}
</style>
