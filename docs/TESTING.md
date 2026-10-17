# Testing — what was actually run

## Results
| Suite | Where run | Result |
|---|---|---|
| `ai-service/tests` (pytest) | build sandbox (Python 3.12, Tesseract 5.3) | **39 passed** |
| `laravel-overlay/tests/Feature` (PHPUnit) | **not run** (no Composer/Packagist/MySQL in the sandbox) | PHP syntax lint (`php -l`, PHP 8.3) passed on every PHP file; Blade directive balance checked by script; **no test executed** |

Treat the Laravel code as *untested* until `php artisan test` has been run on your machine and failures fixed.

## AI service tests (39)
Also: LLM layer with fake and mocked providers (verified citations, hallucinated amount/date withheld, missing key fallback, delimiter forging, key never in errors), evidence-checked LLM extraction, **Laravel↔FastAPI contract test** (every key Laravel reads exists; `/qa` citation chunk ids match `/process` chunk ids on the real sample PDFs), index health. None of this used a real LLM.

Original 20:
Token required / rejected; page-aware extraction and chunking; chunk ids and overlap; grounded answer with page citation; insufficient evidence; off-topic budget question not answered with another project's budget; Bangla+English question returns budget and deadline; authorization filtering before retrieval; conflicting amounts flagged; prompt-injection line ignored; reprocess does not duplicate; corrupt/non-PDF/validation errors; forged citation rejection; classifier categories; extraction leaves unknown fields `None`; classification and page text returned by `/process`; summary + authorization (403/404); related documents only among authorized ids; **OCR on an image-only PDF**.

## Manual smoke test (sandbox, real HTTP)
`uvicorn` started; `/health` ok; request without token → 401; sample document 01 processed (Project Report, BDT 50,000,000, 30 June 2027, 2 chunks); Bangla question `এই project-এর budget কত এবং deadline কবে?` → `answered` with both values and citations.

## Sample-document outcomes (synthetic, written by us)
01 Project Report ✔ | 02 Budget or Financial Document ✔ | 03 Circular ✔ | 04 Policy or Guideline ✔ | 05 Meeting Minutes ✔ | 06 Project Report ✔ | 07 Budget ✔ | 08 scanned → OCR → Official Notice ✔ | 09 corrupted → 422 `corrupt_pdf` ✔.
**This is not an accuracy evaluation**: 8 self-written documents are biased toward the rules. A real evaluation needs a labeled set of real-style Bangla/English documents (planned in your proposal's Milestone 8).

## Laravel tests (written, to be run by you)
`AuthTest` (7), `AccessControlTest` (17), `UploadAndProcessingTest` (11), `SearchAndChatTest` (10), `ReviewAndAiTest` (11), `PasswordResetTest` (5) = 61 tests, plus `AiIntegrationTest` (3, real AI service, skipped unless `bash scripts/integration-test.sh`). They use SQLite in-memory, `Storage::fake`, and `Http::fake` for the AI service, so no AI service or paid API is needed.
Commands: `cd laravel-app && php artisan test` · `cd ai-service && .venv/bin/python -m pytest -q`.

## Not covered anywhere yet
Browser/UI testing, load testing, usability testing, live-LLM tests, Bangla OCR quality, classifier accuracy on real documents.
