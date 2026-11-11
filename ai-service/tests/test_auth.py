"""Test service-token authentication and constant-time check."""
import pytest
from httpx import ASGITransport, AsyncClient
from app.main import app


@pytest.mark.asyncio
async def test_health_no_auth_required():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        res = await ac.get("/health")
        assert res.status_code == 200
        data = res.json()
        assert data["status"] == "healthy"
        assert "ocr" in data
        assert "vector_store" in data


@pytest.mark.asyncio
async def test_protected_endpoint_without_token():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        res = await ac.post("/v1/search", json={"query": "test", "authorized_document_ids": []})
        assert res.status_code == 401
        assert "Invalid or missing service token" in res.json()["detail"]


@pytest.mark.asyncio
async def test_protected_endpoint_with_invalid_token():
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        res = await ac.post(
            "/v1/search",
            headers={"X-Service-Token": "wrong-token-abc"},
            json={"query": "test", "authorized_document_ids": []}
        )
        assert res.status_code == 401


@pytest.mark.asyncio
async def test_protected_endpoint_with_valid_token(auth_headers):
    transport = ASGITransport(app=app)
    async with AsyncClient(transport=transport, base_url="http://test") as ac:
        res = await ac.post(
            "/v1/search",
            headers=auth_headers,
            json={"query": "test", "authorized_document_ids": []}
        )
        assert res.status_code == 200
        assert res.json() == {"results": []}
