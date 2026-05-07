<template>
  <div class="global-search-container" ref="searchRef">
    <div class="input-group input-group-outline" :class="{ 'is-focused': isFocused || searchQuery }">
      <label class="form-label" v-if="!searchQuery && !isFocused">Type to search... (Ctrl+K)</label>
      <span class="input-group-text text-body">
        <i class="material-symbols-rounded" aria-hidden="true">search</i>
      </span>
      <input
        type="text"
        class="form-control"
        v-model="searchQuery"
        @focus="isFocused = true"
        @input="handleInput"
        @keydown.down.prevent="moveSelection(1)"
        @keydown.up.prevent="moveSelection(-1)"
        @keydown.enter.prevent="selectItem"
        @keydown.esc="closeSearch"
        ref="searchInput"
      />
    </div>

    <!-- Suggestions Dropdown -->
    <div v-if="isOpen" class="search-suggestions shadow-lg border-radius-lg bg-white">
      <div v-if="loading" class="p-3 text-center">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        <span class="ms-2 text-sm">Searching...</span>
      </div>

      <div v-else-if="results.length > 0">
        <div v-for="(categoryResults, category) in groupedResults" :key="category">
          <h6 class="text-xs text-uppercase text-muted ps-3 pt-2 mb-1">{{ category }}</h6>
          <ul class="list-group list-group-flush">
            <li
              v-for="item in categoryResults"
              :key="item.id || item.url + item.title"
              class="list-group-item list-group-item-action border-0 d-flex align-items-center py-2 px-3 border-radius-lg cursor-pointer"
              :class="{ 'bg-gray-100': isSelected(item) }"
              @click="navigateTo(item)"
              @mouseenter="selectedIndex = findIndex(item)"
            >
              <i class="material-symbols-rounded text-sm me-2 text-secondary">{{ item.icon || 'star' }}</i>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-bold text-dark">{{ item.title }}</span>
                <span v-if="item.description" class="text-xs text-muted">{{ item.description }}</span>
              </div>
              <i class="material-symbols-rounded text-xs ms-auto text-secondary">chevron_right</i>
            </li>
          </ul>
        </div>
      </div>

      <div v-else-if="searchQuery.length >= 2" class="p-3 text-center text-muted">
        <i class="material-symbols-rounded d-block mb-2 opacity-5" style="font-size: 40px">search_off</i>
        <span class="text-sm">No results found for "{{ searchQuery }}"</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const searchQuery = ref('')
const isFocused = ref(false)
const loading = ref(false)
const results = ref([])
const selectedIndex = ref(-1)
const searchRef = ref(null)
const searchInput = ref(null)

const isOpen = computed(() => isFocused.value && searchQuery.value.length >= 2)

const groupedResults = computed(() => {
  const groups = {}
  results.value.forEach(item => {
    if (!groups[item.category]) {
      groups[item.category] = []
    }
    groups[item.category].push(item)
  })
  return groups
})

const handleInput = debounce(async () => {
  if (searchQuery.value.length < 2) {
    results.value = []
    return
  }

  loading.value = true
  try {
    const response = await axios.get('/api/search', {
      params: { q: searchQuery.value }
    })
    results.value = response.data
    selectedIndex.value = results.value.length > 0 ? 0 : -1
  } catch (error) {
    console.error('Search error:', error)
  } finally {
    loading.value = false
  }
}, 300)

function debounce(fn, delay) {
  let timeoutId
  return function(...args) {
    if (timeoutId) clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn.apply(this, args), delay)
  }
}

const moveSelection = (direction) => {
  if (results.value.length === 0) return
  selectedIndex.value = (selectedIndex.value + direction + results.value.length) % results.value.length
}

const isSelected = (item) => {
  return results.value.indexOf(item) === selectedIndex.value
}

const findIndex = (item) => {
  return results.value.indexOf(item)
}

const selectItem = () => {
  if (selectedIndex.value >= 0 && results.value[selectedIndex.value]) {
    navigateTo(results.value[selectedIndex.value])
  }
}

const navigateTo = (item) => {
  searchQuery.value = ''
  isFocused.value = false
  router.visit(item.url)
}

const closeSearch = () => {
  isFocused.value = false
}

const handleClickOutside = (event) => {
  if (searchRef.value && !searchRef.value.contains(event.target)) {
    closeSearch()
  }
}

const handleKeydown = (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    searchInput.value?.focus()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<style scoped>
.global-search-container {
  position: relative;
  width: 100%;
  max-width: 300px;
}

.search-suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 1050;
  margin-top: 5px;
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #e9ecef;
}

.list-group-item:hover {
  background-color: #f8f9fa;
}

.cursor-pointer {
  cursor: pointer;
}

.input-group.input-group-outline .form-control {
  background: transparent;
}

.input-group-text {
  padding-left: 12px;
  border-right: 0;
}

.form-control {
  border-left: 0;
}

/* Hide default label when focused or has value */
.is-focused .form-label {
    display: none;
}
</style>
