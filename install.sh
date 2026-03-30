#!/usr/bin/env bash
#
# MegaCrypter — Fully Automated Installer
#
# Usage:
#   sudo bash install.sh [OPTIONS]
#
# Options:
#   --non-interactive   Run without prompts (uses defaults / auto-detection)
#   --url-base URL      Set the URL base (e.g. http://megacrypter.example.com)
#   --install-dir DIR   Installation directory (default: current directory)
#   --skip-apache       Skip Apache virtual host configuration
#   --skip-db           Skip MySQL database setup
#   --db-name NAME      MySQL database name for blacklist (default: megacrypter)
#   --db-user USER      MySQL user (default: megacrypter)
#   --db-pass PASS      MySQL password (auto-generated if not provided)
#   --db-host HOST      MySQL host (default: localhost)
#   --help              Show this help message
#
set -euo pipefail

# ─── Color helpers ────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

info()    { echo -e "${BLUE}[INFO]${NC}  $*"; }
ok()      { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
err()     { echo -e "${RED}[ERROR]${NC} $*"; }
header()  { echo -e "\n${BOLD}${CYAN}── $* ──${NC}\n"; }

# ─── Defaults ─────────────────────────────────────────────────────────────────
NON_INTERACTIVE=false
URL_BASE=""
INSTALL_DIR=""
SKIP_APACHE=false
SKIP_DB=false
DB_NAME="megacrypter"
DB_USER="megacrypter"
DB_PASS=""
DB_HOST="localhost"

# ─── Parse arguments ─────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --non-interactive) NON_INTERACTIVE=true; shift ;;
        --url-base)        URL_BASE="$2"; shift 2 ;;
        --install-dir)     INSTALL_DIR="$2"; shift 2 ;;
        --skip-apache)     SKIP_APACHE=true; shift ;;
        --skip-db)         SKIP_DB=true; shift ;;
        --db-name)         DB_NAME="$2"; shift 2 ;;
        --db-user)         DB_USER="$2"; shift 2 ;;
        --db-pass)         DB_PASS="$2"; shift 2 ;;
        --db-host)         DB_HOST="$2"; shift 2 ;;
        --help)
            sed -n '3,16p' "$0"
            exit 0
            ;;
        *)
            err "Unknown option: $1"
            exit 1
            ;;
    esac
done

# ─── Resolve install directory ────────────────────────────────────────────────
if [[ -z "$INSTALL_DIR" ]]; then
    # Default to the directory containing this script
    INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
fi

if [[ ! -f "$INSTALL_DIR/composer.json" ]]; then
    err "composer.json not found in $INSTALL_DIR — is this the MegaCrypter root?"
    exit 1
fi

# ─── Helper: prompt with default ─────────────────────────────────────────────
prompt() {
    local var_name="$1" prompt_msg="$2" default="$3"
    if $NON_INTERACTIVE; then
        eval "$var_name=\"$default\""
    else
        read -rp "$(echo -e "${CYAN}$prompt_msg${NC} [${default}]: ")" input
        eval "$var_name=\"${input:-$default}\""
    fi
}

# ─── Helper: yes/no prompt ───────────────────────────────────────────────────
confirm() {
    local prompt_msg="$1" default="${2:-y}"
    if $NON_INTERACTIVE; then
        [[ "$default" == "y" ]]
        return $?
    fi
    local yn
    read -rp "$(echo -e "${CYAN}$prompt_msg${NC} [${default}]: ")" yn
    yn="${yn:-$default}"
    [[ "$yn" =~ ^[Yy] ]]
}

# ─── Helper: generate a cryptographically secure random hex string ────────────
gen_hex() {
    local bytes="${1:-32}"
    openssl rand -hex "$bytes" 2>/dev/null || head -c "$bytes" /dev/urandom | od -An -tx1 | tr -d ' \n'
}

# ─── Helper: generate a random alphanumeric password ──────────────────────────
gen_password() {
    local length="${1:-24}"
    tr -dc 'A-Za-z0-9!@#%^*_+=' < /dev/urandom | head -c "$length" 2>/dev/null || openssl rand -base64 "$length" | head -c "$length"
}

# ═══════════════════════════════════════════════════════════════════════════════
echo -e "${BOLD}${GREEN}"
echo "  ╔══════════════════════════════════════════════════╗"
echo "  ║       MegaCrypter — Automated Installer          ║"
echo "  ╚══════════════════════════════════════════════════╝"
echo -e "${NC}"
# ═══════════════════════════════════════════════════════════════════════════════

# ─── 1. Check privileges ─────────────────────────────────────────────────────
header "1/8 Checking privileges"

NEED_SUDO=""
if [[ $EUID -ne 0 ]]; then
    if command -v sudo &>/dev/null; then
        NEED_SUDO="sudo"
        warn "Not running as root — will use sudo for system commands."
    else
        warn "Not running as root and sudo is not available."
        warn "System package installation and Apache configuration may fail."
    fi
else
    ok "Running as root."
fi

# ─── 2. Detect OS & install system dependencies ──────────────────────────────
header "2/8 Installing system dependencies"

install_packages() {
    if command -v apt-get &>/dev/null; then
        info "Detected Debian/Ubuntu — using apt-get"
        $NEED_SUDO apt-get update -qq
        $NEED_SUDO apt-get install -y -qq \
            php php-cli php-curl php-mbstring php-xml php-json php-openssl \
            apache2 libapache2-mod-php \
            git unzip curl openssl 2>/dev/null || \
        $NEED_SUDO apt-get install -y -qq \
            php php-cli php-curl php-mbstring php-xml \
            apache2 libapache2-mod-php \
            git unzip curl openssl
    elif command -v yum &>/dev/null; then
        info "Detected RHEL/CentOS — using yum"
        $NEED_SUDO yum install -y -q \
            php php-cli php-curl php-mbstring php-xml php-json php-openssl \
            httpd mod_php \
            git unzip curl openssl 2>/dev/null || \
        $NEED_SUDO yum install -y -q \
            php php-cli php-curl php-mbstring php-xml \
            httpd mod_php \
            git unzip curl openssl
    elif command -v dnf &>/dev/null; then
        info "Detected Fedora — using dnf"
        $NEED_SUDO dnf install -y -q \
            php php-cli php-curl php-mbstring php-xml php-json php-openssl \
            httpd mod_php \
            git unzip curl openssl 2>/dev/null || \
        $NEED_SUDO dnf install -y -q \
            php php-cli php-curl php-mbstring php-xml \
            httpd mod_php \
            git unzip curl openssl
    elif command -v pacman &>/dev/null; then
        info "Detected Arch Linux — using pacman"
        $NEED_SUDO pacman -Sy --noconfirm --needed \
            php apache git unzip curl openssl
    elif command -v apk &>/dev/null; then
        info "Detected Alpine — using apk"
        $NEED_SUDO apk add --no-cache \
            php82 php82-curl php82-mbstring php82-openssl php82-json php82-xml \
            php82-phar php82-iconv php82-dom php82-tokenizer \
            apache2 php82-apache2 \
            git unzip curl openssl bash
    else
        warn "Could not detect package manager. Please install dependencies manually:"
        warn "  PHP >= 7.2 with extensions: curl, mbstring, openssl"
        warn "  Apache with mod_rewrite"
        warn "  git, unzip, curl, openssl"
    fi
}

install_packages
ok "System dependencies installed."

# ─── 3. Verify PHP & required extensions ──────────────────────────────────────
header "3/8 Verifying PHP installation"

if ! command -v php &>/dev/null; then
    err "PHP is not installed or not in PATH."
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')

if [[ "$PHP_MAJOR" -lt 7 ]] || { [[ "$PHP_MAJOR" -eq 7 ]] && [[ "$PHP_MINOR" -lt 2 ]]; }; then
    err "PHP >= 7.2 is required. Found PHP $PHP_VERSION."
    exit 1
fi
ok "PHP $PHP_VERSION detected."

MISSING_EXT=()
for ext in curl mbstring openssl; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
        ok "PHP extension '$ext' is loaded."
    else
        MISSING_EXT+=("$ext")
        warn "PHP extension '$ext' is NOT loaded."
    fi
done

if [[ ${#MISSING_EXT[@]} -gt 0 ]]; then
    warn "Missing PHP extensions: ${MISSING_EXT[*]}"
    warn "Attempting to enable them..."
    for ext in "${MISSING_EXT[@]}"; do
        # Try phpenmod (Debian/Ubuntu)
        if command -v phpenmod &>/dev/null; then
            $NEED_SUDO phpenmod "$ext" 2>/dev/null || true
        fi
        # Try to install the extension package
        if command -v apt-get &>/dev/null; then
            $NEED_SUDO apt-get install -y -qq "php-${ext}" 2>/dev/null || true
        elif command -v yum &>/dev/null; then
            $NEED_SUDO yum install -y -q "php-${ext}" 2>/dev/null || true
        elif command -v dnf &>/dev/null; then
            $NEED_SUDO dnf install -y -q "php-${ext}" 2>/dev/null || true
        fi
    done

    # Re-check
    STILL_MISSING=()
    for ext in "${MISSING_EXT[@]}"; do
        if ! php -m 2>/dev/null | grep -qi "^${ext}$"; then
            STILL_MISSING+=("$ext")
        fi
    done
    if [[ ${#STILL_MISSING[@]} -gt 0 ]]; then
        warn "Could not enable: ${STILL_MISSING[*]}. Please install them manually."
        warn "The application may not work correctly without these extensions."
    else
        ok "All missing extensions are now enabled."
    fi
fi

# ─── 4. Install Composer dependencies ────────────────────────────────────────
header "4/8 Installing Composer dependencies"

cd "$INSTALL_DIR"

if [[ -d vendor ]] && [[ -f vendor/autoload.php ]]; then
    info "Vendor directory already exists. Running composer update..."
    php composer.phar update --no-interaction --no-dev --optimize-autoloader 2>&1 || \
    php composer.phar install --no-interaction --no-dev --optimize-autoloader 2>&1
else
    info "Installing dependencies via Composer..."
    php composer.phar install --no-interaction --no-dev --optimize-autoloader 2>&1
fi

if [[ -f vendor/autoload.php ]]; then
    ok "Composer dependencies installed successfully."
else
    err "Composer install failed — vendor/autoload.php not found."
    exit 1
fi

# ─── 5. Generate configuration files ─────────────────────────────────────────
header "5/8 Generating configuration files"

CONFIG_DIR="$INSTALL_DIR/application/config"

# --- paths.php (always safe to overwrite — it's path-relative) ---
if [[ ! -f "$CONFIG_DIR/paths.php" ]]; then
    cp "$CONFIG_DIR/paths.php.sample" "$CONFIG_DIR/paths.php"
    ok "Created paths.php"
else
    ok "paths.php already exists — skipping."
fi

# --- memcache.php ---
if [[ ! -f "$CONFIG_DIR/memcache.php" ]]; then
    cp "$CONFIG_DIR/memcache.php.sample" "$CONFIG_DIR/memcache.php"
    ok "Created memcache.php (defaults: localhost:11211)"
else
    ok "memcache.php already exists — skipping."
fi

# --- gmail.php ---
if [[ ! -f "$CONFIG_DIR/gmail.php" ]]; then
    cp "$CONFIG_DIR/gmail.php.sample" "$CONFIG_DIR/gmail.php"
    ok "Created gmail.php (edit manually to enable takedown email tool)"
else
    ok "gmail.php already exists — skipping."
fi

# --- database.php ---
if [[ ! -f "$CONFIG_DIR/database.php" ]]; then
    cp "$CONFIG_DIR/database.php.sample" "$CONFIG_DIR/database.php"
    ok "Created database.php"
else
    ok "database.php already exists — skipping."
fi

# --- miscellaneous.php (the main config — needs secure values) ---
MASTER_KEY=$(gen_hex 32)       # 256-bit AES key (64 hex chars)
GENERIC_PASSWORD=$(gen_password 24)

# Auto-detect URL_BASE
if [[ -z "$URL_BASE" ]]; then
    # Try to detect from hostname
    HOSTNAME_DETECTED=$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo "localhost")
    DEFAULT_URL="http://${HOSTNAME_DETECTED}"
    prompt URL_BASE "Enter the URL base for MegaCrypter" "$DEFAULT_URL"
fi

# Remove trailing slash from URL_BASE
URL_BASE="${URL_BASE%/}"

if [[ ! -f "$CONFIG_DIR/miscellaneous.php" ]]; then
    # Generate from sample, replacing placeholder values
    sed \
        -e "s|define('URL_BASE', '[^']*')|define('URL_BASE', '${URL_BASE}')|" \
        -e "s|define('MASTER_KEY', '[^']*')|define('MASTER_KEY', '${MASTER_KEY}')|" \
        -e "s|define('GENERIC_PASSWORD', '[^']*')|define('GENERIC_PASSWORD', '${GENERIC_PASSWORD}')|" \
        "$CONFIG_DIR/miscellaneous.php.sample" > "$CONFIG_DIR/miscellaneous.php"
    ok "Created miscellaneous.php with secure auto-generated keys."
    info "  URL_BASE       = $URL_BASE"
    info "  MASTER_KEY     = ${MASTER_KEY:0:8}...${MASTER_KEY: -8} (256-bit)"
    info "  GENERIC_PASSWORD = ${GENERIC_PASSWORD:0:4}****"
else
    ok "miscellaneous.php already exists — skipping (keys preserved)."
fi

# ─── 6. Optional: MySQL blacklist database ───────────────────────────────────
header "6/8 Database setup (optional — for blacklist feature)"

setup_database() {
    if ! command -v mysql &>/dev/null; then
        warn "MySQL client not found. Skipping database setup."
        warn "Install mysql-client and re-run, or set up the database manually."
        return
    fi

    if [[ -z "$DB_PASS" ]]; then
        DB_PASS=$(gen_password 20)
    fi

    prompt DB_NAME "Database name" "$DB_NAME"
    prompt DB_USER "Database user" "$DB_USER"
    prompt DB_HOST "Database host" "$DB_HOST"

    info "Creating database '$DB_NAME' and user '$DB_USER'..."

    # Attempt to create database and user (requires root/admin mysql access)
    local MYSQL_ROOT_CMD="mysql"
    if [[ $EUID -eq 0 ]] || [[ -n "$NEED_SUDO" ]]; then
        # Try socket auth first (common on Debian/Ubuntu)
        MYSQL_ROOT_CMD="$NEED_SUDO mysql"
    fi

    $MYSQL_ROOT_CMD -e "
        CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
        GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}';
        FLUSH PRIVILEGES;
        USE \`${DB_NAME}\`;
        CREATE TABLE IF NOT EXISTS \`blacklist\` (
            \`id\` varchar(512) NOT NULL DEFAULT '',
            \`reporter\` varchar(255) DEFAULT NULL,
            \`ip\` varchar(15) DEFAULT NULL,
            PRIMARY KEY (\`id\`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    " 2>&1 && {
        ok "Database '$DB_NAME' created with blacklist table."
        ok "User '$DB_USER' granted access."

        # Update database.php with actual credentials
        cat > "$CONFIG_DIR/database.php" <<DBEOF
<?php

    define('DB_TYPE', 'mysql');
    define('DB_HOST', '${DB_HOST}');
    define('DB_NAME', '${DB_NAME}');
    define('DB_USER', '${DB_USER}');
    define('DB_PASS', '${DB_PASS}');
DBEOF
        ok "Updated database.php with credentials."
        info "  DB_NAME = $DB_NAME"
        info "  DB_USER = $DB_USER"
        info "  DB_PASS = ${DB_PASS:0:4}****"
    } || {
        warn "Could not create database automatically."
        warn "Please create the database manually and edit application/config/database.php"
    }
}

if $SKIP_DB; then
    info "Skipping database setup (--skip-db)."
elif $NON_INTERACTIVE; then
    if command -v mysql &>/dev/null; then
        setup_database
    else
        info "MySQL not available — skipping database setup."
    fi
else
    if confirm "Set up MySQL database for blacklist feature?" "n"; then
        setup_database
    else
        info "Skipping database setup."
    fi
fi

# ─── 7. Apache virtual host configuration ────────────────────────────────────
header "7/8 Configuring Apache web server"

configure_apache() {
    local DOC_ROOT="$INSTALL_DIR/public"
    local VHOST_NAME
    # Extract hostname from URL_BASE
    VHOST_NAME=$(echo "$URL_BASE" | sed -E 's|^https?://||; s|/.*||; s|:.*||')

    # Detect Apache configuration directory
    local APACHE_CONF_DIR=""
    local APACHE_SERVICE=""
    if [[ -d /etc/apache2/sites-available ]]; then
        # Debian/Ubuntu style
        APACHE_CONF_DIR="/etc/apache2/sites-available"
        APACHE_SERVICE="apache2"
    elif [[ -d /etc/httpd/conf.d ]]; then
        # RHEL/CentOS/Fedora style
        APACHE_CONF_DIR="/etc/httpd/conf.d"
        APACHE_SERVICE="httpd"
    else
        warn "Could not detect Apache configuration directory."
        warn "Please configure your web server manually."
        warn "Document root: $DOC_ROOT"
        return
    fi

    local VHOST_FILE="$APACHE_CONF_DIR/megacrypter.conf"

    info "Creating virtual host: $VHOST_NAME -> $DOC_ROOT"

    $NEED_SUDO tee "$VHOST_FILE" > /dev/null <<VHOSTEOF
<VirtualHost *:80>
    ServerName ${VHOST_NAME}
    DocumentRoot ${DOC_ROOT}

    RewriteEngine On

    <Directory ${DOC_ROOT}>
        AllowOverride None
        Require all granted
        Include ${DOC_ROOT}/.htaccess
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/megacrypter-error.log
    CustomLog \${APACHE_LOG_DIR}/megacrypter-access.log combined
</VirtualHost>
VHOSTEOF

    ok "Created virtual host at $VHOST_FILE"

    # Enable mod_rewrite
    if command -v a2enmod &>/dev/null; then
        $NEED_SUDO a2enmod rewrite -q 2>/dev/null || true
        ok "Enabled mod_rewrite."
    fi

    # Enable the site (Debian/Ubuntu)
    if command -v a2ensite &>/dev/null; then
        $NEED_SUDO a2ensite megacrypter -q 2>/dev/null || true
        ok "Enabled megacrypter site."
    fi

    # Set file ownership for Apache
    local APACHE_USER="www-data"
    if id "apache" &>/dev/null; then
        APACHE_USER="apache"
    elif id "http" &>/dev/null; then
        APACHE_USER="http"
    elif id "nginx" &>/dev/null; then
        APACHE_USER="nginx"
    fi

    $NEED_SUDO chown -R "$APACHE_USER:$APACHE_USER" "$INSTALL_DIR" 2>/dev/null || \
        warn "Could not set ownership to $APACHE_USER — set permissions manually."

    # Restart Apache
    if command -v systemctl &>/dev/null; then
        $NEED_SUDO systemctl restart "$APACHE_SERVICE" 2>/dev/null && \
            ok "Apache restarted." || \
            warn "Could not restart Apache. Run: sudo systemctl restart $APACHE_SERVICE"
    elif command -v service &>/dev/null; then
        $NEED_SUDO service "$APACHE_SERVICE" restart 2>/dev/null && \
            ok "Apache restarted." || \
            warn "Could not restart Apache. Run: sudo service $APACHE_SERVICE restart"
    else
        warn "Please restart Apache manually."
    fi
}

if $SKIP_APACHE; then
    info "Skipping Apache configuration (--skip-apache)."
elif $NON_INTERACTIVE; then
    configure_apache
else
    if confirm "Configure Apache virtual host?" "y"; then
        configure_apache
    else
        info "Skipping Apache configuration."
    fi
fi

# ─── 8. Set file permissions ─────────────────────────────────────────────────
header "8/8 Setting file permissions"

# Ensure config files are not world-readable (contain secrets)
chmod 640 "$CONFIG_DIR"/*.php 2>/dev/null || true
ok "Config files set to mode 640 (owner + group read)."

# Ensure public directory is readable by web server
chmod -R 755 "$INSTALL_DIR/public" 2>/dev/null || true
ok "Public directory set to mode 755."

# Ensure composer.phar is executable
chmod +x "$INSTALL_DIR/composer.phar" 2>/dev/null || true

# ═══════════════════════════════════════════════════════════════════════════════
header "Installation Complete!"
# ═══════════════════════════════════════════════════════════════════════════════

echo -e "${GREEN}${BOLD}MegaCrypter has been installed successfully!${NC}\n"
echo -e "  ${BOLD}Install directory:${NC}  $INSTALL_DIR"
echo -e "  ${BOLD}Document root:${NC}     $INSTALL_DIR/public"
echo -e "  ${BOLD}URL:${NC}               $URL_BASE"
echo ""
echo -e "${BOLD}Configuration files:${NC}"
echo "  $CONFIG_DIR/miscellaneous.php  — Main settings (URL, keys, features)"
echo "  $CONFIG_DIR/paths.php          — Application paths"
echo "  $CONFIG_DIR/database.php       — MySQL settings (optional)"
echo "  $CONFIG_DIR/memcache.php       — Memcache settings (optional)"
echo "  $CONFIG_DIR/gmail.php          — Gmail/SMTP for takedown tool (optional)"
echo ""
echo -e "${BOLD}Next steps:${NC}"
echo "  1. Visit $URL_BASE in your browser to verify the installation."
echo "  2. Edit $CONFIG_DIR/miscellaneous.php to customize features."
echo "  3. Use Megabasterd (https://github.com/tonikelope/megabasterd) to"
echo "     download files from your MegaCrypter links."
echo ""
echo -e "${BOLD}Optional features you can enable:${NC}"
echo "  • Blacklist:     Set BLACKLIST_LEVEL in miscellaneous.php + configure database.php"
echo "  • Takedown tool: Set TAKEDOWN_TOOL=true in miscellaneous.php + configure gmail.php"
echo "  • reCAPTCHA:     Set RECAPTCHA_PUBLIC_KEY / RECAPTCHA_PRIVATE_KEY in miscellaneous.php"
echo "  • Memcache:      Install memcached + php-memcache and configure memcache.php"
echo ""
echo -e "${YELLOW}${BOLD}IMPORTANT:${NC} Keep your MASTER_KEY safe! If you lose it, all existing"
echo "MegaCrypter links created with that key will become invalid."
echo ""
