"""Test PDF validation and text extraction pipeline."""
import pytest
from app.extraction import ExtractionError, extract_pdf_pages, validate_pdf_bytes


def test_validate_non_pdf_fails():
    with pytest.raises(ExtractionError) as exc_info:
        validate_pdf_bytes(b"This is a plain text file, not a pdf.")
    assert exc_info.value.code == "unsupported_file"


def test_validate_corrupt_pdf_fails():
    corrupt_bytes = b"%PDF-1.4\nSome random corrupted bytes with no xref or trailer."
    with pytest.raises(ExtractionError) as exc_info:
        extract_pdf_pages(corrupt_bytes)
    assert exc_info.value.code == "corrupt_pdf"


def test_file_too_large_fails(monkeypatch):
    from app.config import settings
    monkeypatch.setattr(settings, "MAX_UPLOAD_SIZE", 50)
    oversized = b"%PDF" + b"A" * 100
    with pytest.raises(ExtractionError) as exc_info:
        validate_pdf_bytes(oversized)
    assert exc_info.value.code == "file_too_large"


def test_valid_pdf_native_extraction(make_simple_pdf):
    pdf_bytes = make_simple_pdf([
        "Page 1: Government Project Progress Report. Total estimated budget is BDT 50,000,000.",
        "Page 2: Implementation timeline and completion deadline is 30 June 2027."
    ])
    pages = extract_pdf_pages(pdf_bytes)
    assert len(pages) == 2
    assert pages[0].number == 1
    assert "BDT 50,000,000" in pages[0].text
    assert pages[0].method == "text"
    assert pages[1].number == 2
    assert "30 June 2027" in pages[1].text
    assert pages[1].method == "text"
