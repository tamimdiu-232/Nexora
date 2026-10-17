"""Page-aware PDF text extraction with OCR fallback for scanned pages."""
from .config import settings
import io, shutil, subprocess, tempfile, os
from dataclasses import dataclass
from pypdf import PdfReader

MIN_CHARS = 25


class ExtractionError(Exception):
    def __init__(self, code: str, message: str):
        self.code, self.message = code, message
        super().__init__(message)


@dataclass
class Page:
    number: int
    text: str
    method: str  # "text" | "ocr" | "empty"


def _ocr_page(pdf_path: str, page_no: int) -> str:
    if not (shutil.which("tesseract") and shutil.which("pdftoppm")):
        return ""
    with tempfile.TemporaryDirectory() as d:
        prefix = os.path.join(d, "p")
        subprocess.run(["pdftoppm", "-r", "200", "-f", str(page_no), "-l", str(page_no), "-png", pdf_path, prefix],
                       check=True, capture_output=True, timeout=120)
        imgs = sorted(f for f in os.listdir(d) if f.endswith(".png"))
        if not imgs:
            return ""
        r = subprocess.run(["tesseract", os.path.join(d, imgs[0]), "stdout", "-l", settings.ocr_langs],
                           capture_output=True, timeout=180)
        return r.stdout.decode("utf-8", "ignore")


def clean(text: str) -> str:
    lines = [" ".join(l.split()) for l in text.replace("\x00", "").splitlines()]
    return "\n".join(l for l in lines if l).strip()


def extract_pdf(data: bytes) -> list[Page]:
    if not data.startswith(b"%PDF"):
        raise ExtractionError("unsupported_file", "File is not a PDF")
    try:
        reader = PdfReader(io.BytesIO(data))
        encrypted = reader.is_encrypted
        n = 0 if encrypted else len(reader.pages)
    except Exception as e:
        raise ExtractionError("corrupt_pdf", f"Cannot read PDF: {type(e).__name__}")
    if encrypted:
        raise ExtractionError("password_protected", "PDF is password protected")
    if n == 0:
        raise ExtractionError("empty_pdf", "PDF has no pages")
    pages: list[Page] = []
    tmp_path = None
    for i, p in enumerate(reader.pages, start=1):
        try:
            txt = clean(p.extract_text() or "")
        except Exception:
            txt = ""
        method = "text"
        if len(txt) < MIN_CHARS:
            if tmp_path is None:
                f = tempfile.NamedTemporaryFile(suffix=".pdf", delete=False)
                f.write(data); f.close(); tmp_path = f.name
            try:
                txt = clean(_ocr_page(tmp_path, i))
                method = "ocr" if txt else "empty"
            except Exception:
                txt, method = "", "empty"
        pages.append(Page(i, txt, method))
    if tmp_path:
        os.unlink(tmp_path)
    if not any(p.text for p in pages):
        raise ExtractionError("no_text", "No text could be extracted (OCR unavailable or failed)")
    return pages
