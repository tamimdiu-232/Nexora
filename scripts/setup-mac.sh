#!/usr/bin/env bash
# One-time setup for macOS + XAMPP. Run from anywhere:  bash scripts/setup-mac.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

XAMPP="/Applications/XAMPP/xamppfiles"
export PATH="$XAMPP/bin:$PATH"          # use XAMPP's PHP and mysql client

say() { printf "\n\033[1m==> %s\033[0m\n" "$*"; }
die() { printf "\033[31mERROR: %s\033[0m\n" "$*" >&2; exit 1; }

say "Checking tools"
command -v php >/dev/null      || die "php not found. Is XAMPP installed at $XAMPP ?"
command -v composer >/dev/null || die "composer not found. Install it: https://getcomposer.org/download/ (or: brew install composer)"
command -v python3 >/dev/null  || die "python3 not found (need 3.10+)."
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' || die "PHP >= 8.2 required (found $(php -r 'echo PHP_VERSION;')). Update XAMPP."
for ext in mbstring openssl pdo_mysql fileinfo; do
  php -r "exit(extension_loaded('$ext') ? 0 : 1);" || die "PHP extension '$ext' missing."
done
python3 -c 'import sys; sys.exit(0 if sys.version_info >= (3,10) else 1)' || die "Python 3.10+ required."
echo "php $(php -r 'echo PHP_VERSION;'), composer $(composer --version | awk '{print $3}'), $(python3 --version)"

set_env() {  # set_env FILE KEY VALUE  (replace commented or existing line, else append)
  local f="$1" k="$2" v="$3"
  if grep -qE "^#?[[:space:]]*${k}=" "$f"; then perl -pi -e "s|^#?\s*${k}=.*|${k}=${v}|" "$f"; else echo "${k}=${v}" >> "$f"; fi
}

say "1/5 Creating Laravel skeleton (needs internet; takes a few minutes)"
if [ ! -d laravel-app ]; then
  composer create-project laravel/laravel laravel-app --no-interaction
else
  echo "laravel-app already exists - reusing it"
fi

say "2/5 Copying GovDoc BD application code into laravel-app"
rsync -a laravel-overlay/ laravel-app/
rm -f laravel-app/tests/Feature/ExampleTest.php laravel-app/tests/Unit/ExampleTest.php

say "3/5 Configuring .env"
cd laravel-app
[ -f .env ] || cp .env.example .env
TOKEN="$(php -r 'echo bin2hex(random_bytes(24));')"
set_env .env APP_NAME GovDocBD
set_env .env DB_CONNECTION mysql
set_env .env DB_HOST 127.0.0.1
set_env .env DB_PORT 3306
set_env .env DB_DATABASE govdoc_bd
set_env .env DB_USERNAME root
set_env .env DB_PASSWORD ""
set_env .env QUEUE_CONNECTION sync          # no queue worker needed; use "database" + php artisan queue:work for async
set_env .env AI_SERVICE_URL http://127.0.0.1:8001
set_env .env AI_SERVICE_TOKEN "$TOKEN"
set_env .env GOVDOC_SEED_PROCESS false
set_env .env MAIL_MAILER log                # password-reset links are written to storage/logs/laravel.log
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

say "4/5 Creating MySQL database and migrating"
echo "(XAMPP MySQL must be running - start it from the XAMPP control panel)"
# A dedicated DB user avoids depending on how root is configured. If root has a password:  MYSQL_ROOT_PASSWORD=xxx bash scripts/setup-mac.sh
MYSQL_ARGS=(-h 127.0.0.1 -uroot)
[ -n "${MYSQL_ROOT_PASSWORD:-}" ] && MYSQL_ARGS+=("-p${MYSQL_ROOT_PASSWORD}")
mysql "${MYSQL_ARGS[@]}" -e "CREATE DATABASE IF NOT EXISTS govdoc_bd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'govdoc'@'localhost' IDENTIFIED BY 'govdoc_dev_pass'; CREATE USER IF NOT EXISTS 'govdoc'@'127.0.0.1' IDENTIFIED BY 'govdoc_dev_pass'; GRANT ALL PRIVILEGES ON govdoc_bd.* TO 'govdoc'@'localhost'; GRANT ALL PRIVILEGES ON govdoc_bd.* TO 'govdoc'@'127.0.0.1'; FLUSH PRIVILEGES;" \
  || die "Could not create the database user. Start MySQL in XAMPP; if root has a password run: MYSQL_ROOT_PASSWORD=yourpassword bash scripts/setup-mac.sh"
set_env .env DB_USERNAME govdoc
set_env .env DB_PASSWORD govdoc_dev_pass
php artisan migrate --seed --force
cd "$ROOT"

say "5/5 Setting up the Python AI service"
cd ai-service
[ -d .venv ] || python3 -m venv .venv
.venv/bin/pip install --upgrade pip -q
.venv/bin/pip install -r requirements.txt -q
printf "SERVICE_TOKEN=%s\nDATA_DIR=data\nOCR_LANGS=eng\n" "$TOKEN" > .env
cd "$ROOT"

command -v tesseract >/dev/null || echo "NOTE: tesseract not found - scanned-PDF OCR will not work. Install: brew install tesseract poppler"
command -v pdftoppm  >/dev/null || echo "NOTE: pdftoppm not found - OCR needs poppler. Install: brew install poppler"

cat <<MSG

Setup finished. Start the project in 2 terminals:

  Terminal 1 (AI service):
    cd ai-service && source .venv/bin/activate && uvicorn app.main:app --port 8001

  Terminal 2 (web app):
    export PATH="$XAMPP/bin:\$PATH"
    cd laravel-app && php artisan serve

Then open http://127.0.0.1:8000  and log in with the development accounts in README.md.
Process the demo documents (AI service must be running):
    cd laravel-app && php artisan govdoc:process
MSG
