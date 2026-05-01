<template>
  <MainLayout>
    <div class="phpmyadmin-wrapper">
      <div class="header-bar border-bottom">
        <h5 class="mb-0 text-dark font-weight-bolder">
          <i class="material-symbols-rounded me-2 text-primary">storage</i>
          Database
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
      <iframe ref="pmaFrame" :src="pmaUrl" class="pma-iframe" frameborder="0"></iframe>
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
  let url = '/db/'
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


