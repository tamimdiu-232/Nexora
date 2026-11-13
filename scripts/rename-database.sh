#!/usr/bin/env bash
# ==============================================================================
# Nexora — Rename Database Utility
# Updates DB_DATABASE in laravel-app/.env and initializes the new database
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "${SCRIPT_DIR}")"
LARAVEL_DIR="${ROOT_DIR}/laravel-app"

NEW_DB_NAME="${1:-}"

if [ -z "${NEW_DB_NAME}" ]; then
    echo "Usage: $0 <new_database_name>"
    echo "Example: $0 nexora_production"
    exit 1
fi

# Sanitize database name (alphanumeric and underscore only)
if [[ ! "${NEW_DB_NAME}" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo "ERROR: Database name must only contain alphanumeric characters and underscores."
    exit 1
fi

echo "Switching Nexora database to: ${NEW_DB_NAME}..."

cd "${LARAVEL_DIR}"

if [ ! -f ".env" ]; then
    echo "ERROR: .env not found in ${LARAVEL_DIR}. Run setup first."
    exit 1
fi

# Check MySQL CLI
MYSQL_BIN="mysql"
if ! command -v mysql >/dev/null 2>&1 && [ -x "/Applications/XAMPP/xamppfiles/bin/mysql" ]; then
    MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
fi

if command -v "${MYSQL_BIN}" >/dev/null 2>&1; then
    echo "Creating database \`${NEW_DB_NAME}\` in MySQL if it does not exist..."
    "${MYSQL_BIN}" -u root -e "CREATE DATABASE IF NOT EXISTS \`${NEW_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
fi

# Update .env
if grep -q "^DB_DATABASE=" .env; then
    sed -i.bak -E "s/^DB_DATABASE=.*/DB_DATABASE=${NEW_DB_NAME}/" .env
    rm -f .env.bak
else
    echo "DB_DATABASE=${NEW_DB_NAME}" >> .env
fi

echo "Running migrations on new database..."
php artisan migrate --force

echo "Database successfully switched to '${NEW_DB_NAME}'!"
