"""PDF document text extraction pipeline combining pypdf and parallel OCR."""
import io
import os
import tempfile
from dataclasses import dataclass
from typing import List, Literal, Tuple
import pypdf
from pypdf.errors import PdfReadError

from app.config import settings
from app.ocr import ocr_pages_parallel, resolve_ocr_languages
from app.textutil import clean_text, is_garbled_legacy_bangla


class ExtractionError(Exception):
    """Custom extraction error carrying machine-readable error codes."""
    def __init__(self, code: str, message: str):
        super().__init__(message)
        self.code = code
        self.message = message


@dataclass
class ExtractedPage:
    number: int
    text: str
    method: Literal["text", "ocr", "empty"]


def validate_pdf_bytes(pdf_bytes: bytes) -> None:
    """Validate PDF header and size constraints."""
    if len(pdf_bytes) > settings.MAX_UPLOAD_SIZE:
        raise ExtractionError(
            code="file_too_large",
            message=f"File size {len(pdf_bytes)} bytes exceeds limit {settings.MAX_UPLOAD_SIZE} bytes"
        )
    if not pdf_bytes.startswith(b"%PDF"):
        # Check first 1024 bytes in case of leading whitespace/comments
        if b"%PDF" not in pdf_bytes[:1024]:
            raise ExtractionError(
                code="unsupported_file",
                message="File is not a valid PDF document (missing %PDF signature)"
            )


def extract_pdf_pages(pdf_bytes: bytes) -> List[ExtractedPage]:
    """
    Extract text page by page from PDF bytes.
    Automatically applies OCR when a page has < 25 native characters or contains garbled legacy Bangla.
    """
    validate_pdf_bytes(pdf_bytes)

    try:
        reader = pypdf.PdfReader(io.BytesIO(pdf_bytes))
    except PdfReadError as e:
        raise ExtractionError(code="corrupt_pdf", message=f"Corrupt or unreadable PDF: {str(e)}")
    except Exception as e:
        raise ExtractionError(code="corrupt_pdf", message=f"Failed to parse PDF: {str(e)}")

    if reader.is_encrypted:
        try:
            # Try empty password
            decrypted = reader.decrypt("")
            if decrypted == 0:
                raise ExtractionError(
                    code="password_protected",
                    message="PDF is password protected and cannot be opened"
                )
        except Exception:
            raise ExtractionError(
                code="password_protected",
                message="PDF is password protected and cannot be opened"
            )

    total_pages = len(reader.pages)
    if total_pages == 0:
        raise ExtractionError(code="empty_pdf", message="PDF contains no pages")

    extracted_pages: List[ExtractedPage] = []
    pages_needing_ocr: List[int] = []
    native_texts: dict[int, str] = {}

    for idx, page in enumerate(reader.pages):
        page_num = idx + 1
        try:
            raw_text = page.extract_text() or ""
        except Exception:
            raw_text = ""

        cleaned = clean_text(raw_text)
        native_texts[page_num] = cleaned

        # Page requires OCR if character count < 25 or if native text is garbled legacy font
        needs_ocr = len(cleaned) < 25 or is_garbled_legacy_bangla(cleaned)
        if needs_ocr:
            pages_needing_ocr.append(page_num)

    # Perform parallel OCR if any page requires it
    ocr_results: dict[int, str] = {}
    if pages_needing_ocr:
        langs, _, _ = resolve_ocr_languages(settings.OCR_LANGS)
        # Write PDF to temp file for pdftoppm rendering
        with tempfile.NamedTemporaryFile(suffix=".pdf", delete=False) as tmp_pdf:
            tmp_pdf.write(pdf_bytes)
            tmp_pdf_path = tmp_pdf.name

        try:
            ocr_results = ocr_pages_parallel(
                tmp_pdf_path,
                pages_needing_ocr,
                langs=langs,
                max_workers=settings.OCR_WORKERS
            )
        finally:
            if os.path.exists(tmp_pdf_path):
                os.remove(tmp_pdf_path)

    # Assemble final page representations in order
    for idx in range(total_pages):
        page_num = idx + 1
        if page_num in pages_needing_ocr:
            ocr_text = clean_text(ocr_results.get(page_num, ""))
            if ocr_text:
                extracted_pages.append(ExtractedPage(number=page_num, text=ocr_text, method="ocr"))
            elif native_texts[page_num]:
                # Fallback to whatever native text existed if OCR produced nothing
                extracted_pages.append(ExtractedPage(number=page_num, text=native_texts[page_num], method="text"))
            else:
                extracted_pages.append(ExtractedPage(number=page_num, text="", method="empty"))
        else:
            text = native_texts[page_num]
            if text:
                extracted_pages.append(ExtractedPage(number=page_num, text=text, method="text"))
            else:
                extracted_pages.append(ExtractedPage(number=page_num, text="", method="empty"))

    # Check if all pages are completely empty
    all_empty = all(len(p.text.strip()) == 0 for p in extracted_pages)
    if all_empty:
        raise ExtractionError(code="no_text", message="No readable text could be extracted from the PDF")

    return extracted_pages
