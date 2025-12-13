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

</td>
<td width="50%">

### 🔒 SSL Certificates
- One-click Let's Encrypt SSL
- Auto-renewal support
- Certificate status monitoring
- HTTPS redirects

</td>
</tr>
<tr>
<td width="50%">

### 🗄️ Database Management
- Create MySQL/MariaDB databases
- User management with permissions
- phpMyAdmin integration
- Database import/export

</td>
<td width="50%">

### 📁 File Manager
- Web-based file browser
- Upload, download, delete files
- Create folders
- File permissions editor

</td>
</tr>
<tr>
<td width="50%">

### ⚡ Supervisor
- Queue worker management
- Process monitoring
- Start/Stop/Restart workers
- Real-time logs

</td>
<td width="50%">

### ⏰ Cron Jobs
- Visual cron scheduler
- Quick presets (every minute, hourly, daily)
- Human-readable schedule descriptions
- Run jobs manually

</td>
</tr>
<tr>
<td width="50%">

### 📧 Email Server
- Postfix + Dovecot setup
- Virtual mailboxes
- Roundcube webmail
- Email account management

</td>
<td width="50%">

### 📊 Server Monitoring
- Real-time CPU, RAM, Disk usage
- Network statistics
- Process list
- System uptime

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
- [x] Domain Management
- [x] SSL Certificates (Let's Encrypt)
- [x] Database Management
- [x] File Manager
- [x] Supervisor / Queue Workers
- [x] Cron Jobs
- [x] Email Server (Postfix + Dovecot + Roundcube)
- [x] PHP Configuration
- [x] Log Viewer
- [x] Resource Monitoring
- [x] Profile & Settings
- [x] Auto Updates

### 🚧 Coming Soon
- [ ] **Backups** - Scheduled backups with cloud storage
- [ ] **FTP Accounts** - Create and manage FTP users
- [ ] **Two-Factor Authentication**
- [ ] **Multiple Users** - Team management
- [ ] **Docker Support**
- [ ] **WordPress Quick Install**
- [ ] **DNS Management**
- [ ] **Firewall Rules**

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
