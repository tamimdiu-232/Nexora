import logging
from fastapi import Depends, FastAPI, File, Form, HTTPException, UploadFile
from pydantic import BaseModel, Field
from .chunking import chunk_pages
from .classify import classify
from .info import extract_info
from .lexicon import translate
from .summarize import summarize
from .config import settings
from .embeddings import get_embedder
from .extraction import ExtractionError, extract_pdf
from . import llm
from .llm_extract import enhance
from .rag import generate_answer, validate_citations
from .security import require_service_token
from .store import VectorStore

logging.basicConfig(level=logging.INFO)
log = logging.getLogger("govdoc.ai")
app = FastAPI(title="Nexora AI Service", version="0.1.0")
store = VectorStore(get_embedder())
auth = [Depends(require_service_token)]


class QueryIn(BaseModel):
    question: str = Field(min_length=2, max_length=1000)
    authorized_document_ids: list[str] = Field(max_length=5000)
    top_k: int = Field(default=8, ge=1, le=20)


@app.get("/health")
def health():
    provider, warning = llm.get_provider()
    return {"status": "ok", "embedder": store.embedder.name, "llm": settings.llm_provider,
            "llm_ready": provider is not None or settings.llm_provider in ("", "extractive"), "llm_warning": warning,
            "extraction_mode": settings.extraction_mode, "documents_indexed": len(store.chunks)}


@app.get("/v1/index/health", dependencies=auth)
def index_health():
    empty = [d for d, c in store.chunks.items() if not c]
    mismatched = [d for d, c in store.chunks.items() if c and len(store.vecs[d]) != len(c)]
    return {"documents": len(store.chunks), "chunks": sum(len(c) for c in store.chunks.values()),
            "documents_without_chunks": empty, "documents_with_vector_mismatch": mismatched,
            "ok": not empty and not mismatched}


@app.post("/v1/documents/process", dependencies=auth)
async def process(document_id: str = Form(..., max_length=64), title: str = Form("", max_length=300),
                  file: UploadFile = File(...)):
    limit = settings.max_upload_mb * 1024 * 1024
    data = await file.read(limit + 1)
    if len(data) > limit:
        raise HTTPException(413, "File too large")
    try:
        pages = extract_pdf(data)
    except ExtractionError as e:
        log.warning("extraction failed doc=%s code=%s", document_id, e.code)
        raise HTTPException(422, {"code": e.code, "message": e.message})
    chunks = chunk_pages(document_id, pages, settings.chunk_size, settings.chunk_overlap)
    full = "\n".join(p.text for p in pages)
    cls, info = classify(full), extract_info(pages)
    if settings.extraction_mode == "llm":
        info = enhance(pages, info, llm.get_provider()[0])
    store.index(document_id, chunks, {"title": title or info["title"], "category": cls["category"],
                                      "keywords": info["keywords"]}, pages)
    return {"document_id": document_id,
            "pages": [{"page": p.number, "method": p.method, "chars": len(p.text), "text": p.text} for p in pages],
            "chunks": [{"chunk_id": c.chunk_id, "page": c.page, "index": c.index, "chars": len(c.text)} for c in chunks],
            "classification": cls, "info": info}


@app.delete("/v1/documents/{document_id}", dependencies=auth)
def remove(document_id: str):
    store.delete(document_id)
    return {"deleted": document_id}


@app.post("/v1/search", dependencies=auth)
def search(q: QueryIn):
    hits = store.search(translate(q.question), q.authorized_document_ids, q.top_k)
    return {"results": [{"document_id": c.document_id, "chunk_id": c.chunk_id, "page": c.page,
                         "title": m.get("title"), "snippet": c.text[:300], "score": round(s, 3)} for s, c, m in hits]}


@app.post("/v1/qa", dependencies=auth)
def qa(q: QueryIn):
    ids = set(q.authorized_document_ids)
    eq = translate(q.question)  # Bangla glossary terms -> English, for retrieval only
    res = generate_answer(q.question, store.search(eq, list(ids), q.top_k), eq)
    res["citations"] = validate_citations(store, ids, res["citations"])
    if not res["citations"] and res["status"] != "insufficient_evidence":
        res.update(status="insufficient_evidence", answer="No valid, authorized source could be confirmed.")
    return res


class DocIn(BaseModel):
    document_id: str = Field(min_length=1, max_length=64)
    authorized_document_ids: list[str] = Field(max_length=5000)
    top_k: int = Field(default=5, ge=1, le=20)


def _require(d: DocIn):
    if d.document_id not in d.authorized_document_ids:
        raise HTTPException(403, "Document not in authorized scope")
    if d.document_id not in store.chunks:
        raise HTTPException(404, "Document not indexed")


@app.post("/v1/summarize", dependencies=auth)
def summarize_doc(d: DocIn):
    _require(d)
    pages = store.pages[d.document_id]
    return summarize(pages, extract_info(pages))


@app.post("/v1/related", dependencies=auth)
def related(d: DocIn):
    _require(d)
    return {"results": store.related(d.document_id, d.authorized_document_ids, d.top_k)}
