"""FastAPI Application for Nexora AI Document Microservice."""
import logging
import os
import sys
from contextlib import asynccontextmanager
from typing import Any, Dict, List, Optional
from fastapi import Depends, FastAPI, File, Form, HTTPException, UploadFile, status
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from app.auth import verify_service_token
from app.chunking import chunk_document
from app.classify import classify_document
from app.config import settings
from app.extraction import ExtractionError, extract_pdf_pages
from app.info import extract_document_info
from app.llm import call_llm_qa, llm_status
from app.ocr import ocr_status
from app.rag import extract_grounded_answer
from app.summarize import summarize_document
from app.vector_store import global_vector_store

# Configure structured logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger("nexora.ai")


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: Load vector index cache if configured
    if settings.DATA_DIR:
        index_file = os.path.join(settings.DATA_DIR, "index.pkl")
        if global_vector_store.load(index_file):
            logger.info(f"Loaded existing vector index from {index_file}")
        else:
            logger.info("Starting with fresh in-memory vector store")
    yield
    # Shutdown: Persist vector index if configured
    if settings.DATA_DIR:
        index_file = os.path.join(settings.DATA_DIR, "index.pkl")
        global_vector_store.save(index_file)
        logger.info(f"Persisted vector index to {index_file}")


app = FastAPI(
    title="Nexora AI Document Microservice",
    version="1.0.0",
    description="Secure, isolated AI service for document OCR, classification, extraction, semantic search, and grounded Q&A.",
    lifespan=lifespan
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# --- Request & Response Models ---

class SearchRequest(BaseModel):
    query: str = Field(..., min_length=1, max_length=1000)
    authorized_document_ids: List[str] = Field(default_factory=list)
    top_k: int = Field(default=20, ge=1, le=100)


class QARequest(BaseModel):
    question: str = Field(..., min_length=2, max_length=1000)
    authorized_document_ids: List[str] = Field(default_factory=list)
    top_k: int = Field(default=8, ge=1, le=20)


class DocumentScopeRequest(BaseModel):
    document_id: str = Field(..., min_length=1, max_length=100)
    authorized_document_ids: List[str] = Field(..., min_length=1)
    top_k: int = Field(default=5, ge=1, le=20)


# --- Endpoints ---

@app.get("/health")
@app.get("/v1/health")
async def health_check():
    """Unauthenticated health and capability check endpoint."""
    return {
        "status": "healthy",
        "service": "nexora-ai",
        "version": "1.0.0",
        "ocr": ocr_status(),
        "llm": llm_status(),
        "vector_store": global_vector_store.index_health()
    }


@app.post("/v1/documents/process", dependencies=[Depends(verify_service_token)])
async def process_document(
    document_id: str = Form(..., min_length=1, max_length=100),
    title: str = Form(default=""),
    file: UploadFile = File(...)
):
    """
    Extract text, run OCR if needed, classify, extract entities, chunk, and index into vector store.
    """
    try:
        content = await file.read()
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={"code": "read_error", "message": f"Could not read uploaded file: {str(e)}"}
        )

    # 1. Extract text and pages
    try:
        pages = extract_pdf_pages(content)
    except ExtractionError as e:
        status_code = status.HTTP_413_REQUEST_ENTITY_TOO_LARGE if e.code == "file_too_large" else status.HTTP_422_UNPROCESSABLE_ENTITY
        raise HTTPException(
            status_code=status_code,
            detail={"code": e.code, "message": e.message}
        )
    except Exception as e:
        logger.error(f"Extraction unexpected error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            detail={"code": "corrupt_pdf", "message": f"PDF processing failed: {str(e)}"}
        )

    # 2. Document Classification
    classification = classify_document(pages)

    # 3. Information and Entity Extraction
    extracted_info = extract_document_info(pages, fallback_title=title)
    final_title = extracted_info.get("title") or title or "Untitled Document"

    # 4. Page-aware Chunking
    chunks = chunk_document(document_id=document_id, pages=pages)

    # 5. Atomically index in Vector Store
    global_vector_store.add_or_replace_document(
        document_id=document_id,
        title=final_title,
        category=classification["category"],
        keywords=extracted_info.get("keywords", []),
        chunks=chunks
    )

    # Format response
    response_pages = [
        {"page_number": p.number, "text": p.text, "extraction_method": p.method}
        for p in pages
    ]
    response_chunks = [
        {
            "chunk_id": c.chunk_id,
            "page_number": c.page_number,
            "chunk_index": c.chunk_index,
            "char_count": c.char_count
        }
        for c in chunks
    ]

    return {
        "document_id": document_id,
        "page_count": len(pages),
        "pages": response_pages,
        "chunks": response_chunks,
        "classification": classification,
        "extracted_info": extracted_info,
        "entities": extracted_info.get("entities", [])
    }


@app.delete("/v1/documents/{document_id}", dependencies=[Depends(verify_service_token)])
async def delete_document(document_id: str):
    """Delete document and its chunks from vector index."""
    deleted = global_vector_store.delete_document(document_id)
    return {"deleted": deleted, "document_id": document_id}


@app.post("/v1/search", dependencies=[Depends(verify_service_token)])
async def semantic_search(request: SearchRequest):
    """Semantic vector search scoped strictly to authorized document UUIDs."""
    results = global_vector_store.search(
        query=request.query,
        authorized_document_ids=request.authorized_document_ids,
        top_k=request.top_k
    )
    return {"results": results}


@app.post("/v1/qa", dependencies=[Depends(verify_service_token)])
async def ask_question(request: QARequest):
    """Grounded question answering with citations and conflict detection."""
    if settings.LLM_PROVIDER.lower() in ("openai", "anthropic"):
        result = await call_llm_qa(
            question=request.question,
            authorized_document_ids=request.authorized_document_ids,
            top_k=request.top_k
        )
    else:
        result = extract_grounded_answer(
            question=request.question,
            authorized_document_ids=request.authorized_document_ids,
            top_k=request.top_k
        )
    return result


@app.post("/v1/summarize", dependencies=[Depends(verify_service_token)])
async def generate_summary(request: DocumentScopeRequest):
    """Generate extractive hierarchical summary for an authorized document."""
    # Enforce authorization scope: requested document must be in authorized list
    if request.document_id not in request.authorized_document_ids:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Document ID is not within authorized document list"
        )

    meta = global_vector_store.documents.get(request.document_id)
    if not meta:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found in vector index"
        )

    # Reconstruct pages from stored chunks
    doc_chunks = [
        c for c in global_vector_store.chunks.values()
        if c.document_id == request.document_id
    ]

    # Group chunk text by page
    page_texts: Dict[int, List[str]] = {}
    for c in sorted(doc_chunks, key=lambda x: (x.page_number, x.chunk_index)):
        if c.page_number not in page_texts:
            page_texts[c.page_number] = []
        page_texts[c.page_number].append(c.text)

    from app.extraction import ExtractedPage
    pages = [
        ExtractedPage(number=p_num, text="\n".join(texts), method="text")
        for p_num, texts in sorted(page_texts.items())
    ]

    summary_result = summarize_document(pages)
    return {
        "document_id": request.document_id,
        "summary": summary_result
    }


@app.post("/v1/related", dependencies=[Depends(verify_service_token)])
async def related_documents(request: DocumentScopeRequest):
    """Find related documents within authorized scope using centroid similarity."""
    if request.document_id not in request.authorized_document_ids:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Target document is not in authorized document list"
        )

    if request.document_id not in global_vector_store.documents:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Target document not found in index"
        )

    related = global_vector_store.find_related_documents(
        document_id=request.document_id,
        authorized_document_ids=request.authorized_document_ids,
        top_k=request.top_k
    )
    return {"document_id": request.document_id, "related_documents": related}


@app.get("/v1/index/health", dependencies=[Depends(verify_service_token)])
async def index_health():
    """Vector index integrity and diagnostics."""
    return global_vector_store.index_health()
