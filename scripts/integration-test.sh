#!/usr/bin/env bash
# ==============================================================================
# Nexora — End-to-End AI Microservice Integration Test
# Validates the full pipeline: Health -> Ingest -> Search -> Q&A -> Summary -> Delete
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "${SCRIPT_DIR}")"
AI_DIR="${ROOT_DIR}/ai-service"

echo "================================================================================"
echo " Nexora — End-to-End AI Microservice Integration Test"
echo "================================================================================"

cd "${AI_DIR}"

if [ ! -d ".venv" ]; then
    echo "ERROR: Virtual environment not found in ${AI_DIR}/.venv"
    echo "Run ./scripts/setup-mac.sh first."
    exit 1
fi

# Ensure synthetic sample documents are present
if [ ! -f "${ROOT_DIR}/sample-documents/demo_riverside_connector_report.pdf" ]; then
    echo "Generating sample documents..."
    "${AI_DIR}/.venv/bin/python" "${ROOT_DIR}/scripts/make_sample_documents.py"
fi

# Run comprehensive end-to-end integration test
"${AI_DIR}/.venv/bin/python" "${ROOT_DIR}/scripts/test_integration_client.py"
