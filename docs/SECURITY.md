# Security

**This is an academic prototype. It has had no independent security assessment and must not be called production-grade.**

## Controls implemented (in code)
- Password hashing (bcrypt via Laravel `hashed` cast), session regeneration on login, CSRF on all forms, login throttling (5 attempts/min per email+IP) and route rate limits, deactivated users logged out on next request.
- Authorization on the server: permission middleware per route + policies + scoped queries (`Document::scopeVisibleTo`). UI hiding is cosmetic only.
- UUID document URLs; `stored_path` hidden from serialization; files streamed only through authorized controllers; `nosniff`, `no-store` headers.
- Upload validation: extension, MIME, `%PDF-` magic bytes, size limit; generated filenames; private disk; nothing executed.
- AI isolation: Laravel computes authorized UUIDs first; the AI service filters by them before scoring; it never reads files/DB by ID. Summaries only send the single document id.
- Citations validated twice (AI service and Laravel) and re-authorized when displayed.
- Service-to-service: shared secret header, constant-time compare, timeouts, request-size limits (Pydantic bounds, 25 MB upload cap).
- Output escaping via Blade `{{ }}`; Eloquent bindings (no raw user SQL); LIKE wildcards stripped.
- Audit logs for login/logout/failures, upload, edit, delete, archive, preview/download, processing, summary, search (length only), chat (status only — question text not logged), role changes, reviews. Secret-like metadata keys are stripped.
- Chat history is private to its owner (policy denies even admins).
- Password reset: broker tokens, same response for unknown/inactive accounts, throttled, audited.
- LLM path (optional): sources wrapped in neutralized delimiters and labelled untrusted; every answer must cite existing sources; amounts/dates must appear in the cited text; otherwise the answer is withheld. API keys only from environment and never logged.

## The 12 required attack tests
| # | Attack | Test | Status |
|---|---|---|---|
| 1 | Unauthenticated access | `AuthTest::test_guests_are_redirected…` | written, unrun |
| 2 | Viewer upload/delete | `AccessControlTest::test_viewer_cannot_upload_or_delete` | written, unrun |
| 3 | Change doc ID → other department | `…test_other_department_document_is_forbidden_everywhere` | written, unrun |
| 4 | Guess/manipulate download URL | `…test_other_organization_document_is_forbidden_and_unknown_uuid_is_404` | written, unrun |
| 5 | Search for other org's documents | `SearchAndChatTest::test_keyword_search…`, `test_semantic_search…` | written, unrun |
| 6 | RAG for unauthorized content | `test_chat_sends_only_authorized_documents_to_ai`; AI: `test_authorization_filtering_before_retrieval` | AI **passed**; Laravel unrun |
| 7 | Another user's chat history | `test_other_users_chat_history_is_protected` | written, unrun |
| 8 | Malicious/oversized upload | `UploadAndProcessingTest::test_non_pdf_and_fake_pdf…`, `test_oversized_upload…` | written, unrun |
| 9 | Forged citations | `test_forged_citations_are_dropped`; AI: `test_forged_citation_rejected` | AI **passed**; Laravel unrun |
| 10 | Unauthorized permission changes | `test_dept_admin_cannot_assign_privileged_roles`, `…cannot_change_own_role…`, `…org_admin_cannot_edit_users…` | written, unrun |
| 11 | Prompt injection in documents | AI: `test_prompt_injection_line_ignored` | **passed** (see limits below) |
| 12 | FastAPI without credentials | AI: `test_no_token_rejected`; Laravel: `test_ai_401_is_reported…` | AI **passed**; Laravel unrun |

## Known risks
- Prompt-injection defense is a regex that drops instruction-like lines, plus the fact that the default answerer is extractive and cannot follow instructions. If you later plug in a generative LLM, add stronger isolation and evaluate it.
- Shared-secret service auth only; keep the AI service on localhost/private network. `/docs` is enabled.
- Index persistence uses `pickle` — only load files this service wrote itself.
- No HTTPS locally, no MFA, no malware scanning, no account-lockout beyond throttling. Reset links go to the log file unless a real mail server is configured.
- DB administrators can alter audit logs (no tamper-proofing). Soft-deleted files remain on disk.
- Super Admin can see every document by design.
