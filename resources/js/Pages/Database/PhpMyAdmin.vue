<template>
  <MainLayout>
    <div class="phpmyadmin-wrapper">
      <div class="header-bar border-bottom">
        <h5 class="mb-0 text-dark font-weight-bolder">
          <i class="material-symbols-rounded me-2 text-primary">storage</i>
          Nimbus DB
        </h5>
        <div class="controls">
          <button class="btn btn-sm btn-outline-dark me-2 mb-0" @click="openInNewTab">
            <i class="material-symbols-rounded text-sm me-1">open_in_new</i>
            Open in New Tab
          </button>
          <button class="btn btn-sm bg-gradient-dark mb-0 text-white" @click="refresh">
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
  padding: 16px 24px;
  background: #fff;
  border-bottom: 1px solid #f0f2f5;
}

.header-bar h5 {
  display: flex;
  align-items: center;
  font-weight: 700;
  color: #344767;
}

.controls .btn {
  text-transform: uppercase;
  font-weight: 700;
  font-size: 0.75rem;
}

.pma-iframe {
  flex: 1;
  width: 100%;
  border: none;
}
</style>
