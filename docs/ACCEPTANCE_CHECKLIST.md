# Final acceptance checklist

Legend: ✅ verified by a test that was actually run · 🟡 code written and syntax-checked but **never executed** (needs your Mac run) · ⚠️ partial/baseline · ❌ not done.
Verified = the 45 AI-service tests and a live uvicorn smoke test, run in the build sandbox. Nothing in Laravel has been executed.

| Definition-of-complete item | Status | Evidence / what to do |
|---|---|---|
| Laravel application starts | 🟡 | run `php artisan serve` |
| FastAPI service starts | ✅ | started with uvicorn, `/health` OK |
| Schema can be created with documented commands | 🟡 | `php artisan migrate --seed` |
| Users can log in | 🟡 | `AuthTest` (written) |
| Roles and access restrictions work | 🟡 | `AccessControlTest` (written) |
| Organizations/departments isolated | 🟡 | `AccessControlTest`, `SearchAndChatTest` (written) |
| Documents uploaded and stored privately | 🟡 | `UploadAndProcessingTest` (written) |
| Digital PDF text extraction | ✅ | AI tests + sample PDFs |
| OCR for scanned PDFs | ✅ | OCR test on an image-only PDF (English). Bangla OCR ❌ untested |
| Classification and indexing | ✅/⚠️ | works on all sample docs; rule-based, accuracy not evaluated on real data |
| Keyword search | 🟡 | `SearchAndChatTest` (written) |
| Semantic search | ⚠️ | works (AI tests) but embeddings are a hash baseline, not a true semantic model |
| RAG Q&A returns grounded answers | ✅ (AI) / 🟡 (Laravel) | extractive by default; optional LLM path tested only with fake/mocked providers — **never run against a real LLM** |
| Source references point to real documents/pages | ✅ (AI + contract test) / 🟡 (Laravel validation) | |
| Unsupported questions handled honestly | ✅ | insufficient-evidence tests |
| Conflicting sources identified | ✅ | BDT 50M vs 65M case |
| Unauthorized content cannot leak via AI or direct routes | ✅ (AI filtering, forged citations, delimiter forging) / 🟡 (Laravel routes) | |
| Summarization | ✅ (extractive baseline) | no LLM summaries |
| Reviewers can verify AI results | 🟡 | `ReviewAndAiTest` (written) |
| Audit records generated | 🟡 | tests written |
| Core automated tests pass | ✅ AI (45/45) · 🟡 Laravel (72 written, 0 run) · 🟡 real Laravel↔FastAPI test (`scripts/integration-test.sh`) | |
| Synthetic demo data works end to end | ⚠️ | AI side verified on all 9 PDFs; seeder + UI flow not run |
| Documentation reflects the actual system | ✅ | docs state which parts are unrun |
| Known limitations disclosed | ✅ | `KNOWN_LIMITATIONS.md` |
| Optional: Docker Compose | 🟡 | written, Docker not available to test |
| Password reset | 🟡 | `PasswordResetTest` (written); link goes to the log file in development |

## Your acceptance run (about 30 minutes)
1. `bash scripts/setup-mac.sh` → 2. `bash scripts/run-tests.sh` → 3. `bash scripts/integration-test.sh` → 4. follow `docs/DEMO_SCRIPT.md`.
Send me any failure output; the 🟡 rows turn ✅ only after those runs.
