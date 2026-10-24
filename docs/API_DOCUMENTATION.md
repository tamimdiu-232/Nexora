# Nexora — AI Service API Documentation

## 1. Authentication
All endpoints except `GET /health` require the HTTP header:
```http
X-Service-Token: <NEXORA_AI_SERVICE_TOKEN>
```
Authentication failures return HTTP 401 Unauthorized (`{"detail": "Invalid or missing service token"}`). Constant-time comparison is enforced.

---

## 2. Endpoints Contract

### 2.1 Health & Diagnostics
- **Method & Path:** `GET /health`
- **Auth:** None
- **Response (200 OK):**
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "ocr": {
    "tesseract_available": true,
    "pdftoppm_available": true,
    "languages": ["eng", "ben"],
    "bangla_ready": true,
    "warning": null
  },
  "llm": {
    "provider": "extractive",
    "llm_ready": false,
    "llm_warning": "Using extractive engine (LLM not configured)"
  },
  "vector_store": {
    "indexed_documents": 12,
    "total_chunks": 84
  }
}
```

### 2.2 Process Document
- **Method & Path:** `POST /v1/documents/process`
- **Content-Type:** `multipart/form-data`
- **Fields:**
  - `document_id`: string (UUID)
  - `title`: string
  - `file`: binary (PDF file)
- **Response (200 OK):**
```json
{
  "document_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "page_count": 2,
  "pages": [
    { "page_number": 1, "text": "...", "extraction_method": "text" },
    { "page_number": 2, "text": "...", "extraction_method": "ocr" }
  ],
  "chunks": [
    { "chunk_id": "9b1deb4d...:p1:c0", "page_number": 1, "chunk_index": 0, "char_count": 420 }
  ],
  "classification": {
    "category": "Project Report",
    "confidence": 0.85,
    "scores": { "Project Report": 12.0, "Other": 1.0 }
  },
  "extracted_info": {
    "title": "Riverside Connector Project Report",
    "issuing_organization": "Department of Roads",
    "reference_number": "PD/DRCP/2026/001",
    "document_date": "2026-03-15",
    "project_name": "Demo Riverside Connector Project",
    "budget": "50,000,000",
    "deadline": "2027-06-30",
    "keywords": ["riverside", "project", "budget", "roads"]
  },
  "entities": [
    { "type": "budget", "value": "50,000,000", "page_number": 1, "evidence": "Total project budget is BDT 50,000,000." }
  ]
}
```

### 2.3 Delete Document from Vector Index
- **Method & Path:** `DELETE /v1/documents/{document_id}`
- **Response (200 OK):** `{"deleted": true, "document_id": "..."}`

### 2.4 Semantic Search
- **Method & Path:** `POST /v1/search`
- **Body:**
```json
{
  "query": "budget deadline riverside",
  "authorized_document_ids": ["uuid-1", "uuid-2"],
  "top_k": 20
}
```
- **Response (200 OK):**
```json
{
  "results": [
    {
      "document_id": "uuid-1",
      "chunk_id": "uuid-1:p1:c0",
      "page_number": 1,
      "score": 0.82,
      "snippet": "..."
    }
  ]
}
```

### 2.5 Grounded Q&A (RAG)
- **Method & Path:** `POST /v1/qa`
- **Body:**
```json
{
  "question": "এই project-এর budget কত এবং deadline কবে?",
  "authorized_document_ids": ["uuid-1", "uuid-2"],
  "top_k": 8
}
```
- **Response (200 OK):**
```json
{
  "answer": "The project budget is BDT 50,000,000 [1] and the deadline is 30 June 2027 [2].",
  "status": "answered",
  "conflict_detected": false,
  "conflict_details": null,
  "citations": [
    {
      "source_num": 1,
      "document_id": "uuid-1",
      "chunk_id": "uuid-1:p1:c0",
      "page_number": 1,
      "snippet": "Total project budget is BDT 50,000,000.",
      "score": 0.91
    }
  ],
  "verified": false,
  "generated_by": "extractive",
  "warning": null
}
```

### 2.6 Summarization & Related Documents
- `POST /v1/summarize`: Body `{"document_id": "uuid-1", "authorized_document_ids": ["uuid-1"]}`
- `POST /v1/related`: Body `{"document_id": "uuid-1", "authorized_document_ids": ["uuid-1", "uuid-2"]}`
- `GET /v1/index/health`: Returns empty document count, orphaned vectors, total memory usage.
