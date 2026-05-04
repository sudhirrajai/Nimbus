<template>
  <div class="web-terminal-wrapper" :class="{ 'terminal-open': isOpen, 'terminal-maximized': isMaximized }">
    <!-- Toggle Button (shown when terminal is closed) -->
    <button v-if="!isOpen" class="terminal-toggle-btn" @click="openTerminal" title="Open Web Terminal">
      <i class="material-symbols-rounded">terminal</i>
      <span>Terminal</span>
    </button>

    <!-- Terminal Panel -->
    <div v-if="isOpen" class="terminal-panel" :style="panelStyle">
      <!-- Resize Handle -->
      <div class="terminal-resize-handle" @mousedown="startResize" v-if="!isMaximized"></div>

      <!-- Terminal Header -->
      <div class="terminal-header" @dblclick="toggleMaximize">
        <div class="terminal-header-left">
          <div class="terminal-dots">
            <span class="dot red" @click="closeTerminal" title="Close"></span>
            <span class="dot yellow" @click="minimizeTerminal" title="Minimize"></span>
            <span class="dot green" @click="toggleMaximize" title="Maximize"></span>
          </div>
          <div class="terminal-title">
            <i class="material-symbols-rounded terminal-title-icon">terminal</i>
            <span class="terminal-title-text">
              www-data@nimbus: /var/www/{{ domain }}{{ terminalCwd ? '/' + terminalCwd : '' }}
            </span>
          </div>
        </div>
        <div class="terminal-header-right">
          <button class="terminal-header-btn" @click="clearTerminal" title="Clear (Ctrl+L)">
            <i class="material-symbols-rounded">cleaning_services</i>
          </button>
          <button class="terminal-header-btn" @click="toggleMaximize" :title="isMaximized ? 'Restore' : 'Maximize'">
            <i class="material-symbols-rounded">{{ isMaximized ? 'close_fullscreen' : 'open_in_full' }}</i>
          </button>
          <button class="terminal-header-btn close" @click="closeTerminal" title="Close Terminal">
            <i class="material-symbols-rounded">close</i>
          </button>
        </div>
      </div>

      <!-- Terminal Body -->
      <div class="terminal-body" ref="terminalBody" @click="focusInput">
        <!-- Output Lines -->
        <div v-for="(line, index) in outputLines" :key="index" class="terminal-line">
          <template v-if="line.type === 'prompt'">
            <span class="prompt-user">www-data</span><span class="prompt-at">@</span><span class="prompt-host">nimbus</span><span class="prompt-colon">:</span><span class="prompt-path">{{ line.cwd ? '~/' + line.cwd : '~' }}</span><span class="prompt-dollar">$</span>
            <span class="prompt-command">{{ line.command }}</span>
          </template>
          <template v-else-if="line.type === 'output'">
            <span class="output-text" v-html="line.html || escapeHtml(line.text)"></span>
          </template>
          <template v-else-if="line.type === 'error'">
            <span class="output-error">{{ line.text }}</span>
          </template>
          <template v-else-if="line.type === 'info'">
            <span class="output-info">{{ line.text }}</span>
          </template>
        </div>

        <!-- Active Input Line -->
        <div class="terminal-input-line" v-if="!isExecuting">
          <span class="prompt-user">www-data</span><span class="prompt-at">@</span><span class="prompt-host">nimbus</span><span class="prompt-colon">:</span><span class="prompt-path">{{ terminalCwd ? '~/' + terminalCwd : '~' }}</span><span class="prompt-dollar">$</span>
          <input
            ref="terminalInput"
            v-model="currentCommand"
            class="terminal-command-input"
            @keydown="handleKeyDown"
            spellcheck="false"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
          />
        </div>

        <!-- Executing Indicator -->
        <div v-if="isExecuting" class="terminal-executing">
          <span class="executing-spinner"></span>
          <span class="executing-text">Running...</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  domain: { type: String, required: true },
  currentPath: { type: String, default: '' },
})

const emit = defineEmits(['refresh-files'])

// Terminal state
const isOpen = ref(false)
const isMaximized = ref(false)
const isExecuting = ref(false)
const currentCommand = ref('')
const terminalCwd = ref(props.currentPath || '')
const outputLines = ref([])
const commandHistory = ref([])
const historyIndex = ref(-1)
const panelHeight = ref(380)

// Refs
const terminalBody = ref(null)
const terminalInput = ref(null)

// Computed panel style
const panelStyle = ref({})

watch(() => panelHeight.value, (val) => {
  if (!isMaximized.value) {
    panelStyle.value = { height: val + 'px' }
  }
})

watch(() => isMaximized.value, (val) => {
  panelStyle.value = val ? {} : { height: panelHeight.value + 'px' }
})

// Sync with parent's currentPath when it changes
watch(() => props.currentPath, (newPath) => {
  if (!isOpen.value) {
    terminalCwd.value = newPath || ''
  }
})

// Open / close
const openTerminal = () => {
  isOpen.value = true
  terminalCwd.value = props.currentPath || ''
  panelStyle.value = { height: panelHeight.value + 'px' }

  if (outputLines.value.length === 0) {
    outputLines.value.push({
      type: 'info',
      text: '╔══════════════════════════════════════════════════════════════╗',
    })
    outputLines.value.push({
      type: 'info',
      text: '║     Nimbus Web Terminal — Secure Shell Access               ║',
    })
    outputLines.value.push({
      type: 'info',
      text: '║     Type "help" for available info. Use with caution.       ║',
    })
    outputLines.value.push({
      type: 'info',
      text: '╚══════════════════════════════════════════════════════════════╝',
    })
    outputLines.value.push({ type: 'output', text: '' })
  }

  nextTick(() => focusInput())
}

const closeTerminal = () => {
  isOpen.value = false
  isMaximized.value = false
}

const minimizeTerminal = () => {
  isOpen.value = false
}

const toggleMaximize = () => {
  isMaximized.value = !isMaximized.value
  nextTick(() => focusInput())
}

const clearTerminal = () => {
  outputLines.value = []
  nextTick(() => focusInput())
}

const focusInput = () => {
  nextTick(() => {
    terminalInput.value?.focus()
  })
}

const scrollToBottom = () => {
  nextTick(() => {
    if (terminalBody.value) {
      terminalBody.value.scrollTop = terminalBody.value.scrollHeight
    }
  })
}

// Command handling
const handleKeyDown = (e) => {
  switch (e.key) {
    case 'Enter':
      executeCommand()
      break
    case 'ArrowUp':
      e.preventDefault()
      navigateHistory(-1)
      break
    case 'ArrowDown':
      e.preventDefault()
      navigateHistory(1)
      break
    case 'l':
      if (e.ctrlKey) {
        e.preventDefault()
        clearTerminal()
      }
      break
    case 'c':
      if (e.ctrlKey && !currentCommand.value) {
        // Add a cancelled prompt line
        outputLines.value.push({
          type: 'prompt',
          cwd: terminalCwd.value,
          command: '^C',
        })
        scrollToBottom()
      }
      break
  }
}

const navigateHistory = (direction) => {
  if (commandHistory.value.length === 0) return

  if (direction === -1) {
    // Up
    if (historyIndex.value < commandHistory.value.length - 1) {
      historyIndex.value++
      currentCommand.value = commandHistory.value[commandHistory.value.length - 1 - historyIndex.value]
    }
  } else {
    // Down
    if (historyIndex.value > 0) {
      historyIndex.value--
      currentCommand.value = commandHistory.value[commandHistory.value.length - 1 - historyIndex.value]
    } else {
      historyIndex.value = -1
      currentCommand.value = ''
    }
  }
}

const executeCommand = async () => {
  const command = currentCommand.value.trim()

  // Add prompt line to output
  outputLines.value.push({
    type: 'prompt',
    cwd: terminalCwd.value,
    command: command,
  })

  currentCommand.value = ''
  historyIndex.value = -1

  if (!command) {
    scrollToBottom()
    return
  }

  // Add to history
  commandHistory.value.push(command)
  if (commandHistory.value.length > 100) {
    commandHistory.value.shift()
  }

  // Handle built-in 'help' command
  if (command === 'help') {
    outputLines.value.push({
      type: 'info',
      text: '  Available commands:',
    })
    outputLines.value.push({
      type: 'info',
      text: '  • Any standard Linux command (ls, cat, grep, npm, php, etc.)',
    })
    outputLines.value.push({
      type: 'info',
      text: '  • cd <dir>      — Navigate directories (within /var/www)',
    })
    outputLines.value.push({
      type: 'info',
      text: '  • clear          — Clear the terminal screen',
    })
    outputLines.value.push({
      type: 'info',
      text: '  • help           — Show this help message',
    })
    outputLines.value.push({
      type: 'output',
      text: '',
    })
    outputLines.value.push({
      type: 'info',
      text: '  ⚠ Commands run as www-data user. Some system commands are restricted.',
    })
    scrollToBottom()
    return
  }

  isExecuting.value = true
  scrollToBottom()

  try {
    const response = await axios.post(`/file-manager/${props.domain}/terminal/execute`, {
      command: command,
      path: terminalCwd.value,
    })

    const data = response.data

    if (data.output === '__CLEAR__') {
      outputLines.value = []
    } else if (data.output) {
      // Process output into lines, handling ANSI colors
      const lines = data.output.split('\n')
      lines.forEach((line) => {
        outputLines.value.push({
          type: data.success ? 'output' : 'error',
          text: line,
          html: ansiToHtml(line),
        })
      })
    }

    // Update cwd if changed (from cd command)
    if (data.cwd !== undefined) {
      terminalCwd.value = data.cwd
    }

    // If command was file-modifying, emit refresh
    const modifyingCommands = ['touch', 'mkdir', 'rm', 'mv', 'cp', 'chmod', 'chown', 'unzip', 'tar', 'npm', 'composer']
    if (modifyingCommands.some(cmd => command.startsWith(cmd))) {
      emit('refresh-files')
    }
  } catch (error) {
    const msg = error.response?.data?.output || error.response?.data?.error || 'Command execution failed.'
    outputLines.value.push({
      type: 'error',
      text: msg,
    })
  } finally {
    isExecuting.value = false
    scrollToBottom()
    focusInput()
  }
}

// Basic ANSI to HTML conversion
const ansiToHtml = (text) => {
  if (!text) return ''
  let html = escapeHtml(text)

  // Convert ANSI color codes to spans
  const ansiMap = {
    '30': '#4a4a4a', '31': '#ff6b6b', '32': '#51cf66', '33': '#ffd43b',
    '34': '#74c0fc', '35': '#da77f2', '36': '#66d9e8', '37': '#dee2e6',
    '90': '#868e96', '91': '#ff8787', '92': '#69db7c', '93': '#ffe066',
    '94': '#91a7ff', '95': '#e599f7', '96': '#99e9f2', '97': '#f8f9fa',
    '1': null, // bold
    '0': null, // reset
  }

  // Replace ANSI sequences like \x1b[32m
  html = html.replace(/\x1b\[([0-9;]+)m/g, (match, codes) => {
    const parts = codes.split(';')
    let style = ''
    for (const code of parts) {
      if (code === '0') return '</span>'
      if (code === '1') style += 'font-weight:bold;'
      if (ansiMap[code]) style += `color:${ansiMap[code]};`
    }
    return style ? `<span style="${style}">` : ''
  })

  return html
}

const escapeHtml = (text) => {
  if (!text) return ''
  const div = document.createElement('div')
  div.textContent = text
  return div.innerHTML
}

// Resize handling
let isResizing = false
let startY = 0
let startHeight = 0

const startResize = (e) => {
  isResizing = true
  startY = e.clientY
  startHeight = panelHeight.value
  document.addEventListener('mousemove', doResize)
  document.addEventListener('mouseup', stopResize)
  document.body.style.cursor = 'ns-resize'
  document.body.style.userSelect = 'none'
}

const doResize = (e) => {
  if (!isResizing) return
  const delta = startY - e.clientY
  const newHeight = Math.max(200, Math.min(window.innerHeight - 100, startHeight + delta))
  panelHeight.value = newHeight
}

const stopResize = () => {
  isResizing = false
  document.removeEventListener('mousemove', doResize)
  document.removeEventListener('mouseup', stopResize)
  document.body.style.cursor = ''
  document.body.style.userSelect = ''
}

// Global keyboard shortcut
const handleGlobalKeyDown = (e) => {
  // Ctrl+` to toggle terminal
  if (e.ctrlKey && e.key === '`') {
    e.preventDefault()
    if (isOpen.value) {
      closeTerminal()
    } else {
      openTerminal()
    }
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleGlobalKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleGlobalKeyDown)
  document.removeEventListener('mousemove', doResize)
  document.removeEventListener('mouseup', stopResize)
})
</script>

<style scoped>
.web-terminal-wrapper {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 1050;
  pointer-events: none;
}

.web-terminal-wrapper > * {
  pointer-events: auto;
}

/* Toggle Button */
.terminal-toggle-btn {
  position: fixed;
  bottom: 20px;
  right: 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  color: #e0e0e0;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), 0 0 40px rgba(102, 126, 234, 0.1);
  letter-spacing: 0.3px;
  z-index: 1051;
}

.terminal-toggle-btn:hover {
  background: linear-gradient(135deg, #1e2a4a 0%, #1a2a50 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), 0 0 60px rgba(102, 126, 234, 0.15);
  color: #fff;
}

.terminal-toggle-btn i {
  font-size: 18px;
  color: #74c0fc;
}

/* Terminal Panel */
.terminal-panel {
  position: fixed;
  bottom: 0;
  left: calc(14rem + 1rem);
  right: 0;
  display: flex;
  flex-direction: column;
  background: #0d1117;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
  box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.5);
  animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 1050;
}

.terminal-maximized .terminal-panel {
  top: 0;
  left: calc(14rem + 1rem);
  height: 100vh !important;
  border-radius: 0;
}

/* On mobile / smaller screens where sidebar collapses */
@media (max-width: 1199.98px) {
  .terminal-panel {
    left: 0;
    border-top-left-radius: 12px;
  }

  .terminal-maximized .terminal-panel {
    left: 0;
  }
}

@keyframes slideUp {
  from {
    transform: translateY(100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* Resize Handle */
.terminal-resize-handle {
  position: absolute;
  top: -4px;
  left: 0;
  right: 0;
  height: 8px;
  cursor: ns-resize;
  z-index: 10;
}

.terminal-resize-handle:hover,
.terminal-resize-handle:active {
  background: linear-gradient(180deg, rgba(102, 126, 234, 0.3) 0%, transparent 100%);
}

/* Terminal Header */
.terminal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: linear-gradient(180deg, #161b22 0%, #0d1117 100%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  user-select: none;
  flex-shrink: 0;
}

.terminal-header-left {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
}

.terminal-dots {
  display: flex;
  gap: 7px;
  flex-shrink: 0;
}

.dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.15s ease;
  border: none;
}

.dot:hover {
  transform: scale(1.2);
}

.dot.red { background: #ff5f56; }
.dot.yellow { background: #ffbd2e; }
.dot.green { background: #27c93f; }

.dot.red:hover { background: #ff3b30; box-shadow: 0 0 8px rgba(255, 59, 48, 0.5); }
.dot.yellow:hover { background: #f5a623; box-shadow: 0 0 8px rgba(245, 166, 35, 0.5); }
.dot.green:hover { background: #17b53a; box-shadow: 0 0 8px rgba(39, 201, 63, 0.5); }

.terminal-title {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.terminal-title-icon {
  font-size: 16px;
  color: #8b949e;
}

.terminal-title-text {
  font-size: 12px;
  color: #8b949e;
  font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', 'Consolas', monospace;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.terminal-header-right {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.terminal-header-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  color: #8b949e;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.terminal-header-btn:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #c9d1d9;
}

.terminal-header-btn.close:hover {
  background: rgba(255, 59, 48, 0.15);
  color: #ff5f56;
}

.terminal-header-btn i {
  font-size: 16px;
}

/* Terminal Body */
.terminal-body {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
  font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', 'Consolas', 'Courier New', monospace;
  font-size: 13px;
  line-height: 1.7;
  color: #c9d1d9;
  cursor: text;
  min-height: 0;
}

.terminal-body::-webkit-scrollbar {
  width: 8px;
}

.terminal-body::-webkit-scrollbar-track {
  background: transparent;
}

.terminal-body::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.terminal-body::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Terminal Lines */
.terminal-line {
  white-space: pre-wrap;
  word-break: break-all;
  min-height: 1.7em;
}

/* Prompt styling */
.prompt-user { color: #7ee787; font-weight: 600; }
.prompt-at { color: #8b949e; }
.prompt-host { color: #79c0ff; font-weight: 600; }
.prompt-colon { color: #8b949e; }
.prompt-path { color: #d2a8ff; font-weight: 500; }
.prompt-dollar { color: #8b949e; margin: 0 6px 0 2px; }
.prompt-command { color: #e6edf3; }

/* Output styling */
.output-text { color: #c9d1d9; }
.output-error { color: #ff7b72; }
.output-info { color: #79c0ff; }

/* Input Line */
.terminal-input-line {
  display: flex;
  align-items: center;
  white-space: nowrap;
}

.terminal-command-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #e6edf3;
  font-family: inherit;
  font-size: inherit;
  line-height: inherit;
  padding: 0;
  caret-color: #58a6ff;
}

.terminal-command-input::selection {
  background: rgba(88, 166, 255, 0.3);
}

/* Executing indicator */
.terminal-executing {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
}

.executing-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid rgba(88, 166, 255, 0.3);
  border-top-color: #58a6ff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.executing-text {
  color: #8b949e;
  font-size: 12px;
}
</style>
