"""Tesseract OCR and Poppler pdftoppm wrapper with parallel page execution."""
import os
import shutil
import subprocess
import tempfile
from concurrent.futures import ThreadPoolExecutor
from typing import Dict, List, Optional, Tuple
from app.config import settings
from app.textutil import clean_text

def get_installed_tesseract_languages() -> List[str]:
    """Query tesseract --list-langs to get available language models."""
    tess_bin = shutil.which("tesseract")
    if not tess_bin:
        return []
    try:
        proc = subprocess.run(
            [tess_bin, "--list-langs"],
            capture_output=True,
            text=True,
            check=True
        )
        lines = [line.strip() for line in proc.stdout.splitlines() if line.strip()]
        # Skip header line like 'List of available languages in...'
        langs = [l for l in lines if not l.startswith("List of") and not l.startswith("Found")]
        return langs
    except Exception:
        return []

def resolve_ocr_languages(configured: str) -> Tuple[str, bool, Optional[str]]:
    """
    Resolve OCR language string based on available models.
    Returns: (languages_to_use, bangla_ready, warning_message)
    """
    installed = get_installed_tesseract_languages()
    has_eng = "eng" in installed
    has_ben = "ben" in installed or "script/Bengali" in installed

    warning = None
    if not has_ben:
        warning = "Bangla language model (ben) not found in Tesseract. Run: brew install tesseract-lang"

    if configured.lower() == "auto":
        langs = []
        if has_eng:
            langs.append("eng")
        if has_ben:
            langs.append("ben")
        if not langs:
            langs = ["eng"]
        return "+".join(langs), has_ben, warning
    else:
        return configured, has_ben, warning

def ocr_status() -> Dict:
    """Return health status dictionary for OCR tools."""
    tess_path = shutil.which("tesseract")
    ppm_path = shutil.which("pdftoppm")
    installed_langs = get_installed_tesseract_languages()
    resolved_langs, bangla_ready, warning = resolve_ocr_languages(settings.OCR_LANGS)

    return {
        "tesseract_available": tess_path is not None,
        "pdftoppm_available": ppm_path is not None,
        "tesseract_path": tess_path,
        "pdftoppm_path": ppm_path,
        "configured_langs": settings.OCR_LANGS,
        "active_langs": resolved_langs,
        "available_languages": installed_langs,
        "bangla_ready": bangla_ready,
        "warning": warning
    }

def render_page_to_png(pdf_path: str, page_number: int, dpi: int = 300, output_prefix: str = "page") -> Optional[str]:
    """
    Render a specific PDF page to a PNG image file at 300 DPI using pdftoppm.
    Page numbers for pdftoppm are 1-indexed.
    """
    ppm_bin = shutil.which("pdftoppm")
    if not ppm_bin:
        raise RuntimeError("pdftoppm not found. Please install poppler (brew install poppler).")

    cmd = [
        ppm_bin,
        "-png",
        "-r", str(dpi),
        "-f", str(page_number),
        "-l", str(page_number),
        pdf_path,
        output_prefix
    ]

    subprocess.run(cmd, capture_output=True, check=True)
    
    # pdftoppm generates files named <prefix>-<page>.png or <prefix>-01.png
    out_dir = os.path.dirname(output_prefix) or "."
    base_prefix = os.path.basename(output_prefix)
    
    candidates = [
        f for f in os.listdir(out_dir)
        if f.startswith(base_prefix) and f.endswith(".png")
    ]
    if candidates:
        # Sort to pick matching candidate
        candidates.sort()
        return os.path.join(out_dir, candidates[0])
    return None

def ocr_image_file(image_path: str, langs: str) -> str:
    """Run Tesseract on an image file with OMP_THREAD_LIMIT=1."""
    tess_bin = shutil.which("tesseract")
    if not tess_bin:
        raise RuntimeError("tesseract not found. Please install tesseract (brew install tesseract).")

    env = os.environ.copy()
    env["OMP_THREAD_LIMIT"] = "1"

    cmd = [tess_bin, image_path, "stdout", "-l", langs, "--psm", "1"]
    proc = subprocess.run(cmd, capture_output=True, text=True, env=env)
    
    # If PSM 1 (auto page segmentation with OSD) fails or gives empty, retry with standard PSM 3
    if proc.returncode != 0 or not proc.stdout.strip():
        cmd[cmd.index("--psm") + 1] = "3"
        proc = subprocess.run(cmd, capture_output=True, text=True, env=env)

    return clean_text(proc.stdout)

def ocr_single_page(pdf_path: str, page_number: int, langs: str) -> str:
    """Render a single page and run OCR in an isolated temp directory."""
    with tempfile.TemporaryDirectory() as tmp_dir:
        prefix = os.path.join(tmp_dir, f"ocr_p{page_number}")
        img_path = render_page_to_png(pdf_path, page_number, dpi=300, output_prefix=prefix)
        if not img_path or not os.path.exists(img_path):
            return ""
        return ocr_image_file(img_path, langs)

def ocr_pages_parallel(pdf_path: str, pages_to_ocr: List[int], langs: str, max_workers: int = 3) -> Dict[int, str]:
    """
    Process multiple PDF pages needing OCR in parallel while preserving page index mapping.
    """
    if not pages_to_ocr:
        return {}

    results: Dict[int, str] = {}
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_page = {
            executor.submit(ocr_single_page, pdf_path, p, langs): p
            for p in pages_to_ocr
        }
        for future in future_to_page:
            page_num = future_to_page[future]
            try:
                text = future.result()
                results[page_num] = text
            except Exception as e:
                results[page_num] = ""

    return results
