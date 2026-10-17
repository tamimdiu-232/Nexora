#!/usr/bin/env bash
# Runs both test suites. Needs setup-mac.sh to have been run first.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"
echo "== AI service tests =="; (cd "$ROOT/ai-service" && .venv/bin/python -m pytest -q)
echo; echo "== Laravel tests (SQLite in-memory; needs the pdo_sqlite PHP extension) =="; (cd "$ROOT/laravel-app" && php artisan test)
