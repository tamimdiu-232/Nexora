# Nexora — Requirements Specification & Traceability Matrix

## 1. Product Identity
- **Name:** Nexora
- **Tagline:** Next-generation organizational intelligence
- **Mission:** Provide secure, AI-powered document intelligence and retrieval for enterprise and public-sector organizations with large bilingual (English and Bangla) document collections.
- **Academic Context:** Final-year capstone (Daffodil International University, SE331). Demonstrated with synthetic government-style documents of Bangladesh.
- **Data Policy:** 100% synthetic documents only. No connection to real government databases.

---

## 2. Core Architectural Planes
1. **Organization Plane:**
   - Multi-tenant model scoped by `organization_id` and optional `department_id`.
   - Users: Organization Admin, Department Admin, Officer, Reviewer, Viewer.
   - Operations: Document upload, processing, search, Q&A, citations, summarization, human review.
2. **Platform Plane:**
   - Operator: Super Admin (`users.role = 'super_admin'`).
   - Scope: System health, organization management, account recovery, security incidents, platform-wide audit log.
   - **Platform Boundary Rule:** The Super Admin has **no access** to organization document contents, text extractions, search results, or AI chat histories. Enforced by policies, scoped queries, and retrieval filtering.

---

## 3. Role & Permission Matrix

| Role | Dashboard | View Docs | Upload | Edit/Reprocess | Delete Docs | Submit Review | View Reviews | Decide Review | Manage Users | Manage Depts | Manage Orgs | System Health | Security/Recovery | Audit Logs |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Super Admin** | Platform Only | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | Org Admins Only | ✘ | ✔ | ✔ | ✔ | All Orgs |
| **Org Admin** | Org Scoped | ✔ | ✔ | All in Org | ✔ | ✔ | ✔ | ✘ | All in Org | ✔ | ✘ | ✘ | ✘ | Own Org |
| **Dept Admin** | Dept Scoped | ✔ | ✔ | Own Dept | Own Dept | ✔ | ✔ | ✘ | Dept Users | ✘ | ✘ | ✘ | ✘ | ✘ |
| **Officer** | Dept Scoped | ✔ | ✔ | Own Uploads | ✘ | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ |
| **Reviewer** | Org Scoped | ✔ | ✘ | ✘ | ✘ | ✔ | ✔ | ✔ (Not Own) | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ |
| **Viewer** | Scoped | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ |

---

## 4. Document Visibility Rule
A document is visible to a user `U` if and only if:
1. `U` is not a Super Admin (`U.role != 'super_admin'`).
2. `document.organization_id == U.organization_id`.
3. If `document.department_id` is NOT NULL:
   - `U.role == 'org_admin'` OR `U.department_id == document.department_id`.
4. If `document.department_id` IS NULL:
   - Visible to all non-Super Admin members of `document.organization_id`.

---

## 5. Requirements Traceability Matrix

| Req ID | Description | Source Phase | Verification Method |
|---|---|---|---|
| REQ-01 | Dual-plane isolation (Platform vs Organization) | Phase 3, 4, 12 | Policy unit tests + Super Admin crawl test |
| REQ-02 | Service-token authentication for AI service | Phase 2, 7 | Pytest + Laravel AiClient tests |
| REQ-03 | PDF Extraction (PyPDF + Tesseract 300 DPI OCR) | Phase 2, 7 | Pytest with synthetic & scanned PDFs |
| REQ-04 | Garbled / Bijoy legacy font detection | Phase 2 | Pytest regex unit test |
| REQ-05 | Rule-based bilingual document classification | Phase 2 | Pytest category verification |
| REQ-06 | Deterministic bilingual entity extraction | Phase 2 | Pytest extraction test |
| REQ-07 | Page-aware chunking & lexical hashing embedder | Phase 2 | Pytest chunk & embedding test |
| REQ-08 | Extractive Grounded Q&A with source citations | Phase 2, 9 | Pytest + Laravel chat verification |
| REQ-09 | Conflicting evidence detection | Phase 2, 9 | Pytest test on budget revision pair |
| REQ-10 | Bangla glossary expansion & stemming | Phase 2, 9 | Pytest Bengali query tests |
| REQ-11 | Human verification workflow for AI answers | Phase 11 | Laravel Feature tests |
| REQ-12 | Platform operations: Health, Recovery, Incidents | Phase 12 | Laravel Feature tests |
| REQ-13 | Private file storage with generated UUIDs | Phase 6 | Storage assertions |
| REQ-14 | Masked audit logging (no secrets / text logged) | Phase 13 | AuditLogger unit tests |
| REQ-15 | Responsive UI with Bootstrap 5.3 & typography | Phase 14 | Blade syntax & route verification |
