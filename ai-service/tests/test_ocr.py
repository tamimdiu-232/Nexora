"""Test OCR status reporting and Tesseract integration."""
import pytest
from app.ocr import get_installed_tesseract_languages, ocr_status, resolve_ocr_languages


def test_ocr_status_reporting():
    status = ocr_status()
    assert "tesseract_available" in status
    assert "pdftoppm_available" in status
    assert "bangla_ready" in status
    assert isinstance(status["available_languages"], list)


def test_resolve_ocr_languages():
    langs, bangla_ready, warning = resolve_ocr_languages("auto")
    assert "eng" in langs
    # In this environment, 'ben' is installed
    if bangla_ready:
        assert "ben" in langs
        assert warning is None
