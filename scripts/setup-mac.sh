#!/usr/bin/env bash
# ==============================================================================
# Nexora — Mac Setup Script
# Configures local environment, validates dependencies, initializes DB & AI service
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "${SCRIPT_DIR}")"
LARAVEL_DIR="${ROOT_DIR}/laravel-app"
AI_DIR="${ROOT_DIR}/ai-service"

echo "================================================================================"
echo " Nexora — Setup & Environment Verification (macOS)"
echo " Next-generation organizational intelligence"
echo "================================================================================"

# --- 1. Tool Verification ---
echo "[1/6] Checking system tools..."

# PHP
if ! command -v php >/dev/null 2>&1; then
    if [ -x "/Applications/XAMPP/xamppfiles/bin/php" ]; then
        export PATH="/Applications/XAMPP/xamppfiles/bin:${PATH}"
    else
        echo "ERROR: PHP is not installed or not in PATH."
        echo "Run: brew install php"
        exit 1
    fi
fi
PHP_VER=$(php -r 'echo PHP_VERSION;')
echo "  ✓ PHP: ${PHP_VER}"

# Composer
if ! command -v composer >/dev/null 2>&1; then
    echo "ERROR: Composer is not installed."
    echo "Run: brew install composer"
    exit 1
fi
echo "  ✓ Composer: $(composer --version | head -n1)"

# Python
if ! command -v python3 >/dev/null 2>&1; then
    echo "ERROR: Python 3 is not installed."
    echo "Run: brew install python@3.11"
    exit 1
fi
PY_VER=$(python3 -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}")')
echo "  ✓ Python: ${PY_VER}"

# Poppler (pdftoppm)
if ! command -v pdftoppm >/dev/null 2>&1; then
    echo "WARNING: pdftoppm (Poppler) is not installed. Scanned PDF OCR will fail."
    echo "Install via: brew install poppler"
else
    echo "  ✓ Poppler pdftoppm: available"
fi

# Tesseract & Bengali language model
if ! command -v tesseract >/dev/null 2>&1; then
    echo "WARNING: Tesseract OCR is not installed."
    echo "Install via: brew install tesseract tesseract-lang"
else
    echo "  ✓ Tesseract: $(tesseract --version | head -n1)"
    LANGS=$(tesseract --list-langs 2>&1 || true)
    if echo "${LANGS}" | grep -E '^ben$' >/dev/null 2>&1; then
        echo "  ✓ Tesseract language model: Bengali (ben) installed"
    else
        echo "  ! Tesseract Bengali (ben) model is missing."
        echo "    Run: brew install tesseract-lang"
    fi
fi

# MySQL client check
MYSQL_BIN="mysql"
if ! command -v mysql >/dev/null 2>&1; then
    if [ -x "/Applications/XAMPP/xamppfiles/bin/mysql" ]; then
        MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
        echo "  ✓ MySQL found in XAMPP: ${MYSQL_BIN}"
    else
        echo "  ! MySQL CLI not found in PATH or XAMPP."
    fi
else
    echo "  ✓ MySQL CLI: $(mysql --version | head -n1)"
fi

# --- 2. Python AI Service Setup ---
echo ""
echo "[2/6] Setting up Python AI microservice..."
cd "${AI_DIR}"

if [ ! -d ".venv" ]; then
    echo "  Creating virtual environment in ${AI_DIR}/.venv..."
    python3 -m venv .venv
fi

# Activate venv
source .venv/bin/activate
echo "  Installing Python dependencies..."
pip install --quiet --upgrade pip
pip install --quiet -r requirements.txt

if [ ! -f ".env" ]; then
    echo "  Creating ai-service/.env from .env.example..."
    cp .env.example .env
fi
echo "  ✓ AI microservice virtual environment configured."

# --- 3. Laravel Web Application Setup ---
echo ""
echo "[3/6] Setting up Laravel application..."
cd "${LARAVEL_DIR}"

if [ ! -d "vendor" ]; then
    echo "  Installing Composer dependencies (vendor/)..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "  ✓ Composer dependencies already present."
fi

if [ ! -f ".env" ]; then
    echo "  Creating laravel-app/.env from .env.example..."
    cp .env.example .env
fi

# Ensure application key is generated
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate --force
fi

# Ensure storage directories exist
mkdir -p storage/app/private/documents
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
chmod -R 775 storage bootstrap/cache

# Create storage link if not already linked
if [ ! -L "public/storage" ]; then
    php artisan storage:link --quiet || true
fi

# Helper function to safely update .env values
set_env_val() {
    local key="$1"
    local val="$2"
    if grep -q "^${key}=" .env 2>/dev/null; then
        sed -i '' "s|^${key}=.*|${key}=${val}|" .env 2>/dev/null || sed -i "s|^${key}=.*|${key}=${val}|" .env
    elif grep -q "^# *${key}=" .env 2>/dev/null; then
        sed -i '' "s|^# *${key}=.*|${key}=${val}|" .env 2>/dev/null || sed -i "s|^# *${key}=.*|${key}=${val}|" .env
    else
        echo "${key}=${val}" >> .env
    fi
}

# --- 4. Database Initialization ---
echo ""
echo "[4/6] Initializing Database..."

DB_CONNECTED=false
if command -v "${MYSQL_BIN}" >/dev/null 2>&1; then
    # Try querying port from MySQL
    PORT_CHECK=$("${MYSQL_BIN}" -u root -e "SELECT @@port;" 2>/dev/null || true)
    if [ -n "${PORT_CHECK}" ]; then
        DB_PORT=$(echo "${PORT_CHECK}" | tail -n1)
        echo "  ✓ MySQL is running on port ${DB_PORT}"
        # Create database and user if not exists
        "${MYSQL_BIN}" -u root -e "CREATE DATABASE IF NOT EXISTS \`nexora\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
        "${MYSQL_BIN}" -u root -e "CREATE USER IF NOT EXISTS 'nexora'@'localhost' IDENTIFIED BY 'nexora_local_pass';" 2>/dev/null || true
        "${MYSQL_BIN}" -u root -e "CREATE USER IF NOT EXISTS 'nexora'@'127.0.0.1' IDENTIFIED BY 'nexora_local_pass';" 2>/dev/null || true
        "${MYSQL_BIN}" -u root -e "GRANT ALL PRIVILEGES ON \`nexora\`.* TO 'nexora'@'localhost'; GRANT ALL PRIVILEGES ON \`nexora\`.* TO 'nexora'@'127.0.0.1'; FLUSH PRIVILEGES;" 2>/dev/null || true

        # Configure .env for MySQL connection
        set_env_val "DB_CONNECTION" "mysql"
        set_env_val "DB_HOST" "127.0.0.1"
        set_env_val "DB_PORT" "${DB_PORT}"
        set_env_val "DB_DATABASE" "nexora"
        set_env_val "DB_USERNAME" "root"
        set_env_val "DB_PASSWORD" ""

        # Test running migrations
        if php artisan migrate --force 2>/dev/null; then
            php artisan db:seed --force
            echo "  ✓ MySQL database initialized & seeded on port ${DB_PORT}."
            DB_CONNECTED=true
        else
            echo "  ! Trying with nexora user credentials..."
            set_env_val "DB_USERNAME" "nexora"
            set_env_val "DB_PASSWORD" "nexora_local_pass"
            if php artisan migrate --force 2>/dev/null; then
                php artisan db:seed --force
                echo "  ✓ MySQL database initialized & seeded with user nexora."
                DB_CONNECTED=true
            fi
        fi
    fi
fi

if [ "${DB_CONNECTED}" != true ]; then
    echo "  ! MySQL server connection not completed. Falling back to SQLite for local development..."
    set_env_val "DB_CONNECTION" "sqlite"
    touch database/database.sqlite
    php artisan migrate:fresh --database=sqlite --force
    php artisan db:seed --database=sqlite --force
    echo "  ✓ SQLite database seeded with demo accounts & synthetic documents."
fi

# --- 5. Generate Synthetic Documents ---
echo ""
echo "[5/6] Checking synthetic test documents..."
if [ -f "${ROOT_DIR}/scripts/make_sample_documents.py" ]; then
    cd "${ROOT_DIR}"
    if [ -x "${AI_DIR}/.venv/bin/python" ]; then
        "${AI_DIR}/.venv/bin/python" scripts/make_sample_documents.py
    else
        python3 scripts/make_sample_documents.py
    fi
fi

# --- 6. Done ---
echo ""
echo "================================================================================"
echo " Nexora Setup Complete!"
echo "================================================================================"
echo "To start the application:"
echo ""
echo "  1. Start the AI Microservice:"
echo "     cd ${AI_DIR} && source .venv/bin/activate"
echo "     uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload"
echo ""
echo "  2. Start the Laravel Web Application:"
echo "     cd ${LARAVEL_DIR}"
echo "     php artisan serve --port 8000"
echo ""
echo "  3. Open your browser:"
echo "     http://127.0.0.1:8000"
echo ""
echo "Default Accounts (Password: Password@123):"
echo "  - Platform Super Admin: superadmin@nexora.internal"
echo "  - Organization Admin:   admin@energy.gov.bd"
echo "  - Department Officer:   officer@energy.gov.bd"
echo "  - Reviewer:             reviewer@energy.gov.bd"
echo "================================================================================"
