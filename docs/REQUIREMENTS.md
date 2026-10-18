# Requirements and traceability

Source: the project proposal (SE331, Fall 2026) and the master build prompt. FR = proposal functional requirement, AI = AI requirement, NFR = non-functional.

Status legend: **Tested** = automated test passed in the build sandbox. **Implemented (unrun)** = code written and syntax-checked, never executed. **Baseline** = works but deliberately simple.

| Req | Description | Where implemented | Status |
|---|---|---|---|
| FR1 | Accounts | `UserController`, `ProfileController`, `AuthController` | Implemented (unrun) |
| FR2 | Role-based access control | `config/nexora.php`, `RequirePermission`, Policies | Implemented (unrun) |
| FR3 | Organizations/departments | `OrganizationController`, `DepartmentController` | Implemented (unrun) |
| FR4/5 | Upload, metadata, file info | `DocumentController`, `DocumentService`, `StoreDocumentRequest` | Implemented (unrun) |
| FR6 | Keyword search with filters | `SearchController` (keyword mode) | Implemented (unrun) |
| FR7 / AI17 | OCR for scanned PDFs | `ai-service/app/extraction.py` (Tesseract) | **Tested** (English synthetic scan) |
| FR8 / AI18 | Classification | `ai-service/app/classify.py` (rule-based) | **Tested**, Baseline, accuracy NOT measured |
| FR9 / AI19 | Information extraction | `ai-service/app/info.py` | **Tested**, Baseline |
| FR10 / AI20 | Semantic search | `store.py` + `/v1/search` | **Tested**, Baseline (hash embeddings, not a semantic model) |
| FR11 / AI21 | Summaries | `summarize.py`, `DocumentAiController` | AI side **Tested**; Laravel Implemented (unrun); extractive, no LLM |
| FR12 / AI22 | Q&A over documents (RAG) | `rag.py`, `ChatController` | AI side **Tested**; extractive answerer, no generative LLM configured |
| FR13 / AI23 | Source/page citations | `rag.validate_citations`, `ChatController` | AI side **Tested**; Laravel Implemented (unrun) |
| FR14 / AI24 | Human review of AI output | `ReviewController`, `ReviewPolicy` | Implemented (unrun) |
| FR15 | Audit logs | `AuditLogger`, `AuditLogController` | Implemented (unrun) |
| FR16 | Admin dashboard | `DashboardController` | Implemented (unrun) |
| NFR Security | Org/dept/role isolation | `Document::scopeVisibleTo`, policies | AI-side filtering **Tested**; Laravel Implemented (unrun) |
| NFR Reliability | AI failure recorded, file kept | `DocumentProcessor` | Implemented (unrun) |
| NFR Maintainability | Modular Laravel + FastAPI | repository layout | — |
| NFR Isolation of AI data | Only authorized content reaches AI | scope computed in Laravel; AI filters again | **Tested** (AI), Implemented (Laravel) |
| Bangla OCR (proposal: "if the model is good enough") | | `OCR_LANGS=eng+ben` option | NOT tested; needs `tesseract-lang` |

## Assumptions
- PDF is the only supported upload format (as per prototype scope).
- One role per user; Super Admin has no organization.
- A document with no department is visible to everyone in its organization; a document with a department is visible to that department and to Organization Admins.
- Soft-delete keeps the file on the private disk for recovery.
- Password reset is not implemented (no mail server); administrators reset passwords.
- No live LLM is configured by default; the answerer is extractive (see KNOWN_LIMITATIONS).
