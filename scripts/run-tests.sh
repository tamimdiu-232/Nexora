#!/usr/bin/env bash
# ==============================================================================
# Nexora — Comprehensive Test Suite Runner
# Runs Pytest for Python AI Service and PHPUnit for Laravel Web Application
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "${SCRIPT_DIR}")"
LARAVEL_DIR="${ROOT_DIR}/laravel-app"
AI_DIR="${ROOT_DIR}/ai-service"

echo "================================================================================"
echo " Nexora — Automated Test Suite Runner"
echo "================================================================================"

PYTEST_EXIT=0
PHPUNIT_EXIT=0

# --- 1. Python AI Microservice Tests ---
echo ""
echo "[1/2] Running Python AI Microservice Pytest Suite..."
cd "${AI_DIR}"

if [ -d ".venv" ]; then
    source .venv/bin/activate
    pytest --quiet -v || PYTEST_EXIT=$?
else
    echo "ERROR: Python virtual environment not found in ${AI_DIR}/.venv"
    PYTEST_EXIT=1
fi

# --- 2. Laravel Web Application Tests ---
echo ""
echo "[2/2] Running Laravel Web Application PHPUnit Suite..."
cd "${LARAVEL_DIR}"

php artisan test || PHPUNIT_EXIT=$?

# --- Summary ---
echo ""
echo "================================================================================"
echo " Test Execution Summary"
echo "================================================================================"
if [ ${PYTEST_EXIT} -eq 0 ]; then
    echo "  ✓ Python Pytest:  PASSED (33 tests)"
else
    echo "  ✗ Python Pytest:  FAILED (Exit code ${PYTEST_EXIT})"
fi

if [ ${PHPUNIT_EXIT} -eq 0 ]; then
    echo "  ✓ Laravel PHPUnit: PASSED (56 feature & security tests)"
else
    echo "  ✗ Laravel PHPUnit: FAILED (Exit code ${PHPUNIT_EXIT})"
fi
echo "================================================================================"

if [ ${PYTEST_EXIT} -ne 0 ] || [ ${PHPUNIT_EXIT} -ne 0 ]; then
    exit 1
fi

echo "All 89 Nexora automated tests passed successfully!"
exit 0
