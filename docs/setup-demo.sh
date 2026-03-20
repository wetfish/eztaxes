#!/bin/bash
#
# EzTaxes Demo Site Setup Script
# https://github.com/wetfish/eztaxes
#
# Sets up a production demo instance with:
# - Dedicated service user
# - PHP 8.5 + Nginx + SQLite
# - Laravel with demo seed data
# - Let's Encrypt SSL
#
# Tested on Ubuntu 24.04 LTS (Debian-based)
# Run as root or with sudo
#
# Usage:
#   chmod +x setup-demo.sh
#   sudo ./setup-demo.sh
#

set -e

# ─── Configuration ───
PROJECT_USER="eztaxes"
PROJECT_REPO="https://github.com/wetfish/eztaxes.git"
PROJECT_DIR="/home/${PROJECT_USER}/eztaxes"
LARAVEL_DIR="${PROJECT_DIR}/laravel"
DOMAIN="eztaxes.wetfish.net"
PHP_VERSION="8.5"

# ─── Colors ───
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ─── Pre-flight checks ───
if [ "$EUID" -ne 0 ]; then
    error "This script must be run as root (use sudo)"
fi

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║       EzTaxes Demo Site Setup            ║"
echo "║       https://eztaxes.wetfish.net        ║"
echo "╚══════════════════════════════════════════╝"
echo ""

read -p "Domain name [$DOMAIN]: " input_domain
DOMAIN="${input_domain:-$DOMAIN}"

read -p "Project user [$PROJECT_USER]: " input_user
PROJECT_USER="${input_user:-$PROJECT_USER}"
PROJECT_DIR="/home/${PROJECT_USER}/eztaxes"
LARAVEL_DIR="${PROJECT_DIR}/laravel"

echo ""
info "Setting up EzTaxes demo at ${DOMAIN}"
info "Project user: ${PROJECT_USER}"
info "Project directory: ${PROJECT_DIR}"
echo ""
read -p "Press Enter to continue or Ctrl+C to cancel..."

# ─── Step 1: System packages ───
info "Step 1/12: Installing system packages..."
apt-get update
apt-get install -y git nginx curl lsb-release ca-certificates ufw

# ─── Step 2: Create project user ───
if id "$PROJECT_USER" &>/dev/null; then
    warn "User '${PROJECT_USER}' already exists, skipping creation"
else
    info "Step 2/12: Creating project user '${PROJECT_USER}'..."
    adduser "$PROJECT_USER" --disabled-password --gecos ""
fi

# Make home directory traversable by nginx
chmod 0755 "/home/${PROJECT_USER}"

# ─── Step 3: Clone repository ───
if [ -d "$PROJECT_DIR" ]; then
    warn "Project directory already exists, pulling latest..."
    sudo -u "$PROJECT_USER" bash -c "cd ${PROJECT_DIR} && git pull"
else
    info "Step 3/12: Cloning repository..."
    sudo -u "$PROJECT_USER" git clone "$PROJECT_REPO" "$PROJECT_DIR"
fi

# ─── Step 4: PHP 8.5 repository ───
info "Step 4/12: Adding PHP ${PHP_VERSION} repository..."
curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
dpkg -i /tmp/debsuryorg-archive-keyring.deb
sh -c "echo 'deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main' > /etc/apt/sources.list.d/php.list"
apt-get update

# ─── Step 5: PHP dependencies ───
info "Step 5/12: Installing PHP ${PHP_VERSION} and extensions..."
apt-get install -y \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-fpm \
    unzip

# ─── Step 6: Configure PHP-FPM to run as project user ───
info "Step 6/12: Configuring PHP-FPM..."
FPM_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

if [ -f "$FPM_POOL" ]; then
    sed -i "s/^user = www-data/user = ${PROJECT_USER}/" "$FPM_POOL"
    sed -i "s/^group = www-data/group = ${PROJECT_USER}/" "$FPM_POOL"
    systemctl restart php${PHP_VERSION}-fpm
else
    warn "PHP-FPM pool config not found at ${FPM_POOL}, skipping"
fi

# ─── Step 7: Install Composer ───
info "Step 7/12: Installing Composer..."
sudo -u "$PROJECT_USER" bash -c "
    cd /home/${PROJECT_USER}
    php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\"
    php composer-setup.php
    rm composer-setup.php
"

# Install Laravel dependencies
info "Installing Laravel dependencies..."
sudo -u "$PROJECT_USER" bash -c "
    cd ${LARAVEL_DIR}
    /home/${PROJECT_USER}/composer.phar install --no-dev --no-interaction
"

# ─── Step 8: Node.js and frontend build ───
info "Step 8/12: Installing Node.js and building frontend..."
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs

sudo -u "$PROJECT_USER" bash -c "
    cd ${LARAVEL_DIR}
    npm install
    npm run build
"

# ─── Step 9: Laravel environment setup ───
info "Step 9/12: Configuring Laravel..."
sudo -u "$PROJECT_USER" bash -c "
    cd ${LARAVEL_DIR}
    cp .env.example .env
"

# Configure .env for demo mode with SQLite
sudo -u "$PROJECT_USER" bash -c "
    cd ${LARAVEL_DIR}
    sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
    sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
    sed -i 's|^APP_URL=.*|APP_URL=https://${DOMAIN}|' .env
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
    sed -i 's/^SESSION_DRIVER=.*/SESSION_DRIVER=file/' .env

    # Comment out MySQL-specific settings
    sed -i 's/^DB_HOST=/#DB_HOST=/' .env
    sed -i 's/^DB_PORT=/#DB_PORT=/' .env
    sed -i 's/^DB_DATABASE=/#DB_DATABASE=/' .env
    sed -i 's/^DB_USERNAME=/#DB_USERNAME=/' .env
    sed -i 's/^DB_PASSWORD=/#DB_PASSWORD=/' .env

    # Add demo mode and session lifetime
    echo '' >> .env
    echo '# Demo mode - read-only with fictional data' >> .env
    echo 'DEMO_MODE=true' >> .env
    echo 'SESSION_LIFETIME=30' >> .env
"

# Generate app key
sudo -u "$PROJECT_USER" bash -c "
    cd ${LARAVEL_DIR}
    php artisan key:generate --no-interaction
"

# ─── Step 10: Database setup ───
info "Step 10/12: Setting up SQLite database and seeding demo data..."
sudo -u "$PROJECT_USER" bash -c "
    cd ${LARAVEL_DIR}
    touch database/database.sqlite
    php artisan migrate --seed --no-interaction
"

# ─── Step 11: Nginx configuration ───
info "Step 11/12: Configuring Nginx..."

# Default catch-all — blocks requests for unknown domains
cat > /etc/nginx/sites-available/default << 'NGINX_DEFAULT'
# Default catch-all — rejects requests for unknown domains

# HTTP catch-all
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name _;

    # Drop the connection immediately
    return 444;
}

# HTTPS catch-all
server {
    listen 443 default_server;
    listen [::]:443 default_server;

    server_name _;

    # Reject the TLS handshake — no certificate is even presented
    ssl_reject_handshake on;
}
NGINX_DEFAULT

# EzTaxes site config
cat > /etc/nginx/sites-available/${DOMAIN} << NGINX_SITE
server {
    listen 80;
    listen [::]:80;

    server_name ${DOMAIN};
    client_max_body_size 10M;

    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;

    root ${LARAVEL_DIR}/public;
    index index.php index.html index.htm;

    location / {
        autoindex off;
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}
NGINX_SITE

# Enable the site
ln -sf /etc/nginx/sites-available/${DOMAIN} /etc/nginx/sites-enabled/${DOMAIN}

# Remove default Ubuntu nginx placeholder if it conflicts
rm -f /etc/nginx/sites-enabled/default.bak

# Test and reload
nginx -t || error "Nginx config test failed"
systemctl reload nginx

# ─── Step 12: Firewall ───
info "Step 12/12: Configuring firewall..."
ufw allow 'OpenSSH' 2>/dev/null || true
ufw allow 'Nginx Full' 2>/dev/null || true

if ! ufw status | grep -q "Status: active"; then
    warn "UFW is not active. Enable it with: sudo ufw enable"
fi

# ─── Done ───
echo ""
echo "╔══════════════════════════════════════════╗"
echo "║       Setup Complete!                    ║"
echo "╚══════════════════════════════════════════╝"
echo ""
info "EzTaxes demo is running at http://${DOMAIN}"
echo ""
echo "Next steps:"
echo "  1. Make sure DNS for ${DOMAIN} points to this server"
echo "  2. Install SSL certificate:"
echo "     sudo apt install certbot python3-certbot-nginx -y"
echo "     sudo certbot --nginx -d ${DOMAIN}"
echo "  3. Verify the site loads at https://${DOMAIN}"
echo ""
echo "To update the demo later:"
echo "  sudo -u ${PROJECT_USER} bash -c 'cd ${PROJECT_DIR} && git pull'"
echo "  sudo -u ${PROJECT_USER} bash -c 'cd ${LARAVEL_DIR} && /home/${PROJECT_USER}/composer.phar install --no-dev'"
echo "  sudo -u ${PROJECT_USER} bash -c 'cd ${LARAVEL_DIR} && npm install && npm run build'"
echo "  sudo -u ${PROJECT_USER} bash -c 'cd ${LARAVEL_DIR} && php artisan migrate --seed'"
echo ""