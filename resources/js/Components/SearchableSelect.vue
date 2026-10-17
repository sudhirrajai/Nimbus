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

    <!-- Dropdown Menu (Styled like File Manager Jump to Website) -->
    <div v-if="isOpen" class="custom-select-menu shadow-2xl border position-absolute w-100 mt-1 z-index-modal">
      <!-- Quick Search inside Dropdown -->
      <div class="px-2 pb-2 border-bottom select-search-wrapper">
        <div class="select-search-box d-flex align-items-center gap-2 px-2.5 py-1.5 rounded-2">
          <i class="material-symbols-rounded select-search-icon flex-shrink-0">search</i>
          <input type="text"
                 ref="searchInputRef"
                 v-model="searchQuery"
                 class="border-0 bg-transparent p-0 select-search-input flex-grow-1"
                 :placeholder="searchPlaceholder"
                 @click.stop
                 @keydown.stop>
          <i v-if="searchQuery"
             class="material-symbols-rounded select-clear-icon flex-shrink-0"
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
  border: 1px solid rgba(16, 185, 129, 0.24) !important;
  border-radius: 12px !important;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12), 0 4px 12px rgba(16, 185, 129, 0.08) !important;
  z-index: 1060;
  padding: 6px !important;
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
  border-color: rgba(16, 185, 129, 0.15) !important;
  padding-bottom: 6px !important;
  margin-bottom: 4px !important;
}

.select-search-box {
  background-color: #f8fafc;
  border: 1px solid rgba(16, 185, 129, 0.2);
  border-radius: 8px !important;
  transition: all 0.2s ease;
}

.select-search-box:focus-within {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.18) !important;
  background-color: #ffffff;
}

.select-search-input {
  color: #0f172a;
  font-size: 0.8125rem !important;
  outline: none !important;
  box-shadow: none !important;
}

.select-search-input::placeholder {
  color: #94a3b8 !important;
  opacity: 1 !important;
}

.select-search-icon {
  color: #10b981;
  font-size: 16px !important;
}

.select-clear-icon {
  color: #94a3b8;
  font-size: 16px !important;
  cursor: pointer;
  transition: color 0.15s ease;
}
.select-clear-icon:hover {
  color: #0f172a;
}

.select-empty {
  color: #64748b;
}

.custom-select-item {
  user-select: none;
  transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
  margin: 2px 2px;
  padding: 7px 10px !important;
  border-radius: 8px;
}

.item-icon {
  color: #10b981;
  font-size: 16px !important;
}

.item-label {
  color: #334155;
  font-size: 0.8125rem !important;
  font-weight: 500;
}

.custom-select-item:hover {
  background-color: rgba(16, 185, 129, 0.1);
  color: #065f46;
  transform: translateX(3px);
}

.custom-select-item:hover .item-label {
  color: #047857;
}

.custom-select-item.active-item {
  background-color: rgba(16, 185, 129, 0.18);
}

.custom-select-item.active-item .item-label {
  color: #047857 !important;
  font-weight: 600;
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
   DARK VERSION (Matching File Manager Jump to Website)
   ========================================================================== */
:global(body.dark-version) .custom-select-trigger {
  background-color: rgba(14, 28, 22, 0.94) !important;
  border: 1px solid rgba(16, 185, 129, 0.28) !important;
  border-radius: 10px !important;
  color: #f8fafc !important;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3) !important;
}

:global(body.dark-version) .custom-select-trigger:hover:not(.is-disabled) {
  background-color: rgba(18, 36, 28, 0.98) !important;
  border-color: rgba(16, 185, 129, 0.55) !important;
  box-shadow: 0 0 16px rgba(16, 185, 129, 0.2) !important;
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
  color: #f8fafc !important;
  font-weight: 500 !important;
}

:global(body.dark-version) .select-placeholder {
  color: #94a3b8 !important;
}

:global(body.dark-version) .select-chevron {
  color: #94a3b8 !important;
}

:global(body.dark-version) .custom-select-menu {
  background: rgba(11, 23, 19, 0.98) !important;
  backdrop-filter: blur(24px) saturate(190%) !important;
  -webkit-backdrop-filter: blur(24px) saturate(190%) !important;
  border: 1px solid rgba(16, 185, 129, 0.28) !important;
  border-radius: 12px !important;
  box-shadow: 0 22px 55px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(16, 185, 129, 0.15) !important;
  padding: 6px !important;
}

:global(body.dark-version) .select-search-wrapper {
  border-color: rgba(16, 185, 129, 0.15) !important;
  padding-bottom: 6px !important;
  margin-bottom: 4px !important;
}

:global(body.dark-version) .select-search-box {
  background-color: rgba(255, 255, 255, 0.05) !important;
  border: 1px solid rgba(16, 185, 129, 0.22) !important;
  border-radius: 8px !important;
}

:global(body.dark-version) .select-search-box:focus-within {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.22) !important;
  background-color: rgba(16, 185, 129, 0.1) !important;
}

:global(body.dark-version) .select-search-input {
  color: #ffffff !important;
  font-size: 0.8125rem !important;
}

:global(body.dark-version) .select-search-input::placeholder {
  color: #94a3b8 !important;
  opacity: 1 !important;
}

:global(body.dark-version) .select-search-icon {
  color: #34d399 !important;
  font-size: 16px !important;
}

:global(body.dark-version) .select-clear-icon {
  color: #94a3b8 !important;
  font-size: 16px !important;
}
:global(body.dark-version) .select-clear-icon:hover {
  color: #ffffff !important;
}

:global(body.dark-version) .item-label {
  color: #e2e8f0 !important;
  font-size: 0.8125rem !important;
  font-weight: 500 !important;
}

:global(body.dark-version) .select-empty {
  color: #94a3b8 !important;
}

:global(body.dark-version) .custom-select-item {
  border-radius: 8px !important;
  padding: 7px 10px !important;
  margin: 2px 2px !important;
}

:global(body.dark-version) .custom-select-item:hover {
  background-color: rgba(16, 185, 129, 0.16) !important;
  color: #ffffff !important;
  transform: translateX(3px) !important;
}

:global(body.dark-version) .custom-select-item:hover .item-label {
  color: #ffffff !important;
}

:global(body.dark-version) .custom-select-item.active-item {
  background-color: rgba(16, 185, 129, 0.25) !important;
}

:global(body.dark-version) .custom-select-item.active-item .item-label {
  color: #34d399 !important;
  font-weight: 600 !important;
}

:global(body.dark-version) .select-sublabel {
  background-color: rgba(16, 185, 129, 0.16) !important;
  color: #34d399 !important;
  border: 1px solid rgba(16, 185, 129, 0.3) !important;
  border-radius: 6px !important;
  font-size: 10px !important;
  font-weight: 600 !important;
  padding: 2px 7px !important;
}
</style>
