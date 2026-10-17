# Architecture

```
Browser (Blade + Bootstrap)
   │  HTTPS (deployment) / HTTP (local)
   ▼
Laravel app ── auth, roles, policies, scoped queries ──► MySQL (metadata, pages, chunk refs, chats, reviews, audit)
   │  │
   │  └── private disk: storage/app/private/documents/<org>/<uuid>.pdf
   │
   │  X-Service-Token + only authorized document UUIDs
   ▼
FastAPI AI service (127.0.0.1:8001)
   extraction/OCR → classify → extract info → chunk → embed → in-memory vector index (persisted to ai-service/data/index.pkl)
   search / Q&A (extractive, grounded) / summarize / related
   ▼
answer + citations ──► Laravel re-validates citations ──► chat message ──► human review queue
```

## Responsibilities
- **Laravel**: everything involving users and permissions; decides *which documents a user may see* before any search or AI call; stores results and audit logs.
- **FastAPI**: stateless-by-contract computation. It never opens files by ID and never queries MySQL. Process calls receive the PDF bytes; query calls receive the list of authorized document UUIDs and filter by it *before* scoring.
- **MySQL**: relational data. Vectors are held by the AI service; MySQL holds `document_chunks` references (chunk ids, pages).

## Key design decisions
1. **Public IDs are UUIDs** (`documents.uuid`), also the AI-service document id.
2. **Single visibility rule** in `Document::isVisibleTo` and its query form `scopeVisibleTo`; every list, search, chat and related call uses it.
3. **Double validation of citations**: the AI service checks (document authorized, chunk exists, page matches); Laravel checks again against its own `document_chunks` table before saving.
4. **Extractive answerer by default** so the demo runs offline and cannot invent text. The provider is configurable by design (`LLM_PROVIDER`) but only `extractive` exists today.
5. **Hash embeddings** (no model download) behind an `embed()` interface; replace with sentence-transformers for real semantic quality.
6. **In-memory vector store + pickle persistence**: simplest replaceable option; a single index for the prototype.
7. **Queue**: `ProcessDocumentJob`; default `sync` for easy demo, `database` for async.
