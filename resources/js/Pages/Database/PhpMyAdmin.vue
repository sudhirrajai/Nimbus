<template>
  <MainLayout>
    <div class="phpmyadmin-wrapper">
      <div class="header-bar">
        <h5 class="mb-0">
          <i class="material-symbols-rounded me-2">database</i>
          phpMyAdmin
        </h5>
        <div class="controls">
          <button class="btn btn-sm btn-outline-secondary me-2" @click="openInNewTab">
            <i class="material-symbols-rounded text-sm me-1">open_in_new</i>
            Open in New Tab
          </button>
          <button class="btn btn-sm btn-outline-primary" @click="refresh">
            <i class="material-symbols-rounded text-sm me-1">refresh</i>
            Refresh
          </button>
        </div>
      </div>
      <iframe 
        ref="pmaFrame"
        :src="pmaUrl" 
        class="pma-iframe"
        frameborder="0"
      ></iframe>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  database: {
    type: String,
    default: ''
  }
})

const pmaFrame = ref(null)

const pmaUrl = computed(() => {
  let url = '/phpmyadmin/index.php'
  if (props.database) {
    url += '?db=' + encodeURIComponent(props.database)
  }
  return url
})

const refresh = () => {
  if (pmaFrame.value) {
    pmaFrame.value.src = pmaFrame.value.src
  }
}

const openInNewTab = () => {
  window.open(pmaUrl.value, '_blank')
}
</script>

<style scoped>
.phpmyadmin-wrapper {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 60px);
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  margin: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.header-bar h5 {
  display: flex;
  align-items: center;
  font-weight: 600;
}

.controls .btn {
  border-color: rgba(255,255,255,0.5);
  color: white;
}

.controls .btn:hover {
  background: rgba(255,255,255,0.2);
  border-color: white;
}

.pma-iframe {
  flex: 1;
  width: 100%;
  border: none;
}
</style>
