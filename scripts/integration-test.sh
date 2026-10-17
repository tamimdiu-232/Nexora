#!/usr/bin/env bash
# Starts the AI service on port 8011 with a throw-away token and index, runs the REAL Laravel<->FastAPI test, stops it.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PATH="/Applications/XAMPP/xamppfiles/bin:$PATH"
TOKEN="integration-$(date +%s)"; PORT=8011; DATA="$(mktemp -d)"
cd "$ROOT/ai-service"
SERVICE_TOKEN="$TOKEN" DATA_DIR="$DATA" .venv/bin/uvicorn app.main:app --port $PORT >"$DATA/uvicorn.log" 2>&1 &
PID=$!; trap 'kill $PID 2>/dev/null; rm -rf "$DATA"' EXIT
for i in $(seq 1 30); do curl -sf "http://127.0.0.1:$PORT/health" >/dev/null && break; sleep 1; done
curl -sf "http://127.0.0.1:$PORT/health" >/dev/null || { echo "AI service did not start:"; cat "$DATA/uvicorn.log"; exit 1; }
cd "$ROOT/laravel-app"
AI_INTEGRATION=1 AI_SERVICE_URL="http://127.0.0.1:$PORT" AI_SERVICE_TOKEN="$TOKEN" php artisan test --filter=AiIntegrationTest
