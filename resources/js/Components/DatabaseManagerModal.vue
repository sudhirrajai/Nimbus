<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="show" class="db-manager-overlay" @click.self="close">
        <div class="db-manager-modal glass-card shadow-2xl d-flex flex-column">

          <!-- Modal Header -->
          <div class="db-manager-header px-4 py-3 bg-gradient-dark d-flex align-items-center justify-content-between text-white border-radius-top-xl">
            <div class="d-flex align-items-center gap-3">
              <div class="icon-shape icon-md bg-gradient-info text-white text-center border-radius-xl shadow-info">
                <i class="material-symbols-rounded">database</i>
              </div>
              <div>
                <h5 class="text-white font-weight-bolder mb-0 d-flex align-items-center gap-2">
                  <span>{{ databaseName }}</span>
                  <span class="badge bg-white text-dark text-xxs font-weight-bold">{{ tables.length }} tables</span>
                </h5>
                <small class="text-white opacity-8">Native Database Manager</small>
              </div>
            </div>

            <!-- Workspace Tabs -->
            <div class="nav-tabs-wrapper bg-dark-soft p-1 border-radius-lg d-none d-md-flex align-items-center gap-1">
              <button class="nav-tab-btn" :class="{ active: activeTab === 'browse' }" @click="activeTab = 'browse'">
                <i class="material-symbols-rounded text-sm me-1">table_chart</i> Data & Schema
              </button>
              <button class="nav-tab-btn" :class="{ active: activeTab === 'sql' }" @click="activeTab = 'sql'">
                <i class="material-symbols-rounded text-sm me-1">terminal</i> SQL Console
              </button>
              <button class="nav-tab-btn" :class="{ active: activeTab === 'create_table' }" @click="activeTab = 'create_table'">
                <i class="material-symbols-rounded text-sm me-1">add_box</i> New Table
              </button>
              <button class="nav-tab-btn" :class="{ active: activeTab === 'export_import' }" @click="activeTab = 'export_import'">
                <i class="material-symbols-rounded text-sm me-1">import_export</i> Export / Import
              </button>
            </div>

            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-sm btn-icon-only btn-rounded bg-white-soft text-white mb-0" @click="loadTables" title="Refresh Database">
                <i class="material-symbols-rounded text-sm" :class="{ 'spin-animation': loadingTables }">refresh</i>
              </button>
              <button class="btn btn-sm btn-icon-only btn-rounded bg-white-soft text-white mb-0" @click="close" title="Close Workspace">
                <i class="material-symbols-rounded text-sm">close</i>
              </button>
            </div>
          </div>

          <!-- Alert Toast -->
          <transition name="fade">
            <div v-if="alert.show" :class="`alert alert-${alert.type} alert-dismissible fade show m-3 border-radius-lg shadow-sm`">
              <span class="alert-icon"><i class="material-symbols-rounded">{{ alert.type === 'success' ? 'check_circle' : 'error' }}</i></span>
              <span class="alert-text ms-2 font-weight-bold text-white">{{ alert.message }}</span>
              <button type="button" class="btn-close" @click="alert.show = false"></button>
            </div>
          </transition>

          <!-- Main Content Area -->
          <div class="db-manager-body flex-grow-1 overflow-hidden d-flex">

            <!-- Tab 1: Browse Data & Schema -->
            <template v-if="activeTab === 'browse'">
              <!-- Left Sidebar: Tables List -->
              <div class="db-tables-sidebar border-end p-3 d-flex flex-column bg-gray-50" style="width: 280px; min-width: 250px;">
                <div class="search-box mb-3">
                  <div class="input-group input-group-sm bg-white border-radius-lg overflow-hidden border shadow-sm">
                    <span class="input-group-text border-0 bg-transparent text-secondary"><i class="material-symbols-rounded text-sm">search</i></span>
                    <input v-model="tableSearchQuery" type="text" class="form-control border-0 ps-1 text-xs" placeholder="Filter tables..." />
                    <button v-if="tableSearchQuery" class="btn btn-link p-0 m-0 me-2 text-secondary" @click="tableSearchQuery = ''">
                      <i class="material-symbols-rounded text-xs">close</i>
                    </button>
                  </div>
                </div>

                <div class="tables-list-scroll flex-grow-1 overflow-y-auto pe-1">
                  <div v-if="filteredTables.length === 0" class="text-center py-4 text-muted">
                    <i class="material-symbols-rounded text-secondary opacity-5 fs-2 mb-1">search_off</i>
                    <p class="text-xs mb-0">No tables found</p>
                  </div>
                  <button v-for="t in filteredTables" :key="t.name" 
                    class="table-list-item w-100 text-start d-flex align-items-center justify-content-between p-2 border-radius-lg mb-1"
                    :class="{ active: selectedTable === t.name }"
                    @click="selectTable(t.name)">
                    <div class="d-flex align-items-center text-truncate me-2">
                      <i class="material-symbols-rounded text-sm me-2" :class="selectedTable === t.name ? 'text-primary font-weight-bold' : 'text-secondary'">table_rows</i>
                      <span class="text-xs font-weight-bold text-truncate" :title="t.name">{{ t.name }}</span>
                    </div>
                    <span class="badge badge-sm bg-gray-200 text-dark text-xxs px-2 py-1 border-radius-sm">{{ t.rows }}</span>
                  </button>
                </div>

                <div class="pt-3 border-top mt-auto text-center">
                  <button class="btn btn-xs btn-outline-primary w-100 mb-0" @click="activeTab = 'create_table'">
                    <i class="material-symbols-rounded text-xs me-1">add</i> New Table
                  </button>
                </div>
              </div>

              <!-- Right Panel: Data Grid & Schema Inspector -->
              <div class="db-main-content flex-grow-1 overflow-y-auto p-3 bg-white d-flex flex-column">
                <template v-if="selectedTable">
                  <!-- Table Sub-Header & Controls -->
                  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-2 border-bottom position-relative" style="z-index: 1050;">
                    <div class="d-flex align-items-center gap-2">
                      <h5 class="mb-0 font-weight-bolder text-dark">{{ selectedTable }}</h5>
                      <span v-if="tableSchema" class="badge bg-light text-secondary border text-xxs">{{ tableSchema.columns.length }} cols</span>
                      
                      <!-- Sub-mode toggle: Data vs Schema -->
                      <div class="btn-group btn-group-sm shadow-none ms-3">
                        <button class="btn btn-xs border-radius-lg" :class="subView === 'data' ? 'btn-primary' : 'btn-outline-secondary'" @click="subView = 'data'">
                          <i class="material-symbols-rounded text-xs me-1">dataset</i> Data Grid
                        </button>
                        <button class="btn btn-xs border-radius-lg ms-1" :class="subView === 'schema' ? 'btn-primary' : 'btn-outline-secondary'" @click="subView = 'schema'">
                          <i class="material-symbols-rounded text-xs me-1">schema</i> Structure
                        </button>
                      </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                      <button v-if="subView === 'data'" class="btn btn-sm bg-gradient-success mb-0 border-radius-lg" @click="openInsertRowModal">
                        <i class="material-symbols-rounded text-sm me-1">add_circle</i> Insert Row
                      </button>
                      <button v-if="subView === 'schema'" class="btn btn-sm bg-gradient-primary mb-0 border-radius-lg" @click="openAddColumnModal">
                        <i class="material-symbols-rounded text-sm me-1">add</i> Add Column
                      </button>
                      <button v-if="subView === 'schema'" class="btn btn-sm btn-outline-secondary mb-0 border-radius-lg" @click="openAlterTableModal">
                        <i class="material-symbols-rounded text-sm me-1">settings</i> Table Settings
                      </button>
                      <button class="btn btn-sm btn-outline-secondary mb-0 border-radius-lg" @click="loadTableData" :disabled="loadingData">
                        <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin-animation': loadingData }">refresh</i> Refresh
                      </button>
                      <div class="dropdown position-relative" style="z-index: 1051;">
                        <button class="btn btn-sm btn-dark mb-0 border-radius-lg dropdown-toggle-custom d-flex align-items-center gap-1 shadow-sm" type="button" data-bs-toggle="dropdown">
                          <i class="material-symbols-rounded text-sm">tune</i>
                          <span>Actions</span>
                          <i class="material-symbols-rounded text-xs ms-1">expand_more</i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-xl border-0 p-2 position-absolute" style="z-index: 99999 !important; min-width: 180px;">
                          <li>
                            <a class="dropdown-item text-xs border-radius-md py-2 d-flex align-items-center" href="#" @click.prevent="quickQuery(`SELECT * FROM \`${selectedTable}\` LIMIT 50`)">
                              <i class="material-symbols-rounded text-sm me-2 text-primary">terminal</i>
                              Query Table
                            </a>
                          </li>
                          <li>
                            <a class="dropdown-item text-xs border-radius-md py-2 d-flex align-items-center" href="#" @click.prevent="exportTableCsv(selectedTable)">
                              <i class="material-symbols-rounded text-sm me-2 text-info">download</i>
                              Export CSV
                            </a>
                          </li>
                          <li>
                            <a class="dropdown-item text-xs border-radius-md py-2 d-flex align-items-center" href="#" @click.prevent="exportTableSql(selectedTable)">
                              <i class="material-symbols-rounded text-sm me-2 text-info">code</i>
                              Export SQL
                            </a>
                          </li>
                          <li><hr class="dropdown-divider my-1"></li>
                          <li>
                            <a class="dropdown-item text-xs text-warning border-radius-md py-2 d-flex align-items-center" href="#" @click.prevent="confirmTruncateTable(selectedTable)">
                              <i class="material-symbols-rounded text-sm me-2 text-warning">delete_sweep</i>
                              Truncate Table
                            </a>
                          </li>
                          <li>
                            <a class="dropdown-item text-xs text-danger border-radius-md py-2 d-flex align-items-center" href="#" @click.prevent="confirmDropTable(selectedTable)">
                              <i class="material-symbols-rounded text-sm me-2 text-danger">delete_forever</i>
                              Drop Table
                            </a>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <!-- SubView 1: Data Grid -->
                  <div v-if="subView === 'data'" class="flex-grow-1 d-flex flex-column">
                    <!-- Filters & Search Toolbar -->
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                      <div class="d-flex align-items-center gap-2">
                        <div class="search-input-box bg-gray-100 px-2 py-1 border-radius-lg border d-flex align-items-center" style="width: 260px;">
                          <i class="material-symbols-rounded text-sm me-1 text-secondary">search</i>
                          <input v-model="dataSearchQuery" type="text" class="form-control border-0 bg-transparent p-0 text-xs" placeholder="Search rows..." @keyup.enter="loadTableData" />
                          <button v-if="dataSearchQuery" class="btn btn-link p-0 m-0 text-secondary" @click="dataSearchQuery = ''; loadTableData()">
                            <i class="material-symbols-rounded text-xs">close</i>
                          </button>
                        </div>
                        <select v-model="dataSearchCol" class="form-select form-select-sm text-xs border-radius-lg" style="width: 140px;" @change="loadTableData">
                          <option value="">All Columns</option>
                          <option v-for="c in tableColumns" :key="c" :value="c">{{ c }}</option>
                        </select>
                      </div>

                      <div class="d-flex align-items-center gap-3">
                        <div v-if="selectedRows.length > 0" class="d-flex align-items-center gap-2">
                          <span class="text-xs font-weight-bold text-primary">{{ selectedRows.length }} selected</span>
                          <button class="btn btn-xs btn-danger mb-0 border-radius-lg" @click="bulkDeleteRows">Delete Selected</button>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                          <span class="text-xxs text-secondary text-uppercase font-weight-bold">Rows:</span>
                          <select v-model="perPage" class="form-select form-select-sm text-xs border-radius-lg" style="width: 80px;" @change="currentPage = 1; loadTableData()">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <!-- Data Table -->
                    <div class="table-responsive flex-grow-1 border border-radius-lg overflow-y-auto position-relative" style="max-height: 450px; z-index: 1;">
                      <div v-if="loadingData" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-xs text-secondary mt-2">Loading table rows...</p>
                      </div>

                      <div v-else-if="tableRows.length === 0" class="text-center py-5 text-muted">
                        <i class="material-symbols-rounded text-secondary opacity-5 fs-1 mb-2">inbox</i>
                        <h6 class="text-dark font-weight-bold">No Records Found</h6>
                        <p class="text-xs text-secondary mb-3">This table is empty or no rows match your search query.</p>
                        <button class="btn btn-sm bg-gradient-primary border-radius-lg" @click="openInsertRowModal">Insert First Row</button>
                      </div>

                      <table v-else class="table table-hover align-items-center mb-0">
                        <thead class="bg-gray-100 sticky-top" style="z-index: 2;">
                          <tr>
                            <th style="width: 40px;" class="ps-3">
                              <input type="checkbox" class="form-check-input" :checked="allRowsSelected" @click.prevent="toggleSelectAllRows" />
                            </th>
                            <th v-for="col in tableColumns" :key="col" 
                              class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8 cursor-pointer user-select-none"
                              @click="sortBy(col)">
                              <div class="d-flex align-items-center justify-content-between">
                                <span>{{ col }} <span v-if="primaryKeys.includes(col)" class="badge bg-primary text-white text-xxs ms-1">PK</span></span>
                                <i class="material-symbols-rounded text-xs ms-1" v-if="sortCol === col">
                                  {{ sortDir === 'ASC' ? 'arrow_upward' : 'arrow_downward' }}
                                </i>
                              </div>
                            </th>
                            <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-8" style="width: 90px;">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(row, idx) in tableRows" :key="idx" class="data-row-hover" :class="{ 'bg-light-primary': isRowSelected(row) }">
                            <td class="ps-3">
                              <input type="checkbox" class="form-check-input" :checked="isRowSelected(row)" @click.prevent.stop="toggleSelectRow(row)" />
                            </td>
                            <td v-for="col in tableColumns" :key="col" class="text-xs text-dark font-weight-bold text-truncate" style="max-width: 250px;" :title="row[col]">
                              <span v-if="row[col] === null" class="badge bg-light text-secondary font-monospace text-xxs opacity-7">NULL</span>
                              <span v-else>{{ row[col] }}</span>
                            </td>
                            <td class="text-center">
                              <div class="d-flex justify-content-center gap-1">
                                <button class="action-btn-sm" @click.stop="openEditRowModal(row)" title="Edit Row">
                                  <i class="material-symbols-rounded text-sm text-info">edit</i>
                                </button>
                                <button class="action-btn-sm" @click.stop="deleteSingleRow(row)" title="Delete Row">
                                  <i class="material-symbols-rounded text-sm text-danger">delete</i>
                                </button>
                              </div>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div v-if="totalPages > 1" class="d-flex justify-content-between align-items-center pt-3 border-top">
                      <span class="text-xs text-secondary font-weight-bold">
                        Showing {{ (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, totalRows) }} of {{ totalRows }} rows
                      </span>
                      <ul class="pagination pagination-primary pagination-xs mb-0">
                        <li class="page-item" :class="{ disabled: currentPage === 1 }">
                          <button class="page-link" @click="currentPage--; loadTableData()"><i class="material-symbols-rounded">chevron_left</i></button>
                        </li>
                        <li class="page-item disabled"><span class="page-link">Page {{ currentPage }} of {{ totalPages }}</span></li>
                        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                          <button class="page-link" @click="currentPage++; loadTableData()"><i class="material-symbols-rounded">chevron_right</i></button>
                        </li>
                      </ul>
                    </div>
                  </div>

                  <!-- SubView 2: Schema / Structure -->
                  <div v-else-if="subView === 'schema'" class="flex-grow-1 overflow-y-auto">
                    <div v-if="loadingSchema" class="text-center py-5">
                      <div class="spinner-border text-primary" role="status"></div>
                      <p class="text-xs text-secondary mt-2">Loading schema details...</p>
                    </div>

                    <template v-else-if="tableSchema">
                      <!-- Columns Table -->
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bolder text-dark mb-0">Columns Definition</h6>
                        <div class="d-flex gap-2">
                          <button class="btn btn-xs btn-outline-primary border-radius-lg mb-0" @click="openAddColumnModal">
                            <i class="material-symbols-rounded text-xs me-1">add</i> Add Column
                          </button>
                          <button class="btn btn-xs btn-outline-secondary border-radius-lg mb-0" @click="openAlterTableModal">
                            <i class="material-symbols-rounded text-xs me-1">settings</i> Table Settings
                          </button>
                        </div>
                      </div>

                      <div class="table-responsive border border-radius-lg mb-4">
                        <table class="table align-items-center mb-0">
                          <thead class="bg-gray-100">
                            <tr>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8 ps-3">Field</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Type</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Null</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Key</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Default</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Extra</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Comment</th>
                              <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-8" style="width: 90px;">Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="c in tableSchema.columns" :key="c.name">
                              <td class="ps-3 font-weight-bold text-xs text-dark">
                                {{ c.name }}
                                <span v-if="c.is_primary" class="badge bg-gradient-primary text-white text-xxs ms-1">PRIMARY</span>
                              </td>
                              <td><code class="text-xs text-primary font-weight-bold">{{ c.type }}</code></td>
                              <td><span :class="c.null ? 'badge bg-light text-dark' : 'badge bg-secondary'">{{ c.null ? 'YES' : 'NO' }}</span></td>
                              <td><span v-if="c.key" class="badge bg-info text-white text-xxs">{{ c.key }}</span><span v-else class="text-secondary">-</span></td>
                              <td class="text-xs text-dark">{{ c.default !== null ? c.default : 'NULL' }}</td>
                              <td class="text-xs text-secondary">{{ c.extra || '-' }}</td>
                              <td class="text-xs text-muted">{{ c.comment || '-' }}</td>
                              <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                  <button class="action-btn-sm" @click="openEditColumnModal(c)" title="Edit Column / Type">
                                    <i class="material-symbols-rounded text-sm text-info">edit</i>
                                  </button>
                                  <button class="action-btn-sm" @click="saveDropColumn(c.name)" title="Drop Column">
                                    <i class="material-symbols-rounded text-sm text-danger">delete</i>
                                  </button>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>

                      <!-- Indexes Section -->
                      <h6 class="font-weight-bolder text-dark mb-3">Indexes</h6>
                      <div class="table-responsive border border-radius-lg mb-4">
                        <table class="table align-items-center mb-0">
                          <thead class="bg-gray-100">
                            <tr>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8 ps-3">Index Name</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Type</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Columns</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="idx in tableSchema.indexes" :key="idx.name">
                              <td class="ps-3 font-weight-bold text-xs text-dark">{{ idx.name }}</td>
                              <td>
                                <span :class="idx.unique ? 'badge bg-success' : 'badge bg-secondary'">
                                  {{ idx.name === 'PRIMARY' ? 'PRIMARY KEY' : (idx.unique ? 'UNIQUE' : 'INDEX') }}
                                </span>
                              </td>
                              <td class="text-xs text-dark font-weight-bold">{{ idx.columns.join(', ') }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>

                      <!-- Foreign Keys Section -->
                      <h6 class="font-weight-bolder text-dark mb-3">Foreign Keys</h6>
                      <div v-if="tableSchema.foreign_keys.length > 0" class="table-responsive border border-radius-lg">
                        <table class="table align-items-center mb-0">
                          <thead class="bg-gray-100">
                            <tr>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8 ps-3">Constraint</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Column</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Referenced Table</th>
                              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Referenced Column</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="fk in tableSchema.foreign_keys" :key="fk.CONSTRAINT_NAME">
                              <td class="ps-3 font-weight-bold text-xs text-dark">{{ fk.CONSTRAINT_NAME }}</td>
                              <td class="text-xs text-primary font-weight-bold">{{ fk.COLUMN_NAME }}</td>
                              <td class="text-xs text-dark font-weight-bold">{{ fk.REFERENCED_TABLE_NAME }}</td>
                              <td class="text-xs text-info font-weight-bold">{{ fk.REFERENCED_COLUMN_NAME }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                      <div v-else class="text-muted text-xs p-3 bg-gray-50 border-radius-lg border border-dashed text-center">
                        No foreign key constraints defined for this table.
                      </div>
                    </template>
                  </div>
                </template>

                <!-- No Table Selected Prompt -->
                <div v-else class="text-center py-7 my-auto">
                  <div class="icon-box-lg bg-light-primary mx-auto mb-3 shadow-sm border-radius-xl d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; margin: 0 auto;">
                    <i class="material-symbols-rounded text-primary" style="font-size: 32px;">table_chart</i>
                  </div>
                  <h5 class="text-dark font-weight-bolder">Select a Table</h5>
                  <p class="text-secondary text-xs mb-4">Choose a table from the left sidebar to inspect rows or table schema</p>
                </div>
              </div>
            </template>

            <!-- Tab 2: Interactive SQL Console -->
            <div v-else-if="activeTab === 'sql'" class="flex-grow-1 p-4 bg-white overflow-y-auto d-flex flex-column">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <h5 class="font-weight-bolder text-dark mb-0">SQL Query Console</h5>
                  <small class="text-secondary">Execute raw SQL queries against <code>{{ databaseName }}</code></small>
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-xs btn-outline-secondary mb-0" @click="sqlQuery = `SELECT * FROM \`${selectedTable || 'tables'}\` LIMIT 25`">SELECT Template</button>
                  <button class="btn btn-xs btn-outline-secondary mb-0" @click="sqlQuery = `SHOW TABLES`">SHOW TABLES</button>
                  <button class="btn bg-gradient-primary mb-0 border-radius-lg px-4" @click="runQuery" :disabled="executingSql || !sqlQuery.trim()">
                    <span v-if="executingSql" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="material-symbols-rounded text-sm me-1">play_arrow</i>
                    Run Query (Ctrl+Enter)
                  </button>
                </div>
              </div>

              <!-- SQL Editor Box -->
              <div class="sql-editor-container mb-4 shadow-inner border border-radius-lg bg-gradient-dark p-3">
                <textarea v-model="sqlQuery" class="form-control bg-transparent border-0 text-white font-monospace text-sm" 
                  rows="6" placeholder="SELECT * FROM users WHERE active = 1;" @keydown.ctrl.enter="runQuery" @keydown.meta.enter="runQuery"></textarea>
              </div>

              <!-- Query Results Section -->
              <div v-if="sqlResult" class="sql-results-container flex-grow-1 border border-radius-lg p-3 bg-gray-50">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                  <div class="d-flex align-items-center gap-2">
                    <span :class="sqlResult.success ? 'badge bg-success' : 'badge bg-danger'">
                      {{ sqlResult.success ? 'Success' : 'Query Failed' }}
                    </span>
                    <span v-if="sqlResult.execution_time_ms !== undefined" class="text-xs font-weight-bold text-secondary">
                      <i class="material-symbols-rounded text-xs align-middle">timer</i> {{ sqlResult.execution_time_ms }} ms
                    </span>
                  </div>
                  <span v-if="sqlResult.type === 'select'" class="text-xs font-weight-bold text-dark">{{ sqlResult.count }} rows returned</span>
                  <span v-else-if="sqlResult.type === 'affected'" class="text-xs font-weight-bold text-dark">{{ sqlResult.affected_rows }} rows affected</span>
                </div>

                <!-- SELECT Grid Result -->
                <div v-if="sqlResult.type === 'select' && sqlResult.rows" class="table-responsive border border-radius-lg bg-white overflow-y-auto" style="max-height: 350px;">
                  <table class="table table-hover align-items-center mb-0">
                    <thead class="bg-gray-100 sticky-top">
                      <tr>
                        <th v-for="c in sqlResult.columns" :key="c" class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8 ps-3">{{ c }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(r, ri) in sqlResult.rows" :key="ri">
                        <td v-for="c in sqlResult.columns" :key="c" class="text-xs text-dark font-weight-bold ps-3">
                          <span v-if="r[c] === null" class="badge bg-light text-secondary font-monospace text-xxs opacity-7">NULL</span>
                          <span v-else>{{ r[c] }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Non-SELECT Result Message -->
                <div v-else-if="sqlResult.message" class="alert alert-info py-2 px-3 text-xs text-white border-radius-lg mb-0">
                  <i class="material-symbols-rounded text-sm me-1 align-middle">info</i> {{ sqlResult.message }}
                </div>

                <!-- Error Message -->
                <div v-else-if="sqlResult.error" class="alert alert-danger py-2 px-3 text-xs text-white border-radius-lg mb-0 font-monospace">
                  <i class="material-symbols-rounded text-sm me-1 align-middle">error</i> {{ sqlResult.error }}
                </div>
              </div>
            </div>

            <!-- Tab 3: Create Table Form -->
            <div v-else-if="activeTab === 'create_table'" class="flex-grow-1 p-4 bg-white overflow-y-auto">
              <h5 class="font-weight-bolder text-dark mb-1">Create New Table</h5>
              <p class="text-xs text-secondary mb-4">Define table name, columns, and properties for <code>{{ databaseName }}</code></p>

              <div class="row mb-4">
                <div class="col-md-4">
                  <label class="form-label text-xs font-weight-bold text-uppercase">Table Name</label>
                  <input v-model="newTable.name" type="text" class="form-control" placeholder="my_new_table" />
                </div>
                <div class="col-md-4">
                  <label class="form-label text-xs font-weight-bold text-uppercase">Engine</label>
                  <select v-model="newTable.engine" class="form-select">
                    <option value="InnoDB">InnoDB (Standard)</option>
                    <option value="MyISAM">MyISAM</option>
                    <option value="MEMORY">MEMORY</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label text-xs font-weight-bold text-uppercase">Collation</label>
                  <select v-model="newTable.collation" class="form-select">
                    <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                    <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                    <option value="utf8_general_ci">utf8_general_ci</option>
                  </select>
                </div>
              </div>

              <!-- Columns Builder Grid -->
              <h6 class="font-weight-bolder text-dark mb-3">Columns Definition</h6>
              <div class="table-responsive border border-radius-lg mb-4">
                <table class="table align-items-center mb-0">
                  <thead class="bg-gray-100">
                    <tr>
                      <th class="ps-3 text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Name</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Type</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Length / Values</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Default</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Nullable</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">A_I</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-8">Primary</th>
                      <th style="width: 40px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(c, idx) in newTable.columns" :key="idx">
                      <td class="ps-3">
                        <input v-model="c.name" type="text" class="form-control form-control-sm text-xs" placeholder="col_name" />
                      </td>
                      <td>
                        <select v-model="c.type" class="form-select form-select-sm text-xs">
                          <option value="INT">INT</option>
                          <option value="BIGINT">BIGINT</option>
                          <option value="VARCHAR">VARCHAR</option>
                          <option value="TEXT">TEXT</option>
                          <option value="LONGTEXT">LONGTEXT</option>
                          <option value="DATETIME">DATETIME</option>
                          <option value="TIMESTAMP">TIMESTAMP</option>
                          <option value="TINYINT">TINYINT (Bool)</option>
                          <option value="DECIMAL">DECIMAL</option>
                          <option value="JSON">JSON</option>
                        </select>
                      </td>
                      <td>
                        <input v-model="c.length" type="text" class="form-control form-control-sm text-xs" placeholder="255" />
                      </td>
                      <td>
                        <input v-model="c.default" type="text" class="form-control form-control-sm text-xs" placeholder="NULL or value" />
                      </td>
                      <td class="text-center">
                        <input type="checkbox" class="form-check-input" v-model="c.nullable" />
                      </td>
                      <td class="text-center">
                        <input type="checkbox" class="form-check-input" v-model="c.auto_increment" />
                      </td>
                      <td class="text-center">
                        <input type="checkbox" class="form-check-input" v-model="c.primary_key" />
                      </td>
                      <td>
                        <button class="btn btn-link text-danger p-0 m-0" @click="newTable.columns.splice(idx, 1)" :disabled="newTable.columns.length === 1">
                          <i class="material-symbols-rounded text-sm">remove_circle</i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="d-flex justify-content-between align-items-center">
                <button class="btn btn-sm btn-outline-primary mb-0 border-radius-lg" @click="addColumnToNewTable">
                  <i class="material-symbols-rounded text-xs me-1">add</i> Add Column
                </button>
                <button class="btn bg-gradient-primary mb-0 border-radius-lg px-4" @click="createTableAction" :disabled="creatingTable || !newTable.name.trim()">
                  <span v-if="creatingTable" class="spinner-border spinner-border-sm me-1"></span>
                  Create Table Now
                </button>
              </div>
            </div>

            <!-- Tab 4: Export / Import -->
            <div v-else-if="activeTab === 'export_import'" class="flex-grow-1 p-4 bg-white overflow-y-auto">
              <div class="row g-4">
                <!-- Export Section -->
                <div class="col-md-6">
                  <div class="p-4 border border-radius-xl bg-gray-50 h-100 shadow-sm">
                    <div class="d-flex align-items-center mb-3">
                      <div class="icon-shape icon-sm bg-gradient-info text-white border-radius-lg text-center me-2">
                        <i class="material-symbols-rounded text-sm">download</i>
                      </div>
                      <h5 class="font-weight-bolder text-dark mb-0">Export Database</h5>
                    </div>
                    <p class="text-xs text-secondary mb-4">Download a full SQL dump or CSV export for <code>{{ databaseName }}</code>.</p>

                    <div class="mb-3">
                      <label class="form-label text-xs font-weight-bold text-uppercase">Format</label>
                      <select v-model="exportFormat" class="form-select">
                        <option value="sql">SQL Dump (.sql)</option>
                        <option value="csv">CSV File (.csv)</option>
                      </select>
                    </div>

                    <div class="mb-4">
                      <label class="form-label text-xs font-weight-bold text-uppercase">Target Table</label>
                      <select v-model="exportTable" class="form-select">
                        <option value="">Whole Database (All Tables)</option>
                        <option v-for="t in tables" :key="t.name" :value="t.name">{{ t.name }}</option>
                      </select>
                    </div>

                    <button class="btn bg-gradient-info w-100 mb-0 border-radius-lg" @click="downloadExport">
                      <i class="material-symbols-rounded text-sm me-1">download</i> Download Export
                    </button>
                  </div>
                </div>

                <!-- Import Section -->
                <div class="col-md-6">
                  <div class="p-4 border border-radius-xl bg-gray-50 h-100 shadow-sm">
                    <div class="d-flex align-items-center mb-3">
                      <div class="icon-shape icon-sm bg-gradient-success text-white border-radius-lg text-center me-2">
                        <i class="material-symbols-rounded text-sm">upload</i>
                      </div>
                      <h5 class="font-weight-bolder text-dark mb-0">Import SQL Dump</h5>
                    </div>
                    <p class="text-xs text-secondary mb-4">Upload a <code>.sql</code> file to execute queries into <code>{{ databaseName }}</code>.</p>

                    <div class="mb-4">
                      <label class="form-label text-xs font-weight-bold text-uppercase">Select Dump File</label>
                      <input type="file" ref="importFileInput" class="form-control" accept=".sql,.txt" />
                    </div>

                    <button class="btn bg-gradient-success w-100 mb-0 border-radius-lg" @click="uploadImport" :disabled="importing">
                      <span v-if="importing" class="spinner-border spinner-border-sm me-1"></span>
                      <i v-else class="material-symbols-rounded text-sm me-1">upload</i>
                      {{ importing ? 'Importing SQL Dump...' : 'Import Dump Now' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

          </div>

        </div>
      </div>
    </transition>

    <!-- Insert / Edit Row Modal -->
    <div v-if="showRowModal" class="modal-backdrop fade show" style="z-index: 10050;"></div>
    <div v-if="showRowModal" class="modal fade show d-block" style="z-index: 10051;">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="glass-card modal-content border-0 shadow-2xl bg-white p-3">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title font-weight-bolder text-dark">
              <i class="material-symbols-rounded text-primary me-2 align-middle">{{ isEditingRow ? 'edit' : 'add_circle' }}</i>
              {{ isEditingRow ? 'Edit Row' : 'Insert New Row' }}
            </h5>
            <button type="button" class="btn-close" @click="showRowModal = false"></button>
          </div>
          <div class="modal-body overflow-y-auto" style="max-height: 450px;">
            <div class="row g-3">
              <div v-for="c in tableColumns" :key="c" class="col-md-6">
                <label class="form-label text-xs font-weight-bold text-dark mb-1">
                  {{ c }}
                  <span v-if="primaryKeys.includes(c)" class="badge bg-primary text-white text-xxs ms-1">PK</span>
                </label>
                <input v-model="rowFormData[c]" type="text" class="form-control text-xs" :placeholder="c" />
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button class="btn btn-link text-secondary mb-0" @click="showRowModal = false">Cancel</button>
            <button class="btn bg-gradient-primary mb-0 border-radius-lg px-4" @click="saveRowAction" :disabled="savingRow">
              <span v-if="savingRow" class="spinner-border spinner-border-sm me-1"></span>
              {{ isEditingRow ? 'Update Row' : 'Insert Row' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Column Modal -->
    <div v-if="showEditColumnModal" class="modal-backdrop fade show" style="z-index: 10050;"></div>
    <div v-if="showEditColumnModal" class="modal fade show d-block" style="z-index: 10051;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="glass-card modal-content border-0 shadow-2xl bg-white p-3">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title font-weight-bolder text-dark">
              <i class="material-symbols-rounded text-info me-2 align-middle">edit</i>
              Edit Column: {{ columnForm.column }}
            </h5>
            <button type="button" class="btn-close" @click="showEditColumnModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Column Name</label>
              <input v-model="columnForm.new_name" type="text" class="form-control" />
            </div>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label text-xs font-weight-bold text-uppercase">Data Type</label>
                <select v-model="columnForm.type" class="form-select">
                  <option value="VARCHAR">VARCHAR</option>
                  <option value="INT">INT</option>
                  <option value="BIGINT">BIGINT</option>
                  <option value="TINYINT">TINYINT (Bool)</option>
                  <option value="TEXT">TEXT</option>
                  <option value="LONGTEXT">LONGTEXT</option>
                  <option value="DATETIME">DATETIME</option>
                  <option value="TIMESTAMP">TIMESTAMP</option>
                  <option value="DECIMAL">DECIMAL</option>
                  <option value="JSON">JSON</option>
                  <option value="ENUM">ENUM</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label text-xs font-weight-bold text-uppercase">Length / Values</label>
                <input v-model="columnForm.length" type="text" class="form-control" placeholder="e.g. 255 or 10,2" />
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Default Value</label>
              <input v-model="columnForm.default" type="text" class="form-control" placeholder="NULL or default value" />
            </div>
            <div class="d-flex align-items-center gap-4 mb-3">
              <div class="form-check mb-0">
                <input v-model="columnForm.nullable" type="checkbox" class="form-check-input" id="colNullModal" />
                <label class="form-check-label text-xs font-weight-bold" for="colNullModal">Allow NULL</label>
              </div>
              <div class="form-check mb-0">
                <input v-model="columnForm.auto_increment" type="checkbox" class="form-check-input" id="colAiModal" />
                <label class="form-check-label text-xs font-weight-bold" for="colAiModal">Auto Increment</label>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Comment</label>
              <input v-model="columnForm.comment" type="text" class="form-control text-xs" placeholder="Optional column comment" />
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button class="btn btn-link text-secondary mb-0" @click="showEditColumnModal = false">Cancel</button>
            <button class="btn bg-gradient-primary mb-0 border-radius-lg px-4" @click="saveEditColumn" :disabled="savingColumn">
              <span v-if="savingColumn" class="spinner-border spinner-border-sm me-1"></span>
              Update Column Structure
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Column Modal -->
    <div v-if="showAddColumnModal" class="modal-backdrop fade show" style="z-index: 10050;"></div>
    <div v-if="showAddColumnModal" class="modal fade show d-block" style="z-index: 10051;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="glass-card modal-content border-0 shadow-2xl bg-white p-3">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title font-weight-bolder text-dark">
              <i class="material-symbols-rounded text-primary me-2 align-middle">add_circle</i>
              Add New Column to {{ selectedTable }}
            </h5>
            <button type="button" class="btn-close" @click="showAddColumnModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Column Name</label>
              <input v-model="columnForm.name" type="text" class="form-control" placeholder="new_column_name" />
            </div>
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label text-xs font-weight-bold text-uppercase">Data Type</label>
                <select v-model="columnForm.type" class="form-select">
                  <option value="VARCHAR">VARCHAR</option>
                  <option value="INT">INT</option>
                  <option value="BIGINT">BIGINT</option>
                  <option value="TINYINT">TINYINT (Bool)</option>
                  <option value="TEXT">TEXT</option>
                  <option value="LONGTEXT">LONGTEXT</option>
                  <option value="DATETIME">DATETIME</option>
                  <option value="TIMESTAMP">TIMESTAMP</option>
                  <option value="DECIMAL">DECIMAL</option>
                  <option value="JSON">JSON</option>
                  <option value="ENUM">ENUM</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label text-xs font-weight-bold text-uppercase">Length / Values</label>
                <input v-model="columnForm.length" type="text" class="form-control" placeholder="e.g. 255" />
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Position</label>
              <select v-model="columnForm.position" class="form-select">
                <option value="">At End of Table</option>
                <option value="FIRST">At Beginning (FIRST)</option>
                <option v-for="c in tableColumns" :key="c" :value="c">After {{ c }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Default Value</label>
              <input v-model="columnForm.default" type="text" class="form-control" placeholder="NULL or default value" />
            </div>
            <div class="d-flex align-items-center gap-4 mb-3">
              <div class="form-check mb-0">
                <input v-model="columnForm.nullable" type="checkbox" class="form-check-input" id="addColNullModal" />
                <label class="form-check-label text-xs font-weight-bold" for="addColNullModal">Allow NULL</label>
              </div>
              <div class="form-check mb-0">
                <input v-model="columnForm.auto_increment" type="checkbox" class="form-check-input" id="addColAiModal" />
                <label class="form-check-label text-xs font-weight-bold" for="addColAiModal">Auto Increment</label>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button class="btn btn-link text-secondary mb-0" @click="showAddColumnModal = false">Cancel</button>
            <button class="btn bg-gradient-primary mb-0 border-radius-lg px-4" @click="saveAddColumn" :disabled="savingColumn || !columnForm.name.trim()">
              <span v-if="savingColumn" class="spinner-border spinner-border-sm me-1"></span>
              Add Column Now
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Settings Modal -->
    <div v-if="showAlterTableModal" class="modal-backdrop fade show" style="z-index: 10050;"></div>
    <div v-if="showAlterTableModal" class="modal fade show d-block" style="z-index: 10051;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="glass-card modal-content border-0 shadow-2xl bg-white p-3">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title font-weight-bolder text-dark">
              <i class="material-symbols-rounded text-secondary me-2 align-middle">settings</i>
              Table Settings: {{ selectedTable }}
            </h5>
            <button type="button" class="btn-close" @click="showAlterTableModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Rename Table</label>
              <input v-model="alterTableForm.new_name" type="text" class="form-control" />
            </div>
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Engine</label>
              <select v-model="alterTableForm.engine" class="form-select">
                <option value="InnoDB">InnoDB</option>
                <option value="MyISAM">MyISAM</option>
                <option value="MEMORY">MEMORY</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label text-xs font-weight-bold text-uppercase">Collation</label>
              <select v-model="alterTableForm.collation" class="form-select">
                <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                <option value="utf8_general_ci">utf8_general_ci</option>
              </select>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button class="btn btn-link text-secondary mb-0" @click="showAlterTableModal = false">Cancel</button>
            <button class="btn bg-gradient-primary mb-0 border-radius-lg px-4" @click="saveAlterTableProps" :disabled="savingTableProps">
              <span v-if="savingTableProps" class="spinner-border spinner-border-sm me-1"></span>
              Save Table Properties
            </button>
          </div>
        </div>
      </div>
    </div>

  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  show: Boolean,
  databaseName: String
})

const emit = defineEmits(['close'])

const activeTab = ref('browse')
const subView = ref('data')
const tableSearchQuery = ref('')
const selectedTable = ref('')
const tables = ref([])
const loadingTables = ref(false)

const tableColumns = ref([])
const primaryKeys = ref([])
const tableRows = ref([])
const totalRows = ref(0)
const currentPage = ref(1)
const perPage = ref(25)
const totalPages = computed(() => Math.ceil(totalRows.value / perPage.value))

const sortCol = ref('')
const sortDir = ref('ASC')
const dataSearchQuery = ref('')
const dataSearchCol = ref('')

const loadingData = ref(false)
const selectedRows = ref([])
const allRowsSelected = ref(false)

// Schema
const tableSchema = ref(null)
const loadingSchema = ref(false)

// Column & Structure Editing Modals
const showAddColumnModal = ref(false)
const showEditColumnModal = ref(false)
const showAlterTableModal = ref(false)
const savingColumn = ref(false)
const savingTableProps = ref(false)

const columnForm = ref({
  column: '',
  name: '',
  new_name: '',
  type: 'VARCHAR',
  length: '255',
  nullable: true,
  default: null,
  auto_increment: false,
  comment: '',
  position: ''
})

const alterTableForm = ref({
  new_name: '',
  engine: 'InnoDB',
  collation: 'utf8mb4_unicode_ci'
})

// SQL Console
const sqlQuery = ref('')
const sqlResult = ref(null)
const executingSql = ref(false)

// Create Table
const newTable = ref({
  name: '',
  engine: 'InnoDB',
  collation: 'utf8mb4_unicode_ci',
  columns: [
    { name: 'id', type: 'BIGINT', length: '', nullable: false, auto_increment: true, primary_key: true, default: null },
    { name: 'created_at', type: 'DATETIME', length: '', nullable: true, auto_increment: false, primary_key: false, default: 'CURRENT_TIMESTAMP' }
  ]
})
const creatingTable = ref(false)

// Export / Import
const exportFormat = ref('sql')
const exportTable = ref('')
const importFileInput = ref(null)
const importing = ref(false)

// Row Insert/Edit Modal
const showRowModal = ref(false)
const isEditingRow = ref(false)
const rowFormData = ref({})
const originalRowData = ref({})
const savingRow = ref(false)

// Alert
const alert = ref({ show: false, type: 'success', message: '' })

const filteredTables = computed(() => {
  if (!tableSearchQuery.value) return tables.value
  const q = tableSearchQuery.value.toLowerCase()
  return tables.value.filter(t => t.name.toLowerCase().includes(q))
})

watch(() => props.show, (val) => {
  if (val && props.databaseName) {
    loadTables()
  }
})

watch(selectedTable, (val) => {
  if (val) {
    currentPage.value = 1
    selectedRows.value = []
    loadTableData()
    loadTableSchema()
  }
})

const close = () => emit('close')

const showAlert = (type, message) => {
  alert.value = { show: true, type, message }
  setTimeout(() => alert.value.show = false, 5000)
}

const loadTables = async () => {
  if (!props.databaseName) return
  try {
    loadingTables.value = true
    const response = await axios.get(`/database/manager/${props.databaseName}/tables`)
    tables.value = response.data.tables || []
    if (tables.value.length > 0 && !selectedTable.value) {
      selectedTable.value = tables.value[0].name
    }
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to load database tables')
  } finally {
    loadingTables.value = false
  }
}

const selectTable = (name) => {
  selectedTable.value = name
}

const loadTableData = async () => {
  if (!props.databaseName || !selectedTable.value) return
  try {
    loadingData.value = true
    const response = await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/data`, {
      page: currentPage.value,
      per_page: perPage.value,
      sort_col: sortCol.value,
      sort_dir: sortDir.value,
      search_query: dataSearchQuery.value,
      search_col: dataSearchCol.value
    })
    tableColumns.value = response.data.columns || []
    primaryKeys.value = response.data.primary_keys || []
    tableRows.value = response.data.rows || []
    totalRows.value = response.data.total_rows || 0
    selectedRows.value = []
    allRowsSelected.value = false
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to load table rows')
  } finally {
    loadingData.value = false
  }
}

const loadTableSchema = async () => {
  if (!props.databaseName || !selectedTable.value) return
  try {
    loadingSchema.value = true
    const response = await axios.get(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/schema`)
    tableSchema.value = response.data
  } catch (err) {
    showAlert('danger', 'Failed to load schema')
  } finally {
    loadingSchema.value = false
  }
}

// Column & Schema Editing Handlers
const openAddColumnModal = () => {
  columnForm.value = {
    column: '',
    name: '',
    new_name: '',
    type: 'VARCHAR',
    length: '255',
    nullable: true,
    default: null,
    auto_increment: false,
    comment: '',
    position: ''
  }
  showAddColumnModal.value = true
}

const openEditColumnModal = (col) => {
  let rawType = col.type || 'VARCHAR'
  let mainType = rawType.split('(')[0].toUpperCase()
  let lenMatch = rawType.match(/\((.+)\)/)
  let lengthStr = lenMatch ? lenMatch[1] : ''

  columnForm.value = {
    column: col.name,
    name: col.name,
    new_name: col.name,
    type: mainType,
    length: lengthStr,
    nullable: col.null,
    default: col.default,
    auto_increment: col.is_auto_increment || false,
    comment: col.comment || '',
    position: ''
  }
  showEditColumnModal.value = true
}

const openAlterTableModal = () => {
  const currentTableObj = tables.value.find(t => t.name === selectedTable.value)
  alterTableForm.value = {
    new_name: selectedTable.value,
    engine: currentTableObj?.engine || 'InnoDB',
    collation: currentTableObj?.collation || 'utf8mb4_unicode_ci'
  }
  showAlterTableModal.value = true
}

const saveAddColumn = async () => {
  if (!columnForm.value.name.trim()) return
  try {
    savingColumn.value = true
    await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/column/add`, columnForm.value)
    showAlert('success', `Column '${columnForm.value.name}' added successfully`)
    showAddColumnModal.value = false
    loadTableSchema()
    loadTableData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to add column')
  } finally {
    savingColumn.value = false
  }
}

const saveEditColumn = async () => {
  if (!columnForm.value.new_name.trim()) return
  try {
    savingColumn.value = true
    await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/column/update`, columnForm.value)
    showAlert('success', `Column '${columnForm.value.column}' updated successfully`)
    showEditColumnModal.value = false
    loadTableSchema()
    loadTableData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to update column')
  } finally {
    savingColumn.value = false
  }
}

const saveDropColumn = async (colName) => {
  if (!confirm(`Drop column '${colName}' from table '${selectedTable.value}'? Data in this column will be permanently lost.`)) return
  try {
    await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/column/drop`, {
      column: colName
    })
    showAlert('success', `Column '${colName}' dropped`)
    loadTableSchema()
    loadTableData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to drop column')
  }
}

const saveAlterTableProps = async () => {
  try {
    savingTableProps.value = true
    const response = await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/alter-props`, alterTableForm.value)
    showAlert('success', response.data.message || 'Table properties updated')
    showAlterTableModal.value = false
    const oldName = selectedTable.value
    await loadTables()
    selectedTable.value = alterTableForm.value.new_name || oldName
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to update table properties')
  } finally {
    savingTableProps.value = false
  }
}

const sortBy = (col) => {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'ASC' ? 'DESC' : 'ASC'
  } else {
    sortCol.value = col
    sortDir.value = 'ASC'
  }
  loadTableData()
}

const isRowSelected = (row) => {
  return selectedRows.value.some(r => JSON.stringify(r) === JSON.stringify(row))
}

const toggleSelectRow = (row) => {
  const idx = selectedRows.value.findIndex(r => JSON.stringify(r) === JSON.stringify(row))
  if (idx > -1) selectedRows.value.splice(idx, 1)
  else selectedRows.value.push(row)
}

const toggleSelectAllRows = () => {
  if (allRowsSelected.value) {
    selectedRows.value = []
  } else {
    selectedRows.value = [...tableRows.value]
  }
  allRowsSelected.value = !allRowsSelected.value
}

// Row Insert / Edit
const openInsertRowModal = () => {
  isEditingRow.value = false
  rowFormData.value = {}
  tableColumns.value.forEach(c => rowFormData.value[c] = '')
  showRowModal.value = true
}

const openEditRowModal = (row) => {
  isEditingRow.value = true
  originalRowData.value = { ...row }
  rowFormData.value = { ...row }
  showRowModal.value = true
}

const saveRowAction = async () => {
  try {
    savingRow.value = true
    if (isEditingRow.value) {
      let whereDict = {}
      if (primaryKeys.value.length > 0) {
        primaryKeys.value.forEach(pk => whereDict[pk] = originalRowData.value[pk])
      } else {
        whereDict = originalRowData.value
      }
      await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/row/update`, {
        where: whereDict,
        data: rowFormData.value
      })
      showAlert('success', 'Row updated successfully')
    } else {
      await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/row/insert`, {
        data: rowFormData.value
      })
      showAlert('success', 'Row inserted successfully')
    }
    showRowModal.value = false
    loadTableData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Row save failed')
  } finally {
    savingRow.value = false
  }
}

const deleteSingleRow = async (row) => {
  if (!confirm('Are you sure you want to delete this row?')) return
  try {
    const whereDict = {}
    if (primaryKeys.value.length > 0) {
      primaryKeys.value.forEach(pk => whereDict[pk] = row[pk])
    } else {
      whereDict = row
    }
    await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/row/delete`, {
      rows: [whereDict]
    })
    showAlert('success', 'Row deleted')
    loadTableData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to delete row')
  }
}

const bulkDeleteRows = async () => {
  if (!confirm(`Delete ${selectedRows.value.length} selected row(s)?`)) return
  try {
    const rowsToDelete = selectedRows.value.map(row => {
      if (primaryKeys.value.length > 0) {
        const d = {}
        primaryKeys.value.forEach(pk => d[pk] = row[pk])
        return d
      }
      return row
    })
    await axios.post(`/database/manager/${props.databaseName}/tables/${selectedTable.value}/row/delete`, {
      rows: rowsToDelete
    })
    showAlert('success', 'Selected rows deleted')
    loadTableData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Bulk delete failed')
  }
}

// SQL Console
const runQuery = async () => {
  if (!sqlQuery.value.trim()) return
  try {
    executingSql.value = true
    const response = await axios.post(`/database/manager/${props.databaseName}/query`, {
      sql: sqlQuery.value.trim()
    })
    sqlResult.value = response.data
    if (response.data.success) {
      loadTables()
    }
  } catch (err) {
    sqlResult.value = {
      success: false,
      error: err.response?.data?.error || 'SQL query failed'
    }
  } finally {
    executingSql.value = false
  }
}

const quickQuery = (q) => {
  sqlQuery.value = q
  activeTab.value = 'sql'
  runQuery()
}

// Create Table DDL
const addColumnToNewTable = () => {
  newTable.value.columns.push({
    name: '', type: 'VARCHAR', length: '255', nullable: true, auto_increment: false, primary_key: false, default: null
  })
}

const createTableAction = async () => {
  if (!newTable.value.name.trim()) return
  try {
    creatingTable.value = true
    await axios.post(`/database/manager/${props.databaseName}/tables/create`, newTable.value)
    showAlert('success', `Table '${newTable.value.name}' created`)
    activeTab.value = 'browse'
    await loadTables()
    selectedTable.value = newTable.value.name
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to create table')
  } finally {
    creatingTable.value = false
  }
}

// Drop / Truncate Table DDL
const confirmTruncateTable = async (tName) => {
  if (!confirm(`Truncate table '${tName}'? All rows will be permanently deleted.`)) return
  try {
    await axios.post(`/database/manager/${props.databaseName}/tables/${tName}/truncate`)
    showAlert('success', `Table '${tName}' truncated`)
    loadTableData()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to truncate table')
  }
}

const confirmDropTable = async (tName) => {
  if (!confirm(`DROP table '${tName}' permanently? This action CANNOT be undone.`)) return
  try {
    await axios.post(`/database/manager/${props.databaseName}/tables/${tName}/drop`)
    showAlert('success', `Table '${tName}' dropped`)
    selectedTable.value = ''
    loadTables()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Failed to drop table')
  }
}

// Export / Import
const downloadExport = () => {
  const url = `/database/manager/${props.databaseName}/export?format=${exportFormat.value}&table=${exportTable.value}`
  window.location.href = url
}

const exportTableCsv = (tName) => {
  window.location.href = `/database/manager/${props.databaseName}/export?format=csv&table=${tName}`
}

const exportTableSql = (tName) => {
  window.location.href = `/database/manager/${props.databaseName}/export?format=sql&table=${tName}`
}

const uploadImport = async () => {
  if (!importFileInput.value?.files?.length) {
    showAlert('warning', 'Please select a .sql dump file first')
    return
  }
  try {
    importing.value = true
    const formData = new FormData()
    formData.append('file', importFileInput.value.files[0])
    await axios.post(`/database/manager/${props.databaseName}/import`, formData)
    showAlert('success', 'SQL dump imported successfully!')
    loadTables()
  } catch (err) {
    showAlert('danger', err.response?.data?.error || 'Import failed')
  } finally {
    importing.value = false
  }
}
</script>

<style scoped>
.db-manager-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.db-manager-modal {
  width: 95vw;
  max-width: 1400px;
  height: 90vh;
  background: #ffffff;
  border-radius: 1rem;
  overflow: hidden;
}

.nav-tab-btn {
  padding: 6px 14px;
  border: none;
  background: transparent;
  color: rgba(255, 255, 255, 0.7);
  font-size: 12px;
  font-weight: 600;
  border-radius: 8px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
}

.nav-tab-btn:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.1);
}

.nav-tab-btn.active {
  background: #ffffff;
  color: #344767;
  font-weight: 700;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.table-list-item {
  border: none;
  background: transparent;
  transition: all 0.2s ease;
}

.table-list-item:hover {
  background: rgba(94, 114, 228, 0.08);
}

.table-list-item.active {
  background: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.action-btn-sm {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.action-btn-sm:hover {
  background: #f8f9fa;
  transform: scale(1.1);
}

.spin-animation {
  animation: rotate 1.5s linear infinite;
}

@keyframes rotate {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.dropdown-toggle-custom::after {
  display: none !important;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
