# Nexora — Next-generation organizational intelligence

Nexora is a secure, AI-powered document intelligence and retrieval platform. It extracts text from digital and scanned PDFs in both English and Bengali, classifies documents, deterministically extracts metadata, supports keyword and semantic search, provides grounded conversational Q&A with source document and page citations, flags conflicting evidence, and provides a human verification workflow.

---

## Architecture Overview

Nexora enforces a strict two-plane separation:
- **Organization Plane:** Organization Admin, Department Admin, Officer, Reviewer, and Viewer roles access documents scoped to their organization and department.
- **Platform Plane:** The Super Admin manages system health, organizations, security incidents, and account recovery. The Super Admin has **zero access** to organizational document contents, search, or chat.

---

## Directory Layout

```
nexora/
├── ai-service/           # FastAPI Python microservice (port 8001)
├── laravel-app/          # Laravel 12 web application (port 8000)
├── sample-documents/     # Synthetic demo PDFs (digital, scanned, bilingual)
├── scripts/              # Setup, migration, and test runner scripts
├── docs/                 # Complete system documentation
├── docker-compose.yml    # Optional local containers (MySQL on 3307, AI service)
├── PROJECT_PROGRESS.md   # Live verification log & checklist
└── README.md
```

---

## Prerequisites (macOS Developer Setup)

- **PHP 8.2+** with extensions `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `sqlite3`
- **Composer 2+**
- **Python 3.10+**
- **Tesseract OCR** with English and Bangla language packages:
  ```bash
  brew install tesseract tesseract-lang poppler composer
  ```
- **MySQL** (via XAMPP or Docker)

---

## Quick Start (Local Development)

### 1. Automated Setup
```bash
./scripts/setup-mac.sh
```

### 2. Run the Application
Open three terminal tabs:

- **Tab 1: AI Microservice**
  ```bash
  cd ai-service
  source .venv/bin/activate
  uvicorn app.main:app --port 8001
  ```

- **Tab 2: Laravel Web Application**
  ```bash
  cd laravel-app
  php artisan serve --port 8000
  ```

- **Tab 3: Document Processing Queue Worker**
  ```bash
  cd laravel-app
  php artisan queue:work
  # Or run processing manually:
  php artisan nexora:process
  ```

Visit `http://localhost:8000` in your browser.

---

## Default Demo Credentials

> **Notice:** The default development password for all seeded accounts is `Password@123`.

- **Platform Super Admin:** `super@nexora.test`
- **Org A Admin:** `orgadmin@demo-a.test`
- **Org A Planning Officer:** `officer.planning@demo-a.test`
- **Org A Reviewer:** `reviewer@demo-a.test`
- **Org A Finance Viewer:** `viewer.finance@demo-a.test`
- **Org B Admin:** `orgadmin@demo-b.test`
- **Org B Officer:** `officer@demo-b.test`

---

## Documentation
- [Requirements & Traceability](file:///docs/REQUIREMENTS.md)
- [System Architecture](file:///docs/ARCHITECTURE.md)
- [Database Design](file:///docs/DATABASE_DESIGN.md)
- [AI Service API Documentation](file:///docs/API_DOCUMENTATION.md)
- [Security Threat Verification](file:///docs/SECURITY.md)
- [Testing Strategy](file:///docs/TESTING.md)
- [User Manual](file:///docs/USER_MANUAL.md)
- [Demonstration Script](file:///docs/DEMO_SCRIPT.md)
- [Known Limitations](file:///docs/KNOWN_LIMITATIONS.md)
- [Acceptance Checklist](file:///docs/ACCEPTANCE_CHECKLIST.md)
