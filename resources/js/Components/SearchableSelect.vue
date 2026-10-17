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
        <i class="material-symbols-rounded select-icon text-sm flex-shrink-0"
           :class="icon === 'public' ? 'text-info' : 'text-emerald-400'">{{ icon }}</i>
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
    <ul v-if="isOpen"
        class="dropdown-menu custom-select-menu shadow-2xl p-2 position-absolute w-100 show"
        style="display: block; top: calc(100% + 4px); left: 0; min-width: 240px; max-height: 320px; overflow-y: auto; margin-top: 4px; z-index: 999999 !important;">
      <!-- Quick Search inside Dropdown -->
      <li class="px-2 py-1 mb-1 border-bottom select-search-wrapper" style="border-color: rgba(16, 185, 129, 0.15) !important;">
        <div class="select-search-box d-flex align-items-center gap-2 px-2.5 py-1.5 rounded-3">
          <i class="material-symbols-rounded text-xs text-secondary flex-shrink-0">search</i>
          <input type="text"
                 ref="searchInputRef"
                 v-model="searchQuery"
                 class="form-control form-control-xs border-0 bg-transparent p-0 text-xs text-body flex-grow-1 select-search-input"
                 :placeholder="searchPlaceholder"
                 @click.stop
                 @keydown.stop>
          <button v-if="searchQuery"
                  type="button"
                  class="btn btn-link p-0 m-0 text-secondary d-flex align-items-center"
                  @click.stop="searchQuery = ''">
            <i class="material-symbols-rounded text-xs">close</i>
          </button>
        </div>
      </li>

      <!-- Empty State -->
      <li v-if="filteredItems.length === 0" class="text-center py-2 text-xs text-secondary select-empty">
        <i class="material-symbols-rounded text-sm d-block mb-1">search_off</i>
        No matches found
      </li>

      <!-- Items List -->
      <li v-for="item in filteredItems" :key="item.value">
        <a href="#"
           class="dropdown-item custom-select-item text-xs border-radius-md py-2 px-2.5 d-flex align-items-center justify-content-between"
           :class="{ 'active font-weight-bold': item.value === modelValue }"
           @click.prevent="selectItem(item)">
          <span class="d-flex align-items-center text-truncate flex-grow-1 me-2">
            <i class="material-symbols-rounded text-sm me-2 flex-shrink-0"
               :class="(item.icon || icon) === 'public' ? 'text-info' : 'text-emerald-400'">
              {{ item.icon || icon }}
            </i>
            <span class="text-truncate font-weight-500 item-label">{{ item.label }}</span>
            <span v-if="item.subLabel" class="badge badge-xs select-sublabel ms-2 flex-shrink-0">
              {{ item.subLabel }}
            </span>
          </span>
          <i v-if="item.value === modelValue" class="material-symbols-rounded text-xs text-success ms-2 flex-shrink-0">check</i>
        </a>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'

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
