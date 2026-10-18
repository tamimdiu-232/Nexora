# API documentation

## FastAPI AI service (`http://127.0.0.1:8001`)
All `/v1/*` endpoints require header `X-Service-Token: <SERVICE_TOKEN>` (constant-time compare; 401 otherwise). Bind to localhost only. Auto-generated docs: `/docs` (disable or protect outside development).

| Method & path | Purpose |
|---|---|
| `GET /health` | `{"status":"ok","embedder":"hash-512","llm":"extractive","documents_indexed":N}` (no auth) |
| `POST /v1/documents/process` (multipart: `document_id`, `title`, `file`) | extract text (+OCR), classify, extract info, chunk, index. Replaces any previous index for the id. |
| `DELETE /v1/documents/{id}` | remove from the index |
| `POST /v1/search` | `{question, authorized_document_ids[], top_k}` → ranked chunks |
| `POST /v1/qa` | same body → grounded answer with citations |
| `POST /v1/summarize` | `{document_id, authorized_document_ids[]}`; 403 if the id is not in the authorized list; 404 if not indexed |
| `GET /v1/index/health` | index consistency: documents, chunks, documents without chunks, vector mismatches (auth required) |
| `POST /v1/related` | same body + `top_k` → similar documents among authorized ids |

Process response (abridged, real output for sample document 01):
```json
{"document_id":"doc-1",
 "pages":[{"page":1,"method":"text","chars":"<n>","text":"..."}],
 "chunks":[{"chunk_id":"doc-1:p1:c0","page":1,"index":0,"chars":"<n>"}],
 "classification":{"category":"Project Report","confidence":"<heuristic 0-1>","method":"rule-based"},
 "info":{"reference_number":"PD/DRCP/2026/001","budget":"BDT 50,000,000","deadline":"30 June 2027","keywords":["..."],"entities":[...]}}
```
Q&A response:
```json
{"status":"answered","answer":"The approved budget for the project is BDT 50,000,000. [1] The completion deadline is 30 June 2027. [2]",
 "citations":[{"document_id":"doc-1","chunk_id":"doc-1:p1:c0","page":1,"title":"Report","snippet":"...","score":0.5}],
 "conflicts":[],"generated_by":"extractive-baseline","verified":false}
```
`status` ∈ `answered | insufficient_evidence | conflicting_evidence`. `generated_by` is `extractive-baseline` or `llm:<provider>:<model>`; an optional `warning` explains fallbacks or withheld answers. `/health` also reports `llm_ready`, `llm_warning`, `extraction_mode`.

LLM configuration (env): `LLM_PROVIDER=extractive|anthropic|openai`, `LLM_API_KEY`, `LLM_MODEL`, `LLM_BASE_URL` (openai-compatible), `EXTRACTION_MODE=rules|llm`. A provider set without a key/model falls back to the extractive baseline with a visible warning.
Errors: processing failures return 422 `{"detail":{"code":"corrupt_pdf|password_protected|empty_pdf|unsupported_file|no_text","message":"..."}}`; oversize 413; validation 422.

## Laravel routes (session-authenticated, CSRF-protected, rate-limited)
Documents: `GET/POST /documents`, `GET /documents/create`, `GET/PUT/DELETE /documents/{uuid}`, `/edit`, `/preview`, `/download`, `POST /summarize|/archive|/reprocess`, `GET /related`.
Auth: `GET/POST /login`, `/forgot-password`, `/reset-password`.
Search: `GET /search?q&mode=keyword|semantic&category&department_id&from&to`.
Chat: `GET/POST /chat`, `POST /chat/{id}/ask`, `DELETE /chat/{id}`. Review: `GET /reviews`, `POST /messages/{id}/review`, `POST /reviews/{id}/decide`.
Admin: `users`, `departments`, `organizations` (resource routes), `GET /audit-logs`, `GET/PUT /profile`.
Required permission per route is declared in `routes/web.php`; row-level checks in policies.

## Role–permission matrix
(authoritative copy: `config/nexora.php`)

| Permission | Super Admin | Org Admin | Dept Admin | Officer | Reviewer | Viewer |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| dashboard.view | ✔ (platform overview) | ✔ | ✔ | ✔ | ✔ | ✔ |
| documents.view, search.use, chat.use | ✘ | ✔ | ✔ | ✔ | ✔ | ✔ |
| documents.upload | ✘ | ✔ | ✔ | ✔ | | |
| documents.edit / reprocess | ✘ | ✔ (all in org) | ✔ (own dept) | ✔ (own uploads) | | |
| documents.delete | ✘ | ✔ | ✔ (own dept) | | | |
| reviews.submit | ✘ | ✔ | ✔ | ✔ | ✔ | |
| reviews.view | ✘ | ✔ | ✔ | | ✔ | |
| reviews.decide | ✘ | | | | ✔ (not own submissions) | |
| users.manage | ✔ (Organization Admins only) | ✔ (org) | ✔ (dept; officer/reviewer/viewer) | | | |
| departments.manage | ✘ | ✔ (own org) | | | | |
| organizations.manage | ✔ | | | | | |
| system.view (health, queues, error log) | ✔ | | | | | |
| security.manage (incidents, account recovery) | ✔ | | | | | |
| audit.view | ✔ (all organizations) | ✔ (own org) | | | | |

Visibility: organization users see their organization; department-scoped documents only to that department and Organization Admins. **Super Admin sees no documents, search results or AI chats** (`Document::isVisibleTo` returns false and `scopeVisibleTo` matches nothing for Super Admins).

Platform routes: `GET /admin/system`, `GET /admin/accounts`, `POST /admin/accounts/{user}/reset-link|revoke-sessions|toggle-active`, `GET/POST /admin/incidents`, `PUT /admin/incidents/{id}`.
