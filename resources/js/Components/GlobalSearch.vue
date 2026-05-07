<template>
  <div class="global-search-wrapper" ref="searchRef" :class="{ 'is-expanded': isFocused }">
    <div class="search-input-group" :class="{ 'is-focused': isFocused || searchQuery }">
      <i class="material-symbols-rounded search-icon">search</i>
      <input
        type="text"
        class="search-control"
        placeholder="Search everything... (Ctrl+K)"
        v-model="searchQuery"
        @focus="isFocused = true"
        @input="handleInput"
        @keydown.down.prevent="moveSelection(1)"
        @keydown.up.prevent="moveSelection(-1)"
        @keydown.enter.prevent="selectItem"
        @keydown.esc="closeSearch"
        ref="searchInput"
      />
      <div class="search-shortcut" v-if="!isFocused && !searchQuery">
        <span>Ctrl</span><span>K</span>
      </div>
      <div v-if="loading" class="search-loader">
        <div class="spinner-border spinner-border-sm" role="status"></div>
      </div>
    </div>

    <!-- Suggestions Dropdown with Transition -->
    <transition name="search-fade">
      <div v-if="isOpen" class="search-suggestions-dropdown shadow-xl border-radius-lg bg-white">
        <div v-if="results.length > 0">
          <div v-for="(categoryResults, category) in groupedResults" :key="category" class="category-section">
            <h6 class="category-header">{{ category }}</h6>
            <div
              v-for="item in categoryResults"
              :key="item.id || item.url + item.title"
              class="suggestion-item"
              :class="{ 'is-selected': isSelected(item) }"
              @click="navigateTo(item)"
              @mouseenter="selectedIndex = findIndex(item)"
            >
              <div class="item-icon-wrapper">
                <i class="material-symbols-rounded">{{ item.icon || 'star' }}</i>
              </div>
              <div class="item-content">
                <div class="item-title">{{ item.title }}</div>
                <div v-if="item.description" class="item-desc">{{ item.description }}</div>
              </div>
              <div class="item-badge" v-if="isSelected(item)">
                Enter
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="searchQuery.length >= 2 && !loading" class="no-results p-4 text-center">
          <i class="material-symbols-rounded d-block mb-2 opacity-3">sentiment_dissatisfied</i>
          <p class="text-sm text-muted mb-0">No results found for "{{ searchQuery }}"</p>
        </div>
      </div>
    </transition>
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

function debounce(fn, delay) {
  let timeoutId
  return function(...args) {
    if (timeoutId) clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn.apply(this, args), delay)
  }
}

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
.global-search-wrapper {
  position: relative;
  width: 100%;
  max-width: 280px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.global-search-wrapper.is-expanded {
  max-width: 400px;
}

.search-input-group {
  position: relative;
  display: flex;
  align-items: center;
  background: #f0f2f5;
  border-radius: 12px;
  padding: 0 12px;
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.search-input-group:hover {
  background: #e9ecef;
}

.search-input-group.is-focused {
  background: #fff;
  border-color: #344767;
  box-shadow: 0 4px 20px 0 rgba(0,0,0,0.05);
}

.search-icon {
  color: #67748e;
  font-size: 20px;
  margin-right: 8px;
}

.search-control {
  width: 100%;
  height: 40px;
  border: none;
  background: transparent;
  color: #344767;
  font-size: 0.875rem;
  font-weight: 400;
  outline: none !important;
}

.search-shortcut {
  display: flex;
  gap: 4px;
  opacity: 0.5;
}

.search-shortcut span {
  font-size: 10px;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  padding: 1px 4px;
  font-weight: bold;
}

.search-loader {
  position: absolute;
  right: 12px;
  color: #344767;
}

/* Dropdown */
.search-suggestions-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  z-index: 1100;
  max-height: 450px;
  overflow-y: auto;
  border: 1px solid rgba(0,0,0,0.05);
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  transform-origin: top center;
}

.category-header {
  padding: 12px 16px 4px;
  font-size: 0.65rem;
  text-transform: uppercase;
  color: #67748e;
  font-weight: 700;
  letter-spacing: 1px;
}

.suggestion-item {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  margin: 4px 8px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.suggestion-item:hover, .suggestion-item.is-selected {
  background: rgba(52, 71, 103, 0.05);
  transform: translateX(4px);
}

.suggestion-item.is-selected {
  background: rgba(52, 71, 103, 0.1);
}

.item-icon-wrapper {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  border-radius: 6px;
  margin-right: 12px;
  color: #344767;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.suggestion-item:hover .item-icon-wrapper {
  background: #344767;
  color: #fff;
}

.item-content {
  flex: 1;
}

.item-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #344767;
}

.item-desc {
  font-size: 0.75rem;
  color: #67748e;
}

.item-badge {
  font-size: 0.65rem;
  padding: 2px 6px;
  background: #344767;
  color: #fff;
  border-radius: 4px;
  font-weight: 600;
}

/* Animations */
.search-fade-enter-active, .search-fade-leave-active {
  transition: all 0.2s ease-out;
}

.search-fade-enter-from, .search-fade-leave-to {
  opacity: 0;
  transform: translateY(-10px) scale(0.98);
}

.spinner-border-sm {
  width: 1rem;
  height: 1rem;
}
</style>
