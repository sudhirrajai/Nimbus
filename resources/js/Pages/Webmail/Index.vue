<template>
  <div class="webmail-wrapper" :class="{ 'dark-theme': isDarkMode }">
    <!-- TOP APP BAR -->
    <header class="webmail-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <!-- Mobile Sidebar Toggle -->
        <button class="btn btn-icon d-lg-none p-1 text-secondary" @click="toggleSidebar">
          <i class="material-symbols-rounded">menu</i>
        </button>

        <a :href="isNimbusUser ? '/dashboard' : '/webmail'" class="d-flex align-items-center text-decoration-none gap-2">
          <img :src="'/assets/img/nimbus_logo.png?v=2'" alt="Nimbus" class="topbar-logo" />
          <div class="d-flex flex-column">
            <span class="brand-text">nimbus <span class="brand-badge">WEBMAIL</span></span>
          </div>
        </a>
      </div>

      <!-- Center Search Bar (Desktop) -->
      <div class="search-bar-wrapper flex-grow-1 mx-3 mx-lg-5 d-none d-md-block">
        <div class="search-input-group">
          <i class="material-symbols-rounded search-icon">search</i>
          <input 
            type="text" 
            v-model="searchQuery" 
            @input="debounceSearch" 
            placeholder="Search mail by sender, subject, or content..." 
            class="search-control"
          />
          <button v-if="searchQuery" class="btn-clear-search" @click="clearSearch" type="button" title="Clear search">
            <i class="material-symbols-rounded text-sm">close</i>
          </button>
        </div>
      </div>

      <!-- Top Right Controls -->
      <div class="d-flex align-items-center gap-2">
        <!-- Dark / Light Mode Toggle -->
        <button 
          class="theme-toggle-btn" 
          @click="toggleTheme" 
          :title="isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
        >
          <i class="material-symbols-rounded">{{ isDarkMode ? 'light_mode' : 'dark_mode' }}</i>
        </button>

        <!-- Refresh Mailbox -->
        <button 
          class="btn-icon-top" 
          @click="refreshCurrentFolder" 
          :class="{ 'is-spinning': loadingMessages }" 
          title="Refresh Messages"
        >
          <i class="material-symbols-rounded">refresh</i>
        </button>

        <!-- Account Switcher Dropdown -->
        <div class="dropdown position-relative" v-if="accounts && accounts.length > 0">
          <button 
            class="account-pill-btn d-flex align-items-center gap-2" 
            type="button" 
            @click="isAccountDropdownOpen = !isAccountDropdownOpen"
          >
            <div class="user-avatar-sm">{{ getInitials(activeEmail) }}</div>
            <div class="d-none d-sm-flex flex-column text-start">
              <span class="user-email-text">{{ activeEmail }}</span>
              <span class="user-domain-text">{{ activeEmailDomain }}</span>
            </div>
            <i class="material-symbols-rounded text-xs ms-1">expand_more</i>
          </button>

          <div v-if="isAccountDropdownOpen" class="account-menu-dropdown shadow-lg">
            <div class="dropdown-header-custom">
              <span class="text-xs text-uppercase font-weight-bold opacity-7">Switch Mailbox</span>
            </div>
            <div class="account-menu-list">
              <button 
                v-for="acc in accounts" 
                :key="acc.email" 
                class="account-menu-item" 
                :class="{ 'active': acc.email === activeEmail }"
                @click="switchMailbox(acc.email)"
              >
                <div class="user-avatar-xs">{{ getInitials(acc.email) }}</div>
                <div class="d-flex flex-column flex-grow-1 text-start">
                  <span class="text-xs font-weight-bold">{{ acc.email }}</span>
                  <span class="text-xxs opacity-7">{{ acc.quota ? acc.quota + ' MB' : 'Active' }}</span>
                </div>
                <i v-if="acc.email === activeEmail" class="material-symbols-rounded text-success text-sm">check</i>
              </button>
            </div>
            <div class="dropdown-divider my-1"></div>
            <div class="p-2 d-flex flex-column gap-1">
              <a v-if="isNimbusUser" href="/email" class="dropdown-sublink">
                <i class="material-symbols-rounded text-sm me-1.5">settings</i> Manage Email Accounts
              </a>
              <a v-if="isNimbusUser" href="/dashboard" class="dropdown-sublink">
                <i class="material-symbols-rounded text-sm me-1.5">arrow_back</i> Back to Panel
              </a>
              <button @click="logout" class="dropdown-sublink text-danger border-0 bg-transparent text-start w-100">
                <i class="material-symbols-rounded text-sm me-1.5">logout</i> Sign Out
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- MAIN 3-PANE CONTAINER -->
    <div class="webmail-main-layout">
      <!-- 1. FOLDERS SIDEBAR -->
      <aside class="webmail-sidebar" :class="{ 'sidebar-open': isSidebarOpen }">
        <!-- Compose Button -->
        <div class="p-3">
          <button class="btn-compose-mail w-100 shadow-sm" @click="openComposeModal()">
            <i class="material-symbols-rounded text-lg">edit</i>
            <span>Compose</span>
          </button>
        </div>

        <!-- Folders List -->
        <div class="folders-nav flex-grow-1">
          <div class="folder-group-title">Folders</div>
          <ul class="folder-list">
            <li v-for="folder in folders" :key="folder.id">
              <button 
                class="folder-nav-btn" 
                :class="{ 'active': currentFolder === folder.id }"
                @click="selectFolder(folder.id)"
              >
                <i class="material-symbols-rounded folder-icon">{{ folder.icon }}</i>
                <span class="folder-name">{{ folder.name }}</span>
                <span v-if="folder.unread > 0" class="badge-unread">
                  {{ folder.unread }}
                </span>
                <span v-else-if="folder.total > 0 && folder.id !== 'INBOX'" class="badge-total">
                  {{ folder.total }}
                </span>
              </button>
            </li>
          </ul>
        </div>

        <!-- Mailbox Quota Footer -->
        <div class="mailbox-quota-box p-3 border-top">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-xxs font-weight-bold text-uppercase opacity-7 d-flex align-items-center gap-1">
              <i class="material-symbols-rounded text-xs">storage</i> Storage Quota
            </span>
            <span class="text-xxs font-weight-bold">{{ currentAccount.quota.limit }} MB</span>
          </div>
          <div class="progress quota-progress">
            <div 
              class="progress-bar bg-gradient-info" 
              role="progressbar" 
              :style="{ width: Math.min(((currentAccount.quota.used || 0) / currentAccount.quota.limit) * 100, 100) + '%' }"
            ></div>
          </div>
        </div>
      </aside>

      <!-- Sidebar Backdrop for Mobile -->
      <div v-if="isSidebarOpen" class="sidebar-backdrop" @click="isSidebarOpen = false"></div>

      <!-- 2. MESSAGES LIST PANE -->
      <section class="messages-list-pane" :class="{ 'd-none d-md-flex': selectedMessage && isMobileView }">
        <!-- List Toolbar -->
        <div class="list-pane-toolbar p-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
          <!-- Selection & Filter -->
          <div class="d-flex align-items-center gap-2">
            <div class="form-check m-0 p-0 d-flex align-items-center">
              <input 
                type="checkbox" 
                class="form-check-input select-all-check mt-0" 
                :checked="isAllSelected" 
                :indeterminate.prop="isIndeterminate"
                @change="toggleSelectAll"
                title="Select all"
              />
            </div>

            <!-- Action Buttons when messages are selected -->
            <template v-if="selectedMessageIds.length > 0">
              <div class="bulk-actions d-flex align-items-center gap-1">
                <button class="btn-tool" @click="bulkMarkRead(true)" title="Mark as read">
                  <i class="material-symbols-rounded text-sm">mark_email_read</i>
                </button>
                <button class="btn-tool" @click="bulkMarkRead(false)" title="Mark as unread">
                  <i class="material-symbols-rounded text-sm">mark_email_unread</i>
                </button>
                <button class="btn-tool" @click="bulkStar" title="Star">
                  <i class="material-symbols-rounded text-sm">star</i>
                </button>
                <button class="btn-tool text-danger" @click="bulkDelete" title="Move to Trash">
                  <i class="material-symbols-rounded text-sm">delete</i>
                </button>
                <div class="dropdown d-inline-block">
                  <button class="btn-tool" @click="isMoveDropdownOpen = !isMoveDropdownOpen" title="Move to folder">
                    <i class="material-symbols-rounded text-sm">drive_file_move</i>
                  </button>
                  <div v-if="isMoveDropdownOpen" class="move-dropdown-menu shadow">
                    <button 
                      v-for="f in folders.filter(x => x.id !== currentFolder && x.id !== 'Starred')" 
                      :key="f.id" 
                      class="move-item" 
                      @click="bulkMoveTo(f.id)"
                    >
                      <i class="material-symbols-rounded text-xs me-2">{{ f.icon }}</i> {{ f.name }}
                    </button>
                  </div>
                </div>
              </div>
            </template>

            <!-- Filter Pills when no selection -->
            <div v-else class="filter-pills d-flex align-items-center gap-1">
              <button 
                class="filter-chip" 
                :class="{ 'active': activeFilter === null }" 
                @click="setFilter(null)"
              >All</button>
              <button 
                class="filter-chip" 
                :class="{ 'active': activeFilter === 'unread' }" 
                @click="setFilter('unread')"
              >Unread</button>
              <button 
                class="filter-chip" 
                :class="{ 'active': activeFilter === 'starred' }" 
                @click="setFilter('starred')"
              >Starred</button>
              <button 
                class="filter-chip" 
                :class="{ 'active': activeFilter === 'attachments' }" 
                @click="setFilter('attachments')"
              >Files</button>
            </div>
          </div>

          <!-- Pagination Info -->
          <div class="d-flex align-items-center gap-1 text-xxs opacity-7">
            <span>{{ paginationText }}</span>
            <button 
              class="btn-pager" 
              :disabled="pagination.page <= 1" 
              @click="changePage(pagination.page - 1)"
            >
              <i class="material-symbols-rounded text-sm">chevron_left</i>
            </button>
            <button 
              class="btn-pager" 
              :disabled="pagination.page >= pagination.lastPage" 
              @click="changePage(pagination.page + 1)"
            >
              <i class="material-symbols-rounded text-sm">chevron_right</i>
            </button>
          </div>
        </div>

        <!-- Messages Container -->
        <div class="messages-scroll-area flex-grow-1">
          <!-- Loading State -->
          <div v-if="loadingMessages" class="p-5 text-center">
            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
            <p class="text-xs text-muted mb-0">Loading messages...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="messages.length === 0" class="empty-folder-state p-5 text-center">
            <div class="empty-icon-circle mb-3">
              <i class="material-symbols-rounded text-secondary">{{ currentFolderIcon }}</i>
            </div>
            <h6 class="text-sm font-weight-bold mb-1">No messages in {{ currentFolderName }}</h6>
            <p class="text-xs text-muted mb-0">Your folder is completely clear.</p>
          </div>

          <!-- Message Items List -->
          <div v-else class="messages-stack">
            <div 
              v-for="msg in messages" 
              :key="msg.id" 
              class="message-row"
              :class="{ 
                'is-unread': !msg.isRead, 
                'is-active': selectedMessage && selectedMessage.id === msg.id,
                'is-selected': selectedMessageIds.includes(msg.id)
              }"
              @click="openMessage(msg)"
            >
              <!-- Checkbox -->
              <div class="msg-col-check" @click.stop>
                <input 
                  type="checkbox" 
                  class="form-check-input msg-check" 
                  :value="msg.id" 
                  v-model="selectedMessageIds" 
                />
              </div>

              <!-- Star Button -->
              <button class="msg-star-btn" @click.stop="toggleStar(msg)" title="Star/Unstar">
                <i class="material-symbols-rounded text-sm" :class="msg.isStarred ? 'text-warning fill-star' : 'text-muted'">
                  {{ msg.isStarred ? 'star' : 'star_border' }}
                </i>
              </button>

              <!-- Sender Avatar / Initial -->
              <div class="msg-avatar" :style="{ backgroundColor: getAvatarColor(msg.from.name || msg.from.address) }">
                {{ getInitials(msg.from.name || msg.from.address) }}
              </div>

              <!-- Sender & Snippet Content -->
              <div class="msg-body-preview flex-grow-1">
                <div class="d-flex justify-content-between align-items-baseline mb-0.5">
                  <span class="msg-sender-name text-truncate">
                    {{ msg.from.name || msg.from.address }}
                  </span>
                  <span class="msg-date text-xxs text-muted text-nowrap ms-2">
                    {{ msg.dateFormatted }}
                  </span>
                </div>
                <div class="d-flex align-items-center justify-content-between gap-1">
                  <div class="text-truncate">
                    <span class="msg-subject-text">{{ msg.subject || '(No Subject)' }}</span>
                    <span class="msg-snippet-text ms-1 text-muted">{{ msg.preview }}</span>
                  </div>
                  <i v-if="msg.hasAttachments" class="material-symbols-rounded text-xs text-muted ms-1 flex-shrink-0">
                    attach_file
                  </i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 3. MESSAGE READING PANE -->
      <section class="message-reader-pane flex-grow-1" :class="{ 'd-none d-md-flex': !selectedMessage && isMobileView }">
        <!-- Blank State when no message is selected -->
        <div v-if="!selectedMessage && !loadingMessageDetails" class="reader-empty-state d-flex flex-column align-items-center justify-content-center h-100 p-4">
          <div class="reader-empty-icon mb-3">
            <i class="material-symbols-rounded">mark_email_unread</i>
          </div>
          <h5 class="text-dark font-weight-bold mb-1">Select an email to read</h5>
          <p class="text-xs text-muted text-center" style="max-width: 320px;">
            Choose a message from the list on the left to view full contents, images, and attachments.
          </p>
        </div>

        <!-- Loading Single Message -->
        <div v-else-if="loadingMessageDetails" class="p-5 text-center d-flex flex-column align-items-center justify-content-center h-100">
          <div class="spinner-border text-primary mb-3" role="status"></div>
          <span class="text-xs text-muted">Opening message...</span>
        </div>

        <!-- Full Message Display -->
        <div v-else-if="selectedMessage" class="message-view-container d-flex flex-column h-100">
          <!-- Reader Action Toolbar -->
          <div class="reader-toolbar p-2 px-3 border-bottom d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-1">
              <!-- Back button on mobile -->
              <button class="btn-tool d-md-none me-1" @click="selectedMessage = null" title="Back to list">
                <i class="material-symbols-rounded text-sm">arrow_back</i>
              </button>

              <button class="btn-tool-action" @click="replyToMessage(false)" title="Reply">
                <i class="material-symbols-rounded text-sm me-1">reply</i>
                <span class="d-none d-sm-inline">Reply</span>
              </button>
              <button class="btn-tool-action" @click="replyToMessage(true)" title="Reply All">
                <i class="material-symbols-rounded text-sm me-1">reply_all</i>
                <span class="d-none d-sm-inline">Reply All</span>
              </button>
              <button class="btn-tool-action" @click="forwardMessage" title="Forward">
                <i class="material-symbols-rounded text-sm me-1">forward</i>
                <span class="d-none d-sm-inline">Forward</span>
              </button>
            </div>

            <div class="d-flex align-items-center gap-1">
              <button class="btn-tool" @click="toggleStar(selectedMessage)" :title="selectedMessage.isStarred ? 'Unstar' : 'Star'">
                <i class="material-symbols-rounded text-sm" :class="selectedMessage.isStarred ? 'text-warning fill-star' : ''">
                  {{ selectedMessage.isStarred ? 'star' : 'star_border' }}
                </i>
              </button>
              <button class="btn-tool" @click="markAsUnread(selectedMessage)" title="Mark as unread">
                <i class="material-symbols-rounded text-sm">mark_email_unread</i>
              </button>
              <button class="btn-tool text-danger" @click="deleteSingleMessage(selectedMessage)" title="Delete">
                <i class="material-symbols-rounded text-sm">delete</i>
              </button>
              <button class="btn-tool" @click="printEmail" title="Print message">
                <i class="material-symbols-rounded text-sm">print</i>
              </button>
            </div>
          </div>

          <!-- Message Header Details -->
          <div class="message-header-card p-3 border-bottom">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
              <h4 class="email-subject-title text-dark font-weight-bolder mb-0">
                {{ selectedMessage.subject || '(No Subject)' }}
              </h4>
              <span class="badge badge-sm bg-light text-dark font-monospace flex-shrink-0">
                {{ selectedMessage.folder }}
              </span>
            </div>

            <div class="d-flex align-items-center gap-3">
              <div class="reader-avatar" :style="{ backgroundColor: getAvatarColor(selectedMessage.from.name || selectedMessage.from.address) }">
                {{ getInitials(selectedMessage.from.name || selectedMessage.from.address) }}
              </div>
              <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-baseline justify-content-between">
                  <div class="d-flex align-items-center gap-1.5">
                    <span class="sender-name-bold">{{ selectedMessage.from.name || selectedMessage.from.address }}</span>
                    <span v-if="selectedMessage.from.name" class="text-xs text-muted">&lt;{{ selectedMessage.from.address }}&gt;</span>
                  </div>
                  <span class="text-xxs text-muted">{{ selectedMessage.dateFormatted }}</span>
                </div>
                <div class="text-xxs text-muted d-flex align-items-center gap-1 mt-0.5">
                  <span>To: </span>
                  <span v-for="(rec, i) in selectedMessage.to" :key="i" class="rec-pill">
                    {{ rec.name || rec.address }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Attachments Bar if any -->
          <div v-if="selectedMessage.attachments && selectedMessage.attachments.length > 0" class="attachments-strip p-3 border-bottom bg-light-subtle">
            <div class="text-xs font-weight-bold text-dark mb-2 d-flex align-items-center gap-1">
              <i class="material-symbols-rounded text-sm">attach_file</i>
              <span>Attachments ({{ selectedMessage.attachments.length }})</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <div 
                v-for="(att, idx) in selectedMessage.attachments" 
                :key="idx" 
                class="attachment-card shadow-sm d-flex align-items-center gap-2"
                @click="downloadAttachment(selectedMessage.id, idx)"
              >
                <i class="material-symbols-rounded text-primary text-lg">{{ getFileIcon(att.mime, att.filename) }}</i>
                <div class="d-flex flex-column text-start">
                  <span class="attachment-name text-truncate" style="max-width: 150px;">{{ att.filename }}</span>
                  <span class="attachment-size">{{ att.sizeFormatted }}</span>
                </div>
                <i class="material-symbols-rounded text-muted text-xs ms-1">download</i>
              </div>
            </div>
          </div>

          <!-- Message Body Reader Frame -->
          <div class="message-body-wrapper flex-grow-1 p-3 p-md-4">
            <div v-if="selectedMessage.bodyHtml" class="email-html-content" v-html="selectedMessage.bodyHtml"></div>
            <div v-else class="email-text-content">{{ selectedMessage.bodyText }}</div>
          </div>

          <!-- Quick Reply Box at bottom -->
          <div class="quick-reply-box p-3 border-top bg-white">
            <div v-if="!showQuickReply" class="quick-reply-trigger" @click="showQuickReply = true">
              <i class="material-symbols-rounded text-muted text-sm me-2">reply</i>
              <span class="text-xs text-muted">Click here to reply to {{ selectedMessage.from.name || selectedMessage.from.address }}...</span>
            </div>
            <div v-else class="quick-reply-form">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-xs font-weight-bold text-dark">Quick Reply to {{ selectedMessage.from.address }}</span>
                <button class="btn btn-link btn-sm p-0 text-muted" @click="showQuickReply = false">Cancel</button>
              </div>
              <textarea 
                v-model="quickReplyBody" 
                rows="3" 
                class="form-control text-sm mb-2" 
                placeholder="Type your reply here..."
              ></textarea>
              <div class="d-flex justify-content-end gap-2">
                <button 
                  class="btn bg-gradient-primary btn-sm mb-0 d-flex align-items-center gap-1"
                  :disabled="sendingMail || !quickReplyBody.trim()"
                  @click="sendQuickReply"
                >
                  <span v-if="sendingMail" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="material-symbols-rounded text-sm">send</i>
                  <span>Send Reply</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- COMPOSE EMAIL MODAL / DRAWER -->
    <div v-if="isComposeOpen" class="compose-modal-backdrop">
      <div class="compose-window shadow-2xl">
        <!-- Header -->
        <div class="compose-header d-flex align-items-center justify-content-between px-3 py-2">
          <span class="compose-title text-sm font-weight-bold text-white d-flex align-items-center gap-1.5">
            <i class="material-symbols-rounded text-sm">edit_note</i>
            {{ composeData.isReply ? 'Reply Message' : (composeData.isForward ? 'Forward Message' : 'New Message') }}
          </span>
          <div class="d-flex align-items-center gap-1">
            <button class="btn-compose-ctrl" @click="isComposeOpen = false" title="Close">
              <i class="material-symbols-rounded text-sm">close</i>
            </button>
          </div>
        </div>

        <!-- Form Body -->
        <div class="compose-body p-3 d-flex flex-column flex-grow-1">
          <!-- Compose Error Alert -->
          <div v-if="composeError" class="alert alert-danger text-white text-xs mb-3 py-2 px-3 rounded-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-1.5">
              <i class="material-symbols-rounded text-sm">error</i>
              <span>{{ composeError }}</span>
            </div>
            <button type="button" class="btn-close btn-close-white text-xs" @click="composeError = ''"></button>
          </div>

          <!-- From Pill -->
          <div class="compose-row mb-2">
            <span class="compose-label">From:</span>
            <span class="font-monospace text-xs text-dark font-weight-bold">{{ activeEmail }}</span>
          </div>

          <!-- To field -->
          <div class="compose-row mb-2">
            <span class="compose-label">To:</span>
            <input 
              type="text" 
              v-model="composeData.to" 
              placeholder="recipient@example.com (comma separated)" 
              class="form-control compose-input"
            />
            <button 
              type="button" 
              class="btn-cc-toggle" 
              @click="showCcFields = !showCcFields"
            >
              {{ showCcFields ? 'Hide CC' : 'CC/BCC' }}
            </button>
          </div>

          <!-- CC / BCC (Togglable) -->
          <div v-if="showCcFields" class="cc-fields-group mb-2">
            <div class="compose-row mb-1">
              <span class="compose-label">CC:</span>
              <input type="text" v-model="composeData.cc" placeholder="cc@example.com" class="form-control compose-input" />
            </div>
            <div class="compose-row">
              <span class="compose-label">BCC:</span>
              <input type="text" v-model="composeData.bcc" placeholder="bcc@example.com" class="form-control compose-input" />
            </div>
          </div>

          <!-- Subject field -->
          <div class="compose-row mb-3">
            <span class="compose-label">Subject:</span>
            <input 
              type="text" 
              v-model="composeData.subject" 
              placeholder="Subject" 
              class="form-control compose-input font-weight-bold"
            />
          </div>

          <!-- Message Body Editor -->
          <div class="compose-editor-area flex-grow-1 d-flex flex-column">
            <textarea 
              v-model="composeData.bodyText" 
              class="form-control compose-textarea flex-grow-1" 
              placeholder="Write your email here..."
            ></textarea>
          </div>

          <!-- Attachments list -->
          <div v-if="composeAttachments.length > 0" class="compose-attachments-list mt-2 d-flex flex-wrap gap-2">
            <div v-for="(file, i) in composeAttachments" :key="i" class="compose-att-pill">
              <i class="material-symbols-rounded text-xs me-1">attach_file</i>
              <span class="text-truncate" style="max-width: 140px;">{{ file.name }}</span>
              <button class="btn-remove-att ms-1" @click="removeAttachment(i)">
                <i class="material-symbols-rounded text-xxs">close</i>
              </button>
            </div>
          </div>
        </div>

        <!-- Footer / Actions -->
        <div class="compose-footer p-3 border-top d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <button 
              class="btn bg-gradient-primary btn-sm mb-0 d-flex align-items-center gap-1.5 px-3 py-2" 
              :disabled="sendingMail || !composeData.to" 
              @click="sendComposedEmail"
            >
              <span v-if="sendingMail" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="material-symbols-rounded text-sm">send</i>
              <span>Send Message</span>
            </button>

            <!-- Save as Draft Button -->
            <button 
              class="btn btn-outline-secondary btn-sm mb-0 d-flex align-items-center gap-1.5 px-3 py-2" 
              :disabled="savingDraft || (!composeData.bodyText && !composeData.subject && !composeData.to)" 
              @click="saveCurrentDraft"
              title="Save as Draft (Ctrl+S)"
            >
              <span v-if="savingDraft" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="material-symbols-rounded text-sm">save</i>
              <span>Save Draft</span>
            </button>

            <!-- File Upload Input -->
            <label class="btn btn-outline-secondary btn-sm mb-0 d-flex align-items-center gap-1 cursor-pointer">
              <i class="material-symbols-rounded text-sm">attach_file</i>
              <span class="d-none d-sm-inline">Attach</span>
              <input type="file" multiple class="d-none" @change="handleFileUpload" />
            </label>
          </div>

          <button class="btn btn-link text-secondary btn-sm mb-0" @click="discardCompose">
            <i class="material-symbols-rounded text-sm me-1">delete</i> Discard
          </button>
        </div>
      </div>
    </div>

    <!-- Session Inactivity Warning Modal -->
    <div v-if="showTimeoutWarning" class="timeout-warning-backdrop">
      <div class="timeout-warning-card shadow-2xl p-4 text-center">
        <div class="timeout-icon-circle mx-auto mb-3">
          <i class="material-symbols-rounded text-warning" style="font-size: 36px;">schedule</i>
        </div>
        <h5 class="text-dark font-weight-bold mb-1">Session Inactivity Warning</h5>
        <p class="text-xs text-muted mb-3">
          You have been inactive for a while. For your security, your session will automatically expire in 
          <span class="font-weight-bold text-danger">{{ timeoutCountdown }}s</span>.
        </p>
        <div class="d-flex justify-content-center gap-2">
          <button class="btn bg-gradient-primary btn-sm mb-0 px-4" @click="resetActivityTimer">
            <i class="material-symbols-rounded text-sm me-1">lock_open</i> Stay Logged In
          </button>
          <button class="btn btn-outline-secondary btn-sm mb-0" @click="logout">
            Sign Out
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1099; pointer-events: none;">
      <div 
        v-if="toast.show" 
        class="toast show align-items-center border-0 shadow-lg" 
        :class="toast.type === 'success' ? 'bg-gradient-success text-white' : 'bg-gradient-danger text-white'"
        role="alert"
        style="pointer-events: auto; min-width: 280px; border-radius: 10px;"
      >
        <div class="d-flex align-items-center justify-content-between p-2">
          <div class="d-flex align-items-center gap-2 py-1 px-2">
            <i class="material-symbols-rounded text-base">{{ toast.type === 'success' ? 'check_circle' : 'error' }}</i>
            <span class="text-xs font-weight-bold">{{ toast.message }}</span>
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toast.show = false"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  currentAccount: Object,
  accounts: Array,
  isNimbusUser: Boolean,
  initialFolders: Array,
  sessionTimeoutMinutes: {
    type: Number,
    default: 30
  }
});

// State
const isDarkMode = ref(false);
const isSidebarOpen = ref(false);
const isAccountDropdownOpen = ref(false);
const isMoveDropdownOpen = ref(false);
const isMobileView = ref(window.innerWidth < 768);

const activeEmail = ref(props.currentAccount?.email || '');
const activeEmailDomain = computed(() => {
  return activeEmail.value.includes('@') ? activeEmail.value.split('@')[1] : '';
});

const folders = ref(props.initialFolders || []);
const currentFolder = ref('INBOX');
const activeFilter = ref(null);
const searchQuery = ref('');
let searchTimer = null;

const messages = ref([]);
const loadingMessages = ref(false);
const selectedMessageIds = ref([]);
const selectedMessage = ref(null);
const loadingMessageDetails = ref(false);

const pagination = ref({
  page: 1,
  perPage: 25,
  total: 0,
  lastPage: 1
});

// Quick Reply
const showQuickReply = ref(false);
const quickReplyBody = ref('');

// Compose Modal
const isComposeOpen = ref(false);
const showCcFields = ref(false);
const sendingMail = ref(false);
const savingDraft = ref(false);
const composeError = ref('');
const composeAttachments = ref([]);
const composeData = ref({
  to: '',
  cc: '',
  bcc: '',
  subject: '',
  bodyText: '',
  draftId: null,
  isReply: false,
  isForward: false
});

// Toast Notifications
const toast = ref({
  show: false,
  message: '',
  type: 'success'
});
let toastTimer = null;

const notify = (message, type = 'success') => {
  clearTimeout(toastTimer);
  toast.value = { show: true, message, type };
  toastTimer = setTimeout(() => {
    toast.value.show = false;
  }, 4000);
};

// Computed
const currentFolderName = computed(() => {
  const f = folders.value.find(x => x.id === currentFolder.value);
  return f ? f.name : currentFolder.value;
});

const currentFolderIcon = computed(() => {
  const f = folders.value.find(x => x.id === currentFolder.value);
  return f ? f.icon : 'inbox';
});

const isAllSelected = computed(() => {
  return messages.value.length > 0 && selectedMessageIds.value.length === messages.value.length;
});

const isIndeterminate = computed(() => {
  return selectedMessageIds.value.length > 0 && selectedMessageIds.value.length < messages.value.length;
});

const paginationText = computed(() => {
  if (pagination.value.total === 0) return '0 of 0';
  const start = (pagination.value.page - 1) * pagination.value.perPage + 1;
  const end = Math.min(pagination.value.page * pagination.value.perPage, pagination.value.total);
  return `${start}-${end} of ${pagination.value.total}`;
});

// Theme Toggle
const toggleTheme = () => {
  isDarkMode.value = !isDarkMode.value;
  localStorage.setItem('nimbus_webmail_theme', isDarkMode.value ? 'dark' : 'light');
};

const checkTheme = () => {
  const saved = localStorage.getItem('nimbus_webmail_theme');
  if (saved) {
    isDarkMode.value = saved === 'dark';
  } else {
    // Sync with Nimbus panel body dark-version class if present
    isDarkMode.value = document.body.classList.contains('dark-version');
  }
};

// Window resize handler
const handleResize = () => {
  isMobileView.value = window.innerWidth < 768;
};

// Load Messages
const loadMessages = async (page = 1) => {
  try {
    loadingMessages.value = true;
    selectedMessageIds.value = [];
    
    const params = {
      folder: currentFolder.value,
      page,
      perPage: pagination.value.perPage
    };

    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (activeFilter.value) {
      params.filter = activeFilter.value;
    }

    const res = await axios.get('/webmail/api/messages', { params });
    messages.value = res.data.messages || [];
    pagination.value.page = res.data.page || 1;
    pagination.value.total = res.data.total || 0;
    pagination.value.lastPage = res.data.lastPage || 1;
  } catch (err) {
    console.error('Failed to load messages', err);
  } finally {
    loadingMessages.value = false;
  }
};

const loadFolders = async () => {
  try {
    const res = await axios.get('/webmail/api/folders');
    folders.value = res.data.folders || [];
  } catch (err) {
    console.error('Failed to load folders', err);
  }
};

const selectFolder = (folderId) => {
  currentFolder.value = folderId;
  selectedMessage.value = null;
  activeFilter.value = null;
  searchQuery.value = '';
  isSidebarOpen.value = false;
  loadMessages(1);
};

const setFilter = (filter) => {
  activeFilter.value = filter;
  loadMessages(1);
};

const debounceSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    loadMessages(1);
  }, 350);
};

const clearSearch = () => {
  searchQuery.value = '';
  loadMessages(1);
};

const refreshCurrentFolder = async () => {
  await Promise.all([loadFolders(), loadMessages(pagination.value.page)]);
};

const changePage = (newPage) => {
  if (newPage >= 1 && newPage <= pagination.value.lastPage) {
    loadMessages(newPage);
  }
};

// Open Single Message
const openMessage = async (msg) => {
  try {
    loadingMessageDetails.value = true;
    showQuickReply.value = false;
    quickReplyBody.value = '';
    
    const res = await axios.get('/webmail/api/message', {
      params: { id: msg.id }
    });
    const message = res.data.message;

    // If opened from Drafts folder or is a draft, open directly in Compose Editor!
    if (currentFolder.value === 'Drafts' || msg.isDraft || msg.folder === 'Drafts') {
      loadingMessageDetails.value = false;
      openComposeModal({
        to: message.to?.map(x => x.address).join(', ') || '',
        cc: message.cc?.map(x => x.address).join(', ') || '',
        bcc: message.bcc?.map(x => x.address).join(', ') || '',
        subject: message.subject || '',
        bodyText: message.bodyText || '',
        draftId: msg.id
      });
      return;
    }

    selectedMessage.value = message;
    msg.isRead = true;
    
    // Decrement unread in folder counter locally
    const f = folders.value.find(x => x.id === currentFolder.value);
    if (f && f.unread > 0) f.unread--;
  } catch (err) {
    console.error('Failed to open message', err);
  } finally {
    loadingMessageDetails.value = false;
  }
};

// Selection
const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedMessageIds.value = messages.value.map(m => m.id);
  } else {
    selectedMessageIds.value = [];
  }
};

// Flags & Star
const toggleStar = async (msg) => {
  msg.isStarred = !msg.isStarred;
  try {
    await axios.post('/webmail/api/flags', {
      messageIds: [msg.id],
      flags: { starred: msg.isStarred }
    });
  } catch (err) {
    msg.isStarred = !msg.isStarred; // revert
  }
};

const bulkMarkRead = async (isRead) => {
  if (selectedMessageIds.value.length === 0) return;
  try {
    await axios.post('/webmail/api/flags', {
      messageIds: selectedMessageIds.value,
      flags: { read: isRead }
    });
    messages.value.forEach(m => {
      if (selectedMessageIds.value.includes(m.id)) {
        m.isRead = isRead;
      }
    });
    selectedMessageIds.value = [];
    loadFolders();
  } catch (err) {
    console.error(err);
  }
};

const markAsUnread = async (msg) => {
  try {
    await axios.post('/webmail/api/flags', {
      messageIds: [msg.id],
      flags: { read: false }
    });
    msg.isRead = false;
    selectedMessage.value = null;
    loadFolders();
  } catch (err) {
    console.error(err);
  }
};

const bulkStar = async () => {
  if (selectedMessageIds.value.length === 0) return;
  try {
    await axios.post('/webmail/api/flags', {
      messageIds: selectedMessageIds.value,
      flags: { starred: true }
    });
    messages.value.forEach(m => {
      if (selectedMessageIds.value.includes(m.id)) m.isStarred = true;
    });
    selectedMessageIds.value = [];
  } catch (err) {
    console.error(err);
  }
};

const bulkMoveTo = async (targetFolder) => {
  if (selectedMessageIds.value.length === 0) return;
  try {
    await axios.post('/webmail/api/move', {
      messageIds: selectedMessageIds.value,
      targetFolder
    });
    isMoveDropdownOpen.value = false;
    selectedMessageIds.value = [];
    refreshCurrentFolder();
  } catch (err) {
    console.error(err);
  }
};

const bulkDelete = async () => {
  if (selectedMessageIds.value.length === 0) return;
  try {
    await axios.post('/webmail/api/delete', {
      messageIds: selectedMessageIds.value
    });
    selectedMessageIds.value = [];
    refreshCurrentFolder();
  } catch (err) {
    console.error(err);
  }
};

const deleteSingleMessage = async (msg) => {
  try {
    await axios.post('/webmail/api/delete', {
      messageIds: [msg.id]
    });
    selectedMessage.value = null;
    refreshCurrentFolder();
  } catch (err) {
    console.error(err);
  }
};

// Attachments
const downloadAttachment = (messageId, index) => {
  window.open(`/webmail/api/attachment?id=${encodeURIComponent(messageId)}&index=${index}`, '_blank');
};

// Compose Actions
const openComposeModal = (prefill = {}) => {
  composeData.value = {
    to: prefill.to || '',
    cc: prefill.cc || '',
    bcc: prefill.bcc || '',
    subject: prefill.subject || '',
    bodyText: prefill.bodyText || '',
    draftId: prefill.draftId || null,
    isReply: prefill.isReply || false,
    isForward: prefill.isForward || false
  };
  composeAttachments.value = [];
  composeError.value = '';
  showCcFields.value = !!(prefill.cc || prefill.bcc);
  isComposeOpen.value = true;
};

const saveCurrentDraft = async () => {
  if (!composeData.value.bodyText && !composeData.value.subject && !composeData.value.to) {
    return;
  }

  try {
    savingDraft.value = true;
    composeError.value = '';

    const payload = {
      to: composeData.value.to,
      cc: composeData.value.cc,
      bcc: composeData.value.bcc,
      subject: composeData.value.subject || '(No Subject)',
      bodyText: composeData.value.bodyText,
      bodyHtml: composeData.value.bodyText ? composeData.value.bodyText.replace(/\n/g, '<br>') : '',
      draftId: composeData.value.draftId || null
    };

    const res = await axios.post('/webmail/api/draft', payload);

    if (res.data.success) {
      notify('Draft saved to Drafts folder', 'success');
      loadFolders();
      if (currentFolder.value === 'Drafts') {
        loadMessages(1);
      }
    } else {
      const errorMsg = res.data.error || 'Failed to save draft.';
      composeError.value = errorMsg;
      notify(errorMsg, 'danger');
    }
  } catch (err) {
    const errorMsg = err.response?.data?.error || err.message || 'Something went wrong while saving draft.';
    composeError.value = errorMsg;
    notify(errorMsg, 'danger');
  } finally {
    savingDraft.value = false;
  }
};

const replyToMessage = (isReplyAll = false) => {
  if (!selectedMessage.value) return;
  const msg = selectedMessage.value;
  const replyTo = msg.replyTo?.address || msg.from?.address || '';
  
  let ccList = '';
  if (isReplyAll && msg.to) {
    ccList = msg.to
      .map(x => x.address)
      .filter(addr => addr && addr !== activeEmail.value && addr !== replyTo)
      .join(', ');
  }

  const origDate = msg.dateFormatted || '';
  const origFrom = msg.from.name ? `${msg.from.name} <${msg.from.address}>` : msg.from.address;
  const quote = `\n\n\n--- On ${origDate}, ${origFrom} wrote: ---\n` + (msg.bodyText || '');

  openComposeModal({
    to: replyTo,
    cc: ccList,
    subject: msg.subject.startsWith('Re:') ? msg.subject : `Re: ${msg.subject}`,
    bodyText: quote,
    isReply: true
  });
};

const forwardMessage = () => {
  if (!selectedMessage.value) return;
  const msg = selectedMessage.value;
  const origDate = msg.dateFormatted || '';
  const origFrom = msg.from.name ? `${msg.from.name} <${msg.from.address}>` : msg.from.address;
  const quote = `\n\n\n---------- Forwarded message ---------\nFrom: ${origFrom}\nDate: ${origDate}\nSubject: ${msg.subject}\nTo: ${msg.to?.map(x => x.address).join(', ')}\n\n` + (msg.bodyText || '');

  openComposeModal({
    to: '',
    subject: msg.subject.startsWith('Fwd:') ? msg.subject : `Fwd: ${msg.subject}`,
    bodyText: quote,
    isForward: true
  });
};

const handleFileUpload = (e) => {
  const files = Array.from(e.target.files || []);
  composeAttachments.value.push(...files);
};

const removeAttachment = (index) => {
  composeAttachments.value.splice(index, 1);
};

const sendComposedEmail = async () => {
  if (!composeData.value.to) return;
  try {
    sendingMail.value = true;
    composeError.value = '';
    const formData = new FormData();
    formData.append('to', composeData.value.to);
    if (composeData.value.cc) formData.append('cc', composeData.value.cc);
    if (composeData.value.bcc) formData.append('bcc', composeData.value.bcc);
    formData.append('subject', composeData.value.subject);
    formData.append('bodyText', composeData.value.bodyText);
    formData.append('bodyHtml', composeData.value.bodyText.replace(/\n/g, '<br>'));
    if (composeData.value.draftId) {
      formData.append('draftId', composeData.value.draftId);
    }

    composeAttachments.value.forEach(file => {
      formData.append('attachments[]', file);
    });

    const res = await axios.post('/webmail/api/send', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    if (res.data.success) {
      isComposeOpen.value = false;
      notify('Email sent successfully!', 'success');
      loadFolders();
      if (currentFolder.value === 'Sent' || currentFolder.value === 'Drafts') {
        loadMessages(1);
      }
    } else {
      const errorMsg = res.data.error || 'Failed to send email. Please check your settings.';
      composeError.value = errorMsg;
      notify(errorMsg, 'danger');
    }
  } catch (err) {
    const errorMsg = err.response?.data?.error || err.message || 'Something went wrong while sending email.';
    composeError.value = errorMsg;
    notify(errorMsg, 'danger');
  } finally {
    sendingMail.value = false;
  }
};

const discardCompose = async () => {
  if (composeData.value.draftId) {
    try {
      await axios.post('/webmail/api/delete', {
        messageIds: [composeData.value.draftId],
        permanent: true
      });
      notify('Draft discarded', 'success');
      loadFolders();
      if (currentFolder.value === 'Drafts') loadMessages(1);
    } catch (e) {
      console.error(e);
    }
  }
  isComposeOpen.value = false;
  composeError.value = '';
};

// Quick Reply send
const sendQuickReply = async () => {
  if (!selectedMessage.value || !quickReplyBody.value.trim()) return;
  try {
    sendingMail.value = true;
    const msg = selectedMessage.value;
    const replyTo = msg.replyTo?.address || msg.from?.address || '';

    const payload = {
      to: replyTo,
      subject: msg.subject.startsWith('Re:') ? msg.subject : `Re: ${msg.subject}`,
      bodyText: quickReplyBody.value,
      bodyHtml: quickReplyBody.value.replace(/\n/g, '<br>')
    };

    const res = await axios.post('/webmail/api/send', payload);
    if (res.data.success) {
      quickReplyBody.value = '';
      showQuickReply.value = false;
      notify('Reply sent successfully!', 'success');
      loadFolders();
    } else {
      notify(res.data.error || 'Failed to send reply.', 'danger');
    }
  } catch (err) {
    notify(err.response?.data?.error || err.message || 'Something went wrong while sending reply.', 'danger');
  } finally {
    sendingMail.value = false;
  }
};

// Mailbox switching
const switchMailbox = async (email) => {
  try {
    isAccountDropdownOpen.value = false;
    await axios.post('/webmail/switch-account', { email });
    activeEmail.value = email;
    selectedMessage.value = null;
    currentFolder.value = 'INBOX';
    refreshCurrentFolder();
  } catch (err) {
    console.error('Failed to switch mailbox', err);
  }
};

// Inactivity Session Timeout
const timeoutMinutes = computed(() => props.sessionTimeoutMinutes || 30);
const showTimeoutWarning = ref(false);
const timeoutCountdown = ref(60);
let activityTimeout = null;
let countdownInterval = null;
let lastKeepAlive = Date.now();
let axiosInterceptor = null;

const resetActivityTimer = () => {
  showTimeoutWarning.value = false;
  clearInterval(countdownInterval);
  clearTimeout(activityTimeout);

  const warnAfterMs = Math.max(10000, (timeoutMinutes.value * 60 - 60) * 1000);
  
  activityTimeout = setTimeout(() => {
    showTimeoutWarning.value = true;
    timeoutCountdown.value = 60;
    countdownInterval = setInterval(() => {
      timeoutCountdown.value--;
      if (timeoutCountdown.value <= 0) {
        clearInterval(countdownInterval);
        logoutDueToTimeout();
      }
    }, 1000);
  }, warnAfterMs);

  // Keep backend session alive on user action (at most once every 3 minutes)
  if (Date.now() - lastKeepAlive > 180000) {
    lastKeepAlive = Date.now();
    axios.post('/webmail/api/keep-alive').catch(() => {});
  }
};

const logoutDueToTimeout = () => {
  window.location.href = '/webmail/login?timeout=1';
};

const activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
const handleUserInteraction = () => {
  if (!showTimeoutWarning.value) {
    resetActivityTimer();
  }
};

// Logout
const logout = async () => {
  try {
    await axios.post('/webmail/logout');
    window.location.href = props.isNimbusUser ? '/email' : '/webmail/login';
  } catch (err) {
    window.location.href = '/webmail/login';
  }
};

const printEmail = () => {
  window.print();
};

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// Visual Helpers
const getInitials = (str) => {
  if (!str) return '?';
  const clean = str.replace(/<[^>]+>/g, '').trim();
  const parts = clean.split(/[\s@._-]+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return clean.substring(0, 2).toUpperCase();
};

const getAvatarColor = (str) => {
  if (!str) return '#4f46e5';
  const colors = ['#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#009688', '#4caf50', '#ff9800', '#795548', '#607d8b'];
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }
  const index = Math.abs(hash) % colors.length;
  return colors[index];
};

const getFileIcon = (mime, filename) => {
  const m = (mime || '').toLowerCase();
  const f = (filename || '').toLowerCase();
  if (m.includes('image') || f.endsWith('.png') || f.endsWith('.jpg') || f.endsWith('.jpeg')) return 'image';
  if (m.includes('pdf') || f.endsWith('.pdf')) return 'picture_as_pdf';
  if (m.includes('zip') || m.includes('tar') || m.includes('rar') || f.endsWith('.zip')) return 'folder_zip';
  if (m.includes('word') || f.endsWith('.doc') || f.endsWith('.docx')) return 'description';
  if (m.includes('sheet') || f.endsWith('.xls') || f.endsWith('.xlsx') || f.endsWith('.csv')) return 'table_chart';
  return 'draft';
};

onMounted(() => {
  checkTheme();
  window.addEventListener('resize', handleResize);
  loadMessages(1);

  // Setup user activity listeners
  activityEvents.forEach(evt => window.addEventListener(evt, handleUserInteraction, { passive: true }));
  resetActivityTimer();

  // Setup Axios 401 interceptor
  axiosInterceptor = axios.interceptors.response.use(
    response => response,
    error => {
      if (error.response && error.response.status === 401 && error.response.data?.timeout) {
        logoutDueToTimeout();
      }
      return Promise.reject(error);
    }
  );
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  clearTimeout(searchTimer);
  activityEvents.forEach(evt => window.removeEventListener(evt, handleUserInteraction));
  clearTimeout(activityTimeout);
  clearInterval(countdownInterval);
  if (axiosInterceptor !== null) {
    axios.interceptors.response.eject(axiosInterceptor);
  }
});
</script>

<style scoped>
/* ───────────────────────────────────────────────────────────── */
/* Nimbus Webmail Styling System (Light & Dark Support)          */
/* ───────────────────────────────────────────────────────────── */

.webmail-wrapper {
  --wm-bg: #f8f9fa;
  --wm-surface: #ffffff;
  --wm-surface-subtle: #f1f3f5;
  --wm-border: #e9ecef;
  --wm-text: #212529;
  --wm-text-muted: #6c757d;
  --wm-active-row: #eef2ff;
  --wm-primary: #e91e63;
  --wm-primary-grad: linear-gradient(195deg, #ec407a 0%, #d81b60 100%);
  --wm-header-grad: linear-gradient(195deg, #42424a 0%, #191919 100%);

  display: flex;
  flex-direction: column;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
  background-color: var(--wm-bg);
  color: var(--wm-text);
  font-family: 'Inter', system-ui, sans-serif;
}

/* Dark Theme Overrides */
.webmail-wrapper.dark-theme {
  --wm-bg: #111827;
  --wm-surface: #1f2937;
  --wm-surface-subtle: #374151;
  --wm-border: #374151;
  --wm-text: #f9fafb;
  --wm-text-muted: #9ca3af;
  --wm-active-row: #283548;
}

/* Topbar */
.webmail-topbar {
  height: 60px;
  background-color: var(--wm-surface);
  border-bottom: 1px solid var(--wm-border);
  flex-shrink: 0;
  z-index: 100;
}

.topbar-logo {
  width: 32px;
  height: 32px;
  border-radius: 6px;
}

.brand-text {
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--wm-text);
}

.brand-badge {
  font-size: 0.65rem;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--wm-primary-grad);
  color: white;
  font-weight: 800;
  letter-spacing: 0.05em;
}

/* Search Bar */
.search-bar-wrapper {
  max-width: 600px;
}

.search-input-group {
  display: flex;
  align-items: center;
  position: relative;
  background-color: var(--wm-surface-subtle);
  border: 1px solid var(--wm-border);
  border-radius: 20px;
  padding: 4px 12px 4px 14px;
  transition: all 0.2s ease;
}

.search-input-group:focus-within {
  background-color: var(--wm-surface);
  border-color: var(--wm-primary);
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.15);
}

.search-icon {
  color: var(--wm-text-muted);
  font-size: 20px;
  margin-right: 8px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.search-control {
  width: 100%;
  height: 32px;
  border: none !important;
  background: transparent !important;
  color: var(--wm-text) !important;
  font-size: 0.875rem !important;
  outline: none !important;
  padding: 0 !important;
  box-shadow: none !important;
}

.search-control::placeholder {
  color: var(--wm-text-muted);
  opacity: 0.8;
}

.btn-clear-search {
  border: none;
  background: transparent;
  color: var(--wm-text-muted);
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-left: 6px;
}

.btn-clear-search:hover {
  color: var(--wm-text);
}

/* Theme Toggle & Controls */
.theme-toggle-btn, .btn-icon-top {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1px solid var(--wm-border);
  background-color: var(--wm-surface);
  color: var(--wm-text);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.15s;
}

.theme-toggle-btn:hover, .btn-icon-top:hover {
  background-color: var(--wm-surface-subtle);
}

.is-spinning i {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

/* Account Pill */
.account-pill-btn {
  border: 1px solid var(--wm-border);
  background-color: var(--wm-surface);
  color: var(--wm-text);
  border-radius: 24px;
  padding: 4px 10px 4px 4px;
  cursor: pointer;
}

.user-avatar-sm, .user-avatar-xs {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--wm-primary-grad);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
}

.user-avatar-xs {
  width: 24px;
  height: 24px;
  font-size: 0.65rem;
}

.user-email-text {
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

.user-domain-text {
  font-size: 0.65rem;
  color: var(--wm-text-muted);
  line-height: 1;
}

/* Account Menu Dropdown */
.account-menu-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: 260px;
  background-color: var(--wm-surface);
  border: 1px solid var(--wm-border);
  border-radius: 12px;
  padding: 6px 0;
  z-index: 1000;
}

.dropdown-header-custom {
  padding: 6px 14px;
}

.account-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 14px;
  border: none;
  background: transparent;
  color: var(--wm-text);
  cursor: pointer;
  transition: background 0.15s;
}

.account-menu-item:hover, .account-menu-item.active {
  background-color: var(--wm-surface-subtle);
}

.dropdown-sublink {
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  color: var(--wm-text);
  text-decoration: none;
  display: flex;
  align-items: center;
}

.dropdown-sublink:hover {
  background-color: var(--wm-surface-subtle);
}

/* ───────────────────────────────────────────────────────────── */
/* Main Layout                                                   */
/* ───────────────────────────────────────────────────────────── */
.webmail-main-layout {
  display: flex;
  flex-grow: 1;
  overflow: hidden;
}

/* 1. Folders Sidebar */
.webmail-sidebar {
  width: 240px;
  background-color: var(--wm-surface);
  border-right: 1px solid var(--wm-border);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  transition: transform 0.25s ease;
}

@media (max-width: 991.98px) {
  .webmail-sidebar {
    position: fixed;
    top: 60px;
    bottom: 0;
    left: 0;
    z-index: 1000;
    transform: translateX(-100%);
  }
  .webmail-sidebar.sidebar-open {
    transform: translateX(0);
  }
  .sidebar-backdrop {
    position: fixed;
    inset: 60px 0 0 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 999;
  }
}

.btn-compose-mail {
  background: var(--wm-primary-grad);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 10px 16px;
  font-weight: 600;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  transition: transform 0.15s;
}

.btn-compose-mail:hover {
  transform: translateY(-1px);
}

.folder-group-title {
  padding: 6px 18px;
  font-size: 0.65rem;
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: var(--wm-text-muted);
}

.folder-list {
  list-style: none;
  padding: 0 8px;
  margin: 0;
}

.folder-nav-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 8px;
  border: none;
  background: transparent;
  color: var(--wm-text);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
}

.folder-nav-btn:hover {
  background-color: var(--wm-surface-subtle);
}

.folder-nav-btn.active {
  background-color: var(--wm-active-row);
  color: var(--wm-primary);
  font-weight: 700;
}

.folder-icon {
  font-size: 20px;
  opacity: 0.8;
}

.folder-name {
  flex-grow: 1;
  text-align: left;
}

.badge-unread {
  background-color: var(--wm-primary);
  color: white;
  font-size: 0.7rem;
  font-weight: 700;
  border-radius: 12px;
  padding: 2px 7px;
}

.badge-total {
  font-size: 0.7rem;
  color: var(--wm-text-muted);
}

.quota-progress {
  height: 5px;
  background-color: var(--wm-surface-subtle);
  border-radius: 4px;
}

/* 2. Messages List Pane */
.messages-list-pane {
  width: 380px;
  background-color: var(--wm-surface);
  border-right: 1px solid var(--wm-border);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

@media (max-width: 767.98px) {
  .messages-list-pane {
    width: 100%;
  }
}

.list-pane-toolbar {
  background-color: var(--wm-surface);
  min-height: 48px;
}

.filter-chip {
  padding: 3px 10px;
  border-radius: 16px;
  font-size: 0.725rem;
  font-weight: 600;
  border: 1px solid var(--wm-border);
  background: transparent;
  color: var(--wm-text-muted);
  cursor: pointer;
  transition: all 0.15s;
}

.filter-chip.active {
  background: var(--wm-text);
  color: var(--wm-surface);
  border-color: var(--wm-text);
}

.btn-tool, .btn-pager {
  border: none;
  background: transparent;
  color: var(--wm-text-muted);
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.btn-tool:hover, .btn-pager:hover {
  background-color: var(--wm-surface-subtle);
  color: var(--wm-text);
}

.messages-scroll-area {
  overflow-y: auto;
}

.message-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--wm-border);
  cursor: pointer;
  transition: background 0.12s;
}

.message-row:hover {
  background-color: var(--wm-surface-subtle);
}

.message-row.is-active {
  background-color: var(--wm-active-row);
  border-left: 3px solid var(--wm-primary);
}

.message-row.is-unread {
  font-weight: 700;
}

.message-row.is-unread .msg-sender-name {
  color: var(--wm-text);
  font-weight: 700;
}

.message-row.is-unread .msg-subject-text {
  color: var(--wm-text);
  font-weight: 700;
}

.msg-star-btn {
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
}

.fill-star {
  font-variation-settings: 'FILL' 1;
}

.msg-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

.msg-sender-name {
  font-size: 0.8125rem;
  color: var(--wm-text);
  max-width: 170px;
}

.msg-subject-text {
  font-size: 0.775rem;
  color: var(--wm-text);
}

.msg-snippet-text {
  font-size: 0.75rem;
}

/* 3. Message Reader Pane */
.message-reader-pane {
  background-color: var(--wm-surface);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.reader-toolbar {
  min-height: 48px;
  background-color: var(--wm-surface);
}

.btn-tool-action {
  border: 1px solid var(--wm-border);
  background-color: var(--wm-surface);
  color: var(--wm-text);
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-tool-action:hover {
  background-color: var(--wm-surface-subtle);
}

.reader-empty-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background-color: var(--wm-surface-subtle);
  color: var(--wm-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
}

.email-subject-title {
  font-size: 1.25rem;
  line-height: 1.3;
}

.reader-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  font-weight: 700;
}

.sender-name-bold {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--wm-text);
}

.rec-pill {
  background-color: var(--wm-surface-subtle);
  padding: 1px 6px;
  border-radius: 4px;
}

/* Attachment Cards */
.attachment-card {
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid var(--wm-border);
  background-color: var(--wm-surface);
  cursor: pointer;
  transition: transform 0.15s;
}

.attachment-card:hover {
  transform: translateY(-1px);
  background-color: var(--wm-surface-subtle);
}

.attachment-name {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--wm-text);
}

.attachment-size {
  font-size: 0.65rem;
  color: var(--wm-text-muted);
}

.message-body-wrapper {
  overflow-y: auto;
  font-size: 0.9rem;
  line-height: 1.6;
}

.email-text-content {
  white-space: pre-wrap;
  font-family: inherit;
}

/* Quick Reply */
.quick-reply-trigger {
  border: 1px dashed var(--wm-border);
  border-radius: 8px;
  padding: 10px 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.quick-reply-trigger:hover {
  background-color: var(--wm-surface-subtle);
}

/* ───────────────────────────────────────────────────────────── */
/* Compose Window                                                */
/* ───────────────────────────────────────────────────────────── */
.compose-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.compose-window {
  width: 100%;
  max-width: 680px;
  height: 80vh;
  max-height: 700px;
  background-color: var(--wm-surface);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.compose-header {
  background: var(--wm-header-grad);
  flex-shrink: 0;
}

.btn-compose-ctrl {
  background: transparent;
  border: none;
  color: white;
  cursor: pointer;
  padding: 2px;
}

.compose-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.compose-label {
  font-size: 0.75rem;
  font-weight: 600;
  width: 55px;
  color: var(--wm-text-muted);
  flex-shrink: 0;
}

.compose-input {
  border: none !important;
  border-bottom: 1px solid var(--wm-border) !important;
  border-radius: 0 !important;
  padding: 4px 6px !important;
  font-size: 0.8125rem !important;
  background: transparent !important;
  color: var(--wm-text) !important;
}

.compose-input:focus {
  border-bottom-color: var(--wm-primary) !important;
  box-shadow: none !important;
}

.btn-cc-toggle {
  border: none;
  background: transparent;
  font-size: 0.7rem;
  color: var(--wm-primary);
  cursor: pointer;
  flex-shrink: 0;
}

.compose-textarea {
  border: 1px solid var(--wm-border);
  border-radius: 8px;
  resize: none;
  font-size: 0.875rem;
  padding: 10px;
  background: transparent;
  color: var(--wm-text);
}

.compose-att-pill {
  background-color: var(--wm-surface-subtle);
  border: 1px solid var(--wm-border);
  border-radius: 12px;
  padding: 3px 8px;
  font-size: 0.7rem;
  display: inline-flex;
  align-items: center;
}

.btn-remove-att {
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--wm-text-muted);
  padding: 0;
}

/* Timeout Warning Modal */
.timeout-warning-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
  padding: 16px;
}

.timeout-warning-card {
  width: 100%;
  max-width: 420px;
  background-color: var(--wm-surface);
  border-radius: 16px;
  border: 1px solid var(--wm-border);
}

.timeout-icon-circle {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background-color: rgba(255, 193, 7, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
