#!/bin/bash

# Nimbus Control Panel - Installer Script
# One-command installation for Ubuntu/Debian servers
# Usage:   curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/dev/install.sh | sudo bash
# Smart:   curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/dev/install.sh | sudo bash -s -- --skip-existing
# Uninstall: curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/dev/uninstall.sh | sudo bash

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
SKIP_EXISTING=false
PANEL_SYSTEM_USER=""
DB_ENGINE="mariadb"
DB_ROOT_USER="root"
DB_ROOT_PASS=""
DB_AUTH_MODE="socket"

if [ "${1}" = "--skip-existing" ]; then
    SKIP_EXISTING=true
    shift
fi

detect_panel_system_user() {
    if [ -n "${SUDO_USER:-}" ] && id -u "${SUDO_USER}" >/dev/null 2>&1; then
        PANEL_SYSTEM_USER="${SUDO_USER}"
        return
    fi

    PANEL_SYSTEM_USER=$(awk -F: '$3 >= 1000 && $1 != "nobody" && $7 !~ /(nologin|false)/ { print $1; exit }' /etc/passwd)

    if [ -z "$PANEL_SYSTEM_USER" ]; then
        PANEL_SYSTEM_USER="$NIMBUS_USER"
    fi
}

package_installed() {
    dpkg -s "$1" >/dev/null 2>&1
}

all_packages_installed() {
    for package in "$@"; do
        if ! package_installed "$package"; then
            return 1
        fi
    done

    return 0
}

node_version_ok() {
    command -v node >/dev/null 2>&1 || return 1
    command -v npm >/dev/null 2>&1 || return 1
    local installed_major
    installed_major=$(node -e "process.stdout.write(String(process.version.match(/^v(\d+)/)[1]))" 2>/dev/null)
    [ "${installed_major}" -ge "${NODE_VERSION}" ] 2>/dev/null
}

detect_database_engine() {
    if package_installed mariadb-server || command -v mariadb >/dev/null 2>&1; then
        DB_ENGINE="mariadb"
        return
    fi

    if package_installed mysql-server || command -v mysql >/dev/null 2>&1; then
        DB_ENGINE="mysql"
        return
    fi

    DB_ENGINE="mariadb"
}

prompt_database_credentials() {
    echo ""
    echo -e "${BLUE}Database access for Nimbus${NC}"
    echo -e "Detected database engine: ${GREEN}${DB_ENGINE}${NC}"
    read -r -p "Database root/admin username [root]: " INPUT_DB_ROOT_USER < /dev/tty
    DB_ROOT_USER="${INPUT_DB_ROOT_USER:-root}"
    read -r -s -p "Database password for ${DB_ROOT_USER} (leave blank for socket auth): " INPUT_DB_ROOT_PASS < /dev/tty
    echo ""

    if [ -n "$INPUT_DB_ROOT_PASS" ]; then
        DB_ROOT_PASS="$INPUT_DB_ROOT_PASS"
        DB_AUTH_MODE="password"
    else
        DB_ROOT_PASS=""
        DB_AUTH_MODE="socket"
    fi
}

run_db_command() {
    local sql="$1"

    if [ "$DB_AUTH_MODE" = "password" ]; then
        mysql -u"${DB_ROOT_USER}" -p"${DB_ROOT_PASS}" -e "$sql"
    else
        if [ "$DB_ROOT_USER" = "root" ]; then
            mysql -e "$sql"
        else
            mysql -u"${DB_ROOT_USER}" -e "$sql"
        fi
    fi
}

verify_database_credentials() {
    if run_db_command "SELECT 1;" >/dev/null 2>&1; then
        return 0
    fi

    return 1
}

upsert_env() {
    local key="$1"
    local value="$2"
    local env_file="$3"

    if grep -q "^${key}=" "$env_file"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
    else
        echo "${key}=${value}" >> "$env_file"
    fi
}

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

# ─────────────────────────────────────────────────────────────────
# UNINSTALL mode — redirect to dedicated uninstall.sh
# ─────────────────────────────────────────────────────────────────
if [ "${1}" = "--uninstall" ] || [ "${1}" = "uninstall" ]; then
    echo -e "${YELLOW}Redirecting to the dedicated Nimbus uninstaller...${NC}"

    # If uninstall.sh exists locally (e.g. cloned repo), use it directly
    if [ -f "${NIMBUS_DIR}/uninstall.sh" ]; then
        bash "${NIMBUS_DIR}/uninstall.sh"
    else
        # Download and run from GitHub
        curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/uninstall.sh | bash
    fi
    exit $?
fi

# Check OS
if [ ! -f /etc/debian_version ]; then
    echo -e "${RED}Error: This script only supports Ubuntu/Debian${NC}"
    exit 1
fi

detect_panel_system_user
detect_database_engine

echo -e "${GREEN}[1/12]${NC} Updating system packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
if [ "$SKIP_EXISTING" = false ]; then
    apt-get upgrade -y
else
    echo -e "${YELLOW}Skipping full system upgrade (--skip-existing enabled).${NC}"
fi

echo -e "${GREEN}[2/12]${NC} Installing essential packages..."
ESSENTIAL_PACKAGES=(
    curl wget git unzip zip
    software-properties-common
    apt-transport-https
    ca-certificates
    gnupg lsb-release
    acl sudo
)
if [ "$SKIP_EXISTING" = true ] && all_packages_installed "${ESSENTIAL_PACKAGES[@]}"; then
    echo -e "${YELLOW}Essential packages already installed. Skipping.${NC}"
else
    apt-get install -y "${ESSENTIAL_PACKAGES[@]}"
fi

echo -e "${GREEN}[3/12]${NC} Installing Nginx..."
if [ "$SKIP_EXISTING" = true ] && package_installed nginx; then
    echo -e "${YELLOW}Nginx already installed. Skipping package install.${NC}"
else
    apt-get install -y nginx
fi
systemctl enable nginx
systemctl start nginx

echo -e "${GREEN}[4/12]${NC} Installing PHP ${PHP_VERSION}..."
PHP_PACKAGES=(
    php${PHP_VERSION}
    php${PHP_VERSION}-fpm
    php${PHP_VERSION}-cli
    php${PHP_VERSION}-mysql
    php${PHP_VERSION}-pgsql
    php${PHP_VERSION}-sqlite3
    php${PHP_VERSION}-gd
    php${PHP_VERSION}-curl
    php${PHP_VERSION}-zip
    php${PHP_VERSION}-mbstring
    php${PHP_VERSION}-xml
    php${PHP_VERSION}-dom
    php${PHP_VERSION}-bcmath
    php${PHP_VERSION}-intl
    php${PHP_VERSION}-opcache
    php${PHP_VERSION}-redis
    php${PHP_VERSION}-imagick
)
if [ "$SKIP_EXISTING" = true ] && all_packages_installed "${PHP_PACKAGES[@]}"; then
    echo -e "${YELLOW}PHP ${PHP_VERSION} stack already installed. Skipping package install.${NC}"
else
    add-apt-repository ppa:ondrej/php -y
    apt-get update -y
    apt-get install -y "${PHP_PACKAGES[@]}"
fi

systemctl enable php${PHP_VERSION}-fpm
systemctl start php${PHP_VERSION}-fpm

echo -e "${GREEN}[5/12]${NC} Installing MariaDB..."
if [ "$SKIP_EXISTING" = true ] && { all_packages_installed mariadb-server mariadb-client || all_packages_installed mysql-server mysql-client; }; then
    echo -e "${YELLOW}${DB_ENGINE^} already installed. Skipping database package install.${NC}"
else
    apt-get install -y mariadb-server mariadb-client
    DB_ENGINE="mariadb"
fi
DB_SERVICE_NAME="mariadb"
if systemctl list-unit-files | grep -q '^mysql\.service'; then
    DB_SERVICE_NAME="mysql"
fi
if [ "$DB_ENGINE" = "mariadb" ] && systemctl list-unit-files | grep -q '^mariadb\.service'; then
    DB_SERVICE_NAME="mariadb"
fi
systemctl enable "${DB_SERVICE_NAME}" || true
systemctl start "${DB_SERVICE_NAME}" || true

# Wait for database service to be ready
echo -e "${YELLOW}Waiting for ${DB_ENGINE^} to be ready...${NC}"
for i in $(seq 1 30); do
    if mysqladmin ping --silent 2>/dev/null; then
        echo -e "${GREEN}${DB_ENGINE^} is ready${NC}"
        break
    fi
    sleep 1
done

# Database root/admin access
MYSQL_ROOT_PASS=$(openssl rand -base64 24)

# First, try socket auth (works on fresh installs where root uses unix_socket).
# If that fails, prompt the user for credentials.
if [ "$SKIP_EXISTING" = true ]; then
    prompt_database_credentials
    if ! verify_database_credentials; then
        echo -e "${RED}Unable to authenticate to ${DB_ENGINE} with the provided credentials.${NC}"
        exit 1
    fi
else
    # Fresh install: verify socket auth works; if not, ask for credentials
    if ! verify_database_credentials; then
        echo -e "${YELLOW}Socket authentication failed. Prompting for database credentials...${NC}"
        prompt_database_credentials
        if ! verify_database_credentials; then
            echo -e "${RED}Unable to authenticate to ${DB_ENGINE}. Cannot continue.${NC}"
            exit 1
        fi
    fi
fi

# Secure fresh MariaDB installs
# On fresh installs, root uses unix_socket auth (no password needed via sudo).
# We try the MariaDB-specific combined syntax first, fall back to plain password if needed.

if [ "$SKIP_EXISTING" = true ]; then
    echo -e "${YELLOW}Skipping ${DB_ENGINE^} root-auth changes (--skip-existing enabled).${NC}"
else
    # Try combined unix_socket OR native_password auth (MariaDB 10.4+)
    run_db_command "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket OR mysql_native_password USING PASSWORD('${MYSQL_ROOT_PASS}');" 2>/dev/null || \
        run_db_command "ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASS}';" 2>/dev/null || \
        echo -e "${YELLOW}Note: root auth method unchanged (unix_socket will be used)${NC}"

    run_db_command "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true
    run_db_command "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');" 2>/dev/null || true
    run_db_command "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
    run_db_command "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';" 2>/dev/null || true
    run_db_command "FLUSH PRIVILEGES;" 2>/dev/null || true
fi

echo -e "${GREEN}[6/12]${NC} Installing Composer..."
if [ "$SKIP_EXISTING" = true ] && command -v composer >/dev/null 2>&1; then
    echo -e "${YELLOW}Composer already installed. Skipping.${NC}"
else
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo -e "${GREEN}[7/12]${NC} Installing Node.js ${NODE_VERSION}..."
# Clear stale apt cache before touching nodesource repo
apt-get clean
rm -rf /var/lib/apt/lists/*

NODE_INSTALLED=false

if [ "$SKIP_EXISTING" = true ] && node_version_ok; then
    NODE_INSTALLED=true
    echo -e "${YELLOW}Compatible Node.js $(node -v) and npm $(npm -v) already installed. Skipping.${NC}"
fi

# --- Attempt 1-3: nodesource repo ---
for ATTEMPT in 1 2 3; do
    if [ "$NODE_INSTALLED" = true ]; then
        break
    fi
    echo -e "  Attempt ${ATTEMPT}/3 via nodesource..."
    if curl -fsSL "https://deb.nodesource.com/setup_${NODE_VERSION}.x" -o /tmp/nodesource_setup.sh; then
        bash /tmp/nodesource_setup.sh && apt-get install -y nodejs npm && true
        # Only accept if we got the right major version
        if node_version_ok; then
            NODE_INSTALLED=true
            break
        else
            echo -e "  ${YELLOW}nodesource installed wrong Node version ($(node -v)), trying again...${NC}"
            apt-get purge -y nodejs npm libnode* 2>/dev/null || true
            apt-get clean && rm -rf /var/lib/apt/lists/*
        fi
    fi
    if [ $ATTEMPT -lt 3 ]; then
        echo -e "  ${YELLOW}Attempt ${ATTEMPT} failed, retrying in 15s...${NC}"
        sleep 15
    fi
done

# --- Fallback: nvm (works on any distro, no CDN dependency) ---
if [ "$NODE_INSTALLED" = false ]; then
    echo -e "${YELLOW}nodesource unavailable, installing Node.js ${NODE_VERSION} via nvm...${NC}"
    # Remove any wrong-version node first
    apt-get purge -y nodejs npm libnode* 2>/dev/null || true

    export NVM_DIR="/root/.nvm"
    curl -fsSL https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
    nvm install "${NODE_VERSION}"
    nvm use "${NODE_VERSION}"
    nvm alias default "${NODE_VERSION}"

    # Symlink into /usr/local/bin so node/npm/npx are on PATH for all scripts
    NODE_BIN_DIR="$(dirname "$(nvm which ${NODE_VERSION})")"
    ln -sf "${NODE_BIN_DIR}/node" /usr/local/bin/node
    ln -sf "${NODE_BIN_DIR}/npm"  /usr/local/bin/npm
    ln -sf "${NODE_BIN_DIR}/npx"  /usr/local/bin/npx
    NODE_INSTALLED=true
fi

# --- Final verification ---
if ! command -v npm &>/dev/null || ! node_version_ok; then
    echo -e "${RED}ERROR: Node.js ${NODE_VERSION}+ / npm not available after installation!${NC}"
    echo -e "${RED}  node: $(node -v 2>/dev/null || echo 'not found')${NC}"
    echo -e "${RED}  npm:  $(npm  -v 2>/dev/null || echo 'not found')${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Node.js $(node -v) and npm $(npm -v) ready.${NC}"

echo -e "${GREEN}[8/12]${NC} Installing Supervisor..."
apt-get update -y
if [ "$SKIP_EXISTING" = true ] && package_installed supervisor; then
    echo -e "${YELLOW}Supervisor already installed. Skipping package install.${NC}"
else
    apt-get install -y supervisor
fi
systemctl enable supervisor
systemctl start supervisor

echo -e "${GREEN}[9/12]${NC} Cloning Nimbus Panel..."
if [ -d "$NIMBUS_DIR" ]; then
    echo -e "${YELLOW}Nimbus directory exists, backing up...${NC}"
    mv $NIMBUS_DIR ${NIMBUS_DIR}_backup_$(date +%Y%m%d_%H%M%S)
fi

git clone $GITHUB_REPO $NIMBUS_DIR
cd $NIMBUS_DIR

# Create Nimbus database and user
# Use ALTER USER after CREATE to ensure password is always up-to-date (handles reinstalls)
NIMBUS_DB_PASS=$(openssl rand -base64 24)
run_db_command "CREATE DATABASE IF NOT EXISTS nimbus;"
run_db_command "CREATE USER IF NOT EXISTS 'nimbus'@'localhost' IDENTIFIED BY '${NIMBUS_DB_PASS}';"
run_db_command "ALTER USER 'nimbus'@'localhost' IDENTIFIED BY '${NIMBUS_DB_PASS}';"
run_db_command "GRANT ALL PRIVILEGES ON nimbus.* TO 'nimbus'@'localhost';"
run_db_command "FLUSH PRIVILEGES;"

echo -e "${GREEN}[10/12]${NC} Setting up Nimbus..."
cd $NIMBUS_DIR

# Install PHP dependencies
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build
npm ci --unsafe-perm
# Use direct path to vite to avoid PATH issues
./node_modules/.bin/vite build

# Setup environment
cp .env.example .env
sed -i "s|APP_URL=.*|APP_URL=http://localhost:${NIMBUS_PORT}|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=nimbus|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=nimbus|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${NIMBUS_DB_PASS}|" .env
upsert_env "NIMBUS_GIT_USER" "${PANEL_SYSTEM_USER}" .env

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
find ${NIMBUS_DIR}/storage -type d -exec chmod 2775 {} \;
find ${NIMBUS_DIR}/bootstrap/cache -type d -exec chmod 2775 {} \;
find ${NIMBUS_DIR}/storage -type f -exec chmod 664 {} \;
find ${NIMBUS_DIR}/bootstrap/cache -type f -exec chmod 664 {} \;

# Ensure log file is writable
chmod 664 ${NIMBUS_DIR}/storage/logs/laravel.log
chown ${NIMBUS_USER}:${NIMBUS_USER} ${NIMBUS_DIR}/storage/logs/laravel.log

# Make artisan executable
chmod +x ${NIMBUS_DIR}/artisan

# Setup /var/www/ directory for hosting websites
mkdir -p /var/www
chown -R ${NIMBUS_USER}:${NIMBUS_USER} /var/www
chmod 2775 /var/www
find /var/www -mindepth 1 -type d -exec chmod 2775 {} \; 2>/dev/null || true
find /var/www -mindepth 1 -type f -exec chmod 664 {} \; 2>/dev/null || true
setfacl -m g:${NIMBUS_USER}:rwx /var/www
setfacl -d -m g:${NIMBUS_USER}:rwx /var/www

if id -u "${PANEL_SYSTEM_USER}" >/dev/null 2>&1 && [ "${PANEL_SYSTEM_USER}" != "${NIMBUS_USER}" ] && [ "${PANEL_SYSTEM_USER}" != "root" ]; then
    usermod -aG ${NIMBUS_USER} "${PANEL_SYSTEM_USER}"
fi

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
if [ "$SKIP_EXISTING" = false ]; then
    echo -e "  ${DB_ENGINE^} Root Password: ${YELLOW}${MYSQL_ROOT_PASS}${NC}"
else
    echo -e "  ${DB_ENGINE^} Admin User:   ${YELLOW}${DB_ROOT_USER}${NC}"
    echo -e "  ${DB_ENGINE^} Auth Mode:    ${YELLOW}${DB_AUTH_MODE}${NC}"
fi
echo -e "  Nimbus DB Password:  ${YELLOW}${NIMBUS_DB_PASS}${NC}"
echo ""
echo -e "${BLUE}Important paths:${NC}"
echo -e "  Panel:  ${NIMBUS_DIR}"
echo -e "  Sites:  /var/www/"
echo -e "  Nginx:  /etc/nginx/sites-available/"
echo -e "  Git/Queue User: ${PANEL_SYSTEM_USER}"
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

DB_ENGINE=${DB_ENGINE}
DB_ROOT_USER=${DB_ROOT_USER}
DB_AUTH_MODE=${DB_AUTH_MODE}
MYSQL_ROOT_PASSWORD=$([ "$SKIP_EXISTING" = false ] && printf '%s' "${MYSQL_ROOT_PASS}")
NIMBUS_DB_PASSWORD=${NIMBUS_DB_PASS}
EOF
chmod 600 ${NIMBUS_DIR}/.credentials

echo -e "${YELLOW}⚠️  IMPORTANT: Save the credentials above in a secure location!${NC}"
if [ "${PANEL_SYSTEM_USER}" != "root" ] && [ "${PANEL_SYSTEM_USER}" != "${NIMBUS_USER}" ]; then
    echo -e "${YELLOW}⚠️  ${PANEL_SYSTEM_USER} was added to the ${NIMBUS_USER} group. Start a new shell session before using manual git or queue commands as that user.${NC}"
fi
echo ""
echo -e "${GREEN}Installation complete! Enjoy Nimbus Control Panel!${NC}"
echo ""
