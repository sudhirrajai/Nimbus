# Nimbus Control Panel - Complete Server Architecture Reference

This document outlines the architecture, security isolation model, process execution boundaries, database structure, and background monitoring engine configured across the server (`66.116.204.19`) and automated by `install.sh`.

---

## 1. System Topology & Process Hierarchy

```
                       [ Incoming Web Requests ]
                                   │
                                   ▼
                 ┌───────────────────────────────────┐
                 │          Nginx (Reverse)          │
                 │          User: www-data           │
                 │         Ports: 80, 443            │
                 └─────────────────┬─────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐
│  Nimbus Panel    │      │  crm.vmcore.in   │      │  Other Sites...  │
│  Port: 2095/8443 │      │  Domain Tenant   │      │  Isolated Pool   │
│  User: www-data  │      │  User:site_crm_vm│      │  User: site_*    │
└────────┬─────────┘      └────────┬─────────┘      └────────┬─────────┘
         │                         │                         │
         ▼                         ▼                         ▼
┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐
│ SQLite (Isolated)│      │ MySQL / MariaDB  │      │ MySQL / MariaDB  │
│ /usr/local/nimbus│      │ User: site_crm   │      │ User: site_*     │
└──────────────────┘      └──────────────────┘      └──────────────────┘
```

---

## 2. Security Isolation Architecture

### A. Linux Multi-Tenant Users & POSIX ACLs
- **Dedicated System Accounts**: Every hosted domain is bound to an isolated Linux service account:
  - Format: `site_<slug>` (e.g. `site_crm_vm`, `site_vmcore`, `site_vidhzy`).
  - Shell: `/usr/sbin/nologin` (no interactive login capability).
- **Filesystem Permissions**:
  - Site root: `/var/www/<domain>/` owned by `site_<slug>:site_<slug>` with permissions `chmod 750` (`drwxr-x---`).
  - Strict Cross-Tenant Defense: `other::---` ensures tenants cannot traverse or read each other's project directories.
  - **Nginx Traverse Access via POSIX ACLs**:
    ```bash
    setfacl -R -m u:www-data:rx /var/www/<domain>
    setfacl -R -d -m u:www-data:rx /var/www/<domain>
    ```
    Allows Nginx (`www-data`) to read public static files (`.css`, `.js`, images) and dispatch FastCGI scripts without triggering `13: Permission denied` (404/403).
- **Secret File Isolation (`.env`)**:
  - `chmod 600 /var/www/<domain>/.env`
  - All extended ACLs are explicitly stripped: `setfacl -b /var/www/<domain>/.env`.
  - Only the site's own user (e.g. `site_crm_vm`) can read database passwords and app secrets.

---

### B. PHP-FPM Process Boundaries
- **Dedicated Ondemand Pools**:
  - Located in `/etc/php/<version>/fpm/pool.d/<slug>.conf`.
  - Unix Socket: `/run/php/php<version>-fpm-<slug>.sock` (owned by `www-data:www-data`, mode `0660`).
  - Runs as: `user = site_<slug>`, `group = site_<slug>`.
  - Process Manager: `pm = ondemand`, `pm.max_children = 3`, `pm.process_idle_timeout = 10s` (reclaims 100% RAM when idle).
- **Filesystem Confinement (`open_basedir`)**:
  ```ini
  php_admin_value[open_basedir] = /var/www/<domain>/:/tmp/:/dev/urandom
  ```
  Prevents PHP scripts from accessing files outside their own website directory even in the event of a remote code execution vulnerability.
- **Dangerous Functions Disabled**:
  ```ini
  php_admin_value[disable_functions] = exec,system,passthru,shell_exec,proc_open,popen,dl,show_source
  ```

---

## 3. Database Architecture

### A. Nimbus Control Panel Database
- **Engine**: SQLite (`/usr/local/nimbus/database/database.sqlite`).
- **Permissions**: `chmod 660`, owned by `www-data:www-data`, parent folder `chmod 750`.
- Completely decoupled from external MySQL services to prevent panel lockout during database maintenance.

### B. Hosted Site Databases
- **Engine**: MySQL / MariaDB (Port 3306 or socket).
- **Isolated Credentials**: Each site connects using its dedicated database user (`site_*`).
- **Resource Protections**: Users are capped with `MAX_USER_CONNECTIONS` to avoid database thread pool exhaustion.

---

## 4. 1-Month Resource Usage History Engine

```
[ /etc/cron.d/nimbus ]
        │  Runs every 5 min
        ▼
[ php artisan nimbus:collect-metrics ]
        │  Captures CPU, RAM, Disk, Load, Top 5 Processes
        ▼
[ server_metrics table ] (30-day retention)
        │  Auto-pruned after 30 days
        ▼
[ ResourceController@getHistory ]
        │  Downsamples (5m for 24h, 1h for 7d, 4h for 30d)
        │  Converts timestamps to Asia/Kolkata
        ▼
[ Resources/Index.vue Chart.js UI ]
```

- **Cron Schedule**:
  ```cron
  * * * * * root cd /usr/local/nimbus && php artisan schedule:run >> /dev/null 2>&1
  */5 * * * * root cd /usr/local/nimbus && php artisan nimbus:collect-metrics >> /dev/null 2>&1
  ```
- **Alert Levels**: Records CPU > 80% or RAM > 85% as incident events, recording top culprit processes (`command`, `pid`, `user`, `cpu%`, `mem%`).
- **False-Alarm Mitigation**: Memory usage calculation excludes filesystem buffers and cache (`used = total - free - buffers - cached`).

---

## 5. Timezone Localization Model

- **Storage Standard**: Database timestamps are stored in **UTC**.
- **Display Localization**:
  - Setting key `timezone` in `settings` table set to `Asia/Kolkata`.
  - Shared via Inertia in `HandleInertiaRequests.php` (`server_info.timezone` and `page.props.timezone`).
  - Frontend uses centralized utility in `resources/js/Utils/date.js` (`formatDate`, `formatDateTime`, `formatTime`).
  - Live clock in top navigation capsule displays server time localized to `Asia/Kolkata` (`IST`).

---

## 6. Directory Layout Reference

| Path | Purpose | Ownership / Mode |
|---|---|---|
| `/usr/local/nimbus/` | Nimbus panel application | `www-data:www-data` (755/644) |
| `/usr/local/nimbus/database/` | SQLite database | `www-data:www-data` (750/660) |
| `/var/www/<domain>/` | Hosted website root | `site_<slug>:site_<slug>` (750 + ACL `u:www-data:rx`) |
| `/var/www/<domain>/.env` | App secrets | `site_<slug>:site_<slug>` (600, no ACLs) |
| `/etc/nginx/sites-available/` | Nginx vhost configs | `root:root` (644) |
| `/etc/php/<version>/fpm/pool.d/` | Isolated PHP-FPM pools | `root:root` (644) |
| `/run/php/` | PHP FastCGI Unix sockets | `www-data:www-data` (660) |
| `/etc/cron.d/nimbus` | System crons | `root:root` (644) |

---

## 7. Deployment & Automation

The installer script (`install.sh`) has been updated to automatically apply:
1. Multi-tenant ACL configuration on `/var/www/`.
2. Dedicated `nimbus:collect-metrics` cron entry.
3. Default `Asia/Kolkata` timezone seeding.
4. Clean, isolated permissions for Nginx and PHP-FPM services.
