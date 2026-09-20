<template>

    <Head title="Server Resources &amp; Utilization Report" />

    <MainLayout>
        <div class="container-fluid py-4">
            <!-- Top Hero Bar -->
            <div class="card mb-4 bg-gradient-primary shadow-primary">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <div class="icon icon-shape bg-white shadow text-center border-radius-md me-3 d-flex align-items-center justify-content-center">
                                <i class="material-symbols-rounded text-primary opacity-10">insights</i>
                            </div>
                            <div>
                                <h5 class="text-white mb-0 font-weight-bolder">Server Resources &amp; Performance</h5>
                                <p class="text-white text-xs opacity-8 mb-0">
                                    Real-time server monitoring &bull; Uptime: {{ data.uptime?.formatted || 'Loading...' }}
                                    &bull; Timezone: {{ reportMetadata.timezone || 'Asia/Kolkata' }}
                                </p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-white btn-sm mb-0 d-flex align-items-center" @click="refreshAll" :disabled="loading">
                                <i class="material-symbols-rounded text-sm me-1" :class="{ 'spin-anim': loading }">refresh</i>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div v-if="loading && !data.cpu?.cores" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading metrics...</span>
                </div>
                <p class="text-secondary text-sm mt-2">Collecting synchronized server metrics...</p>
            </div>

            <div v-else>
                <!-- Real-Time Metrics Row -->
                <div class="row mb-4">
                    <!-- CPU -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card h-100 shadow-sm border">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-xs mb-0 text-uppercase font-weight-bold text-secondary">Real-Time CPU</p>
                                            <h4 class="font-weight-bolder mb-0 mt-1" :class="getCpuColor(data.cpu?.usage || 0)">
                                                {{ data.cpu?.usage || 0 }}%
                                            </h4>
                                            <span class="text-xxs text-secondary">{{ data.cpu?.cores || 1 }} Cores ({{ data.cpu?.model || 'Generic' }})</span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                            <i class="material-symbols-rounded opacity-10">memory</i>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar"
                                        :class="(data.cpu?.usage || 0) > 80 ? 'bg-danger' : ((data.cpu?.usage || 0) > 60 ? 'bg-warning' : 'bg-success')"
                                        :style="{ width: Math.min(100, data.cpu?.usage || 0) + '%' }"></div>
                                </div>
                                <div class="d-flex justify-content-between text-xxs text-muted mt-2">
                                    <span>Load: {{ data.load?.['1min'] || 0 }} (1m)</span>
                                    <span>{{ data.load?.['5min'] || 0 }} (5m)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Memory -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card h-100 shadow-sm border">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-xs mb-0 text-uppercase font-weight-bold text-secondary">Memory (RAM)</p>
                                            <h4 class="font-weight-bolder mb-0 mt-1" :class="getMemoryColor(data.memory?.percentage || 0)">
                                                {{ data.memory?.percentage || 0 }}%
                                            </h4>
                                            <span class="text-xxs text-secondary">{{ data.memory?.used || '0 B' }} / {{ data.memory?.total || '0 B' }}</span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                            <i class="material-symbols-rounded opacity-10">storage</i>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar"
                                        :class="(data.memory?.percentage || 0) > 85 ? 'bg-danger' : ((data.memory?.percentage || 0) > 70 ? 'bg-warning' : 'bg-success')"
                                        :style="{ width: Math.min(100, data.memory?.percentage || 0) + '%' }"></div>
                                </div>
                                <div class="d-flex justify-content-between text-xxs text-muted mt-2">
                                    <span>Free: {{ data.memory?.free || '0 B' }}</span>
                                    <span>Swap: {{ data.memory?.swap_used || '0 B' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Load Average -->
                    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                        <div class="card h-100 shadow-sm border">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-xs mb-0 text-uppercase font-weight-bold text-secondary">System Load</p>
                                            <h4 class="font-weight-bolder mb-0 mt-1">
                                                {{ data.load?.['1min'] || 0 }}
                                            </h4>
                                            <span class="text-xxs text-secondary">
                                                Capacity: {{ Math.round(((data.load?.['1min'] || 0) / (data.cpu?.cores || 1)) * 100) }}% on {{ data.cpu?.cores || 1 }} cores
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                            <i class="material-symbols-rounded opacity-10">speed</i>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar bg-warning"
                                        :style="{ width: Math.min(100, ((data.load?.['1min'] || 0) / (data.cpu?.cores || 1)) * 100) + '%' }"></div>
                                </div>
                                <div class="d-flex justify-content-between text-xxs text-muted mt-2">
                                    <span>5m: {{ data.load?.['5min'] || 0 }}</span>
                                    <span>15m: {{ data.load?.['15min'] || 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Uptime -->
                    <div class="col-xl-3 col-sm-6">
                        <div class="card h-100 shadow-sm border">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-xs mb-0 text-uppercase font-weight-bold text-secondary">Server Uptime</p>
                                            <h4 class="font-weight-bolder mb-0 mt-1 text-info">
                                                {{ data.uptime?.days || 0 }} days
                                            </h4>
                                            <span class="text-xxs text-secondary">{{ data.uptime?.hours || 0 }}h {{ data.uptime?.minutes || 0 }}m online</span>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle">
                                            <i class="material-symbols-rounded opacity-10">schedule</i>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar bg-info" style="width: 100%"></div>
                                </div>
                                <div class="d-flex justify-content-between text-xxs text-success font-weight-bold mt-2">
                                    <span>Status: Healthy &amp; Active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Disk & Network Section -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-lg-0 mb-4">
                        <div class="card h-100 shadow-sm border">
                            <div class="card-header pb-0 p-3 bg-white">
                                <h6 class="mb-0 d-flex align-items-center text-sm font-weight-bold">
                                    <i class="material-symbols-rounded text-primary me-2">hard_drive</i>
                                    Storage Volumes
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div v-for="disk in data.disk" :key="disk.mount" class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-xs font-weight-bold text-dark">{{ disk.mount }} ({{ disk.filesystem }})</span>
                                        <span class="text-xs text-secondary">{{ disk.used }} / {{ disk.total }} ({{ disk.percentage }}%)</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar"
                                            :class="disk.percentage > 85 ? 'bg-danger' : disk.percentage > 70 ? 'bg-warning' : 'bg-success'"
                                            :style="{ width: disk.percentage + '%' }">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between text-xxs text-muted mt-1">
                                        <span>Available: {{ disk.free }}</span>
                                        <span>Status: {{ disk.percentage > 85 ? 'High Disk Usage' : 'Healthy' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100 shadow-sm border">
                            <div class="card-header pb-0 p-3 bg-white">
                                <h6 class="mb-0 d-flex align-items-center text-sm font-weight-bold">
                                    <i class="material-symbols-rounded text-primary me-2">lan</i>
                                    Network Interfaces
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table class="table align-items-center mb-0 text-sm">
                                        <thead>
                                            <tr>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder ps-2">Interface</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">RX (Incoming)</th>
                                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder">TX (Outgoing)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="net in data.network" :key="net.interface">
                                                <td class="ps-2">
                                                    <span class="text-xs font-weight-bold">{{ net.interface }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-success border text-xs">
                                                        <i class="material-symbols-rounded text-xs me-1">arrow_downward</i>{{ net.rx }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-info border text-xs">
                                                        <i class="material-symbols-rounded text-xs me-1">arrow_upward</i>{{ net.tx }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AWS-Style Resource Utilization Report Section -->
                <div class="row mt-4" id="aws-report-container">
                    <div class="col-12">
                        <div class="card shadow-sm border">
                            <!-- Report Header & Controls Bar -->
                            <div class="card-header pb-3 p-3 bg-white border-bottom">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-gradient-dark text-white px-2 py-1 text-xxs">AWS CLOUDWATCH STYLE</span>
                                            <span class="badge px-2 py-1 text-xxs font-weight-bold"
                                                :class="reportMetadata.health_status === 'HEALTHY' ? 'bg-success text-white' : (reportMetadata.health_status === 'WARNING' ? 'bg-warning text-dark' : 'bg-danger text-white')">
                                                STATUS: {{ reportMetadata.health_status || 'HEALTHY' }}
                                            </span>
                                        </div>
                                        <h5 class="mb-0 text-dark font-weight-bolder d-flex align-items-center">
                                            <i class="material-symbols-rounded text-primary me-2">analytics</i>
                                            {{ reportMetadata.title || 'Resource Utilization Report' }}
                                        </h5>
                                        <p class="text-xs text-secondary mb-0 mt-1">
                                            <strong>Period:</strong> {{ reportMetadata.period_start || '...' }} &mdash; {{ reportMetadata.period_end || '...' }}
                                            ({{ reportMetadata.timezone || 'Asia/Kolkata' }}) &bull;
                                            <strong>Resolution:</strong> {{ pointsCount }} data points recorded
                                        </p>
                                    </div>

                                    <!-- Range & Date Selectors -->
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <!-- Quick Range Buttons -->
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm mb-0"
                                                :class="selectedRange === '1h' ? 'btn-primary' : 'btn-outline-primary'"
                                                @click="changeRange('1h')">
                                                Last 1 Hour
                                            </button>
                                            <button type="button" class="btn btn-sm mb-0"
                                                :class="selectedRange === '24h' ? 'btn-primary' : 'btn-outline-primary'"
                                                @click="changeRange('24h')">
                                                Last 24 Hours
                                            </button>
                                            <button type="button" class="btn btn-sm mb-0"
                                                :class="selectedRange === 'day' ? 'btn-primary' : 'btn-outline-primary'"
                                                @click="changeRange('day')">
                                                Select Day
                                            </button>
                                            <button type="button" class="btn btn-sm mb-0"
                                                :class="selectedRange === '7d' ? 'btn-primary' : 'btn-outline-primary'"
                                                @click="changeRange('7d')">
                                                7 Days
                                            </button>
                                            <button type="button" class="btn btn-sm mb-0"
                                                :class="selectedRange === '30d' ? 'btn-primary' : 'btn-outline-primary'"
                                                @click="changeRange('30d')">
                                                30 Days
                                            </button>
                                        </div>

                                        <!-- Actions -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary mb-0 d-flex align-items-center"
                                            @click="printReport" title="Print or Save as PDF">
                                            <i class="material-symbols-rounded text-sm me-1">print</i>
                                            Print Report
                                        </button>
                                    </div>
                                </div>

                                <!-- Specific Day Selector Toolbar (Shown when 'day' is selected) -->
                                <div v-if="selectedRange === 'day'" class="mt-3 pt-3 border-top bg-light p-3 rounded-3 d-flex align-items-center flex-wrap gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-symbols-rounded text-primary">calendar_month</i>
                                        <span class="text-xs font-weight-bold text-dark">Select Day from 1 Month:</span>
                                    </div>

                                    <!-- Quick Day Dropdown -->
                                    <div style="min-width: 200px;">
                                        <select class="form-select form-select-sm" v-model="selectedDate" @change="onDateSelected">
                                            <option v-for="d in availableDays" :key="d.date" :value="d.date">
                                                {{ d.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- HTML5 Date Picker Input -->
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-xxs text-secondary">or pick date:</span>
                                        <input type="date" class="form-control form-control-sm"
                                            v-model="selectedDate"
                                            :max="maxSelectableDate"
                                            :min="minSelectableDate"
                                            @change="onDateSelected" style="width: 150px;">
                                    </div>

                                    <!-- Quick Shortcut Buttons -->
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-dark btn-xs mb-0" @click="selectQuickDay(0)">Today</button>
                                        <button type="button" class="btn btn-outline-dark btn-xs mb-0" @click="selectQuickDay(1)">Yesterday</button>
                                        <button type="button" class="btn btn-outline-dark btn-xs mb-0" @click="selectQuickDay(2)">2 Days Ago</button>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body p-3">
                                <!-- AWS CloudWatch KPI Summary Grid -->
                                <div class="row g-3 mb-4">
                                    <!-- CPU Utilization KPI -->
                                    <div class="col-lg-3 col-md-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-xs text-uppercase font-weight-bold text-secondary">CPU Utilization</span>
                                                <span class="badge bg-danger text-white text-xxs">Peak: {{ historySummary.peak_cpu?.value || 0 }}%</span>
                                            </div>
                                            <h3 class="mb-0 text-dark font-weight-bolder mt-1">
                                                {{ historySummary.avg_cpu || 0 }}% <span class="text-xs text-secondary font-weight-normal">avg</span>
                                            </h3>
                                            <div class="mt-2 text-xxs text-secondary border-top pt-2">
                                                <div class="d-flex justify-content-between py-0.5">
                                                    <span>P95 Percentile:</span>
                                                    <strong class="text-dark">{{ historySummary.p95_cpu || 0 }}%</strong>
                                                </div>
                                                <div class="d-flex justify-content-between py-0.5">
                                                    <span>Minimum:</span>
                                                    <strong class="text-dark">{{ historySummary.min_cpu || 0 }}%</strong>
                                                </div>
                                                <div class="d-flex justify-content-between py-0.5" v-if="historySummary.peak_cpu">
                                                    <span>Peak Time:</span>
                                                    <span class="text-dark font-weight-bold">{{ historySummary.peak_cpu.time }}</span>
                                                </div>
                                                <div class="text-truncate mt-1 text-muted" v-if="historySummary.peak_cpu">
                                                    Offending: <code class="text-dark">{{ historySummary.peak_cpu.process }}</code>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Memory Utilization KPI -->
                                    <div class="col-lg-3 col-md-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-xs text-uppercase font-weight-bold text-secondary">Memory Utilization</span>
                                                <span class="badge bg-warning text-dark text-xxs">Peak: {{ historySummary.peak_memory?.value || 0 }}%</span>
                                            </div>
                                            <h3 class="mb-0 text-dark font-weight-bolder mt-1">
                                                {{ historySummary.avg_memory || 0 }}% <span class="text-xs text-secondary font-weight-normal">avg</span>
                                            </h3>
                                            <div class="mt-2 text-xxs text-secondary border-top pt-2">
                                                <div class="d-flex justify-content-between py-0.5">
                                                    <span>P95 Percentile:</span>
                                                    <strong class="text-dark">{{ historySummary.p95_memory || 0 }}%</strong>
                                                </div>
                                                <div class="d-flex justify-content-between py-0.5">
                                                    <span>Minimum:</span>
                                                    <strong class="text-dark">{{ historySummary.min_memory || 0 }}%</strong>
                                                </div>
                                                <div class="d-flex justify-content-between py-0.5" v-if="historySummary.peak_memory">
                                                    <span>Peak Time:</span>
                                                    <span class="text-dark font-weight-bold">{{ historySummary.peak_memory.time }}</span>
                                                </div>
                                                <div class="text-truncate mt-1 text-muted" v-if="historySummary.peak_memory">
                                                    Used at Peak: <strong class="text-dark">{{ Math.round(historySummary.peak_memory.used_mb || 0) }} MB</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Compute Load & Capacity KPI -->
                                    <div class="col-lg-3 col-md-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-xs text-uppercase font-weight-bold text-secondary">System Load &amp; Capacity</span>
                                                <span class="badge bg-info text-white text-xxs">Cores: {{ reportMetadata.cores || 1 }}</span>
                                            </div>
                                            <h3 class="mb-0 text-dark font-weight-bolder mt-1">
                                                {{ historySummary.avg_load || 0 }} <span class="text-xs text-secondary font-weight-normal">avg load</span>
                                            </h3>
                                            <div class="mt-2 text-xxs text-secondary border-top pt-2">
                                                <div class="d-flex justify-content-between py-0.5">
                                                    <span>Peak 1m Load:</span>
                                                    <strong class="text-dark">{{ historySummary.peak_load || 0 }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between py-0.5">
                                                    <span>Core Capacity:</span>
                                                    <strong :class="historySummary.load_capacity_percent > 100 ? 'text-danger' : 'text-success'">
                                                        {{ historySummary.load_capacity_percent || 0 }}%
                                                    </strong>
                                                </div>
                                                <div class="text-truncate mt-1 text-muted">
                                                    Processor: <span class="text-dark">{{ reportMetadata.cpu_model || 'Unknown' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Incidents & Alarms KPI -->
                                    <div class="col-lg-3 col-md-6">
                                        <div class="p-3 bg-light rounded-3 border h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-xs text-uppercase font-weight-bold text-secondary">Threshold Alarms</span>
                                                <span class="badge px-2 py-0.5 text-xxs"
                                                    :class="(historySummary.total_incidents || 0) > 0 ? 'bg-danger text-white' : 'bg-success text-white'">
                                                    {{ (historySummary.total_incidents || 0) > 0 ? 'Alarms Triggered' : 'Normal' }}
                                                </span>
                                            </div>
                                            <h3 class="mb-0 font-weight-bolder mt-1" :class="(historySummary.total_incidents || 0) > 0 ? 'text-danger' : 'text-success'">
                                                {{ historySummary.total_incidents || 0 }}
                                            </h3>
                                            <div class="mt-2 text-xxs text-secondary border-top pt-2">
                                                <p class="mb-1">Monitored thresholds: CPU &gt; 80% or RAM &gt; 85%.</p>
                                                <span v-if="(historySummary.total_incidents || 0) === 0" class="text-success font-weight-bold">
                                                    &check; No resource spikes recorded in this timeframe.
                                                </span>
                                                <span v-else class="text-danger font-weight-bold">
                                                    &excl; High utilization spikes detected. See log below.
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dual Interactive Timeline Charts -->
                                <div class="row">
                                    <!-- CPU Utilization Chart -->
                                    <div class="col-lg-6 mb-4">
                                        <div class="border rounded-3 p-3 h-100 bg-white shadow-none">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="text-sm font-weight-bold mb-0 d-flex align-items-center">
                                                    <span class="badge bg-primary me-2">CPU %</span>
                                                    CPU Utilization &amp; System Load Timeline
                                                </h6>
                                                <span class="badge bg-light text-danger border text-xxs">Threshold: 80%</span>
                                            </div>
                                            <div style="height: 260px; position: relative;">
                                                <canvas id="chart-history-cpu"></canvas>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Memory Utilization Chart -->
                                    <div class="col-lg-6 mb-4">
                                        <div class="border rounded-3 p-3 h-100 bg-white shadow-none">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="text-sm font-weight-bold mb-0 d-flex align-items-center">
                                                    <span class="badge bg-warning me-2 text-dark">RAM %</span>
                                                    Memory &amp; Swap Consumption Timeline
                                                </h6>
                                                <span class="badge bg-light text-warning border text-xxs">Threshold: 85%</span>
                                            </div>
                                            <div style="height: 260px; position: relative;">
                                                <canvas id="chart-history-memory"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Top Workloads Breakdown (AWS Compute Optimizer Style) -->
                                <div v-if="topWorkloads.length > 0" class="mt-3 mb-4">
                                    <h6 class="text-xs text-uppercase font-weight-bold text-secondary mb-2 d-flex align-items-center">
                                        <i class="material-symbols-rounded text-sm me-1 text-primary">bar_chart</i>
                                        Top Workloads Observed During This Period
                                    </h6>
                                    <div class="table-responsive border rounded-3 bg-white">
                                        <table class="table align-items-center mb-0 text-sm">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="text-xxs text-secondary text-uppercase ps-3">Workload / Process</th>
                                                    <th class="text-xxs text-secondary text-uppercase">User</th>
                                                    <th class="text-xxs text-secondary text-uppercase">Peak CPU %</th>
                                                    <th class="text-xxs text-secondary text-uppercase">Peak Memory %</th>
                                                    <th class="text-xxs text-secondary text-uppercase">Snapshots Recorded</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(wl, idx) in topWorkloads" :key="idx">
                                                    <td class="ps-3 text-xs font-weight-bold">
                                                        <code class="text-dark">{{ wl.command }}</code>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border">{{ wl.user }}</span></td>
                                                    <td>
                                                        <span class="badge" :class="wl.max_cpu > 50 ? 'bg-danger' : 'bg-success'">
                                                            {{ wl.max_cpu }}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge" :class="wl.max_memory > 50 ? 'bg-warning text-dark' : 'bg-info'">
                                                            {{ wl.max_memory }}%
                                                        </span>
                                                    </td>
                                                    <td class="text-xs text-secondary">{{ wl.count }} times observed</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- High Resource Spike Incidents Log -->
                                <div v-if="incidents.length > 0" class="mt-3">
                                    <h6 class="text-xs text-uppercase font-weight-bold text-danger mb-2 d-flex align-items-center">
                                        <i class="material-symbols-rounded text-sm me-1">warning</i>
                                        Incident Log (Threshold Violations)
                                    </h6>
                                    <div class="table-responsive border rounded-3 bg-white">
                                        <table class="table align-items-center mb-0 text-sm">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="text-xxs text-secondary text-uppercase ps-3">Time</th>
                                                    <th class="text-xxs text-secondary text-uppercase">CPU</th>
                                                    <th class="text-xxs text-secondary text-uppercase">RAM</th>
                                                    <th class="text-xxs text-secondary text-uppercase">1m Load</th>
                                                    <th class="text-xxs text-secondary text-uppercase">User</th>
                                                    <th class="text-xxs text-secondary text-uppercase">Offending Process</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(inc, idx) in incidents" :key="idx">
                                                    <td class="ps-3 text-xs font-weight-bold">{{ inc.time }}</td>
                                                    <td><span class="badge bg-danger">{{ inc.cpu }}%</span></td>
                                                    <td><span class="badge bg-warning text-dark">{{ inc.memory }}%</span></td>
                                                    <td class="text-xs font-weight-bold">{{ inc.load }}</td>
                                                    <td><span class="badge bg-light text-secondary border">{{ inc.user }}</span></td>
                                                    <td class="text-xs text-break"><code>{{ inc.process }}</code></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
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
import { Head } from '@inertiajs/vue3'
import MainLayout from '@/Layouts/MainLayout.vue'
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import Chart from 'chart.js/auto'

const loading = ref(true)
const data = ref({
    cpu: {},
    memory: {},
    disk: [],
    load: {},
    uptime: {},
    network: [],
    processes: []
})

// Historical report states
const selectedRange = ref('24h')
const selectedDate = ref(new Date().toISOString().split('T')[0])
const historySummary = ref({})
const reportMetadata = ref({})
const incidents = ref([])
const topWorkloads = ref([])
const availableDays = ref([])
const pointsCount = ref(0)

let historyCpuChart = null
let historyMemoryChart = null
let refreshInterval = null

const maxSelectableDate = computed(() => {
    return new Date().toISOString().split('T')[0]
})

const minSelectableDate = computed(() => {
    const d = new Date()
    d.setDate(d.getDate() - 30)
    return d.toISOString().split('T')[0]
})

const getCpuColor = (usage) => {
    if (usage > 80) return 'text-danger'
    if (usage > 60) return 'text-warning'
    return 'text-success'
}

const getMemoryColor = (usage) => {
    if (usage > 85) return 'text-danger'
    if (usage > 70) return 'text-warning'
    return 'text-success'
}

const handleVisibilityChange = () => {
    if (typeof document !== 'undefined' && !document.hidden) {
        loadUsage()
        loadHistory()
    }
}

onMounted(async () => {
    await loadUsage()
    await loadHistory()
    // Auto-refresh real-time metrics every 5 seconds (matched with dashboard)
    refreshInterval = setInterval(loadUsage, 5000)
    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleVisibilityChange)
    }
})

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval)
    if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', handleVisibilityChange)
    }
    destroyHistoryCharts()
})

const refreshAll = async () => {
    loading.value = true
    await Promise.all([loadUsage(), loadHistory()])
    loading.value = false
}

const loadUsage = async () => {
    if (typeof document !== 'undefined' && document.hidden) return

    try {
        const response = await axios.get('/resources/usage')
        if (response.data.success) {
            data.value = response.data.data
        }
    } catch (error) {
        console.error('Failed to load resource usage:', error)
    } finally {
        loading.value = false
    }
}

const loadHistory = async () => {
    try {
        const params = {
            range: selectedRange.value,
        }
        if (selectedRange.value === 'day' && selectedDate.value) {
            params.date = selectedDate.value
        }

        const response = await axios.get('/resources/history', { params })
        if (response.data.success) {
            historySummary.value = response.data.summary || {}
            reportMetadata.value = response.data.report_metadata || {}
            incidents.value = response.data.incidents || []
            topWorkloads.value = response.data.top_workloads || []
            availableDays.value = response.data.available_days || []
            pointsCount.value = response.data.points?.length || 0

            if (response.data.selected_date) {
                selectedDate.value = response.data.selected_date
            }

            await nextTick()
            renderHistoryCharts(response.data.points || [])
        }
    } catch (error) {
        console.error('Failed to load resource history report:', error)
    }
}

const changeRange = async (range) => {
    if (selectedRange.value === range) return
    selectedRange.value = range
    await loadHistory()
}

const onDateSelected = async () => {
    selectedRange.value = 'day'
    await loadHistory()
}

const selectQuickDay = async (daysAgo) => {
    selectedRange.value = 'day'
    if (availableDays.value.length > daysAgo) {
        selectedDate.value = availableDays.value[daysAgo].date
    } else {
        const d = new Date()
        d.setDate(d.getDate() - daysAgo)
        selectedDate.value = d.toISOString().split('T')[0]
    }
    await loadHistory()
}

const printReport = () => {
    window.print()
}

const destroyHistoryCharts = () => {
    if (historyCpuChart) {
        historyCpuChart.destroy()
        historyCpuChart = null
    }
    if (historyMemoryChart) {
        historyMemoryChart.destroy()
        historyMemoryChart = null
    }
}

const renderHistoryCharts = (points) => {
    destroyHistoryCharts()
    if (!points || points.length === 0) return

    const labels = points.map(p => p.time)
    const cpuData = points.map(p => p.cpu)
    const loadData = points.map(p => p.load)
    const memData = points.map(p => p.memory)
    const swapData = points.map(p => p.swap_used_mb || 0)

    const ctxCpu = document.getElementById('chart-history-cpu')
    if (ctxCpu) {
        historyCpuChart = new Chart(ctxCpu, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'CPU Utilization (%)',
                        data: cpuData,
                        borderColor: '#e53e3e',
                        backgroundColor: 'rgba(229, 62, 62, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: points.length > 60 ? 0 : 2,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        yAxisID: 'y',
                    },
                    {
                        label: '1m System Load',
                        data: loadData,
                        borderColor: '#3182ce',
                        backgroundColor: 'transparent',
                        borderDash: [4, 4],
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 2,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: (items) => points[items[0].dataIndex]?.full_time || items[0].label,
                            label: (item) => `${item.dataset.label}: ${item.raw}${item.datasetIndex === 0 ? '%' : ''}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 12, font: { size: 10 } }
                    },
                    y: {
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'CPU %', font: { size: 11 } },
                        ticks: { callback: (val) => val + '%' }
                    },
                    y1: {
                        min: 0,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Load Avg', font: { size: 11 } }
                    }
                }
            }
        })
    }

    const ctxMem = document.getElementById('chart-history-memory')
    if (ctxMem) {
        historyMemoryChart = new Chart(ctxMem, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'RAM Utilization (%)',
                        data: memData,
                        borderColor: '#dd6b20',
                        backgroundColor: 'rgba(221, 107, 32, 0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: points.length > 60 ? 0 : 2,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Swap Used (MB)',
                        data: swapData,
                        borderColor: '#805ad5',
                        backgroundColor: 'transparent',
                        borderDash: [3, 3],
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 1.5,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: (items) => points[items[0].dataIndex]?.full_time || items[0].label,
                            label: (item) => {
                                const pt = points[item.dataIndex]
                                if (item.datasetIndex === 0) {
                                    return `RAM: ${item.raw}% (${Math.round(pt?.memory_used_mb || 0)} MB / ${Math.round(pt?.memory_total_mb || 0)} MB)`
                                }
                                return `Swap: ${item.raw} MB`
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 12, font: { size: 10 } }
                    },
                    y: {
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'RAM %', font: { size: 11 } },
                        ticks: { callback: (val) => val + '%' }
                    },
                    y1: {
                        min: 0,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Swap MB', font: { size: 11 } }
                    }
                }
            }
        })
    }
}
</script>

<style scoped>
.spin-anim {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media print {
    #sidenav-main, .navbar, .btn, .d-print-none {
        display: none !important;
    }
    #aws-report-container {
        margin-top: 0 !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>
