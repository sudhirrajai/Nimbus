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
    php${PHP_VERSION}-dom \
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

# Secure MariaDB - use unix_socket authentication for root (allows sudo mysql without password)
MYSQL_ROOT_PASS=$(openssl rand -base64 24)

# Configure root to use unix_socket auth (allows sudo mysql without password)
# Also set a password for remote tools that need it
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket OR mysql_native_password USING PASSWORD('${MYSQL_ROOT_PASS}');"
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -e "FLUSH PRIVILEGES;"

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
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build
npm install --unsafe-perm
# Use direct path to vite to avoid PATH issues
./node_modules/.bin/vite build

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

# Create required directories if they don't exist
mkdir -p ${NIMBUS_DIR}/storage/logs
mkdir -p ${NIMBUS_DIR}/storage/framework/cache
mkdir -p ${NIMBUS_DIR}/storage/framework/sessions
mkdir -p ${NIMBUS_DIR}/storage/framework/views
mkdir -p ${NIMBUS_DIR}/bootstrap/cache

# Create log file with proper permissions
touch ${NIMBUS_DIR}/storage/logs/laravel.log

# Set proper ownership - www-data owns everything
chown -R ${NIMBUS_USER}:${NIMBUS_USER} ${NIMBUS_DIR}

# Set directory permissions (755 for directories)
find ${NIMBUS_DIR} -type d -exec chmod 755 {} \;

# Set file permissions (644 for files)
find ${NIMBUS_DIR} -type f -exec chmod 644 {} \;

# Storage and cache need to be fully writable (775)
chmod -R 775 ${NIMBUS_DIR}/storage
chmod -R 775 ${NIMBUS_DIR}/bootstrap/cache

# Ensure log file is writable
chmod 664 ${NIMBUS_DIR}/storage/logs/laravel.log
chown ${NIMBUS_USER}:${NIMBUS_USER} ${NIMBUS_DIR}/storage/logs/laravel.log

# Make artisan executable
chmod +x ${NIMBUS_DIR}/artisan

# Setup sudoers for www-data (required for server management)
# NOPASSWD: ALL - panel needs full system access to manage server
tee /etc/sudoers.d/nimbus > /dev/null << 'EOF'
# Nimbus Control Panel - sudo permissions for www-data
# This file grants www-data (web server user) passwordless sudo access
# Required for panel to manage server services, files, and configurations
www-data ALL=(ALL) NOPASSWD: ALL
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
