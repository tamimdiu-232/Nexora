import io, os
os.environ["SERVICE_TOKEN"] = "test-token"
os.environ["DATA_DIR"] = ""  # no persistence in tests
import pytest
from fastapi.testclient import TestClient
from reportlab.pdfgen import canvas
from app.main import app, store

H = {"X-Service-Token": "test-token"}


def make_pdf(pages: list[str]) -> bytes:
    buf = io.BytesIO(); c = canvas.Canvas(buf)
    for t in pages:
        y = 800
        for line in t.split("\n"):
            c.drawString(50, y, line); y -= 18
        c.showPage()
    c.save(); return buf.getvalue()


@pytest.fixture()
def client():
    for d in list(store.chunks):
        store.delete(d)
    return TestClient(app)


def upload(client, doc_id, pages, title="T"):
    return client.post("/v1/documents/process", headers=H, data={"document_id": doc_id, "title": title},
                       files={"file": ("a.pdf", make_pdf(pages), "application/pdf")})
