"""Contract test verifying exact request and response schemas between Laravel and FastAPI."""
import io
import pytest
from httpx import ASGITransport, AsyncClient
from app.config import settings
from app.main import app


@pytest.mark.asyncio
async def test_laravel_contract_process_and_qa_lifecycle(make_simple_pdf):
    transport = ASGITransport(app=app)
    headers = {"X-Service-Token": settings.SERVICE_TOKEN}

    pdf_bytes = make_simple_pdf([
        "Government of Bangladesh. Ministry of Infrastructure.\nProject: Demo Riverside Connector Project.\nTotal budget is BDT 50,000,000.",
        "Deadline for submission of final accounts is 30 June 2027."
    ])

    doc_uuid = "contract-test-uuid-001"

    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        # 1. Replay Laravel DocumentProcessor multipart upload payload
        files = {
            "file": ("demo_doc.pdf", pdf_bytes, "application/pdf")
        }
        data = {
            "document_id": doc_uuid,
            "title": "Riverside Progress Report"
        }

        process_res = await ac.post("/v1/documents/process", headers=headers, data=data, files=files)
        assert process_res.status_code == 200, f"Process failed: {process_res.text}"
        process_data = process_res.json()

        # Contract assertions for /v1/documents/process
        assert process_data["document_id"] == doc_uuid
        assert process_data["page_count"] == 2
        assert len(process_data["pages"]) == 2
        assert len(process_data["chunks"]) >= 2
        assert "classification" in process_data
        assert "extracted_info" in process_data
        assert process_data["extracted_info"]["project_name"] == "Demo Riverside Connector Project"

        indexed_chunk_ids = {c["chunk_id"] for c in process_data["chunks"]}

        # 2. Replay Laravel ChatController /v1/qa payload
        qa_payload = {
            "question": "এই project-এর budget কত এবং deadline কবে?",
            "authorized_document_ids": [doc_uuid],
            "top_k": 8
        }

        qa_res = await ac.post("/v1/qa", headers=headers, json=qa_payload)
        assert qa_res.status_code == 200
        qa_data = qa_res.json()

        # Contract assertions for /v1/qa
        assert qa_data["status"] == "answered"
        assert len(qa_data["citations"]) >= 1

        # CRITICAL CONTRACT REQUIREMENT: Every citation chunk_id in QA response must exist in indexed_chunk_ids
        for citation in qa_data["citations"]:
            assert citation["chunk_id"] in indexed_chunk_ids, (
                f"Citation chunk_id {citation['chunk_id']} was not returned in /process chunks"
            )
            assert citation["document_id"] == doc_uuid
            assert citation["page_number"] in [1, 2]
            assert "snippet" in citation

        # 3. Replay Laravel Semantic Search
        search_payload = {
            "query": "riverside connector budget",
            "authorized_document_ids": [doc_uuid],
            "top_k": 5
        }
        search_res = await ac.post("/v1/search", headers=headers, json=search_payload)
        assert search_res.status_code == 200
        search_data = search_res.json()
        assert len(search_data["results"]) >= 1
        assert search_data["results"][0]["document_id"] == doc_uuid

        # 4. Replay Summarize & Related
        sum_res = await ac.post("/v1/summarize", headers=headers, json={
            "document_id": doc_uuid,
            "authorized_document_ids": [doc_uuid]
        })
        assert sum_res.status_code == 200
        assert "summary" in sum_res.json()

        # 5. Clean up delete
        del_res = await ac.delete(f"/v1/documents/{doc_uuid}", headers=headers)
        assert del_res.status_code == 200
        assert del_res.json()["deleted"] is True
