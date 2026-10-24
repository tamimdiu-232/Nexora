# Nexora — Known Limitations & Design Boundaries

The following known limitations and architectural boundaries are documented truthfully in accordance with project requirements:

1. **Extractive Default Answer Engine:**  
   The primary Q&A engine operates extractively by scoring sentences from retrieved chunks. While deterministic, grounded, and privacy-preserving (no external network transmission required), it lacks the conversational fluidity of large generative models. External LLM integration (OpenAI/Anthropic compatible) is supported as an optional provider and tested via mock transports.

2. **Lexical Hashing Embeddings:**  
   The default vector store utilizes a high-performance hash-based n-gram embedder with stemming. It provides rapid lexical retrieval without multi-gigabyte neural weight downloads, but does not capture abstract conceptual synonyms beyond the configured bilingual lexicon.

3. **Rule-Based Document Classifier:**  
   Document classification relies on weighted regex heuristics and bilingual cues. The confidence score is a normalized heuristic metric and should not be interpreted as statistically measured empirical accuracy.

4. **Bangla OCR Real-World Variability:**  
   Tesseract OCR with the `ben` model performs well on clean synthetic documents at 300 DPI. Real-world degraded scans, physical paper folds, poor contrast, or historical non-Unicode print fonts will exhibit lower character recognition accuracy and slower processing times.

5. **Bilingual Lexicon & Stemming Scope:**  
   Bangla support is powered by a targeted government/administrative lexicon (~50 terms) and a light rule-based suffix stripper. Documents are not machine-translated; cross-lingual matching relies on lexicon query expansion.

6. **In-Memory Vector Store Persistence:**  
   Vector indexes are kept in-memory for low-latency queries and persisted locally to `DATA_DIR/index.pkl`. This setup is optimized for single-node deployments; enterprise multi-node clusters would require a dedicated vector database (e.g., Qdrant or pgvector).

7. **Semantic Search Pagination:**  
   Semantic search returns the top 20 ranked chunks without cursor-based pagination, unlike the keyword search which supports full Laravel database pagination.

8. **Security Boundary & Environment Constraints:**  
   The platform implements defense-in-depth within application code (CSRF, policy authorization, input sanitization, rate limiting, token headers, magic byte verification). However, production deployment hardening—such as hardware Multi-Factor Authentication (MFA), TLS termination/HTTPS configuration, and antivirus ICAP scanning—is external to this repository.

9. **No Super Admin Break-Glass Access:**  
   In this version, Super Admins are strictly prohibited from viewing organization documents and chat contents. An emergency break-glass audit-override mechanism is reserved for future enterprise versions.

10. **Frontend CDN Dependencies:**  
    Bootstrap 5.3, Bootstrap Icons, and the Google Fonts (`Inter` and `Noto Sans Bengali`) are loaded from public CDNs for streamlined zero-build-step local development.
