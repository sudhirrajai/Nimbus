#!/bin/bash

# Nimbus Control Panel - Uninstaller Script
# Interactive uninstallation with multiple removal modes
# Usage: curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/uninstall.sh | sudo bash

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Configuration (must match install.sh)
NIMBUS_DIR="/usr/local/nimbus"
PHP_VERSION="8.2"

# ─────────────────────────────────────────────────────────────────
# Helper functions
# ─────────────────────────────────────────────────────────────────

print_header() {
    echo ""
    echo -e "${RED}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                                                              ║"
    echo "║     ███╗   ██╗██╗███╗   ███╗██████╗ ██╗   ██╗███████╗        ║"
    echo "║     ████╗  ██║██║████╗ ████║██╔══██╗██║   ██║██╔════╝        ║"
    echo "║     ██╔██╗ ██║██║██╔████╔██║██████╔╝██║   ██║███████╗        ║"
    echo "║     ██║╚██╗██║██║██║╚██╔╝██║██╔══██╗██║   ██║╚════██║        ║"
    echo "║     ██║ ╚████║██║██║ ╚═╝ ██║██████╔╝╚██████╔╝███████║        ║"
    echo "║     ╚═╝  ╚═══╝╚═╝╚═╝     ╚═╝╚═════╝  ╚═════╝ ╚══════╝        ║"
    echo "║                                                              ║"
    echo "║              Control Panel Uninstaller v1.0.0                ║"
    echo "║                                                              ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

print_divider() {
    echo -e "${BLUE}──────────────────────────────────────────────────────────${NC}"
}

detect_db_service() {
    if systemctl list-unit-files 2>/dev/null | grep -q '^mariadb\.service'; then
        echo "mariadb"
    elif systemctl list-unit-files 2>/dev/null | grep -q '^mysql\.service'; then
        echo "mysql"
    else
        echo "mariadb"
    fi
}

# Stop and disable a service (silently)
stop_service() {
    local svc="$1"
    systemctl stop    "$svc" 2>/dev/null || true
    systemctl disable "$svc" 2>/dev/null || true
}

# ─────────────────────────────────────────────────────────────────
# Removal functions (modular)
# ─────────────────────────────────────────────────────────────────

remove_nimbus_portal() {
    echo ""
    echo -e "${RED}▸ Removing Nimbus portal files...${NC}"
    rm -rf "${NIMBUS_DIR}"
    rm -f /etc/nginx/sites-available/nimbus
    rm -f /etc/nginx/sites-enabled/nimbus
    rm -f /etc/sudoers.d/nimbus
    rm -f /usr/local/nimbus/storage/logs/nimbus_install.lock 2>/dev/null || true

    # Remove temp/lock files
    rm -f /tmp/adminer_install.sh \
          /tmp/adminer_reinstall.sh \
          /tmp/nodesource_setup.sh

    # Remove Adminer
    rm -rf /usr/share/adminer

    echo -e "${GREEN}  ✓ Nimbus portal removed${NC}"
}

remove_nginx() {
    echo -e "${RED}▸ Stopping and removing Nginx...${NC}"
    stop_service nginx
    DEBIAN_FRONTEND=noninteractive apt-get purge -y \
        nginx nginx-common nginx-full nginx-core 2>/dev/null || true
    rm -rf /etc/nginx
    echo -e "${GREEN}  ✓ Nginx removed${NC}"
}

remove_php() {
    echo -e "${RED}▸ Stopping and removing PHP...${NC}"
    stop_service "php${PHP_VERSION}-fpm"
    DEBIAN_FRONTEND=noninteractive apt-get purge -y \
        "php${PHP_VERSION}"* php8.1* php8.2* php8.3* php8.4* php8.5* 2>/dev/null || true
    rm -rf /etc/php
    echo -e "${GREEN}  ✓ PHP removed${NC}"
}

remove_database() {
    local DB_SVC
    DB_SVC=$(detect_db_service)
    echo -e "${RED}▸ Stopping and removing database (${DB_SVC})...${NC}"
    stop_service "$DB_SVC"
    DEBIAN_FRONTEND=noninteractive apt-get purge -y \
        mariadb-server mariadb-client mariadb-common \
        mysql-server mysql-client mysql-common 2>/dev/null || true
    rm -rf /var/lib/mysql /var/log/mysql /etc/mysql
    echo -e "${GREEN}  ✓ Database server removed${NC}"
}

remove_supervisor() {
    echo -e "${RED}▸ Stopping and removing Supervisor...${NC}"
    stop_service supervisor
    DEBIAN_FRONTEND=noninteractive apt-get purge -y supervisor 2>/dev/null || true
    rm -rf /etc/supervisor /var/log/supervisor
    echo -e "${GREEN}  ✓ Supervisor removed${NC}"
}

remove_nodejs() {
    echo -e "${RED}▸ Removing Node.js...${NC}"
    DEBIAN_FRONTEND=noninteractive apt-get purge -y nodejs npm libnode* 2>/dev/null || true
    rm -f /usr/local/bin/node /usr/local/bin/npm /usr/local/bin/npx
    rm -rf /root/.nvm
    echo -e "${GREEN}  ✓ Node.js removed${NC}"
}

remove_composer() {
    echo -e "${RED}▸ Removing Composer...${NC}"
    rm -f /usr/local/bin/composer
    echo -e "${GREEN}  ✓ Composer removed${NC}"
}

remove_projects() {
    echo -e "${RED}▸ Removing hosted projects (/var/www)...${NC}"
    rm -rf /var/www
    echo -e "${GREEN}  ✓ All hosted projects removed${NC}"
}

cleanup_packages() {
    echo -e "${YELLOW}▸ Cleaning up unused packages...${NC}"
    DEBIAN_FRONTEND=noninteractive apt-get autoremove -y --purge 2>/dev/null || true
    apt-get clean 2>/dev/null || true
    echo -e "${GREEN}  ✓ Package cleanup done${NC}"
}

reset_firewall() {
    echo ""
    read -r -p "$(echo -e "${YELLOW}Reset UFW firewall rules to defaults? [y/N]: ${NC}")" RESET_FW
    if [ "$RESET_FW" = "y" ] || [ "$RESET_FW" = "Y" ]; then
        ufw --force reset 2>/dev/null || true
        ufw default deny incoming 2>/dev/null || true
        ufw default allow outgoing 2>/dev/null || true
        ufw allow ssh 2>/dev/null || true
        ufw --force enable 2>/dev/null || true
        echo -e "${GREEN}  ✓ Firewall reset (SSH allowed)${NC}"
    else
        echo -e "${YELLOW}  ⏭ Firewall rules unchanged${NC}"
    fi
}

# ─────────────────────────────────────────────────────────────────
# Uninstall modes
# ─────────────────────────────────────────────────────────────────

mode_full_uninstall() {
    echo ""
    echo -e "${RED}${BOLD}⚠  FULL UNINSTALL${NC}"
    echo -e "${RED}   This will remove:${NC}"
    echo -e "   • Nimbus control panel"
    echo -e "   • All services (Nginx, PHP, MariaDB/MySQL, Node.js, Supervisor, Composer)"
    echo -e "   • ${RED}ALL databases and data${NC}"
    echo -e "   • ${RED}ALL hosted projects in /var/www${NC}"
    echo ""
    echo -e "${RED}${BOLD}   ⛔ THIS ACTION IS IRREVERSIBLE! ALL DATA WILL BE LOST!${NC}"
    echo ""
    read -r -p "$(echo -e "${RED}Type 'YES I AM SURE' to continue: ${NC}")" CONFIRM
    if [ "$CONFIRM" != "YES I AM SURE" ]; then
        echo -e "${YELLOW}Uninstall cancelled.${NC}"
        exit 0
    fi

    echo ""
    print_divider
    remove_nimbus_portal
    remove_nginx
    remove_php
    remove_database
    remove_supervisor
    remove_nodejs
    remove_composer
    remove_projects
    cleanup_packages
    reset_firewall
}

mode_keep_projects_remove_all() {
    echo ""
    echo -e "${YELLOW}${BOLD}⚠  REMOVE SERVICES + PORTAL (Keep Projects)${NC}"
    echo -e "   This will remove:"
    echo -e "   • Nimbus control panel"
    echo -e "   • All services (Nginx, PHP, MariaDB/MySQL, Node.js, Supervisor, Composer)"
    echo -e "   • ${RED}ALL databases and data${NC}"
    echo ""
    echo -e "   ${GREEN}✓ Projects in /var/www will be preserved${NC}"
    echo ""
    read -r -p "$(echo -e "${YELLOW}Type 'yes' to continue: ${NC}")" CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        echo -e "${YELLOW}Uninstall cancelled.${NC}"
        exit 0
    fi

    echo ""
    print_divider
    remove_nimbus_portal
    remove_nginx
    remove_php
    remove_database
    remove_supervisor
    remove_nodejs
    remove_composer
    cleanup_packages
    reset_firewall
}

mode_keep_projects_keep_db() {
    echo ""
    echo -e "${YELLOW}${BOLD}⚠  REMOVE SERVICES (Keep DB + Projects)${NC}"
    echo -e "   This will remove:"
    echo -e "   • Nimbus control panel"
    echo -e "   • Nginx, PHP, Node.js, Supervisor, Composer"
    echo ""
    echo -e "   ${GREEN}✓ Database (MariaDB/MySQL) will be preserved${NC}"
    echo -e "   ${GREEN}✓ Projects in /var/www will be preserved${NC}"
    echo ""
    read -r -p "$(echo -e "${YELLOW}Type 'yes' to continue: ${NC}")" CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        echo -e "${YELLOW}Uninstall cancelled.${NC}"
        exit 0
    fi

    echo ""
    print_divider
    remove_nimbus_portal
    remove_nginx
    remove_php
    remove_supervisor
    remove_nodejs
    remove_composer
    cleanup_packages
    reset_firewall
}

mode_portal_only() {
    echo ""
    echo -e "${BLUE}${BOLD}ℹ  REMOVE PORTAL ONLY${NC}"
    echo -e "   This will remove:"
    echo -e "   • Nimbus control panel files (${NIMBUS_DIR})"
    echo -e "   • Nimbus Nginx vhost config"
    echo -e "   • Nimbus sudoers file"
    echo -e "   • Adminer"
    echo ""
    echo -e "   ${GREEN}✓ All services will remain running${NC}"
    echo -e "   ${GREEN}✓ Database will be preserved${NC}"
    echo -e "   ${GREEN}✓ Projects in /var/www will be preserved${NC}"
    echo -e "   ${GREEN}✓ Other Nginx vhosts will remain active${NC}"
    echo ""
    read -r -p "$(echo -e "${YELLOW}Type 'yes' to continue: ${NC}")" CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        echo -e "${YELLOW}Uninstall cancelled.${NC}"
        exit 0
    fi

    echo ""
    print_divider
    remove_nimbus_portal

    # Reload Nginx to drop the nimbus vhost (if Nginx is still running)
    if systemctl is-active --quiet nginx 2>/dev/null; then
        nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || true
        echo -e "${GREEN}  ✓ Nginx reloaded${NC}"
    fi
}

# ─────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────

# Must be root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (sudo)${NC}"
    exit 1
fi

print_header

# Check if Nimbus is installed
if [ ! -d "$NIMBUS_DIR" ]; then
    echo -e "${YELLOW}Nimbus does not appear to be installed at ${NIMBUS_DIR}.${NC}"
    read -r -p "Continue anyway? [y/N]: " CONTINUE
    if [ "$CONTINUE" != "y" ] && [ "$CONTINUE" != "Y" ]; then
        echo "Exiting."
        exit 0
    fi
fi

echo -e "${BOLD}Select an uninstall mode:${NC}"
echo ""
echo -e "  ${RED}1)${NC} ${BOLD}Full Uninstall${NC}"
echo -e "     Remove everything: portal, all services, databases, and projects"
echo ""
echo -e "  ${YELLOW}2)${NC} ${BOLD}Remove Services + Portal (Keep Projects)${NC}"
echo -e "     Remove portal and all services but keep /var/www project files"
echo ""
echo -e "  ${YELLOW}3)${NC} ${BOLD}Remove Services except DB + Portal (Keep Projects)${NC}"
echo -e "     Remove portal and services but keep database server and /var/www"
echo ""
echo -e "  ${BLUE}4)${NC} ${BOLD}Remove Portal Only${NC}"
echo -e "     Remove only the Nimbus panel; keep all services and projects running"
echo ""
echo -e "  ${GREEN}0)${NC} Cancel"
echo ""
print_divider
read -r -p "$(echo -e "${CYAN}Enter your choice [0-4]: ${NC}")" CHOICE

case "$CHOICE" in
    1) mode_full_uninstall ;;
    2) mode_keep_projects_remove_all ;;
    3) mode_keep_projects_keep_db ;;
    4) mode_portal_only ;;
    0|"")
        echo -e "${GREEN}Uninstall cancelled. Nothing was changed.${NC}"
        exit 0
        ;;
    *)
        echo -e "${RED}Invalid option. Exiting.${NC}"
        exit 1
        ;;
esac

# ─────────────────────────────────────────────────────────────────
# Done
# ─────────────────────────────────────────────────────────────────
echo ""
print_divider
echo ""
echo -e "${GREEN}${BOLD}✓ Nimbus uninstall completed successfully!${NC}"
echo ""
echo -e "${YELLOW}Notes:${NC}"
echo -e "  • Standard system packages (curl, git, wget, etc.) were not removed."
echo -e "  • If you reinstall later: ${CYAN}curl -sSL https://raw.githubusercontent.com/sudhirrajai/Nimbus/main/install.sh | sudo bash${NC}"
echo ""
