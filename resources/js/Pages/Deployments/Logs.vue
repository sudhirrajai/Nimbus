<template>
  <MainLayout>
    <div class="container-fluid py-4">

      <div class="row mb-4">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
              <button class="btn btn-link text-dark p-0 me-3 mb-0" @click="goBack">
                <i class="material-symbols-rounded">arrow_back</i>
              </button>
              <div>
                <h4 class="font-weight-bolder mb-0">
                  Deployment Logs
                  <span v-if="deployment" class="text-secondary">— {{ deployment.domain }}</span>
                </h4>
                <p class="mb-0 text-sm" v-if="deployment">
                  Branch: <span class="badge badge-sm bg-gradient-dark">{{ deployment.branch }}</span>
                  <span v-if="deployment.commit_hash" class="ms-2">
                    Commit: <code>{{ deployment.commit_hash }}</code>
                  </span>
                </p>
              </div>
            </div>
            <div class="d-flex gap-2" v-if="deployment">
              <span :class="`badge bg-gradient-${deployment.status_color} px-3 py-2`">
                {{ deployment.status }}
              </span>
              <button class="btn btn-outline-dark btn-sm mb-0" @click="refreshLogs" :disabled="refreshing">
                <i class="material-symbols-rounded text-sm" :class="{ 'spin': refreshing }">refresh</i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading state -->
      <div v-if="loading" class="row">
        <div class="col-12 text-center py-5">
          <div class="spinner-border text-dark" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-3 text-secondary">Loading deployment logs...</p>
        </div>
      </div>

      <!-- Log entries -->
      <div v-else class="row">
        <div class="col-12">
          <!-- No logs -->
          <div v-if="logs.length === 0" class="card">
            <div class="card-body text-center py-5">
              <i class="material-symbols-rounded text-secondary" style="font-size: 48px;">terminal</i>
              <p class="text-secondary mt-2 mb-0">No deployment logs available. Deploy the project first.</p>
            </div>
          </div>

          <!-- Log Steps -->
          <div v-else class="deployment-timeline">
            <div v-for="(log, index) in logs" :key="log.id"
              class="timeline-item mb-3"
            >
              <div class="card" :class="{ 'border-start border-3': true, [`border-${getStatusBorderColor(log.status)}`]: true }">
                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center" style="cursor: pointer;" @click="toggleLog(log.id)">
                  <div class="d-flex align-items-center">
                    <div class="step-status-icon me-3"
                      :class="`bg-${getStatusBorderColor(log.status)}`"
                    >
                      <i class="material-symbols-rounded text-white text-sm" :class="{ 'spin': log.status === 'running' }">
                        {{ getStatusMaterialIcon(log.status) }}
                      </i>
                    </div>
                    <div>
                      <h6 class="mb-0 text-sm">{{ log.step_label }}</h6>
                      <small class="text-secondary">
                        {{ log.created_at }}
                        <span v-if="log.duration_seconds !== null" class="ms-2">
                          <i class="material-symbols-rounded text-xs">timer</i>
                          {{ formatDuration(log.duration_seconds) }}
                        </span>
                      </small>
                    </div>
                  </div>
                  <div class="d-flex align-items-center">
                    <span :class="`badge badge-sm bg-gradient-${getStatusBorderColor(log.status)} me-2`">
                      {{ log.status }}
                    </span>
                    <i class="material-symbols-rounded text-secondary">
                      {{ expandedLogs.has(log.id) ? 'expand_less' : 'expand_more' }}
                    </i>
                  </div>
                </div>

                <!-- Expanded log output -->
                <div v-if="expandedLogs.has(log.id)" class="card-body py-2 px-3">
                  <!-- Command -->
                  <div v-if="log.command" class="mb-2">
                    <small class="text-secondary font-weight-bold">Command:</small>
                    <div class="command-block mt-1">
                      <code>$ {{ log.command }}</code>
                    </div>
                  </div>
                  <!-- Output -->
                  <div class="terminal-output">
                    <pre class="mb-0">{{ log.output || 'No output' }}</pre>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Error Summary -->
          <div v-if="deployment && deployment.status === 'failed' && deploymentError" class="card mt-3 border-start border-3 border-danger">
            <div class="card-body py-3">
              <div class="d-flex align-items-center">
                <i class="material-symbols-rounded text-danger me-2">error</i>
                <div>
                  <h6 class="mb-0 text-sm text-danger">Deployment Failed</h6>
                  <p class="text-sm mb-0 mt-1">{{ deploymentError }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>


<script setup>
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, onMounted, reactive } from 'vue'
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  deploymentId: Number,
})

const page = usePage()
const loading = ref(true)
const refreshing = ref(false)
const deployment = ref(null)
const deploymentError = ref(null)
const logs = ref([])
const expandedLogs = reactive(new Set())

onMounted(() => {
  loadLogs()
})

const loadLogs = async () => {
  try {
    loading.value = true
    const id = props.deploymentId || page.props.deploymentId
    const res = await axios.get(`/deployments/${id}/logs`)
    deployment.value = res.data.deployment
    logs.value = res.data.logs

    // Fetch full status for error info
    const statusRes = await axios.get(`/deployments/${id}/status`)
    deploymentError.value = statusRes.data.last_error

    // Auto-expand all log entries initially
    logs.value.forEach(log => expandedLogs.add(log.id))
  } catch (error) {
    console.error('Failed to load logs:', error)
  } finally {
    loading.value = false
  }
}

const refreshLogs = async () => {
  try {
    refreshing.value = true
    const id = props.deploymentId || page.props.deploymentId
    const res = await axios.get(`/deployments/${id}/logs`)
    deployment.value = res.data.deployment
    logs.value = res.data.logs

    const statusRes = await axios.get(`/deployments/${id}/status`)
    deploymentError.value = statusRes.data.last_error
  } catch (error) {
    console.error('Failed to refresh logs:', error)
  } finally {
    refreshing.value = false
  }
}

const toggleLog = (id) => {
  if (expandedLogs.has(id)) {
    expandedLogs.delete(id)
  } else {
    expandedLogs.add(id)
  }
}

const getStatusBorderColor = (status) => {
  switch (status) {
    case 'success': return 'success'
    case 'failed': return 'danger'
    case 'running': return 'warning'
    case 'skipped': return 'secondary'
    default: return 'secondary'
  }
}

const getStatusMaterialIcon = (status) => {
  switch (status) {
    case 'success': return 'check_circle'
    case 'failed': return 'error'
    case 'running': return 'sync'
    case 'skipped': return 'skip_next'
    default: return 'help'
  }
}

const formatDuration = (seconds) => {
  if (seconds === null || seconds === undefined) return ''
  if (seconds < 60) return `${seconds}s`
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}m ${secs}s`
}

const goBack = () => {
  router.visit('/deployments')
}
</script>

<style scoped>
.step-status-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bg-success { background-color: #2dce89 !important; }
.bg-danger { background-color: #f5365c !important; }
.bg-warning { background-color: #fb6340 !important; }
.bg-secondary { background-color: #8898aa !important; }

.terminal-output {
  background: #1a1a2e;
  border-radius: 8px;
  padding: 12px 16px;
  overflow-x: auto;
  max-height: 400px;
  overflow-y: auto;
}

.terminal-output pre {
  color: #e0e0e0;
  font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', 'Consolas', monospace;
  font-size: 0.8rem;
  line-height: 1.6;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.command-block {
  background: #f1f3f5;
  border-radius: 6px;
  padding: 8px 12px;
}

.command-block code {
  color: #344767;
  font-size: 0.8rem;
  background: none;
  padding: 0;
}

code {
  background: rgba(0, 0, 0, 0.06);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.8rem;
}

.border-success { border-color: #2dce89 !important; }
.border-danger { border-color: #f5365c !important; }
.border-warning { border-color: #fb6340 !important; }
.border-secondary { border-color: #8898aa !important; }

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.card {
  transition: all 0.2s ease;
}

.timeline-item .card:hover {
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}
</style>
