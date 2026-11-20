"""Pytest fixtures and test utilities for Nexora AI Service."""
import io
import os
import sys
import pytest
from reportlab.lib.pagesizes import letter
from reportlab.pdfgen import canvas

# Ensure app is on path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from app.config import settings
from app.vector_store import global_vector_store


@pytest.fixture(autouse=True)
def reset_vector_store():
    """Reset global vector store before each test."""
    global_vector_store.chunks.clear()
    global_vector_store.documents.clear()
    yield
    global_vector_store.chunks.clear()
    global_vector_store.documents.clear()


@pytest.fixture
def auth_headers():
    return {"X-Service-Token": settings.SERVICE_TOKEN}


@pytest.fixture
def make_simple_pdf():
    """Helper fixture to create a valid synthetic PDF with custom text on each page."""
    def _create_pdf(pages_text: list[str]) -> bytes:
        buf = io.BytesIO()
        c = canvas.Canvas(buf, pagesize=letter)
        for idx, text in enumerate(pages_text):
            lines = text.split("\n")
            y = 750
            for line in lines:
                c.drawString(72, y, line)
                y -= 20
            c.showPage()
        c.save()
        buf.seek(0)
        return buf.getvalue()
    return _create_pdf
