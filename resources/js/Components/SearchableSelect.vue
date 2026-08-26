<template>
  <div class="searchable-select-wrapper position-relative" ref="dropdownRef">
    <!-- Trigger Button -->
    <div class="custom-select-trigger d-flex align-items-center justify-content-between p-2 px-3 border rounded-3 bg-white cursor-pointer transition-all"
         :class="{ 'is-open': isOpen, 'is-invalid': required && !modelValue }"
         @click="toggleDropdown">
      <div class="d-flex align-items-center gap-2 overflow-hidden">
        <i class="material-symbols-rounded text-secondary text-sm flex-shrink-0">{{ icon }}</i>
        <div v-if="selectedItem" class="d-flex align-items-center gap-2 text-truncate">
          <span class="text-xs font-weight-bold text-dark text-truncate">{{ selectedItem.label }}</span>
          <span v-if="selectedItem.subLabel" class="badge badge-xs bg-info-subtle text-info border border-info-subtle flex-shrink-0">
            {{ selectedItem.subLabel }}
          </span>
        </div>
        <span v-else class="text-xs text-muted">{{ placeholder }}</span>
      </div>
      <i class="material-symbols-rounded text-secondary text-sm transition-transform flex-shrink-0 ms-2"
         :class="{ 'rotate-180': isOpen }">expand_more</i>
    </div>

    <!-- Dropdown Menu -->
    <div v-if="isOpen" class="custom-select-menu shadow-lg border rounded-3 bg-white position-absolute w-100 mt-1 py-2 z-index-modal">
      <!-- Search Box -->
      <div class="px-2 pb-2 border-bottom">
        <div class="input-group input-group-sm bg-gray-100 rounded-2 px-2 py-1 align-items-center">
          <i class="material-symbols-rounded text-secondary text-xs me-1">search</i>
          <input type="text"
                 ref="searchInputRef"
                 v-model="searchQuery"
                 class="form-control form-control-sm border-0 bg-transparent p-0 text-xs"
                 :placeholder="searchPlaceholder"
                 @click.stop>
          <i v-if="searchQuery"
             class="material-symbols-rounded text-secondary text-xs cursor-pointer hover-dark"
             @click.stop="searchQuery = ''">close</i>
        </div>
      </div>

      <!-- Items List -->
      <div class="custom-select-list overflow-auto" style="max-height: 220px;">
        <div v-if="filteredItems.length === 0" class="text-center py-3 text-secondary text-xs">
          <i class="material-symbols-rounded text-sm d-block mb-1">search_off</i>
          No matches found
        </div>
        <div v-for="item in filteredItems"
             :key="item.value"
             class="custom-select-item d-flex align-items-center justify-content-between px-3 py-2 cursor-pointer transition-all"
             :class="{ 'active-item': item.value === modelValue }"
             @click="selectItem(item)">
          <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
            <i class="material-symbols-rounded text-xs flex-shrink-0"
               :class="item.value === modelValue ? 'text-primary' : 'text-secondary'">{{ item.icon || icon }}</i>
            <span class="text-xs text-truncate" :class="item.value === modelValue ? 'font-weight-bold text-primary' : 'text-dark'">
              {{ item.label }}
            </span>
            <span v-if="item.subLabel" class="badge badge-xs bg-info-subtle text-info border border-info-subtle flex-shrink-0">
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
  required: { type: Boolean, default: false }
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
.custom-select-trigger {
  height: 42px;
  border-color: #d2d6da !important;
  user-select: none;
}
.custom-select-trigger:hover {
  border-color: #b0b5ba !important;
  background-color: #fafafa !important;
}
.custom-select-trigger.is-open {
  border-color: #1a73e8 !important;
  box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.15) !important;
}

.custom-select-menu {
  top: 100%;
  left: 0;
  border-color: #e9ecef !important;
  z-index: 1060;
}

.custom-select-item {
  user-select: none;
}
.custom-select-item:hover {
  background-color: #f8f9fa;
}
.custom-select-item.active-item {
  background-color: #f0f7ff;
}

.rotate-180 {
  transform: rotate(180deg);
}

.transition-transform {
  transition: transform 0.2s ease-in-out;
}

.badge-xs {
  font-size: 0.65rem !important;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
}

.bg-info-subtle {
  background-color: #e0f2fe !important;
  color: #0284c7 !important;
}
.border-info-subtle {
  border-color: #bae6fd !important;
}

/* Custom mini scrollbar */
.custom-select-list::-webkit-scrollbar {
  width: 5px;
}
.custom-select-list::-webkit-scrollbar-track {
  background: #f1f1f1;
}
.custom-select-list::-webkit-scrollbar-thumb {
  background: #ccc;
  border-radius: 4px;
}
.custom-select-list::-webkit-scrollbar-thumb:hover {
  background: #aaa;
}
</style>
