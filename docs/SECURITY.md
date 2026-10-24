# Nexora — Security Policy & Threat Verification

## 1. Security Architecture & Threat Model

Nexora is designed with defense-in-depth principles to prevent unauthorized access across tenants, departments, and administrative boundaries.

---

## 2. Verification of the 12 Core Attacks

| Attack ID | Threat Scenario | Implemented Defense Mechanism | Test Verification |
|---|---|---|---|
| **ATTACK-01** | Unauthenticated user attempts to view documents or admin endpoints. | Route middleware `auth` and `RequirePermission` redirect to `/login`. | `AuthenticationTest` |
| **ATTACK-02** | Viewer role attempts document upload, modification, or deletion. | Controller Gate checks and Form Request authorization reject non-privileged roles. | `DocumentPolicyTest` |
| **ATTACK-03** | Changing document UUID in route parameter to access another department's document. | `Document::scopeVisibleTo($user)` returns 404/403 for unauthorized departments. | `DocumentIsolationTest` |
| **ATTACK-04** | Guessing or brute-forcing document preview or download URLs. | Randomized UUIDs used; private storage disk; route requires active session and policy approval. | `DocumentDownloadTest` |
| **ATTACK-05** | Keyword or semantic search targeting documents of another organization. | Query scope filters by `organization_id` **before** SQL execution or vector scoring. | `SearchScopingTest` |
| **ATTACK-06** | RAG prompt injection or unauthorized context retrieval. | Authorized UUID list sent to AI service; AI ignores prompt injection instructions; citations re-validated. | `RagSecurityTest` |
| **ATTACK-07** | Viewing or interrogating another user's chat session. | `ChatSessionPolicy` restricts session access strictly to `user_id == session.user_id`. | `ChatSecurityTest` |
| **ATTACK-08** | Malicious file upload (executables, fake MIME, polyglot PDFs, > 20 MB). | Form request checks `mimes:pdf`, `mimetypes:application/pdf`, 20 MB size, and `%PDF-` magic bytes. | `UploadValidationTest` |
| **ATTACK-09** | Forged citation chunk IDs or invalid page numbers returned in AI payload. | Laravel re-checks every citation against `document_chunks` table and verified page bounds. | `CitationIntegrityTest` |
| **ATTACK-10** | Unauthorized privilege escalation (e.g., Dept Admin promoting self to Super Admin). | User management controllers strictly restrict allowable role choices by current user role. | `UserEscalationTest` |
| **ATTACK-11** | Prompt injection contained within uploaded document text trying to hijack LLM. | Extractive engine relies on sentence scoring; LLM prompt isolates untrusted sources in `<source>` tags. | `PromptInjectionTest` |
| **ATTACK-12** | Direct unauthorized requests to FastAPI AI service without token. | `X-Service-Token` header validated with constant-time comparison; returns 401 Unauthorized. | `AiServiceAuthTest` |
| **ATTACK-13** | **Super Admin Breach:** Super Admin attempting to view organization documents/chats. | Super Admin has no organization ID; `visibleTo` returns empty set; policy rejects view. | `PlatformPlaneIsolationTest` |

---

## 3. Audit Logging & Secret Masking
`AuditLogger` automatically sanitizes metadata payloads before storing to database:
- Keys matching `password`, `secret`, `token`, `api_key`, `authorization` are stripped.
- Document full text, OCR text, and user chat prompt text are **never** logged to audit or error logs.
