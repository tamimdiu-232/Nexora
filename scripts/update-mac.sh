#!/usr/bin/env bash
# Copies the code from THIS folder (a newly unzipped Nexora) over an EXISTING install, keeping its .env, database,
# uploads and vendor/. Usage:  bash scripts/update-mac.sh "/path/to/existing/install"
set -euo pipefail
NEW="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:-}"
export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"
die() { printf "\033[31mERROR: %s\033[0m\n" "$*" >&2; exit 1; }

[ -n "$TARGET" ] || die "Give the path of your existing install, e.g.  bash scripts/update-mac.sh \"\$HOME/Desktop/your-install-folder\""
[ -d "$TARGET/laravel-app" ] && [ -d "$TARGET/ai-service" ] || die "'$TARGET' does not look like an existing install (laravel-app/ and ai-service/ not found)."
[ "$NEW" != "$TARGET" ] || die "Run this from the NEW folder, passing the OLD folder as the argument."

echo "==> Copying web application code"
rsync -a "$NEW/laravel-overlay/" "$TARGET/laravel-app/"
echo "==> Copying AI service code (keeping .venv, data and .env)"
rsync -a --exclude '.venv' --exclude 'data' --exclude '.env' --exclude '__pycache__' "$NEW/ai-service/" "$TARGET/ai-service/"
echo "==> Copying docs, scripts and sample documents"
rsync -a "$NEW/docs/" "$TARGET/docs/"; rsync -a "$NEW/scripts/" "$TARGET/scripts/"; rsync -a "$NEW/sample-documents/" "$TARGET/sample-documents/"
cp "$NEW/README.md" "$NEW/PROJECT_PROGRESS.md" "$TARGET/"
rm -f "$TARGET/laravel-app/resources/views/partials/status.blade.php"   # replaced by the <x-status> component
rm -f "$TARGET/laravel-app/config/govdoc.php"                           # renamed to config/nexora.php

echo "==> Updating configuration and database"
cd "$TARGET/laravel-app"
perl -pi -e 's/^APP_NAME=.*/APP_NAME=Nexora/; s/^GOVDOC_/NEXORA_/' .env   # APP_NAME and renamed env keys
php artisan migrate --force
php artisan optimize:clear >/dev/null

cat <<MSG

Update copied. Now:
  1. Restart the AI service (Ctrl+C in its terminal, then run it again):
       cd "$TARGET/ai-service" && source .venv/bin/activate && uvicorn app.main:app --port 8001
  2. If your database is still called govdoc_bd, rename it to nexora (data is copied, nothing is lost):
       bash "$NEW/scripts/rename-database.sh" "$TARGET"
  3. Rebuild the document index once (the search engine changed):
       cd "$TARGET/laravel-app" && php artisan nexora:process --all
  4. Restart "php artisan serve" and hard-refresh the browser (Cmd+Shift+R).
MSG
