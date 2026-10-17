"""Contract test: replays the exact request shapes Laravel's AiClient sends and checks every response key Laravel
reads. It also proves chunk ids returned by /process match the citation chunk ids returned by /qa, because Laravel
validates citations against the chunk ids it stored from /process."""
import glob, os
import pytest
from conftest import H

SAMPLES = sorted(glob.glob(os.path.join(os.path.dirname(__file__), "..", "..", "sample-documents", "*.pdf")))
pytestmark = pytest.mark.skipif(not SAMPLES, reason="sample-documents not found")


def process_all(client):
    stored = {}
    for f in SAMPLES:
        uuid = os.path.basename(f)
        r = client.post("/v1/documents/process", headers=H, data={"document_id": uuid, "title": uuid},
                        files={"file": ("document.pdf", open(f, "rb").read(), "application/pdf")})
        if r.status_code == 200:
            stored[uuid] = r.json()
        else:
            assert uuid.startswith("09_") and r.json()["detail"]["code"] == "corrupt_pdf"   # the seeded failure case
    return stored


def test_process_response_has_every_key_laravel_reads(client):
    stored = process_all(client)
    assert len(stored) == len(SAMPLES) - 1
    for res in stored.values():
        assert all({"page", "method", "text"} <= set(p) for p in res["pages"])
        assert all({"chunk_id", "page", "index", "chars"} <= set(c) for c in res["chunks"])
        assert {"category", "confidence"} <= set(res["classification"])
        assert {"organization", "reference_number", "document_date", "project_name", "budget", "deadline", "keywords", "entities"} <= set(res["info"])
        assert all({"type", "value"} <= set(e) for e in res["info"]["entities"])


def test_qa_citation_chunk_ids_exist_in_process_response(client):
    stored = process_all(client)
    ids = list(stored)
    for q in ["এই project-এর budget কত এবং deadline কবে?", "When must progress reports be submitted?", "What is the revised budget?"]:
        r = client.post("/v1/qa", headers=H, json={"question": q, "authorized_document_ids": ids}).json()
        assert r["status"] in ("answered", "conflicting_evidence", "insufficient_evidence")
        for c in r["citations"]:
            assert {"document_id", "chunk_id", "page", "snippet", "score"} <= set(c)
            known = {(x["chunk_id"], x["page"]) for x in stored[c["document_id"]]["chunks"]}
            assert (c["chunk_id"], c["page"]) in known


def test_search_summarize_related_shapes(client):
    stored = process_all(client)
    ids = list(stored)
    s = client.post("/v1/search", headers=H, json={"question": "budget", "authorized_document_ids": ids, "top_k": 20}).json()
    assert all({"document_id", "chunk_id", "page", "snippet", "score"} <= set(x) for x in s["results"])
    doc = "01_infrastructure_project_report.pdf"
    sm = client.post("/v1/summarize", headers=H, json={"document_id": doc, "authorized_document_ids": [doc]}).json()
    assert {"short", "detailed", "key_points", "dates", "amounts", "action_items"} <= set(sm)
    rel = client.post("/v1/related", headers=H, json={"document_id": doc, "authorized_document_ids": ids, "top_k": 6}).json()
    assert all({"document_id", "title", "category", "score", "explanation", "snippet"} <= set(x) for x in rel["results"])


def test_org_a_never_sees_org_b_document_through_the_contract(client):
    stored = process_all(client)
    org_a = [d for d in stored if not d.startswith("07_")]
    r = client.post("/v1/qa", headers=H, json={"question": "road maintenance programme budget", "authorized_document_ids": org_a}).json()
    assert "8,000,000" not in str(r) and r["status"] == "insufficient_evidence"


def test_index_health(client):
    process_all(client)
    h = client.get("/v1/index/health", headers=H).json()
    assert h["ok"] is True and h["documents"] >= 1 and h["chunks"] >= h["documents"]
    assert client.get("/v1/index/health").status_code == 401
