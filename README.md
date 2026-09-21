<div align="center">

# ☁️ Nimbus Control Panel

### Next-Generation Cloud Server Management for Modern Web Applications

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-Proprietary-blue?style=for-the-badge)](#)

<br>

**Nimbus** is an ultra-fast, modern, and secure web hosting control panel engineered for Ubuntu and Debian servers. Designed from the ground up with Laravel 12 and Vue 3 (Inertia.js), Nimbus replaces legacy, clunky panels with a sleek glassmorphic interface, enterprise security, multi-PHP isolation, native mail services, and automated CI/CD workflows.

<br>

[Key Features](#-key-features) •
[Security & Shield](#-nimbus-shield--security) •
[Mail & Webmail](#-mail--webmail-stack) •
[Database Designer](#-native-database-manager--schema-designer) •
[Backups](#-multi-destination-cloud-backups) •
[Installation](#-installation) •
[CLI Flags](#-installation-flags) •
[Uninstallation](#-uninstallation)

</div>

---

## ⚡ Key Features

<table>
<tr>
<td width="50%">

### 🌐 High-Performance Web & Domains
- **Nginx Native:** Automated Virtual Host provisioning, HTTP/2 & HTTP/3 readiness, FastCGI caching, and WebSocket reverse-proxying.
- **Multi-PHP Engine:** Run PHP **8.1, 8.2, 8.3, and 8.4** simultaneously.
- **Isolated FPM Pools:** Every domain runs under its own dedicated FPM pool (`/etc/php/{version}/fpm/pool.d/{domain}.conf`) for strict performance and security isolation.
- **One-Click SSL:** Automated Let's Encrypt certificate issuance, background queue renewals, and intelligent HTTP-01 pre-probing.

</td>
<td width="50%">

### 📁 Elite File Manager
- **Ace Editor Pro:** Full-screen code editing with syntax highlighting for 50+ languages, code folding, and auto-completion.
- **Smart Tail Preview:** High-speed, memory-efficient chunked reader for viewing massive log files without browser freezing.
- **UTF-8 Sanitizer:** Clean text streaming that strips invalid byte sequences and avoids JSON encoding crashes.
- **File Operations:** Visual permissions editor (`chmod`/`chown`), ZIP/TAR extraction & creation, deep content search.

</td>
</tr>
<tr>
<td width="50%">

### 🌿 Git CI/CD & Deployments
- **Zero-Downtime Deployments:** Automated Webhooks for GitHub, GitLab, and Bitbucket with custom deployment hooks (`deploy.sh`).
- **Encrypted Token Vault:** Store Personal Access Tokens (PAT) and SSH deploy keys securely.
- **Branch Management:** Switch branches, review commit logs, inspect line-by-line git diffs, and push/pull directly from the UI.

</td>
<td width="50%">

### 🗄️ Native Database Studio
- **No phpMyAdmin Required:** Complete in-panel database administration with zero third-party overhead.
- **Visual Schema Designer:** Inspect tables, view columns, examine foreign keys, and optimize indexes.
- **Interactive SQL Runner:** Execute queries with execution timers, pagination, and one-click CSV data export.

</td>
</tr>
<tr>
<td width="50%">

### ⚡ Background Workers & Cron
- **Supervisor Manager:** Visual dashboard to start, stop, restart, and scale background queue processes and daemon workers.
- **Visual Cron Scheduler:** Create and manage cron jobs with human-friendly frequency builders and execution output logs.

</td>
<td width="50%">

### 👥 Multi-Tenancy & RBAC
- **Tiered Role Architecture:** Super Admin (`root`), Tenant Administrator (`admin`), and Site Developer (`user`).
- **Strict Tenant Boundaries:** Developers only see domains, databases, and cron jobs assigned to them.

</td>
</tr>
</table>

---

## 🛡️ Nimbus Shield & Security

Nimbus treats server security as a first-class priority, packaging enterprise-grade defensive layers directly into the panel:

```
                  ┌────────────────────────────────────────────────────────┐
                  │                 NIMBUS SHIELD DEFENSE                  │
                  └────────────────────────────────────────────────────────┘
                                              │
         ┌───────────────────────────┼───────────────────────────┐
         ▼                           ▼                           ▼
 ┌───────────────┐           ┌───────────────┐           ┌───────────────┐
 │ ClamAV Engine │           │ Fail2Ban Core │           │ UFW Firewall  │
 │ Live Antivirus│           │ Brute Force   │           │ Dynamic Rule  │
 │ & Quarantine  │           │ Protection    │           │ Management    │
 └───────────────┘           └───────────────┘           └───────────────┘
         │                           │                           │
         └───────────────────────────┼───────────────────────────┘
                                     │
                                     ▼
                  ┌─────────────────────────────────────┐
                  │      Hardened Linux POSIX ACLs      │
                  │  Per-domain user & folder isolation │
                  │     Systemd Protected ReadPaths     │
                  └─────────────────────────────────────┘
```

* **ClamAV Real-Time & Scheduled Antivirus:** Run on-demand malware sweeps or schedule automated daily/weekly scans across `/var/www`. Suspicious files can be safely inspected or isolated in the quarantine vault.
* **Fail2Ban Intrusion Prevention:** Live tracking of failed authentication attempts. Monitors SSH, Nginx, Postfix, and Dovecot. View currently banned IP addresses, ban duration, and unban IPs with a single click.
* **Visual UFW Firewall:** Define inbound and outbound firewall rules (Allow, Deny, Reject, Rate Limit) for standard ports or custom ranges without typing manual terminal commands.
* **Hardened POSIX ACLs (Cross-Site Isolation):** Each project runs under its own dedicated unprivileged system user. Strict POSIX Access Control Lists prevent web scripts from traversing neighboring sites or reading sensitive `.env` configurations across directories.
* **Systemd Path Hardening:** Nimbus background services are strictly sandboxed with `ReadWritePaths` overrides, protecting critical host directories (`/etc/postfix`, `/etc/dovecot`, `/etc/opendkim`, `/etc/roundcube`).

---

## ✉️ Mail & Webmail Stack

Transform your server into a full-featured, high-deliverability mail server with complete DNS automation:

* **Postfix & Dovecot Integration:** Enterprise SMTP/IMAP services with support for custom mailboxes, forwarders, wildcards, and disk quotas.
* **Automated DKIM, SPF & DMARC:** Generate 2048-bit OpenDKIM private/public keypairs per domain with one click.
* **Cloudflare DNS Synchronization:** Connect your Cloudflare API token to automatically push MX records, SPF TXT records, DKIM public keys, and DMARC policies straight to your DNS zone in seconds.
* **Roundcube Webmail Integration:** Seamless webmail client pre-configured and accessible via secure HTTPS (`mail.yourdomain.com/webmail`).

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

#### Example: Preserving Existing LAMP/LEMP Stack on Custom Port
```bash
curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/install.sh | sudo bash -s -- --skip-existing --port=8443 --license=NIMBUS-PRO-XXXX
```

---

## 📦 What Gets Installed

| Component | Version / Engine | Role |
|-----------|------------------|------|
| **Nginx** | Latest Mainline/Stable | High-performance Reverse Proxy & Web Server |
| **PHP** | 8.3 (Default), 8.1, 8.2, 8.4 available | Multi-PHP Runtime & FastCGI Process Managers |
| **MariaDB** | 10.11+ / Latest LTS | Primary Relational Database Engine |
| **Node.js & NPM** | Node 20 LTS | Asset building and frontend rendering |
| **Supervisor** | Latest | Process supervisor for queue workers & daemons |
| **Fail2Ban** | Latest | Brute force defense for SSH, Web & Mail |
| **ClamAV** | Latest | Antivirus scanner daemon (`clamd`) |
| **Postfix & Dovecot** | Latest | SMTP Mail Transfer Agent & IMAP/POP3 Delivery |
| **OpenDKIM** | Latest | Cryptographic email signing daemon |
| **Composer** | Latest 2.x | PHP dependency manager |

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

> ⚠️ **Caution:** Modes 1 and 2 permanently drop all databases. Always create a verified backup before running a full wipe.

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
