# Nexora — Testing Strategy & Verification Guide

## 1. Overview
The testing architecture ensures verification at three levels:
1. **AI Service Test Suite (pytest):** Tests text extraction, OCR fallback, Bengali tokenization/stemming, lexical vector store, RAG grounded answers, conflict detection, and LLM mock transports without external network calls.
2. **Laravel Application Test Suite (PHPUnit):** Feature and Unit tests using SQLite in-memory, `Storage::fake()`, and `Http::fake()`.
3. **End-to-End Contract & Integration Test:** Contract test validating payload schemas between Laravel and FastAPI, and end-to-end integration test (`scripts/integration-test.sh`).

---

## 2. Running AI Service Tests (Python)
From `ai-service/`:
```bash
source .venv/bin/activate
pytest tests/ -v
```

### Key AI Test Suites:
- `test_auth.py`: 401 on missing/invalid token, constant-time comparison.
- `test_extraction.py`: Corrupt PDF, empty PDF, password protection, pypdf text extraction, legacy Bijoy detection.
- `test_ocr.py`: 300 DPI Tesseract extraction, parallel workers, page ordering, Bangla language detection.
- `test_bengali.py`: Tokenizer `WORD = [\w\u0980-\u09FF]+`, stop words, Bengali suffix stripper, lexicon expansion.
- `test_rag.py`: Grounded extraction, budget intent boosting, deadline intent boosting, conflicting budget detection, insufficient evidence handling.
- `test_contract.py`: Replays exact Laravel JSON requests to FastAPI and validates citation chunk IDs.

---

## 3. Running Web Application Tests (Laravel)
From `laravel-app/`:
```bash
php artisan test
```

### Key Laravel Test Suites:
- `AuthenticationTest`: Login throttling (5/min per email+IP), session regeneration, password hashing.
- `AuthorizationTest`: Role-permission middleware, deactivated user logout (`EnsureUserIsActive`).
- `DocumentScopingTest`: `Document::visibleTo($user)` isolation across organizations and departments.
- `PlatformPlaneTest`: Super Admin denied access to organization documents, search, and chats.
- `ChatCitationTest`: Forged chunk IDs and wrong page citations stripped before saving.
- `ReviewWorkflowTest`: Human verification lifecycle; self-review prevention.
- `AuditSanitizationTest`: Secret stripping and zero document/chat text leakage.

---

## 4. Full Integration Test Script
```bash
./scripts/integration-test.sh
```
This launches a temporary AI service instance on an isolated port, creates temporary SQLite database, runs end-to-end document upload, processing, search, Bangla Q&A, and citation verification.
