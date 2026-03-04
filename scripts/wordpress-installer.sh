#!/bin/bash

#===============================================================================
#
#          FILE:  wordpress-installer.sh
#
#         USAGE:  sudo ./wordpress-installer.sh
#
#   DESCRIPTION:  Production-ready WordPress installer for Ubuntu/Debian
#                 Installs Nginx, PHP-FPM, MariaDB, and WordPress
#
#       OPTIONS:  Run with -h or --help for options
#        AUTHOR:  Auto-generated production script
#       VERSION:  1.0.0
#       CREATED:  2025
#
#===============================================================================

set -euo pipefail
IFS=$'\n\t'

#===============================================================================
# CONFIGURATION - Modify these values as needed
#===============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Default values (can be overridden via command line or prompts)
DOMAIN=""
WP_DB_NAME=""
WP_DB_USER=""
WP_DB_PASS=""
WP_ADMIN_USER="admin"
WP_ADMIN_EMAIL=""
WP_ADMIN_PASS=""
WP_TITLE="My WordPress Site"
INSTALL_SSL=false
PHP_VERSION="8.2"
WEB_ROOT="/var/www"
ENABLE_REDIS=false
ENABLE_FAIL2BAN=false

# Logging
LOG_FILE="/var/log/wordpress-installer.log"
BACKUP_DIR="/var/backups/wordpress-installer"

#===============================================================================
# HELPER FUNCTIONS
#===============================================================================

log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
    
    case "$level" in
        INFO)
            echo -e "${GREEN}[INFO]${NC} $message"
            ;;
        WARN)
            echo -e "${YELLOW}[WARN]${NC} $message"
            ;;
        ERROR)
            echo -e "${RED}[ERROR]${NC} $message"
            ;;
        DEBUG)
            echo -e "${CYAN}[DEBUG]${NC} $message"
            ;;
        *)
            echo "$message"
            ;;
    esac
}

print_banner() {
    echo -e "${BLUE}"
    cat << 'EOF'
╔══════════════════════════════════════════════════════════════════╗
║                                                                  ║
║   ██╗    ██╗ ██████╗ ██████╗ ██████╗ ██████╗ ██████╗ ███████╗   ║
║   ██║    ██║██╔═══██╗██╔══██╗██╔══██╗██╔══██╗██╔══██╗██╔════╝   ║
║   ██║ █╗ ██║██║   ██║██████╔╝██║  ██║██████╔╝██████╔╝███████╗   ║
║   ██║███╗██║██║   ██║██╔══██╗██║  ██║██╔═══╝ ██╔══██╗╔════██║   ║
║   ╚███╔███╔╝╚██████╔╝██║  ██║██████╔╝██║     ██║  ██║███████║   ║
║    ╚══╝╚══╝  ╚═════╝ ╚═╝  ╚═╝╚═════╝ ╚═╝     ╚═╝  ╚═╝╚══════╝   ║
║                                                                  ║
║            Production-Ready WordPress Installer v1.0             ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log ERROR "This script must be run as root. Use: sudo $0"
        exit 1
    fi
}

check_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
        log INFO "Detected OS: $OS $VER"
        
        if [[ ! "$ID" =~ ^(ubuntu|debian)$ ]]; then
            log WARN "This script is optimized for Ubuntu/Debian. Proceed with caution."
            read -p "Continue anyway? (y/n): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                exit 1
            fi
        fi
    else
        log ERROR "Cannot detect operating system"
        exit 1
    fi
}

generate_password() {
    local length="${1:-32}"
    openssl rand -base64 48 | tr -dc 'a-zA-Z0-9!@#$%^&*()_+' | head -c "$length"
}

generate_username() {
    local prefix="${1:-wp}"
    echo "${prefix}_$(openssl rand -hex 4)"
}

create_backup_dir() {
    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"
}

cleanup_on_error() {
    log ERROR "Installation failed. Rolling back changes..."
    
    if [[ -n "${WP_DB_NAME:-}" ]]; then
        mysql -e "DROP DATABASE IF EXISTS \`$WP_DB_NAME\`;" 2>/dev/null || true
        mysql -e "DROP USER IF EXISTS '$WP_DB_USER'@'localhost';" 2>/dev/null || true
    fi
    
    if [[ -n "${DOMAIN:-}" ]] && [[ -d "$WEB_ROOT/$DOMAIN" ]]; then
        rm -rf "$WEB_ROOT/$DOMAIN"
    fi
    
    if [[ -n "${DOMAIN:-}" ]] && [[ -f "/etc/nginx/sites-enabled/$DOMAIN" ]]; then
        rm -f "/etc/nginx/sites-enabled/$DOMAIN"
        rm -f "/etc/nginx/sites-available/$DOMAIN"
        systemctl reload nginx 2>/dev/null || true
    fi
    
    log INFO "Rollback completed. Check $LOG_FILE for details."
    exit 1
}

trap cleanup_on_error ERR

#===============================================================================
# INSTALLATION FUNCTIONS
#===============================================================================

update_system() {
    log INFO "Updating system packages..."
    apt-get update -qq
    apt-get upgrade -y -qq
    log INFO "System packages updated successfully"
}

install_dependencies() {
    log INFO "Installing required dependencies..."
    
    apt-get install -y -qq \
        curl \
        wget \
        git \
        unzip \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        gnupg \
        lsb-release
    
    log INFO "Dependencies installed successfully"
}

install_nginx() {
    log INFO "Installing Nginx..."
    
    apt-get install -y -qq nginx
    
    # Enable and start Nginx
    systemctl enable nginx
    systemctl start nginx
    
    # Create include directories
    mkdir -p /etc/nginx/snippets
    mkdir -p /etc/nginx/conf.d
    
    log INFO "Nginx installed and started"
}

install_php() {
    log INFO "Installing PHP $PHP_VERSION..."
    
    # Add PHP repository
    if ! command -v php &> /dev/null || ! php -v | grep -q "$PHP_VERSION"; then
        add-apt-repository -y ppa:ondrej/php 2>/dev/null || {
            # For Debian
            wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
            echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
        }
        apt-get update -qq
    fi
    
    # Install PHP and required extensions
    apt-get install -y -qq \
        "php${PHP_VERSION}-fpm" \
        "php${PHP_VERSION}-cli" \
        "php${PHP_VERSION}-common" \
        "php${PHP_VERSION}-mysql" \
        "php${PHP_VERSION}-xml" \
        "php${PHP_VERSION}-xmlrpc" \
        "php${PHP_VERSION}-curl" \
        "php${PHP_VERSION}-gd" \
        "php${PHP_VERSION}-imagick" \
        "php${PHP_VERSION}-dev" \
        "php${PHP_VERSION}-imap" \
        "php${PHP_VERSION}-mbstring" \
        "php${PHP_VERSION}-opcache" \
        "php${PHP_VERSION}-soap" \
        "php${PHP_VERSION}-zip" \
        "php${PHP_VERSION}-intl" \
        "php${PHP_VERSION}-bcmath"
    
    # Install Redis extension if enabled
    if [[ "$ENABLE_REDIS" == true ]]; then
        apt-get install -y -qq "php${PHP_VERSION}-redis"
    fi
    
    # Configure PHP-FPM
    configure_php
    
    # Enable and start PHP-FPM
    systemctl enable "php${PHP_VERSION}-fpm"
    systemctl restart "php${PHP_VERSION}-fpm"
    
    log INFO "PHP $PHP_VERSION installed and configured"
}

configure_php() {
    local php_ini="/etc/php/${PHP_VERSION}/fpm/php.ini"
    local php_fpm_conf="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
    
    # Backup original configs
    cp "$php_ini" "${BACKUP_DIR}/php.ini.backup.$(date +%s)" 2>/dev/null || true
    cp "$php_fpm_conf" "${BACKUP_DIR}/www.conf.backup.$(date +%s)" 2>/dev/null || true
    
    # PHP.ini optimizations for WordPress
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = 256M/" "$php_ini"
    sed -i "s/post_max_size = .*/post_max_size = 256M/" "$php_ini"
    sed -i "s/memory_limit = .*/memory_limit = 512M/" "$php_ini"
    sed -i "s/max_execution_time = .*/max_execution_time = 600/" "$php_ini"
    sed -i "s/max_input_time = .*/max_input_time = 600/" "$php_ini"
    sed -i "s/max_input_vars = .*/max_input_vars = 5000/" "$php_ini"
    sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" "$php_ini"
    
    # Enable OPcache
    sed -i "s/;opcache.enable=.*/opcache.enable=1/" "$php_ini"
    sed -i "s/;opcache.memory_consumption=.*/opcache.memory_consumption=256/" "$php_ini"
    sed -i "s/;opcache.interned_strings_buffer=.*/opcache.interned_strings_buffer=16/" "$php_ini"
    sed -i "s/;opcache.max_accelerated_files=.*/opcache.max_accelerated_files=20000/" "$php_ini"
    sed -i "s/;opcache.revalidate_freq=.*/opcache.revalidate_freq=2/" "$php_ini"
    sed -i "s/;opcache.save_comments=.*/opcache.save_comments=1/" "$php_ini"
    
    # PHP-FPM pool optimizations
    sed -i "s/pm = dynamic/pm = ondemand/" "$php_fpm_conf"
    sed -i "s/pm.max_children = .*/pm.max_children = 50/" "$php_fpm_conf"
    sed -i "s/;pm.process_idle_timeout = .*/pm.process_idle_timeout = 10s/" "$php_fpm_conf"
    sed -i "s/;pm.max_requests = .*/pm.max_requests = 500/" "$php_fpm_conf"
    
    log INFO "PHP configuration optimized for WordPress"
}

install_mariadb() {
    # Check if MariaDB is already installed and running
    if command -v mysql &> /dev/null && systemctl is-active --quiet mariadb; then
        log INFO "MariaDB is already installed and running. Skipping installation."
        
        # Check if .my.cnf exists (already secured)
        if [[ ! -f /root/.my.cnf ]]; then
            log WARN "MariaDB found but /root/.my.cnf not found. Assuming manual setup."
        fi
        return 0
    fi
    
    log INFO "Installing MariaDB..."
    
    # Install MariaDB
    apt-get install -y -qq mariadb-server mariadb-client
    
    # Enable and start MariaDB
    systemctl enable mariadb
    systemctl start mariadb
    
    # Secure installation
    secure_mariadb
    
    log INFO "MariaDB installed and secured"
}

secure_mariadb() {
    log INFO "Securing MariaDB installation..."
    
    # Generate root password
    local MYSQL_ROOT_PASS=$(generate_password 24)
    
    # Run security commands
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$MYSQL_ROOT_PASS';"
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "DELETE FROM mysql.user WHERE User='';"
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "DROP DATABASE IF EXISTS test;"
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    mysql -u root -p"$MYSQL_ROOT_PASS" -e "FLUSH PRIVILEGES;"
    
    # Save credentials securely
    cat > /root/.my.cnf << EOF
[client]
user=root
password=$MYSQL_ROOT_PASS
EOF
    chmod 600 /root/.my.cnf
    
    # Save to credentials file
    echo "MariaDB Root Password: $MYSQL_ROOT_PASS" >> "$BACKUP_DIR/credentials.txt"
    chmod 600 "$BACKUP_DIR/credentials.txt"
    
    log INFO "MariaDB root password saved to $BACKUP_DIR/credentials.txt"
}

create_database() {
    log INFO "Creating WordPress database..."
    
    # Generate credentials if not set
    [[ -z "$WP_DB_NAME" ]] && WP_DB_NAME="wp_$(openssl rand -hex 4)"
    [[ -z "$WP_DB_USER" ]] && WP_DB_USER="wp_$(openssl rand -hex 4)"
    [[ -z "$WP_DB_PASS" ]] && WP_DB_PASS=$(generate_password 24)
    
    # Create database and user
    mysql << EOF
CREATE DATABASE IF NOT EXISTS \`$WP_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE USER IF NOT EXISTS '$WP_DB_USER'@'localhost' IDENTIFIED BY '$WP_DB_PASS';
GRANT ALL PRIVILEGES ON \`$WP_DB_NAME\`.* TO '$WP_DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    log INFO "Database '$WP_DB_NAME' created successfully"
    
    # Save credentials
    cat >> "$BACKUP_DIR/credentials.txt" << EOF

WordPress Database Credentials:
  Database: $WP_DB_NAME
  Username: $WP_DB_USER
  Password: $WP_DB_PASS
EOF
}

install_redis() {
    if [[ "$ENABLE_REDIS" == true ]]; then
        log INFO "Installing Redis..."
        
        apt-get install -y -qq redis-server
        
        # Configure Redis
        sed -i "s/# maxmemory <bytes>/maxmemory 256mb/" /etc/redis/redis.conf
        sed -i "s/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/" /etc/redis/redis.conf
        
        systemctl enable redis-server
        systemctl restart redis-server
        
        log INFO "Redis installed and configured"
    fi
}

install_wordpress() {
    log INFO "Installing WordPress..."
    
    # Create web directory
    local WP_PATH="$WEB_ROOT/$DOMAIN"
    mkdir -p "$WP_PATH"
    
    # Download WordPress
    cd /tmp
    wget -q https://wordpress.org/latest.tar.gz
    tar -xzf latest.tar.gz
    
    # Move WordPress files
    cp -a wordpress/. "$WP_PATH/"
    rm -rf wordpress latest.tar.gz
    
    # Download WP-CLI
    if ! command -v wp &> /dev/null; then
        curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x wp-cli.phar
        mv wp-cli.phar /usr/local/bin/wp
    fi
    
    # Configure WordPress
    cd "$WP_PATH"
    
    # Generate wp-config.php
    cat > wp-config.php << EOF
<?php
/**
 * WordPress Configuration File
 * Generated by Production WordPress Installer
 */

// ** Database settings **
define( 'DB_NAME', '$WP_DB_NAME' );
define( 'DB_USER', '$WP_DB_USER' );
define( 'DB_PASSWORD', '$WP_DB_PASS' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', 'utf8mb4_unicode_520_ci' );

// ** Authentication keys and salts **
$(curl -s https://api.wordpress.org/secret-key/1.1/salt/)

// ** WordPress table prefix **
\$table_prefix = 'wp_';

// ** Debug settings **
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', false );

// ** Performance settings **
define( 'WP_MEMORY_LIMIT', '512M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );

// ** Security settings **
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_UNFILTERED_HTML', true );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );

// ** File system method **
define( 'FS_METHOD', 'direct' );

// ** SSL settings **
if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    \$_SERVER['HTTPS'] = 'on';
}

// ** WordPress URLs **
define( 'WP_HOME', 'https://$DOMAIN' );
define( 'WP_SITEURL', 'https://$DOMAIN' );

// ** Caching **
define( 'WP_CACHE', true );
EOF

    if [[ "$ENABLE_REDIS" == true ]]; then
        cat >> wp-config.php << 'EOF'

// ** Redis Object Cache **
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
define( 'WP_REDIS_DATABASE', 0 );
EOF
    fi

    cat >> wp-config.php << 'EOF'

// ** That's all, stop editing! Happy publishing. **
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
EOF

    # Set permissions
    chown -R www-data:www-data "$WP_PATH"
    find "$WP_PATH" -type d -exec chmod 755 {} \;
    find "$WP_PATH" -type f -exec chmod 644 {} \;
    chmod 600 "$WP_PATH/wp-config.php"
    
    # Create uploads directory
    mkdir -p "$WP_PATH/wp-content/uploads"
    chown -R www-data:www-data "$WP_PATH/wp-content/uploads"
    chmod 755 "$WP_PATH/wp-content/uploads"
    
    log INFO "WordPress installed to $WP_PATH"
}

install_wordpress_core() {
    log INFO "Running WordPress core installation..."
    
    local WP_PATH="$WEB_ROOT/$DOMAIN"
    cd "$WP_PATH"
    
    # Generate admin password if not set (fallback for unattended mode)
    if [[ -z "$WP_ADMIN_PASS" ]]; then
        WP_ADMIN_PASS=$(generate_password 16)
        log INFO "Auto-generated WordPress admin password"
    fi
    [[ -z "$WP_ADMIN_EMAIL" ]] && WP_ADMIN_EMAIL="admin@$DOMAIN"
    
    # Log the password being used (for debugging)
    log INFO "WordPress admin user: $WP_ADMIN_USER"
    
    # Run WordPress installation
    sudo -u www-data wp core install \
        --url="https://$DOMAIN" \
        --title="$WP_TITLE" \
        --admin_user="$WP_ADMIN_USER" \
        --admin_password="$WP_ADMIN_PASS" \
        --admin_email="$WP_ADMIN_EMAIL" \
        --skip-email
    
    # Set timezone
    sudo -u www-data wp option update timezone_string "UTC"
    
    # Update permalinks
    sudo -u www-data wp rewrite structure '/%postname%/' --hard
    
    # Remove default plugins and themes
    sudo -u www-data wp plugin delete hello akismet --quiet 2>/dev/null || true
    sudo -u www-data wp theme delete twentytwentyone twentytwentytwo twentytwentythree --quiet 2>/dev/null || true
    
    # Install recommended plugins
    sudo -u www-data wp plugin install \
        wordfence \
        wp-super-cache \
        sucuri-scanner \
        all-in-one-wp-migration \
        --activate
    
    if [[ "$ENABLE_REDIS" == true ]]; then
        sudo -u www-data wp plugin install redis-cache --activate
        sudo -u www-data wp redis enable
    fi
    
    # Save admin credentials
    cat >> "$BACKUP_DIR/credentials.txt" << EOF

WordPress Admin Credentials:
  URL: https://$DOMAIN/wp-admin
  Username: $WP_ADMIN_USER
  Password: $WP_ADMIN_PASS
  Email: $WP_ADMIN_EMAIL
EOF
    
    log INFO "WordPress core installed and configured"
}

configure_nginx_vhost() {
    local WP_PATH="$WEB_ROOT/$DOMAIN"
    
    # Check if nginx config already exists
    if [[ -f "/etc/nginx/sites-available/$DOMAIN" ]]; then
        log INFO "Nginx virtual host for $DOMAIN already exists. Skipping configuration."
        
        # Ensure it's enabled
        if [[ ! -L "/etc/nginx/sites-enabled/$DOMAIN" ]]; then
            ln -sf "/etc/nginx/sites-available/$DOMAIN" "/etc/nginx/sites-enabled/"
        fi
        
        # Create PHP-FPM socket symlink if not exists
        ln -sf "/run/php/php${PHP_VERSION}-fpm.sock" /run/php/php-fpm.sock 2>/dev/null || true
        
        systemctl reload nginx 2>/dev/null || true
        return 0
    fi
    
    log INFO "Configuring Nginx virtual host..."
    
    # Create security snippets
    cat > /etc/nginx/snippets/security.conf << 'EOF'
# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()" always;

# Hide Nginx version
server_tokens off;

# Prevent access to hidden files
location ~ /\. {
    deny all;
    access_log off;
    log_not_found off;
}

# Disable xmlrpc.php
location = /xmlrpc.php {
    deny all;
    access_log off;
    log_not_found off;
}

# Protect wp-config.php
location ~ /wp-config\.php$ {
    deny all;
}

# Protect sensitive directories
location ~* ^/(wp-content/uploads|wp-includes)/.*\.(php|phps|phtml|php3|php4|php5|php7|phar)$ {
    deny all;
}
EOF

    # Create WordPress optimizations snippet
    cat > /etc/nginx/snippets/wordpress.conf << 'EOF'
# WordPress permalink support
location / {
    try_files $uri $uri/ /index.php?$args;
}

# Pass PHP files to PHP-FPM
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_intercept_errors off;
    fastcgi_buffer_size 16k;
    fastcgi_buffers 4 16k;
    fastcgi_connect_timeout 300;
    fastcgi_send_timeout 300;
    fastcgi_read_timeout 300;
}

# Static file caching
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 365d;
    add_header Cache-Control "public, immutable";
    access_log off;
}

# Gzip compression
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml application/json application/javascript application/xml application/rss+xml application/atom+xml image/svg+xml;
EOF

    # Check if SSL certificate exists (Let's Encrypt or any valid cert)
    local SSL_CERT=""
    local SSL_KEY=""
    local HAS_SSL=false
    
    if [[ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]]; then
        SSL_CERT="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"
        SSL_KEY="/etc/letsencrypt/live/$DOMAIN/privkey.pem"
        HAS_SSL=true
        log INFO "Found existing Let's Encrypt SSL certificate"
    elif [[ -f "/etc/ssl/certs/ssl-cert-snakeoil.pem" ]]; then
        SSL_CERT="/etc/ssl/certs/ssl-cert-snakeoil.pem"
        SSL_KEY="/etc/ssl/private/ssl-cert-snakeoil.key"
        HAS_SSL=true
        log INFO "Using snakeoil SSL certificate"
    fi
    
    # Create main virtual host configuration (HTTP only if no SSL)
    if [[ "$HAS_SSL" == true ]]; then
        cat > "/etc/nginx/sites-available/$DOMAIN" << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;
    
    # Redirect to HTTPS
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $DOMAIN www.$DOMAIN;
    
    root $WP_PATH;
    index index.php index.html index.htm;
    
    # SSL Configuration
    ssl_certificate $SSL_CERT;
    ssl_certificate_key $SSL_KEY;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_stapling on;
    ssl_stapling_verify on;
    
    # Logging
    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;
    
    # Client upload size
    client_max_body_size 256M;
    
    # Include snippets
    include snippets/security.conf;
    include snippets/wordpress.conf;
}
EOF
    else
        # HTTP-only configuration (no SSL available)
        log WARN "No SSL certificate found. Creating HTTP-only configuration."
        log WARN "Run 'certbot --nginx -d $DOMAIN' after installation to add SSL."
        
        cat > "/etc/nginx/sites-available/$DOMAIN" << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;
    
    root $WP_PATH;
    index index.php index.html index.htm;
    
    # Logging
    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;
    
    # Client upload size
    client_max_body_size 256M;
    
    # Include snippets
    include snippets/security.conf;
    include snippets/wordpress.conf;
}
EOF
    fi

    # Create PHP-FPM socket symlink
    ln -sf "/run/php/php${PHP_VERSION}-fpm.sock" /run/php/php-fpm.sock
    
    # Enable site
    ln -sf "/etc/nginx/sites-available/$DOMAIN" "/etc/nginx/sites-enabled/"
    
    # Remove default site
    rm -f /etc/nginx/sites-enabled/default
    
    # Test and reload Nginx
    nginx -t
    systemctl reload nginx
    
    log INFO "Nginx virtual host configured"
}

install_ssl() {
    if [[ "$INSTALL_SSL" == true ]]; then
        log INFO "Installing SSL certificate with Let's Encrypt..."
        
        # Install Certbot
        apt-get install -y -qq certbot python3-certbot-nginx
        
        # Obtain certificate
        certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos --email "$WP_ADMIN_EMAIL" --redirect
        
        # Setup auto-renewal
        systemctl enable certbot.timer
        systemctl start certbot.timer
        
        log INFO "SSL certificate installed and auto-renewal configured"
    else
        log WARN "Skipping SSL installation. Using self-signed certificate."
        log WARN "Run 'certbot --nginx -d $DOMAIN' to install a Let's Encrypt certificate"
    fi
}

install_fail2ban() {
    if [[ "$ENABLE_FAIL2BAN" == true ]]; then
        log INFO "Installing and configuring Fail2Ban..."
        
        apt-get install -y -qq fail2ban
        
        # Create WordPress jail
        cat > /etc/fail2ban/jail.d/wordpress.conf << 'EOF'
[wordpress]
enabled = true
filter = wordpress
logpath = /var/log/nginx/*_access.log
maxretry = 5
findtime = 600
bantime = 3600

[wordpress-xmlrpc]
enabled = true
filter = wordpress-xmlrpc
logpath = /var/log/nginx/*_access.log
maxretry = 1
findtime = 300
bantime = 86400
EOF

        # Create WordPress filter
        cat > /etc/fail2ban/filter.d/wordpress.conf << 'EOF'
[Definition]
failregex = ^<HOST> .* "POST /wp-login.php
ignoreregex =
EOF

        cat > /etc/fail2ban/filter.d/wordpress-xmlrpc.conf << 'EOF'
[Definition]
failregex = ^<HOST> .* "POST /xmlrpc.php
ignoreregex =
EOF

        systemctl enable fail2ban
        systemctl restart fail2ban
        
        log INFO "Fail2Ban installed and configured"
    fi
}

configure_firewall() {
    log INFO "Configuring firewall..."
    
    # Check if UFW is installed
    if command -v ufw &> /dev/null; then
        ufw allow 'Nginx Full'
        ufw allow OpenSSH
        
        # Enable firewall if not already enabled
        if [[ $(ufw status | grep -c "inactive") -gt 0 ]]; then
            echo "y" | ufw enable
        fi
        
        log INFO "Firewall configured with UFW"
    else
        log WARN "UFW not found. Please configure your firewall manually."
    fi
}

setup_cron() {
    log INFO "Setting up WordPress cron..."
    
    local WP_PATH="$WEB_ROOT/$DOMAIN"
    
    # Disable WordPress built-in cron (we'll use system cron)
    if ! grep -q "DISABLE_WP_CRON" "$WP_PATH/wp-config.php"; then
        sed -i "/That's all, stop editing!/i \\\n// Disable WordPress built-in cron\ndefine( 'DISABLE_WP_CRON', true );" "$WP_PATH/wp-config.php"
    fi
    
    # Add system cron job
    (crontab -l 2>/dev/null | grep -v "wp-cron.php" ; echo "*/5 * * * * cd $WP_PATH && /usr/bin/php wp-cron.php > /dev/null 2>&1") | crontab -
    
    log INFO "WordPress cron configured"
}

setup_backup_script() {
    log INFO "Setting up backup script..."
    
    local WP_PATH="$WEB_ROOT/$DOMAIN"
    local BACKUP_SCRIPT="/usr/local/bin/backup-wordpress-$DOMAIN.sh"
    
    cat > "$BACKUP_SCRIPT" << EOF
#!/bin/bash
# WordPress Backup Script for $DOMAIN
# Generated by WordPress Installer

BACKUP_DIR="/var/backups/wordpress/$DOMAIN"
DATE=\$(date +%Y%m%d_%H%M%S)
WP_PATH="$WP_PATH"
DB_NAME="$WP_DB_NAME"

# Create backup directory
mkdir -p "\$BACKUP_DIR"

# Backup database
mysqldump "\$DB_NAME" | gzip > "\$BACKUP_DIR/db_\$DATE.sql.gz"

# Backup files
tar -czf "\$BACKUP_DIR/files_\$DATE.tar.gz" -C "\$WP_PATH" .

# Remove backups older than 7 days
find "\$BACKUP_DIR" -type f -mtime +7 -delete

echo "Backup completed: \$DATE"
EOF

    chmod +x "$BACKUP_SCRIPT"
    
    # Add to cron (daily at 3 AM)
    (crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" ; echo "0 3 * * * $BACKUP_SCRIPT > /dev/null 2>&1") | crontab -
    
    log INFO "Backup script created at $BACKUP_SCRIPT"
}

#===============================================================================
# INTERACTIVE PROMPTS
#===============================================================================

prompt_configuration() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                     Configuration Options                        ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    # Domain
    while [[ -z "$DOMAIN" ]]; do
        read -p "Enter your domain name (e.g., example.com): " DOMAIN
        DOMAIN=$(echo "$DOMAIN" | tr '[:upper:]' '[:lower:]' | sed 's/^www\.//')
    done
    
    # Site title
    read -p "Enter site title [$WP_TITLE]: " input
    WP_TITLE="${input:-$WP_TITLE}"
    
    # Admin username
    read -p "Enter admin username [$WP_ADMIN_USER]: " input
    WP_ADMIN_USER="${input:-$WP_ADMIN_USER}"
    
    # Admin email
    while [[ -z "$WP_ADMIN_EMAIL" ]]; do
        read -p "Enter admin email address: " WP_ADMIN_EMAIL
    done
    
    # Admin password
    read -sp "Enter admin password (leave blank to auto-generate): " input_pass
    echo ""
    
    if [[ -z "$input_pass" ]]; then
        WP_ADMIN_PASS=$(generate_password 16)
        echo -e "${GREEN}[INFO]${NC} Auto-generated admin password: ${YELLOW}$WP_ADMIN_PASS${NC}"
        echo -e "${YELLOW}[IMPORTANT]${NC} Please save this password now!"
    else
        WP_ADMIN_PASS="$input_pass"
    fi
    
    # SSL
    read -p "Install Let's Encrypt SSL certificate? (y/n) [n]: " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        INSTALL_SSL=true
    fi
    
    # Redis
    read -p "Install Redis for object caching? (y/n) [n]: " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        ENABLE_REDIS=true
    fi
    
    # Fail2Ban
    read -p "Install Fail2Ban for security? (y/n) [n]: " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        ENABLE_FAIL2BAN=true
    fi
    
    echo ""
    echo -e "${YELLOW}Configuration Summary:${NC}"
    echo "  Domain: $DOMAIN"
    echo "  Site Title: $WP_TITLE"
    echo "  Admin User: $WP_ADMIN_USER"
    echo "  Admin Email: $WP_ADMIN_EMAIL"
    echo -e "  Admin Password: ${GREEN}$WP_ADMIN_PASS${NC}"
    echo "  Install SSL: $INSTALL_SSL"
    echo "  Enable Redis: $ENABLE_REDIS"
    echo "  Enable Fail2Ban: $ENABLE_FAIL2BAN"
    echo ""
    
    read -p "Proceed with installation? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log INFO "Installation cancelled by user"
        exit 0
    fi
}

print_summary() {
    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                  Installation Complete!                          ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    # Display credentials prominently
    echo -e "${YELLOW}╔══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║                     IMPORTANT CREDENTIALS                        ║${NC}"
    echo -e "${YELLOW}╚══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${CYAN}WordPress Admin Login:${NC}"
    echo -e "  URL:      ${GREEN}https://$DOMAIN/wp-admin${NC}"
    echo -e "  Username: ${GREEN}$WP_ADMIN_USER${NC}"
    echo -e "  Password: ${GREEN}$WP_ADMIN_PASS${NC}"
    echo -e "  Email:    ${GREEN}$WP_ADMIN_EMAIL${NC}"
    echo ""
    echo -e "${CYAN}Database Credentials:${NC}"
    echo -e "  Database: ${GREEN}$WP_DB_NAME${NC}"
    echo -e "  Username: ${GREEN}$WP_DB_USER${NC}"
    echo -e "  Password: ${GREEN}$WP_DB_PASS${NC}"
    echo ""
    echo -e "${YELLOW}╔══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║  SAVE THESE CREDENTIALS! They are also in credentials.txt        ║${NC}"
    echo -e "${YELLOW}╚══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    echo -e "${CYAN}WordPress Site:${NC}"
    echo "  URL: https://$DOMAIN"
    echo "  Admin: https://$DOMAIN/wp-admin"
    echo ""
    echo -e "${CYAN}Files Location:${NC}"
    echo "  Web Root: $WEB_ROOT/$DOMAIN"
    echo "  Credentials: $BACKUP_DIR/credentials.txt"
    echo "  Log File: $LOG_FILE"
    echo ""
    echo -e "${CYAN}Services Installed:${NC}"
    echo "  - Nginx (Web Server)"
    echo "  - PHP $PHP_VERSION (PHP-FPM)"
    echo "  - MariaDB (Database)"
    [[ "$ENABLE_REDIS" == true ]] && echo "  - Redis (Object Cache)"
    [[ "$ENABLE_FAIL2BAN" == true ]] && echo "  - Fail2Ban (Security)"
    [[ "$INSTALL_SSL" == true ]] && echo "  - Let's Encrypt SSL"
    echo ""
    echo -e "${YELLOW}Important:${NC}"
    echo "  - All credentials are saved in: $BACKUP_DIR/credentials.txt"
    echo "  - Daily backups configured to run at 3:00 AM"
    echo "  - Review security settings and update as needed"
    echo ""
    echo -e "${GREEN}Thank you for using the WordPress Installer!${NC}"
    echo ""
}

#===============================================================================
# COMMAND LINE ARGUMENT PARSING
#===============================================================================

show_help() {
    cat << EOF
Usage: $0 [OPTIONS]

Production-ready WordPress installer for Ubuntu/Debian

Options:
  -d, --domain DOMAIN       Domain name for the WordPress site
  -t, --title TITLE         Site title (default: "My WordPress Site")
  -u, --admin-user USER     Admin username (default: admin)
  -e, --admin-email EMAIL   Admin email address
  -p, --admin-pass PASS     Admin password (auto-generated if not set)
  --db-name NAME            Database name (auto-generated if not set)
  --db-user USER            Database username (auto-generated if not set)
  --db-pass PASS            Database password (auto-generated if not set)
  --php-version VER         PHP version to install (default: 8.2)
  --with-ssl                Install Let's Encrypt SSL certificate
  --with-redis              Install Redis for object caching
  --with-fail2ban           Install Fail2Ban for security
  --unattended              Run in unattended mode (requires -d and -e)
  -h, --help                Show this help message

Examples:
  Interactive mode:
    sudo $0

  Unattended mode:
    sudo $0 -d example.com -e admin@example.com --with-ssl --unattended

  Full options:
    sudo $0 -d example.com -t "My Amazing Site" -u admin -e admin@example.com \
            --with-ssl --with-redis --with-fail2ban --unattended

For more information, visit: https://github.com/your-repo/wordpress-installer
EOF
    exit 0
}

parse_arguments() {
    local UNATTENDED=false
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            -d|--domain)
                DOMAIN="$2"
                shift 2
                ;;
            -t|--title)
                WP_TITLE="$2"
                shift 2
                ;;
            -u|--admin-user)
                WP_ADMIN_USER="$2"
                shift 2
                ;;
            -e|--admin-email)
                WP_ADMIN_EMAIL="$2"
                shift 2
                ;;
            -p|--admin-pass)
                WP_ADMIN_PASS="$2"
                shift 2
                ;;
            --db-name)
                WP_DB_NAME="$2"
                shift 2
                ;;
            --db-user)
                WP_DB_USER="$2"
                shift 2
                ;;
            --db-pass)
                WP_DB_PASS="$2"
                shift 2
                ;;
            --php-version)
                PHP_VERSION="$2"
                shift 2
                ;;
            --with-ssl)
                INSTALL_SSL=true
                shift
                ;;
            --with-redis)
                ENABLE_REDIS=true
                shift
                ;;
            --with-fail2ban)
                ENABLE_FAIL2BAN=true
                shift
                ;;
            --unattended)
                UNATTENDED=true
                shift
                ;;
            -h|--help)
                show_help
                ;;
            *)
                log ERROR "Unknown option: $1"
                show_help
                ;;
        esac
    done
    
    if [[ "$UNATTENDED" == true ]]; then
        if [[ -z "$DOMAIN" ]] || [[ -z "$WP_ADMIN_EMAIL" ]]; then
            log ERROR "Unattended mode requires --domain and --admin-email"
            exit 1
        fi
    fi
    
    echo "$UNATTENDED"
}

#===============================================================================
# MAIN INSTALLATION
#===============================================================================

main() {
    print_banner
    check_root
    check_os
    create_backup_dir
    
    # Initialize log file
    mkdir -p "$(dirname "$LOG_FILE")"
    touch "$LOG_FILE"
    chmod 600 "$LOG_FILE"
    
    # Parse command line arguments
    local UNATTENDED=$(parse_arguments "$@")
    
    # Interactive configuration if not unattended
    if [[ "$UNATTENDED" != "true" ]]; then
        prompt_configuration
    fi
    
    log INFO "Starting WordPress installation for $DOMAIN"
    
    # Run installation steps
    update_system
    install_dependencies
    install_nginx
    install_php
    install_mariadb
    create_database
    install_redis
    install_wordpress
    configure_nginx_vhost
    install_wordpress_core
    install_ssl
    install_fail2ban
    configure_firewall
    setup_cron
    setup_backup_script
    
    # Final success message
    print_summary
    
    log INFO "WordPress installation completed successfully"
    
    return 0
}

# Run main function with all script arguments
main "$@"
