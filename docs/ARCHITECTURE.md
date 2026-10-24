# Nexora — Architecture & System Design

## 1. System Overview

Nexora is designed with a strict separation between the **Web Application Plane** (Laravel 12 / PHP 8.2+), the **AI Service Plane** (FastAPI / Python 3.10+), and the **Storage Layer** (MySQL + Private Local Storage).

```
                      +------------------------------------------+
                      |         Browser (Blade + Bootstrap)      |
                      +------------------------------------------+
                                           |  HTTPS / HTTP
                                           v
+----------------------------------------------------------------------------------+
|                     LARAVEL 12 APPLICATION PLANE (Port 8000)                     |
|                                                                                  |
|  [Authentication & Session Guard]  --> [Role & Permission Middleware]           |
|                                                   |                              |
|  +------------------------------------------------+---------------------------+  |
|  | Organization Plane Controllers                 | Platform Plane            |  |
|  | - DocumentsController (Upload/View/Download)   | - PlatformDashboard       |  |
|  | - SearchController (Keyword & Semantic Scope)  | - SystemHealthController  |  |
|  | - ChatController (Q&A + Citation Re-val)      | - AccountRecovery         |  |
|  | - ReviewController (Human Verification)        | - SecurityIncidents       |  |
|  | - Scoped Query Builder (Document::visibleTo)   | - AuditViewer (All Orgs)  |  |
|  +------------------------------------------------+---------------------------+  |
|                                                                                  |
|  [DocumentProcessor Service] <=========> [Queue Worker: sync / database]        |
|  [AiClient (HTTP / X-Service-Token / Timeout: 900s)]                             |
+----------------------------------------------------------------------------------+
          |                                                       |
          | Internal Loopback                                     | SQL & File I/O
          v                                                       v
+-------------------------------------+  +-----------------------------------------+
|     FASTAPI AI SERVICE (Port 8001)   |  |               DATA LAYER                |
|                                     |  |                                         |
| - Service-Token Auth (401 Guard)    |  | [MySQL Database]                        |
| - Text Extraction & PyPDF           |  | - organizations, departments, users     |
| - Tesseract OCR (300 DPI, Ben+Eng)  |  | - documents (UUID key, metadata)        |
| - Rule Classifier (Bilingual)       |  | - document_pages, document_chunks       |
| - Regex Info Extractor              |  | - chats, citations, reviews             |
| - Page-Aware Chunker                |  | - audit_logs, security_incidents        |
| - Lexical Hashing Vector Store      |  |                                         |
| - Grounded Q&A / Lexicon Engine     |  | [Private Storage Disk]                  |
| - Conflict Detector & Summarizer    |  | - storage/app/private/documents/*.pdf   |
+-------------------------------------+  +-----------------------------------------+
```

---

## 2. Platform Plane vs. Organization Plane Boundary

Nexora strictly isolates the **Super Admin** from viewing or querying organizational document content:
1. **Route Level:** Organization document routes are protected by permissions not granted to `super_admin`.
2. **Policy Level:** `DocumentPolicy`, `ChatSessionPolicy`, and `ReviewPolicy` explicitly reject Super Admin users for viewing, reading, or retrieving document details.
3. **Database Level:** Scoped query `Document::scopeVisibleTo($user)` returns an empty builder when evaluated for a Super Admin.
4. **AI Retrieval Level:** All search and Q&A operations compute an authorized set of document UUIDs in Laravel and pass them to FastAPI. For Super Admin, this set is always empty.
5. **Audit Integrity:** Platform admins can view operational metadata (file size, page count, processing status, error codes) but never document titles, raw text, or user chat questions.

---

## 3. Component Details

### 3.1 AI Service (FastAPI)
- **Extraction (`extraction.py`):** Checks `%PDF` header. Reads native text using `pypdf`. If page character count < 25 or character distribution matches legacy garbled Bijoy encoding (>12% in Latin-1 supplement / punctuation ranges), renders 300 DPI image via `pdftoppm` and runs Tesseract OCR.
- **Multilingual Support (`lexicon.py` & `textutil.py`):** Regex pattern `[\w\u0980-\u09FF]+` ensures Bengali grapheme clusters are not split. Stemmer strips common Bengali inflections. Government lexicon maps English/Bangla concepts.
- **Vector Store (`vector_store.py`):** Thread-safe in-memory vector index with authorized-UUID pre-filtering and optional local disk persistence (`DATA_DIR/index.pkl`).
- **Grounded Q&A (`rag.py`):** Extractive sentence scoring with topic-coverage filtering (>50%), intent boosting for budgets and deadlines, cross-document conflict detection, and citation formatting.

### 3.2 Web Application (Laravel 12)
- **Thin Controllers & Service Layer:** Controllers delegate business operations to `DocumentProcessor`, `AiClient`, `AuditLogger`.
- **Form Requests:** Strict input validation including PDF magic byte verification (`%PDF-`).
- **Citation Re-validation:** Every citation returned by the AI is re-checked in Laravel against the user's visible document scope, existing chunk IDs in `document_chunks`, and matching page numbers.
