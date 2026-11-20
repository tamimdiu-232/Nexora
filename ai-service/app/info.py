"""Deterministic bilingual information and entity extraction."""
import re
from typing import Any, Dict, List, Optional
from app.extraction import ExtractedPage
from app.textutil import stem, tokenize

BANGLA_DIGIT_MAP = {
    '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4',
    '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9'
}

GREGORIAN_BANGLA_MONTHS = {
    'জানুয়ারি': '01', 'ফেব্রুয়ারি': '02', 'মার্চ': '03', 'এপ্রিল': '04',
    'মে': '05', 'জুন': '06', 'জুলাই': '07', 'আগস্ট': '08',
    'সেপ্টেম্বর': '09', 'অক্টোবর': '10', 'নভেম্বর': '11', 'ডিসেম্বর': '12'
}

ENGLISH_MONTHS = {
    'january': '01', 'february': '02', 'march': '03', 'april': '04',
    'may': '05', 'june': '06', 'july': '07', 'august': '08',
    'september': '09', 'october': '10', 'november': '11', 'december': '12',
    'jan': '01', 'feb': '02', 'mar': '03', 'apr': '04', 'jun': '06',
    'jul': '07', 'aug': '08', 'sep': '09', 'oct': '10', 'nov': '11', 'dec': '12'
}


def normalize_bangla_digits(text: str) -> str:
    """Convert Bengali numerals (০-৯) to ASCII Arabic numerals (0-9)."""
    return "".join(BANGLA_DIGIT_MAP.get(c, c) for c in text)


def extract_amounts(pages: List[ExtractedPage]) -> List[Dict[str, Any]]:
    """
    Extract currency amounts in English and Bengali.
    Matches BDT, Tk, ৳, and suffix forms like '৫০,০০,০০০ টাকা', কোটি, লাখ/লক্ষ.
    """
    amounts: List[Dict[str, Any]] = []
    seen = set()

    # Pattern 1: Prefix with BDT, Tk, ৳, USD, $
    prefix_rx = re.compile(
        r'(?:BDT|Tk|Tk\.|৳|\$|USD)\s*([\d,]+(?:\.\d+)?)\s*(কোটি|লাখ|লক্ষ|crore|lakh|million|billion)?',
        re.IGNORECASE | re.UNICODE
    )

    # Pattern 2: Bengali number followed by টাকা or currency unit
    suffix_rx = re.compile(
        r'([\d,০-৯]+(?:\.[\d০-৯]+)?)\s*(কোটি|লাখ|লক্ষ|হাজার)?\s*(?:টাকা|টাকার|taka|bdt)',
        re.IGNORECASE | re.UNICODE
    )

    for page in pages:
        # Search prefix
        for match in prefix_rx.finditer(page.text):
            raw_val = match.group(1).replace(",", "")
            unit = match.group(2) or ""
            evidence = match.group(0).strip()
            key = (page.number, evidence)
            if key not in seen:
                seen.add(key)
                amounts.append({
                    "value": f"{evidence}",
                    "normalized": raw_val,
                    "page": page.number,
                    "evidence": evidence
                })

        # Search suffix
        for match in suffix_rx.finditer(page.text):
            raw_digits = normalize_bangla_digits(match.group(1)).replace(",", "")
            unit = match.group(2) or ""
            evidence = match.group(0).strip()
            key = (page.number, evidence)
            if key not in seen:
                seen.add(key)
                amounts.append({
                    "value": f"{evidence}",
                    "normalized": raw_digits,
                    "page": page.number,
                    "evidence": evidence
                })

    return amounts


def extract_dates(pages: List[ExtractedPage]) -> List[Dict[str, Any]]:
    """Extract dates with English/Bangla months, digits, and formats (DD/MM/YYYY, YYYY-MM-DD)."""
    dates: List[Dict[str, Any]] = []
    seen = set()

    # Pattern 1: DD Month YYYY or Month DD, YYYY (English & Bengali)
    months_pattern = "|".join(list(GREGORIAN_BANGLA_MONTHS.keys()) + list(ENGLISH_MONTHS.keys()))
    textual_rx = re.compile(
        rf'\b([\d০-৯]{{1,2}})\s+({months_pattern})\s*,?\s+([\d০-৯]{{4}})\b',
        re.IGNORECASE | re.UNICODE
    )

    # Pattern 2: Standard numeric dates (DD/MM/YYYY or YYYY-MM-DD)
    numeric_rx = re.compile(
        r'\b(?:(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})|([\d০-৯]{1,2})[-/.]([\d০-৯]{1,2})[-/.]([\d০-৯]{4}))\b'
    )

    for page in pages:
        for m in textual_rx.finditer(page.text):
            evidence = m.group(0).strip()
            key = (page.number, evidence)
            if key not in seen:
                seen.add(key)
                dates.append({
                    "value": evidence,
                    "page": page.number,
                    "evidence": evidence
                })

        for m in numeric_rx.finditer(page.text):
            evidence = m.group(0).strip()
            key = (page.number, evidence)
            if key not in seen:
                seen.add(key)
                dates.append({
                    "value": evidence,
                    "page": page.number,
                    "evidence": evidence
                })

    return dates


def extract_project_name(pages: List[ExtractedPage]) -> Optional[Dict[str, Any]]:
    """Extract project name from labels like 'Project:', 'প্রকল্পের নাম:'."""
    rx = re.compile(
        r'(?:প্রকল্পের\s*নাম\s*[:ঃ]|project\s*name\s*[:ঃ]|project\s*[:ঃ])\s*([^\n\r।]+)',
        re.IGNORECASE | re.UNICODE
    )
    for page in pages[:3]:  # Check first 3 pages
        match = rx.search(page.text)
        if match:
            name = match.group(1).strip()
            name = re.sub(r'[\.,:;।]+$', '', name).strip()
            if len(name) > 3:
                return {
                    "value": name,
                    "page": page.number,
                    "evidence": match.group(0).strip()
                }
    return None


def extract_reference_number(pages: List[ExtractedPage]) -> Optional[Dict[str, Any]]:
    """Extract reference/memo number from 'Ref:', 'স্মারক নং', 'Memo No:'."""
    rx = re.compile(
        r'(?:স্মারক\s*ন[ংম্বর]+\s*[:ঃ]|ref(?:erence)?\s*(?:no\.?|number)?\s*[:ঃ]|memo\s*no\.?\s*[:ঃ])\s*([^\n\r।]+)',
        re.IGNORECASE | re.UNICODE
    )
    for page in pages[:3]:
        match = rx.search(page.text)
        if match:
            ref = match.group(1).strip()
            ref = re.sub(r'[\.,:;।]+$', '', ref).strip()
            if len(ref) > 2:
                return {
                    "value": ref,
                    "page": page.number,
                    "evidence": match.group(0).strip()
                }
    return None


def extract_issuing_organization(pages: List[ExtractedPage]) -> Optional[Dict[str, Any]]:
    """Extract issuing government organization or department."""
    rx = re.compile(
        r'((?:Government\s+of\s+[^\n\r]+|মন্ত্রণালয়|অধিদপ্তর|কমিশন|কর্তৃপক্ষ|Department\s+of\s+[^\n\r]+|Ministry\s+of\s+[^\n\r]+)[^\n\r।]{0,80})',
        re.IGNORECASE | re.UNICODE
    )
    if pages:
        page_1 = pages[0]
        # Look in the top 600 characters of the first page
        top_text = page_1.text[:600]
        match = rx.search(top_text)
        if match:
            val = match.group(1).strip()
            # Clean up trailing punctuation
            val = re.sub(r'[,:;।]+$', '', val).strip()
            if len(val) > 4:
                return {
                    "value": val,
                    "page": 1,
                    "evidence": match.group(0).strip()
                }
    return None


def extract_deadline(pages: List[ExtractedPage], all_dates: List[Dict[str, Any]]) -> Optional[Dict[str, Any]]:
    """Extract deadline or completion date."""
    rx = re.compile(
        r'(?:সময়সীমা|মেয়াদ|ডেডলাইন|deadline|completion\s*date|due\s*date|submission\s*deadline)\s*[:ঃ]?\s*([^\n\r।]+)',
        re.IGNORECASE | re.UNICODE
    )
    for page in pages:
        match = rx.search(page.text)
        if match:
            snippet = match.group(0).strip()
            val = match.group(1).strip()
            return {
                "value": val,
                "page": page.number,
                "evidence": snippet
            }
    # If no explicit label, but dates exist near a completion/submission phrase
    for d in all_dates:
        for page in pages:
            if page.number == d["page"]:
                line_rx = re.compile(rf'.{{0,40}}(?:deadline|submission|completion|জমা|সমাপ্তি).{{0,40}}{re.escape(d["value"])}', re.IGNORECASE)
                m = line_rx.search(page.text)
                if m:
                    return {
                        "value": d["value"],
                        "page": d["page"],
                        "evidence": m.group(0).strip()
                    }
    return None


def extract_budget(pages: List[ExtractedPage], all_amounts: List[Dict[str, Any]]) -> Optional[Dict[str, Any]]:
    """Extract project budget specifically."""
    rx = re.compile(
        r'(?:বাজেট|বরাদ্দ|প্রাক্কলিত\s*ব্যয়|মোট\s*ব্যয়|project\s*budget|estimated\s*cost|total\s*budget|total\s*cost)\s*[:ঃ]?\s*([^\n\r।]+)',
        re.IGNORECASE | re.UNICODE
    )
    for page in pages:
        match = rx.search(page.text)
        if match:
            val = match.group(1).strip()
            return {
                "value": val,
                "page": page.number,
                "evidence": match.group(0).strip()
            }
    # Fallback to the first extracted amount if present on page 1
    if all_amounts:
        for a in all_amounts:
            if a["page"] == 1:
                return {
                    "value": a["value"],
                    "page": 1,
                    "evidence": a["evidence"]
                }
        return {
            "value": all_amounts[0]["value"],
            "page": all_amounts[0]["page"],
            "evidence": all_amounts[0]["evidence"]
        }
    return None


def extract_document_info(pages: List[ExtractedPage], fallback_title: str = "") -> Dict[str, Any]:
    """
    Deterministic rule-based entity and information extraction.
    Unknown values remain None.
    """
    all_amounts = extract_amounts(pages)
    all_dates = extract_dates(pages)
    project_info = extract_project_name(pages)
    ref_info = extract_reference_number(pages)
    org_info = extract_issuing_organization(pages)
    budget_info = extract_budget(pages, all_amounts)
    deadline_info = extract_deadline(pages, all_dates)

    # Document title
    title = fallback_title
    if pages:
        lines = [line.strip() for line in pages[0].text.splitlines() if line.strip()]
        # Skip small lines like dates or page numbers
        candidate_lines = [l for l in lines if len(l) > 10 and not l.isdigit()]
        if candidate_lines:
            title = candidate_lines[0]

    # Document date
    doc_date = None
    if all_dates:
        # Prefer date found on page 1
        page_1_dates = [d for d in all_dates if d["page"] == 1]
        doc_date = page_1_dates[0]["value"] if page_1_dates else all_dates[0]["value"]

    # Top keywords
    all_text = " ".join(p.text for p in pages)
    tokens = tokenize(all_text, lowercase=True, remove_stopwords=True)
    stemmed_counts: Dict[str, int] = {}
    for t in tokens:
        st = stem(t)
        if len(st) >= 3 and not st.isdigit():
            stemmed_counts[st] = stemmed_counts.get(st, 0) + 1

    sorted_keywords = sorted(stemmed_counts.items(), key=lambda x: x[1], reverse=True)
    top_keywords = [k for k, _ in sorted_keywords[:15]]

    entities = []
    if project_info:
        entities.append({"type": "project_name", "value": project_info["value"], "page_number": project_info["page"], "evidence": project_info["evidence"]})
    if ref_info:
        entities.append({"type": "reference_number", "value": ref_info["value"], "page_number": ref_info["page"], "evidence": ref_info["evidence"]})
    if budget_info:
        entities.append({"type": "budget", "value": budget_info["value"], "page_number": budget_info["page"], "evidence": budget_info["evidence"]})
    if deadline_info:
        entities.append({"type": "deadline", "value": deadline_info["value"], "page_number": deadline_info["page"], "evidence": deadline_info["evidence"]})
    if org_info:
        entities.append({"type": "issuing_organization", "value": org_info["value"], "page_number": org_info["page"], "evidence": org_info["evidence"]})

    for a in all_amounts[:5]:
        entities.append({"type": "amount", "value": a["value"], "page_number": a["page"], "evidence": a["evidence"]})

    return {
        "title": title or None,
        "issuing_organization": org_info["value"] if org_info else None,
        "reference_number": ref_info["value"] if ref_info else None,
        "document_date": doc_date,
        "project_name": project_info["value"] if project_info else None,
        "budget": budget_info["value"] if budget_info else None,
        "deadline": deadline_info["value"] if deadline_info else None,
        "dates": all_dates,
        "amounts": all_amounts,
        "keywords": top_keywords,
        "entities": entities
    }
