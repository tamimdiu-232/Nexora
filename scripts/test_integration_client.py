"""End-to-End Integration Test for Nexora AI Microservice using TestClient."""
import os
import sys

# Ensure ai-service root is in sys.path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
AI_DIR = os.path.abspath(os.path.join(SCRIPT_DIR, "..", "ai-service"))
sys.path.insert(0, AI_DIR)

from fastapi.testclient import TestClient
from app.main import app
from app.config import settings

def run_integration_test():
    print("================================================================================")
    print(" Nexora — AI Microservice End-to-End Pipeline Integration Test")
    print("================================================================================")
    
    client = TestClient(app)
    token = settings.SERVICE_TOKEN
    headers = {"X-Service-Token": token}
    
    # 1. Health check
    print("[1/6] Testing /v1/health check...")
    resp = client.get("/v1/health")
    assert resp.status_code == 200, f"Health check failed: {resp.text}"
    health_data = resp.json()
    assert health_data["status"] == "healthy"
    print(f"  ✓ Health check passed. Tesseract: {health_data['ocr']['tesseract_available']}, Bangla OCR: {health_data['ocr']['bangla_ready']}, LLM: {health_data['llm']['provider']}")
    
    # 2. Ingest & Process Document
    sample_doc_path = os.path.abspath(os.path.join(SCRIPT_DIR, "..", "sample-documents", "demo_riverside_connector_report.pdf"))
    assert os.path.exists(sample_doc_path), f"Sample document not found at {sample_doc_path}"
    
    doc_uuid = "integration-test-doc-001"
    print(f"[2/6] Ingesting document: {os.path.basename(sample_doc_path)}...")
    with open(sample_doc_path, "rb") as f:
        resp = client.post(
            "/v1/documents/process",
            headers=headers,
            data={"document_id": doc_uuid, "title": "Riverside Connector Feasibility Study"},
            files={"file": ("demo_riverside_connector_report.pdf", f, "application/pdf")}
        )
    assert resp.status_code == 200, f"Process failed: {resp.text}"
    proc_data = resp.json()
    assert proc_data["document_id"] == doc_uuid
    assert proc_data["page_count"] > 0
    assert len(proc_data["chunks"]) > 0
    print(f"  ✓ Document processed successfully. Category: {proc_data['classification']['category']} (conf: {proc_data['classification']['confidence']:.2f}), Pages: {proc_data['page_count']}, Chunks: {len(proc_data['chunks'])}")
    
    # 3. Semantic Search
    print("[3/6] Testing Semantic Search via /v1/search...")
    resp = client.post(
        "/v1/search",
        headers=headers,
        json={"query": "estimated project budget cost", "authorized_document_ids": [doc_uuid], "top_k": 5}
    )
    assert resp.status_code == 200, f"Search failed: {resp.text}"
    search_data = resp.json()
    assert "results" in search_data
    assert len(search_data["results"]) > 0
    print(f"  ✓ Semantic search returned {len(search_data['results'])} ranked chunks. Top score: {search_data['results'][0]['score']:.3f}")
    
    # 4. Grounded Q&A with Citations
    print("[4/6] Testing Grounded Q&A via /v1/qa...")
    resp = client.post(
        "/v1/qa",
        headers=headers,
        json={"question": "What is the total project budget?", "authorized_document_ids": [doc_uuid], "top_k": 5}
    )
    assert resp.status_code == 200, f"Q&A failed: {resp.text}"
    qa_data = resp.json()
    assert qa_data["status"] == "answered"
    assert len(qa_data["citations"]) > 0
    print(f"  ✓ Q&A Answer: {qa_data['answer']}")
    print(f"  ✓ Source citations verified: {len(qa_data['citations'])} citation(s), page {qa_data['citations'][0]['page_number']}")
    
    # 5. Summarization & Related Documents
    print("[5/6] Testing Summarization & Related Documents...")
    resp = client.post(
        "/v1/summarize",
        headers=headers,
        json={"document_id": doc_uuid, "authorized_document_ids": [doc_uuid]}
    )
    assert resp.status_code == 200, f"Summarize failed: {resp.text}"
    sum_data = resp.json()
    assert "summary" in sum_data
    summary_obj = sum_data["summary"]
    assert "short" in summary_obj and "detailed" in summary_obj
    print(f"  ✓ Document summary generated (detailed: {len(summary_obj['detailed'])} chars, {len(summary_obj['key_points'])} key points)")
    
    # 6. Cleanup
    print(f"[6/6] Testing document deletion via DELETE /v1/documents/{doc_uuid}...")
    resp = client.delete(f"/v1/documents/{doc_uuid}", headers=headers)
    assert resp.status_code == 200, f"Delete failed: {resp.text}"
    assert resp.json()["deleted"] is True
    print("  ✓ Document vector chunks removed from store.")
    
    print("================================================================================")
    print(" All End-to-End AI Integration Verification Steps Passed!")
    print("================================================================================")

if __name__ == "__main__":
    run_integration_test()
