# Known limitations

1. **Laravel code has never been executed** (see TESTING.md). Expect small first-run fixes.
2. **Default Q&A is extractive** (returns supporting sentences; cannot synthesize or do arithmetic). Optional providers (`anthropic`, `openai`-compatible) are implemented with citation and value verification, but were tested **only with fake/mocked providers — never against a real LLM**. Generated answers containing amounts/dates not found in the cited text are withheld, which also rejects legitimate paraphrases such as "50 million".
3. **Embeddings are hash-based** (word/bigram hashing), not a semantic model; "semantic" search is closer to fuzzy keyword matching and may miss paraphrases or Bangla synonyms.
4. **Classifier and extractor are rule-based**; accuracy is unmeasured. Extraction picks the first match (e.g. the budget report's "Total allocation" can win over a project-specific amount).
5. **Bangla OCR** is only possible if Tesseract Bangla data is installed and configured; it was not tested. Text-based Bangla PDFs depend on the PDF's font encoding.
6. **Vector index is in-memory** with pickle persistence; single process only; rebuild with `php artisan govdoc:process --all`. Not for large collections.
7. **Semantic search shows the top 20 only** (keyword search is paginated).
8. PDF only; no DOCX/images. Scanned pages processed at 200 DPI, one page at a time (slow for long scans).
9. Password reset exists (broker + log mailer, no real mail server configured); no MFA, no HTTPS configuration, no antivirus scanning.
10. Archived documents are excluded from search/AI but remain downloadable to authorized users; deleted documents are soft-deleted and files are kept.
11. Document-level ACLs beyond organization/department do not exist.
12. Prompt-injection defense is heuristic.
13. No UI/browser automated tests; no load or usability testing.
14. Model-assisted extraction (`EXTRACTION_MODE=llm`) is evidence-checked but tested only with fake providers. Docker Compose is untested.
15. `/v1/index/health` is a consistency check (empty documents, vector mismatches), not a performance monitor. Semantic search is not paginated (top 20).

## Future improvements
Real LLM provider with citation-constrained prompting; sentence-transformer or multilingual embeddings (e.g. a multilingual E5/BGE model) and a persistent vector store (e.g. pgvector/Qdrant/FAISS); labeled Bangla/English evaluation set; async queue with progress; document versioning; stronger auth (MFA, SSO); containerized deployment behind HTTPS.
