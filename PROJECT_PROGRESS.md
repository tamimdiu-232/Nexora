# GovDoc BD — Progress tracker

Legend: Tested = automated test passed. **Implemented (unrun)** = code exists and is syntax-checked but was never executed (the build sandbox had no Composer/Packagist/MySQL). Not Started / Blocked as stated.

| Module | Status | Notes |
|---|---|---|
| AI service: health, service-token auth, limits | Tested | 39 tests |
| PDF extraction, page boundaries | Tested | |
| OCR (Tesseract) | Tested | English synthetic scan only |
| Corrupt/non-PDF handling | Tested | password-protected and oversize paths untested |
| Classification | Tested (baseline) | rule-based; accuracy not evaluated |
| Information extraction | Tested (baseline) | |
| Chunking, reindex without duplicates | Tested | |
| Embeddings / vector store / persistence | Tested (baseline) | hash embeddings, pickle persistence |
| Authorized retrieval, search | Tested | |
| Grounded Q&A, conflicts, insufficient evidence, injection filter, citation validation | Tested | extractive, no LLM |
| Summarization, related documents | Tested (baseline) | |
| Laravel: migrations, models, factories, seeder | Implemented (unrun) | |
| Laravel: auth, roles/permissions, policies, middleware | Implemented (unrun) | |
| Laravel: organizations, departments, users | Implemented (unrun) | |
| Laravel: documents (upload, library, preview, download, archive, delete, retry) | Implemented (unrun) | |
| Laravel: processing pipeline + job + artisan command | Implemented (unrun) | |
| Laravel: search, chat, citations, summaries, related | Implemented (unrun) | |
| Laravel: review workflow, audit log, dashboard | Implemented (unrun) | |
| Blade UI (all listed pages, errors) | Implemented (unrun) | |
| Laravel feature tests (61) + real-AI integration test (3) | Written, not run | `scripts/run-tests.sh`, `scripts/integration-test.sh` |
| LLM provider layer (anthropic/openai-compatible) with verification | Tested with fakes/mocks only | never run against a real LLM |
| Model-assisted extraction (evidence-checked) | Tested with fakes only | |
| Laravel↔FastAPI contract test | Tested | real sample PDFs |
| Index health endpoint | Tested | |
| Password reset | Implemented (unrun) | log mailer |
| Docker Compose | Written, untested | optional |
| Git repository | Done | initial commit included |
| Final acceptance checklist | Done | `docs/ACCEPTANCE_CHECKLIST.md` |
| Synthetic demo data + seeder | Implemented | PDFs generated and processed by the AI service; seeder unrun |
| Docs | Implemented | reflect real status |
| Real-document accuracy evaluation | Not Started | needs labeled data |
