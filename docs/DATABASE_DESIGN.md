# Database design (MySQL via Laravel migrations)

```mermaid
erDiagram
  organizations ||--o{ departments : has
  organizations ||--o{ users : employs
  departments ||--o{ users : groups
  organizations ||--o{ documents : owns
  departments ||--o{ documents : "optionally scopes"
  users ||--o{ documents : uploads
  documents ||--|| document_metadata : has
  documents ||--o{ document_pages : has
  documents ||--o{ document_chunks : "indexed as"
  documents ||--o{ processing_jobs : runs
  documents ||--o{ extracted_entities : yields
  users ||--o{ chat_sessions : owns
  chat_sessions ||--o{ chat_messages : contains
  chat_messages ||--o{ citations : cites
  documents ||--o{ citations : "cited by"
  chat_messages ||--o{ reviews : "reviewed in"
  users ||--o{ reviews : "requests/decides"
  users ||--o{ audit_logs : acts
```

Migrations: `2026_01_01_0001…0004` in `laravel-overlay/database/migrations`. The skeleton's own migrations (users, cache, jobs, sessions) run first; `0200` adds `organization_id`, `department_id`, `role`, `is_active` to `users`.

| Table | Notes |
|---|---|
| organizations, departments | unique names/codes; `restrictOnDelete` (deactivate instead of delete) |
| users | role string, nullable org/dept (Super Admin has none), `is_active` |
| documents | `uuid` unique; `stored_path` hidden from serialization; soft deletes; `archived_at`; indexes on (org, dept), category, status |
| document_metadata | 1:1; extracted/edited fields + cached `summary` JSON |
| document_pages | page-level text (used for keyword search and snippets); unique (document, page) |
| document_chunks | references to vector records (chunk id, page); unique (document, chunk_id) |
| processing_jobs | one row per attempt with error code/message |
| extracted_entities | typed values with page and evidence |
| chat_sessions / chat_messages / citations | private to the session owner |
| reviews | pending / verified / rejected / needs_correction, reviewer, timestamp, comment |
| audit_logs | append-only (no `updated_at`); metadata JSON never receives passwords/secrets |

Original PDFs are **not** stored in MySQL; they live on the private disk (`storage/app/private`).
