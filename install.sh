#!/bin/bash

# Nimbus Control Panel - Installer Script
# One-command installation for Ubuntu/Debian servers
# Usage: curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/install.sh | sudo bash

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NIMBUS_DIR="/usr/local/nimbus"
NIMBUS_USER="www-data"
NIMBUS_PORT="2095"
PHP_VERSION="8.2"
NODE_VERSION="20"
GITHUB_REPO="https://github.com/sudhirrajai/Nimbus.git"

echo -e "${BLUE}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║     ███╗   ██╗██╗███╗   ███╗██████╗ ██╗   ██╗███████╗        ║"
echo "║     ████╗  ██║██║████╗ ████║██╔══██╗██║   ██║██╔════╝        ║"
echo "║     ██╔██╗ ██║██║██╔████╔██║██████╔╝██║   ██║███████╗        ║"
echo "║     ██║╚██╗██║██║██║╚██╔╝██║██╔══██╗██║   ██║╚════██║        ║"
echo "║     ██║ ╚████║██║██║ ╚═╝ ██║██████╔╝╚██████╔╝███████║        ║"
echo "║     ╚═╝  ╚═══╝╚═╝╚═╝     ╚═╝╚═════╝  ╚═════╝ ╚══════╝        ║"
echo "║                                                              ║"
echo "║              Control Panel Installer v1.0.0                  ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (sudo)${NC}"
    exit 1
fi

# Check OS
if [ ! -f /etc/debian_version ]; then
    echo -e "${RED}Error: This script only supports Ubuntu/Debian${NC}"
    exit 1
fi

echo -e "${GREEN}[1/12]${NC} Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

echo -e "${GREEN}[2/12]${NC} Installing essential packages..."
apt-get install -y \
    curl wget git unzip zip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg lsb-release \
    acl sudo

echo -e "${GREEN}[3/12]${NC} Installing Nginx..."
apt-get install -y nginx
systemctl enable nginx
systemctl start nginx

echo -e "${GREEN}[4/12]${NC} Installing PHP ${PHP_VERSION}..."
add-apt-repository ppa:ondrej/php -y
apt-get update -y
apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-pgsql \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-opcache \
    php${PHP_VERSION}-redis \
    php${PHP_VERSION}-imagick

systemctl enable php${PHP_VERSION}-fpm
systemctl start php${PHP_VERSION}-fpm

echo -e "${GREEN}[5/12]${NC} Installing MariaDB..."
apt-get install -y mariadb-server mariadb-client
systemctl enable mariadb
systemctl start mariadb

# Secure MariaDB
MYSQL_ROOT_PASS=$(openssl rand -base64 24)
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASS}';"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "DELETE FROM mysql.user WHERE User='';"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "DROP DATABASE IF EXISTS test;"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}[6/12]${NC} Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

echo -e "${GREEN}[7/12]${NC} Installing Node.js ${NODE_VERSION}..."
curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
apt-get install -y nodejs

echo -e "${GREEN}[8/12]${NC} Installing Supervisor..."
apt-get install -y supervisor
systemctl enable supervisor
systemctl start supervisor

echo -e "${GREEN}[9/12]${NC} Cloning Nimbus Panel..."
if [ -d "$NIMBUS_DIR" ]; then
    echo -e "${YELLOW}Nimbus directory exists, backing up...${NC}"
    mv $NIMBUS_DIR ${NIMBUS_DIR}_backup_$(date +%Y%m%d_%H%M%S)
fi

git clone $GITHUB_REPO $NIMBUS_DIR
cd $NIMBUS_DIR

# Create Nimbus database
NIMBUS_DB_PASS=$(openssl rand -base64 24)
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "CREATE DATABASE IF NOT EXISTS nimbus;"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "CREATE USER IF NOT EXISTS 'nimbus'@'localhost' IDENTIFIED BY '${NIMBUS_DB_PASS}';"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "GRANT ALL PRIVILEGES ON nimbus.* TO 'nimbus'@'localhost';"
mysql -u root -p"${MYSQL_ROOT_PASS}" -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}[10/12]${NC} Setting up Nimbus..."
cd $NIMBUS_DIR

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies and build
npm install
npm run build

# Setup environment
cp .env.example .env
sed -i "s|APP_URL=.*|APP_URL=http://localhost:${NIMBUS_PORT}|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=nimbus|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=nimbus|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${NIMBUS_DB_PASS}|" .env

# Generate key and run migrations
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo -e "${GREEN}[11/12]${NC} Configuring Nginx..."
tee /etc/nginx/sites-available/nimbus > /dev/null << EOF
server {
    listen ${NIMBUS_PORT};
    listen [::]:${NIMBUS_PORT};
    server_name _;

    root ${NIMBUS_DIR}/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Max upload size
    client_max_body_size 100M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Cache static assets
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
EOF

ln -sf /etc/nginx/sites-available/nimbus /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and restart nginx
nginx -t
systemctl restart nginx

echo -e "${GREEN}[12/12]${NC} Setting permissions..."
# Set proper ownership
chown -R ${NIMBUS_USER}:${NIMBUS_USER} ${NIMBUS_DIR}

# Set directory permissions
find ${NIMBUS_DIR} -type d -exec chmod 755 {} \;

# Set file permissions
find ${NIMBUS_DIR} -type f -exec chmod 644 {} \;

# Storage and cache need to be writable
chmod -R 775 ${NIMBUS_DIR}/storage
chmod -R 775 ${NIMBUS_DIR}/bootstrap/cache

# Make artisan executable
chmod +x ${NIMBUS_DIR}/artisan

# Setup sudoers for www-data (required for server management)
# Using NOPASSWD: ALL for simplicity - panel needs extensive system access
tee /etc/sudoers.d/nimbus > /dev/null << 'EOF'
# Nimbus Control Panel - sudo permissions for www-data
# This file grants www-data (web server user) passwordless sudo access
# Required for panel to manage server services, files, and configurations

# Core system commands
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl
www-data ALL=(ALL) NOPASSWD: /usr/bin/service
www-data ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot
www-data ALL=(ALL) NOPASSWD: /usr/bin/nginx
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx

# Database commands
www-data ALL=(ALL) NOPASSWD: /usr/bin/mysql
www-data ALL=(ALL) NOPASSWD: /usr/bin/mariadb
www-data ALL=(ALL) NOPASSWD: /usr/bin/mysqldump
www-data ALL=(ALL) NOPASSWD: /usr/bin/mariadb-dump

# File operations
www-data ALL=(ALL) NOPASSWD: /bin/chown
www-data ALL=(ALL) NOPASSWD: /bin/chmod
www-data ALL=(ALL) NOPASSWD: /bin/mkdir
www-data ALL=(ALL) NOPASSWD: /bin/rm
www-data ALL=(ALL) NOPASSWD: /bin/cp
www-data ALL=(ALL) NOPASSWD: /bin/mv
www-data ALL=(ALL) NOPASSWD: /bin/ln
www-data ALL=(ALL) NOPASSWD: /bin/cat
www-data ALL=(ALL) NOPASSWD: /bin/sed
www-data ALL=(ALL) NOPASSWD: /bin/touch
www-data ALL=(ALL) NOPASSWD: /bin/ls
www-data ALL=(ALL) NOPASSWD: /usr/bin/find
www-data ALL=(ALL) NOPASSWD: /usr/bin/tee
www-data ALL=(ALL) NOPASSWD: /usr/bin/tail
www-data ALL=(ALL) NOPASSWD: /usr/bin/head
www-data ALL=(ALL) NOPASSWD: /usr/bin/truncate
www-data ALL=(ALL) NOPASSWD: /usr/bin/wc

# Package management (for phpMyAdmin, mail server, etc.)
www-data ALL=(ALL) NOPASSWD: /usr/bin/apt-get
www-data ALL=(ALL) NOPASSWD: /usr/bin/apt
www-data ALL=(ALL) NOPASSWD: /usr/bin/dpkg
www-data ALL=(ALL) NOPASSWD: /usr/bin/debconf-set-selections
www-data ALL=(ALL) NOPASSWD: /usr/bin/env

# Development tools
www-data ALL=(ALL) NOPASSWD: /usr/bin/git
www-data ALL=(ALL) NOPASSWD: /usr/bin/composer
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/composer
www-data ALL=(ALL) NOPASSWD: /usr/bin/npm
www-data ALL=(ALL) NOPASSWD: /usr/bin/node
www-data ALL=(ALL) NOPASSWD: /usr/bin/php*

# Cron management
www-data ALL=(ALL) NOPASSWD: /usr/bin/crontab

# Shell access (for running scripts)
www-data ALL=(ALL) NOPASSWD: /bin/bash
www-data ALL=(ALL) NOPASSWD: /bin/sh

# Firewall
www-data ALL=(ALL) NOPASSWD: /usr/sbin/ufw

# Disk utilities
www-data ALL=(ALL) NOPASSWD: /bin/df
www-data ALL=(ALL) NOPASSWD: /usr/bin/du
www-data ALL=(ALL) NOPASSWD: /usr/bin/free

# User management
www-data ALL=(ALL) NOPASSWD: /usr/sbin/adduser
www-data ALL=(ALL) NOPASSWD: /usr/sbin/useradd
www-data ALL=(ALL) NOPASSWD: /usr/sbin/usermod
www-data ALL=(ALL) NOPASSWD: /usr/sbin/deluser
www-data ALL=(ALL) NOPASSWD: /usr/sbin/userdel
www-data ALL=(ALL) NOPASSWD: /usr/sbin/groupadd
www-data ALL=(ALL) NOPASSWD: /usr/sbin/groupdel

# SSL/Certificates
www-data ALL=(ALL) NOPASSWD: /usr/bin/openssl

# Network/Process utilities
www-data ALL=(ALL) NOPASSWD: /usr/bin/lsof
www-data ALL=(ALL) NOPASSWD: /usr/bin/netstat
www-data ALL=(ALL) NOPASSWD: /bin/ss
www-data ALL=(ALL) NOPASSWD: /usr/bin/ss
www-data ALL=(ALL) NOPASSWD: /usr/bin/pgrep
www-data ALL=(ALL) NOPASSWD: /usr/bin/pkill
www-data ALL=(ALL) NOPASSWD: /bin/kill
www-data ALL=(ALL) NOPASSWD: /usr/bin/killall
www-data ALL=(ALL) NOPASSWD: /usr/bin/fuser

# Mail server (Postfix, Dovecot)
www-data ALL=(ALL) NOPASSWD: /usr/sbin/postconf
www-data ALL=(ALL) NOPASSWD: /usr/sbin/postfix
www-data ALL=(ALL) NOPASSWD: /usr/bin/doveadm
www-data ALL=(ALL) NOPASSWD: /usr/bin/doveconf

# Scanning directories
www-data ALL=(ALL) NOPASSWD: /usr/bin/scandir
www-data ALL=(ALL) NOPASSWD: /bin/readlink
www-data ALL=(ALL) NOPASSWD: /usr/bin/realpath

# Archive utilities
www-data ALL=(ALL) NOPASSWD: /usr/bin/zip
www-data ALL=(ALL) NOPASSWD: /usr/bin/unzip
www-data ALL=(ALL) NOPASSWD: /bin/tar
www-data ALL=(ALL) NOPASSWD: /usr/bin/gzip
www-data ALL=(ALL) NOPASSWD: /usr/bin/gunzip
EOF

chmod 0440 /etc/sudoers.d/nimbus

# Configure PHP-FPM for better performance
PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
sed -i 's/pm = .*/pm = dynamic/' $PHP_FPM_CONF
sed -i 's/pm.max_children = .*/pm.max_children = 20/' $PHP_FPM_CONF
sed -i 's/pm.start_servers = .*/pm.start_servers = 4/' $PHP_FPM_CONF
sed -i 's/pm.min_spare_servers = .*/pm.min_spare_servers = 4/' $PHP_FPM_CONF
sed -i 's/pm.max_spare_servers = .*/pm.max_spare_servers = 10/' $PHP_FPM_CONF
sed -i 's/;pm.max_requests = .*/pm.max_requests = 500/' $PHP_FPM_CONF

# Configure PHP.ini
PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i 's/memory_limit = .*/memory_limit = 512M/' $PHP_INI
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' $PHP_INI
sed -i 's/post_max_size = .*/post_max_size = 100M/' $PHP_INI
sed -i 's/max_execution_time = .*/max_execution_time = 300/' $PHP_INI
sed -i 's/max_input_time = .*/max_input_time = 300/' $PHP_INI
sed -i 's/;opcache.enable=.*/opcache.enable=1/' $PHP_INI
sed -i 's/;opcache.memory_consumption=.*/opcache.memory_consumption=256/' $PHP_INI
sed -i 's/;opcache.max_accelerated_files=.*/opcache.max_accelerated_files=20000/' $PHP_INI

systemctl restart php${PHP_VERSION}-fpm

# Configure firewall
echo -e "${YELLOW}Configuring firewall...${NC}"
apt-get install -y ufw
ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow ${NIMBUS_PORT}/tcp
ufw --force enable

# Get server IP
SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${GREEN}"
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              NIMBUS INSTALLATION COMPLETE!                   ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${BLUE}Access your panel:${NC}"
echo -e "  URL: ${GREEN}http://${SERVER_IP}:${NIMBUS_PORT}${NC}"
echo ""
echo -e "${BLUE}First-time setup:${NC}"
echo -e "  Create your admin account at the URL above"
echo ""
echo -e "${BLUE}Database credentials (saved to ${NIMBUS_DIR}/.credentials):${NC}"
echo -e "  MySQL Root Password: ${YELLOW}${MYSQL_ROOT_PASS}${NC}"
echo -e "  Nimbus DB Password:  ${YELLOW}${NIMBUS_DB_PASS}${NC}"
echo ""
echo -e "${BLUE}Important paths:${NC}"
echo -e "  Panel:  ${NIMBUS_DIR}"
echo -e "  Sites:  /var/www/"
echo -e "  Nginx:  /etc/nginx/sites-available/"
echo ""
echo -e "${BLUE}Services installed:${NC}"
echo -e "  ✓ Nginx"
echo -e "  ✓ PHP ${PHP_VERSION}-FPM"
echo -e "  ✓ MariaDB"
echo -e "  ✓ Composer"
echo -e "  ✓ Node.js ${NODE_VERSION}"
echo -e "  ✓ Supervisor"
echo -e "  ✓ UFW Firewall"
echo ""

# Save credentials to file
tee ${NIMBUS_DIR}/.credentials > /dev/null << EOF
# Nimbus Control Panel - Credentials
# Generated on $(date)
# KEEP THIS FILE SECURE!

MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASS}
NIMBUS_DB_PASSWORD=${NIMBUS_DB_PASS}
EOF
chmod 600 ${NIMBUS_DIR}/.credentials

echo -e "${YELLOW}⚠️  IMPORTANT: Save the credentials above in a secure location!${NC}"
echo ""
echo -e "${GREEN}Installation complete! Enjoy Nimbus Control Panel!${NC}"
echo ""
