<div align="center">

# ☁️ Nimbus Control Panel

### Modern, Lightweight Server Management for Laravel Developers

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)](https://vuejs.org)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)

<br>

<img src="https://img.shields.io/badge/Status-Beta-orange?style=flat-square" alt="Status">
<img src="https://img.shields.io/badge/Version-1.0.0-green?style=flat-square" alt="Version">

<br><br>

**Nimbus** is a beautiful, intuitive web-based control panel for managing your Ubuntu/Debian servers. Built with Laravel and Vue.js, it provides a modern alternative to traditional panels like cPanel or Plesk.

[Features](#-features) •
[Installation](#-installation) •
[Screenshots](#-screenshots) •
[Roadmap](#-roadmap) •
[Contributing](#-contributing)

</div>

---

## ✨ Features

<table>
<tr>
<td width="50%">
 
### 🌐 Domain Management
- Create and manage domains
- Automatic Nginx configuration
- Virtual host management
- Directory browsing
- **Upcoming:** Apache & Caddy support

</td>
<td width="50%">

### 📁 File Manager (Elite)
- **Ace Editor Integration:** Pro syntax highlighting
- **In-depth Search:** Find files by name or content
- **Double-click Edit:** Seamless code editing
- Web-based browser with glassmorphic UI
- Permissions editor (chmod/chown)

</td>
</tr>
<tr>
<td width="50%">

### 🔒 SSL Certificates
- One-click Let's Encrypt SSL
- Live certificate probing
- Auto-renewal support
- HTTPS redirects

</td>
<td width="50%">

### 🗄️ Database Management
- Create MySQL/MariaDB databases
- User management with permissions
- phpMyAdmin integration
- **Roadmap:** Multi-DB (SQLite, Postgre, NoSQL)

</td>
</tr>
<tr>
<td width="50%">

### ⚡ Supervisor & Cron
- Queue worker management
- Process monitoring & logs
- Visual cron scheduler
- Human-readable schedules

</td>
<td width="50%">

### 🌿 Git & Deployment
- Full Git integration (Status, Commit, Push/Pull)
- Branch management & switching
- Personal Access Token (PAT) auth
- Repository path auto-detection

</td>
</tr>
<tr>
<td width="50%">

### 🛒 WordPress Manager
- Theme & Plugin management
- User control & Settings
- **Upcoming:** Quick One-click Install
- Auto-configuration

</td>
<td width="50%">

### 📊 Server Monitoring
- Real-time CPU, RAM, Disk usage
- Process list & controls
- **Upcoming:** Long-term resource history
- Network statistics

</td>
</tr>
</table>

### Additional Features

- 🔧 **PHP Configuration** - Edit PHP.ini settings visually
- 📝 **Log Viewer** - View Nginx, PHP, Laravel, and system logs
- 🔄 **Auto Updates** - One-click panel updates from GitHub
- 🎨 **Modern UI** - Beautiful Material Design interface
- 🔐 **Secure** - Built-in security headers and permissions

---

## 🚀 Installation

### One-Command Install

```bash
curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/install.sh | sudo bash
```

### Requirements

- Ubuntu 22.04+ or Debian 11+
- Minimum 1GB RAM
- Root access

### What Gets Installed

| Component | Version |
|-----------|---------|
| Nginx | Latest |
| PHP | 8.2 |
| MariaDB | Latest |
| Node.js | 20.x |
| Composer | Latest |
| Supervisor | Latest |

### After Installation

1. Access your panel at `http://YOUR_IP:2095`
2. Create your admin account
3. Start managing your server!

---

## 🗑️ Uninstallation

### One-Command Uninstall

```bash
curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/uninstall.sh | sudo bash
```

The uninstaller is interactive and will ask you to choose a removal mode:

| # | Mode | Portal | Services | Database | Projects (`/var/www`) |
|---|------|--------|----------|----------|-----------------------|
| 1 | **Full Uninstall** | ❌ Removed | ❌ Removed | ❌ Removed | ❌ Removed |
| 2 | **Remove Services + Portal** | ❌ Removed | ❌ Removed | ❌ Removed | ✅ Kept |
| 3 | **Remove Services (keep DB)** | ❌ Removed | ❌ Removed | ✅ Kept | ✅ Kept |
| 4 | **Remove Portal Only** | ❌ Removed | ✅ Kept | ✅ Kept | ✅ Kept |

> ⚠️ **Warning:** Modes 1 and 2 will permanently delete all MySQL/MariaDB databases. Back up your data before proceeding.

### What Each Mode Removes

**Services** include: Nginx, PHP-FPM, Node.js, Composer, and Supervisor.

- **Full Uninstall** — Complete wipe. Removes the panel, every installed service, all databases, and all hosted project files. Use this when decommissioning the server.
- **Remove Services + Portal (Keep Projects)** — Removes everything except the files in `/var/www`. Useful if you want to migrate project files to another server.
- **Remove Services except DB + Portal (Keep Projects)** — Keeps the database server running along with the project files. Ideal if you plan to export databases manually or migrate them later.
- **Remove Portal Only** — Only removes the Nimbus panel application and its Nginx vhost. All services (Nginx, PHP, MariaDB, etc.) remain running with their current configurations, and all projects stay intact. Use this if you want to stop using the panel UI but keep your server operational.

---

## 📸 Screenshots

<div align="center">

| Dashboard | Domain Management |
|-----------|-------------------|
| ![Dashboard](docs/screenshots/dashboard.png) | ![Domains](docs/screenshots/domains.png) |

| File Manager | Supervisor |
|--------------|------------|
| ![Files](docs/screenshots/files.png) | ![Supervisor](docs/screenshots/supervisor.png) |

</div>

---

## 🗺️ Roadmap

### ✅ Available Now
- [x] **Elite File Manager** (Ace Editor + In-depth Search)
- [x] **Git Integration** (Commit, Pull, Push, Branches)
- [x] **WordPress Manager** (Themes, Plugins, Users)
- [x] Domain Management (Nginx)
- [x] SSL Certificates (Let's Encrypt + Probing)
- [x] Database Management (MariaDB)
- [x] Supervisor & Cron Jobs
- [x] Resource Monitoring (Real-time)
- [x] PHP Configuration & Log Viewer

### 🚧 Current Roadmap (Q2-Q3 2026)
- [ ] **Apache & Caddy Support** - Alternative web server integrations
- [ ] **Redis Manager** - Key management, status, and config
- [ ] **Email Management** - Robust Postfix/Dovecot UI
- [ ] **Auto Backups** - Scheduled backups to cloud (S3, Dropbox, etc.)

### 🚀 Future Vision
- [ ] **PHP Version Switcher** - Change PHP versions per project
- [ ] **Multi-Database Support** - SQLite, PostgreSQL, MongoDB
- [ ] **Longer Resource History** - Extended monitoring charts & logs
- [ ] **Two-Factor Authentication (2FA)**
- [ ] **Docker Support** - Container management

---

## 🛠️ Development Setup

```bash
# Clone the repository
git clone https://github.com/sudhirrajai/Nimbus.git
cd Nimbus

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure your database in .env, then:
php artisan migrate

# Build frontend
npm run build

# Or for development with hot-reload:
npm run dev
```

---

## 📁 Project Structure

```
Nimbus/
├── app/
│   └── Http/Controllers/    # API Controllers
├── resources/
│   └── js/
│       ├── Pages/           # Vue Pages
│       ├── Components/      # Vue Components
│       └── Layouts/         # Layout Templates
├── routes/
│   └── web.php              # Web Routes
├── public/                  # Public Assets
├── install.sh               # One-command installer
├── uninstall.sh             # Interactive uninstaller
└── VERSION                  # Current version
```

---

## 🔒 Security

- All routes protected by authentication
- CSRF protection enabled
- Security headers configured
- Passwords hashed with bcrypt
- Sensitive files protected

**Report vulnerabilities:** security@sudhirrajai.com

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

<div align="center">

**Sudhir Rajai**

[![GitHub](https://img.shields.io/badge/GitHub-sudhirrajai-181717?style=for-the-badge&logo=github)](https://github.com/sudhirrajai)
[![Website](https://img.shields.io/badge/Website-sudhirrajai.com-blue?style=for-the-badge&logo=google-chrome&logoColor=white)](https://sudhirrajai.com)

</div>

---

<div align="center">

Made with ❤️ by [Sudhir Rajai](https://sudhirrajai.com)

⭐ Star this repo if you find it useful!

</div>
