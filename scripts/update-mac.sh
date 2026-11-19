#!/usr/bin/env bash
# ==============================================================================
# Nexora — Update Script (macOS)
# Refreshes dependencies, runs new migrations, and clears caches
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "${SCRIPT_DIR}")"
LARAVEL_DIR="${ROOT_DIR}/laravel-app"
AI_DIR="${ROOT_DIR}/ai-service"

echo "================================================================================"
echo " Nexora — Updating Application"
echo "================================================================================"

# 1. Update Python dependencies
echo "[1/4] Updating AI microservice virtual environment..."
cd "${AI_DIR}"
if [ -d ".venv" ]; then
    source .venv/bin/activate
    pip install --quiet --upgrade pip
    pip install --quiet -r requirements.txt
    echo "  ✓ Python dependencies up to date."
else
    echo "  ! Virtual environment not found. Run ./scripts/setup-mac.sh first."
fi

# 2. Update Composer dependencies
echo "[2/4] Updating Composer packages..."
cd "${LARAVEL_DIR}"
composer install --no-interaction --prefer-dist --optimize-autoloader --quiet
echo "  ✓ Composer packages up to date."

# 3. Database migrations
echo "[3/4] Running database migrations..."
php artisan migrate --force
echo "  ✓ Migrations executed."

# 4. Clear application cache
echo "[4/4] Clearing cache and views..."
php artisan optimize:clear --quiet
echo "  ✓ Caches cleared."

echo ""
echo "Nexora update completed successfully!"
