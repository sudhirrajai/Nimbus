<div align="center">

# ☁️ Nimbus Control Panel

### Next-Generation Cloud Server Management for Modern Web Applications

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Redis](https://img.shields.io/badge/Redis-In--Memory-DC382D?style=for-the-badge&logo=redis&logoColor=white)](https://redis.io)
[![Nginx](https://img.shields.io/badge/Nginx-High--Performance-009639?style=for-the-badge&logo=nginx&logoColor=white)](https://nginx.org)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.11+-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org)
[![License](https://img.shields.io/badge/License-Proprietary-blue?style=for-the-badge)](#)

<br>

**Nimbus** is an ultra-fast, modern, and secure web hosting control panel engineered for Ubuntu and Debian servers. Built from the ground up with **Laravel 12** and **Vue 3 (Inertia.js)**, Nimbus replaces legacy, clunky hosting panels with a sleek glassmorphic interface, enterprise-grade security, isolated multi-PHP environments, native database designer, in-browser terminal, WordPress toolkit, Redis management suite, mail stack, and automated CI/CD workflows.

<br>

[Key Features](#-key-features) •
[Architecture](#-system-architecture) •
[Security & Shield](#-nimbus-shield--security) •
[Redis Suite](#-redis-in-memory-suite) •
[WordPress Toolkit](#-wordpress-management-toolkit) •
[Database Studio](#-native-database-studio--schema-designer) •
[Mail & Webmail](#-mail--webmail-stack) •
[Resource Analytics](#-cloud-grade-resource-monitoring) •
[Cloud Backups](#-multi-destination-cloud-backups) •
[OTA Updates](#-over-the-air-ota-updates) •
[Installation](#-installation) •
[Uninstallation](#-uninstallation)

</div>

---

## ⚡ Key Features

<table>
<tr>
<td width="50%">

### 🌐 High-Performance Web & Virtual Hosts
- **Nginx Native:** Automated Virtual Host provisioning, HTTP/2 & HTTP/3 readiness, FastCGI caching, and WebSocket reverse-proxying.
- **Config Editor & Live Linting:** In-panel Nginx vhost editor with real-time `nginx -t` validation and hot-reload.
- **Project Power Switch:** One-click domain suspension/freeze with a custom 503 maintenance page, halting FPM workers, crons, and processes to reclaim RAM.
- **One-Click SSL:** Automated Let's Encrypt certificate issuance, background renewals, and HTTP-01 challenge pre-probing.

</td>
<td width="50%">

### 🐘 Multi-PHP Engine & Extensions
- **Multi-Version Runtime:** Run PHP **8.1, 8.2, 8.3, and 8.4** concurrently without cross-version interference.
- **Dedicated FPM Pools:** Every domain runs under an isolated pool (`/etc/php/{version}/fpm/pool.d/{domain}.conf`) with custom user permissions.
- **1-Click Extension Switcher:** Toggle `redis`, `imagick`, `curl`, `mbstring`, `intl`, `bcmath`, `gd`, `pdo_mysql`, `zip`, and `opcache`.
- **PHP.ini Tuner:** Tune memory limits, timeouts, and file upload limits with automated Nginx `client_max_body_size` syncing.

</td>
</tr>
<tr>
<td width="50%">

### 📁 Elite File Manager & Web Terminal
- **Monaco/Ace Editor Pro:** Full-screen code editing with syntax highlighting for 50+ languages, code folding, and auto-completion.
- **In-Browser Web Terminal:** Secure Linux shell embedded directly within the File Manager for rapid command execution.
- **Smart Chunked Log Viewer:** High-speed streaming reader for multi-gigabyte server logs without browser freezing.
- **Archive & Permissions:** Visual POSIX permission manager (`chmod`/`chown`), ZIP/TAR.GZ extraction & creation, deep content search.

</td>
<td width="50%">

### 🗄️ Native Database Studio & Designer
- **Zero phpMyAdmin Overhead:** 100% native database administration built directly into the control panel.
- **Visual Schema Designer:** Interactive ER diagram viewer with column types, foreign keys, and index relationships.
- **Interactive SQL Runner:** Query executor with millisecond execution timers, pagination, and one-click CSV export.
- **Full Schema Operations:** Add/drop columns, edit keys, truncate/drop tables, and export/import gzipped SQL dumps.

</td>
</tr>
<tr>
<td width="50%">

### 🔴 Redis In-Memory Suite
- **1-Click Redis Provisioning:** Automated installation, status monitoring, and systemd service control (start, stop, restart).
- **Interactive Keyspace Explorer:** Browse and search keys by pattern, view data structures (String, Hash, List, Set, ZSet), TTLs, and serialized values.
- **Pattern Flusher & DB Wipe:** Safe pattern-based bulk key deletion and per-database cache flush.
- **Live Slowlog & Client Monitor:** Inspect slow queries and active client connection metrics.
- **Memory & Security Tuning:** Configure `maxmemory`, eviction policies (`volatile-lru`, `allkeys-lru`), and requirepass auth.

</td>
<td width="50%">

### 🧰 WordPress Management Toolkit
- **Instant WordPress Scanner:** Detects and links existing WordPress installations automatically.
- **1-Click WordPress Deployer:** Provisions database, downloads core, installs WP-CLI, and configures `wp-config.php`.
- **1-Click WP-Admin Auto-Login:** Secure passwordless access into WordPress Admin via single-use signed tokens.
- **Core & Plugin Manager:** View plugin health, toggle active/inactive state, update plugins, and update WordPress core.
- **Emergency Tools:** Administrator password reset and clean site reinstallation.

</td>
</tr>
<tr>
<td width="50%">

### ☁️ Cloudflare DNS Integration
- **Direct Zone Sync:** Manage Cloudflare DNS records directly from the Nimbus UI using secure API tokens.
- **Full Record CRUD:** Create, edit, and delete A, AAAA, CNAME, MX, TXT, SRV, and CAA records.
- **1-Click Mail DNS Deployment:** Automatically push MX, SPF, DKIM, and DMARC records to Cloudflare with one click.

</td>
<td width="50%">

### 📊 AWS-Grade Resource Analytics
- **Historical System Telemetry:** 30-day historical time-series graphs for CPU, RAM, Disk I/O, and Load Average (1h, 6h, 24h, 7d, 30d).
- **Per-Project Resource Attribution:** Real-time CPU, RAM, and active Linux process attribution per domain.
- **Peak Consumer Detection:** Identifies which website or background daemon consumed peak memory over selected time ranges.

</td>
</tr>
<tr>
<td width="50%">

### 🌿 Git CI/CD & Deployments
- **Zero-Downtime Deployments:** Automated webhooks for GitHub, GitLab, and Bitbucket with custom deployment hooks (`deploy.sh`).
- **Encrypted Token Vault:** Securely store Personal Access Tokens (PAT) and SSH deploy keys.
- **Branch Management:** Switch branches, review commit history, inspect line-by-line git diffs, and pull/push directly from the UI.

</td>
<td width="50%">

### ⚡ Background Workers & Cron
- **Supervisor Manager:** Visual dashboard to start, stop, restart, and scale background queue processes and daemon workers.
- **Visual Cron Scheduler:** Create and manage cron jobs with human-friendly frequency builders and execution output logs.

</td>
</tr>
<tr>
<td width="50%">

### ✉️ Mail & Webmail Stack
- **Postfix & Dovecot Integration:** Enterprise SMTP/IMAP services with custom mailboxes, forwarders, wildcards, and disk quotas.
- **Cryptographic DKIM, SPF & DMARC:** Generate 2048-bit OpenDKIM keypairs per domain with one click.
- **Modern In-Panel Webmail:** Clean, modern Vue/Inertia webmail client with SSO, rich-text composing, and folder management (Roundcube also supported).

</td>
<td width="50%">

### 👥 Multi-Tenancy & RBAC
- **Tiered Role Architecture:** Super Admin (`root`), Tenant Administrator (`admin`), and Site Developer (`user`).
- **Granular Permissions:** Restrict access per module (Domains, Files, Deployments, Databases, SSL, WordPress, DNS, Supervisor, Cron, Backups).
- **Activity Audit Trail:** Complete audit logging of all panel actions, IP addresses, and timestamps with automatic retention pruning.

</td>
</tr>
</table>

---

## 🏛️ System Architecture

Nimbus uses a completely isolated dual-service design that decouples the panel from hosted tenant applications:

```
                    ┌───────────────────────────────────────────────┐
                    │               CLIENT REQUESTS                 │
                    └───────────────────────────────────────────────┘
                                   │                 │
                Port 2095 (Admin)  │                 │  Ports 80/443 (Websites)
                                   ▼                 ▼
          ┌───────────────────────────────┐   ┌───────────────────────────────┐
          │      nimbus-nginx.service     │   │         nginx.service         │
          │   Dedicated Panel Web Server  │   │     Public Reverse Proxy      │
          └───────────────────────────────┘   └───────────────────────────────┘
                         │                                   │
                         ▼                                   ▼
          ┌───────────────────────────────┐   ┌───────────────────────────────┐
          │    nimbus-php-fpm.service     │   │   php{8.1..8.4}-fpm.service   │
          │ Dedicated PHP 8.3 FPM Pool    │   │   Isolated Per-Site Pools     │
          └───────────────────────────────┘   └───────────────────────────────┘
                         │                                   │
                         ▼                                   ▼
          ┌───────────────────────────────┐   ┌───────────────────────────────┐
          │   SQLite Isolated Database    │   │  MariaDB / MySQL Server       │
          │   /usr/local/nimbus/database  │   │  Tenant Databases & Users     │
          └───────────────────────────────┘   └───────────────────────────────┘
                         │                                   │
                         └─────────────────┬─────────────────┘
                                           │
          ┌────────────────────────────────┴───────────────────────────────┐
          │                   SERVER DAEMONS & RUNTIME                     │
          │  • Redis Server (Cache/Store)      • ClamAV Antivirus          │
          │  • Supervisor (Queue Workers)      • Fail2Ban (Intrusion)      │
          │  • Postfix / Dovecot / OpenDKIM    • UFW Firewall Engine       │
          └───────────────────────────────────────────────────────────────┘
```

---

## 🛡️ Nimbus Shield & Security

Nimbus treats server security as an integral core foundation, embedding defensive layers directly into system operations:

* **ClamAV Real-Time & Scheduled Antivirus:** Run on-demand malware sweeps or schedule automated daily/weekly scans across `/var/www`. Suspicious files can be safely inspected or isolated in the quarantine vault.
* **Fail2Ban Intrusion Prevention:** Live tracking of failed authentication attempts. Monitors SSH, Nginx, Postfix, and Dovecot. View currently banned IP addresses, ban duration, and unban IPs with a single click.
* **Visual UFW Firewall:** Define inbound and outbound firewall rules (Allow, Deny, Reject, Rate Limit) for standard ports or custom ranges without typing manual terminal commands.
* **Hardened POSIX ACLs (Cross-Site Isolation):** Each project runs under its own dedicated unprivileged system user. Strict POSIX Access Control Lists prevent web scripts from traversing neighboring sites or reading sensitive `.env` configurations across directories.
* **Systemd Path Hardening:** Nimbus background services are sandboxed with `ReadWritePaths` overrides, protecting critical host directories (`/etc/postfix`, `/etc/dovecot`, `/etc/opendkim`, `/etc/roundcube`, `/etc/nimbus`).

---

## 🔴 Redis In-Memory Suite

Nimbus includes a native Redis management suite for high-performance caching and queues:

* **One-Click Deployment:** Install `redis-server` directly from the UI with automated systemd integration.
* **Keyspace Browser:** Live filtering and pagination of Redis keys across all logical databases (`db0` to `db15`).
* **Data Inspection:** Inspect raw strings, JSON payloads, hashes, sets, and lists with real-time TTL counters.
* **Cache Management:** Delete keys matching wildcard patterns (e.g. `cache:user:*`) or flush specific databases safely.
* **Slowlog & Metrics:** Real-time insight into slow queries, command processing rates, connected clients, and memory fragmentation.
* **Configuration Tuning:** Edit `maxmemory`, configure eviction policies (`volatile-lru`, `allkeys-lru`), and set authentication passwords without touching config files manually.

---

## 🧰 WordPress Management Toolkit

Manage WordPress sites at scale with dedicated WordPress-aware tools:

* **Site Detection & Scanner:** Automatically scans `/var/www/` for WordPress installations and indexes versions, plugins, and admin users.
* **1-Click Provisioning:** Choose a domain, set the site title, admin email, and credentials. Nimbus handles database creation, user privileges, WP-CLI downloads, and salting automatically.
* **Passwordless 1-Click Auto-Login:** Jump straight into the WordPress Admin dashboard without typing credentials via secure, time-limited single-use login tokens.
* **Core & Plugin Management:** View installed plugins, toggle activation states, update plugins, and perform major/minor WordPress core updates.
* **Troubleshooting:** Reset administrator credentials or perform a clean reinstall if a WordPress installation becomes corrupted.

---

## 🗄️ Native Database Studio & Schema Designer

Say goodbye to slow, bloated, and vulnerable third-party tools like phpMyAdmin. Nimbus features a lightning-fast native database suite:

* **Visual Schema Designer:** Interactive entity-relationship visualizer showing tables, primary keys, foreign key constraints, indexes, and column types.
* **Data Grid & Row Editor:** Paginated table browser with inline row editing, insertion, deletion, and quick search.
* **Interactive SQL Console:** Write and execute complex queries with execution time benchmarks, query history, and table auto-completion.
* **CSV Data Export:** Export table contents directly to CSV for reporting and data migration.
* **Gzipped Backups & Restore:** Export compressed SQL database dumps with one click or import `.sql` / `.sql.gz` dump files.
* **User & Privilege Management:** Create MySQL users, rotate passwords, assign database grants, and restrict host access (`localhost` or remote CIDR).

---

## ✉️ Mail & Webmail Stack

Transform your server into a full-featured, high-deliverability mail server with complete DNS automation:

* **Postfix & Dovecot Integration:** Enterprise SMTP/IMAP services with support for custom mailboxes, forwarders, wildcards, and disk quotas.
* **Automated DKIM, SPF & DMARC:** Generate 2048-bit OpenDKIM private/public keypairs per domain with one click.
* **Cloudflare DNS Synchronization:** Connect your Cloudflare API token to automatically push MX records, SPF TXT records, DKIM public keys, and DMARC policies straight to your DNS zone in seconds.
* **Modern In-Panel Webmail:** Built-in Vue/Inertia Webmail client with folder management, rich text composer, attachments, search, and Single Sign-On (SSO).
* **Roundcube Webmail Integration:** Optional Roundcube webmail client pre-configured and accessible via secure HTTPS (`mail.yourdomain.com/webmail`).

---

## 📊 Cloud-Grade Resource Monitoring

Gain complete visibility into server load, capacity, and tenant behavior:

* **Global System Telemetry:** High-resolution charts tracking CPU usage, memory utilization, disk space, and load averages.
* **Flexible Time Windows:** Seamlessly zoom between 1 Hour, 6 Hours, 24 Hours, 7 Days, and 30 Days of historical telemetry.
* **Per-Project Resource Attribution:** Real-time breakdown of memory consumption and CPU usage by individual website user processes.
* **Peak Consumer Detection:** Automatically pinpoints which project consumed the highest RAM during selected time ranges.
* **Active Process Inspector:** See the exact number of active Linux processes and workers running under each project's isolated user.

---

## 💾 Multi-Destination Cloud Backups

Protect your mission-critical applications with scheduled, redundant backup pipelines:

* **Supported Destinations:**
  * ☁️ **Google Drive** (OAuth2 token authentication & Service Account support)
  * 🪣 **Backblaze B2** (Native B2 API integration)
  * 📦 **Amazon S3 & S3-Compatible** (AWS S3, Cloudflare R2, Wasabi, MinIO, DigitalOcean Spaces)
  * 🖥️ **Local / NFS Storage** (Mounted drives and isolated storage directories)
* **Automated Retention & Pruning:** Define daily, weekly, or monthly retention rules (e.g. keep last 7 daily and 4 weekly backups). Old archives are automatically pruned to prevent disk saturation.
* **Full Stack Backup:** Captures complete Nginx vhost configs, database dumps (gzipped SQL), and compressed file trees.
* **One-Click Restore:** Restore full website files and database dumps directly from the panel.

---

## 🔄 Over-The-Air (OTA) Updates

Nimbus includes a safe, zero-downtime updates engine accessible from `/updates`:

* **Release Monitoring:** Panel periodically checks for new releases against GitHub repository releases or the VmCoreCentral API.
* **Changelog Inspector:** View formatted release notes, new feature highlights, and patch notes before initiating an upgrade.
* **Pre-Flight Snapshot:** Creates an automated backup of the panel files and SQLite database before touching any code.
* **Safe Migration & Cache Rebuild:** Runs database migrations (`php artisan migrate --force`), clears old caches, and compiles optimized configuration, route, and view caches.
* **Isolated Service Reload:** Automatically reloads `nimbus-nginx` and `nimbus-php-fpm` and restarts `nimbus-worker` supervisor queues to guarantee zero stale OPcache execution.

---

## 🚀 Installation

Nimbus features an intelligent, automated one-line installer that configures all necessary dependencies, system users, databases, and services.

### Quick Install

Run the following command on a clean **Ubuntu 22.04 / 24.04** or **Debian 11 / 12** server:

```bash
curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/install.sh | sudo bash
```

### Install with Instant License Activation

If you have your Nimbus license key ready from **VmCoreCentral**, pass it directly during installation:

```bash
curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/install.sh | sudo bash -s -- --license=YOUR_LICENSE_KEY
```

---

## ⚙️ Installation Flags

The installer supports flexible flags for automated and headless deployments:

| Flag | Description | Default |
|------|-------------|---------|
| `--license=<KEY>` / `-l <KEY>` | Automatically activate your license key during setup | `None` (prompts on UI) |
| `--skip-existing` | Skip re-installing packages that are already present (preserves existing web stacks) | `false` |
| `--port=<PORT>` / `-p <PORT>` | Set a custom HTTP port for the Nimbus control panel | `2095` |
| `--uninstall` | Run the uninstaller directly | `false` |

#### Example: Preserving Existing Stack on Custom Port
```bash
curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/install.sh | sudo bash -s -- --skip-existing --port=8443 --license=NIMBUS-PRO-XXXX
```

---

## 📦 What Gets Installed

| Component | Version / Engine | Role |
|-----------|------------------|------|
| **Nginx** | Latest Mainline/Stable | High-performance Reverse Proxy & Virtual Hosts |
| **PHP** | 8.3 (Default), 8.1, 8.2, 8.4 available | Multi-PHP Runtime & Isolated FastCGI Process Pools |
| **MariaDB** | 10.11+ / Latest LTS | Primary Relational Database Engine |
| **Redis** | Latest Stable | In-Memory Cache, Session & Key-Value Store |
| **Certbot** | Latest + Nginx Plugin | Automated Let's Encrypt SSL Issuance & Renewals |
| **Node.js & NPM** | Node 20 LTS | Asset building and frontend rendering |
| **Supervisor** | Latest | Process supervisor for queue workers & daemons |
| **Fail2Ban** | Latest | Brute force defense for SSH, Web & Mail |
| **ClamAV** | Latest | Antivirus scanner daemon (`clamd`) |
| **Postfix & Dovecot** | Latest | SMTP Mail Transfer Agent & IMAP/POP3 Delivery |
| **OpenDKIM** | Latest | Cryptographic email signing daemon |
| **Composer** | Latest 2.x | PHP dependency manager |
| **SQLite 3** | Latest | Dedicated isolated panel database |

---

## 🗑️ Uninstallation

If you ever need to decommission a node or remove Nimbus, run the interactive uninstaller:

```bash
curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/uninstall.sh | sudo bash
```

The uninstaller provides four distinct wipe levels:

| Mode | Nimbus Portal | System Services | Databases | Projects (`/var/www`) |
|:-----|:-------------:|:---------------:|:---------:|:---------------------:|
| **1. Full Wipe** | ❌ Removed | ❌ Removed | ❌ Dropped | ❌ Deleted |
| **2. Remove Services + Portal** | ❌ Removed | ❌ Removed | ❌ Dropped | ✅ Preserved |
| **3. Remove Services (Keep DB)** | ❌ Removed | ❌ Removed | ✅ Preserved | ✅ Preserved |
| **4. Remove Portal Only** | ❌ Removed | ✅ Active | ✅ Preserved | ✅ Preserved |

> ⚠️ **Caution:** Modes 1 and 2 permanently drop all databases and cache stores. Always create a verified backup before running a full wipe.

---

## 🛠️ Local Development

To contribute to or customize Nimbus:

```bash
# 1. Clone the repository
git clone https://github.com/sudhirrajai/Nimbus.git
cd Nimbus

# 2. Install dependencies
composer install
npm install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Migrate database
php artisan migrate

# 5. Build frontend assets & run dev server
npm run dev
php artisan serve --port=2095
```

---

## 📄 License

Nimbus Control Panel is proprietary software. All rights reserved. Distributed and managed through **VMCORE**.
