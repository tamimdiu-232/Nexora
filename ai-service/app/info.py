"""Deterministic information extraction. Anything not found stays None (never guessed)."""
import re
from collections import Counter
from .extraction import Page
from .rag import AMOUNT

MONTHS = "January|February|March|April|May|June|July|August|September|October|November|December"
DATE = rf"(?:\d{{1,2}}\s+(?:{MONTHS})\s+\d{{4}}|\d{{4}}-\d{{2}}-\d{{2}}|\d{{1,2}}/\d{{1,2}}/\d{{4}})"
DATE_RE = re.compile(DATE, re.I)
REF_RE = re.compile(r"(?:Ref(?:erence)?(?:\s*No\.?)?|Memo\s*No\.?)\s*[:\-]\s*([A-Za-z0-9][A-Za-z0-9/\-\.]*[A-Za-z0-9])", re.I)
PROJECT_RE = re.compile(r"Project(?:\s*Name)?\s*[:\-]\s*([^\n]{3,100})", re.I)
ORG_RE = re.compile(r"(Ministry|Division|Department|Authority|Office|Directorate)", re.I)
BUDGET_RE = re.compile(rf"(?:budget|estimated cost|total cost|allocation)[^\n]{{0,60}}?({AMOUNT.pattern})", re.I)
DEADLINE_RE = re.compile(rf"(?:deadline|due date|completion date|submit(?:ted)? by|complete(?:d)? by)[^\n]{{0,60}}?({DATE})", re.I)
DATELINE_RE = re.compile(rf"\bDate\s*[:\-]\s*({DATE})", re.I)
STOP = set("""about above after again also been before being between could during each from have having into more most other
over should shall since some such than that their them then there these they this those through under until very were what when
where which while will with would your document project report department division government""".split())


def _first(rx, pages, group=1):
    for p in pages:
        for line in p.text.splitlines():
            m = rx.search(line)
            if m:
                return {"value": m.group(group).strip(" .;"), "page": p.number, "evidence": line[:200]}
    return None


def extract_info(pages: list[Page]) -> dict:
    ents = []

    def add(kind, hit):
        if hit:
            ents.append({"type": kind, **hit})
        return hit["value"] if hit else None

    title = None
    for line in (pages[0].text.splitlines() if pages else []):
        if len(line) > 8 and not REF_RE.search(line):
            title = line[:200]; break
    organization = add("organization", _first(ORG_RE, pages[:1], 0) and
                       {**_first(ORG_RE, pages[:1], 0), "value": next((l for l in pages[0].text.splitlines() if ORG_RE.search(l)), "")[:200]})
    info = {
        "title": title,
        "organization": organization or None,
        "reference_number": add("reference_number", _first(REF_RE, pages)),
        "document_date": add("document_date", _first(DATELINE_RE, pages)),
        "project_name": add("project_name", _first(PROJECT_RE, pages)),
        "budget": add("budget", _first(BUDGET_RE, pages)),
        "deadline": add("deadline", _first(DEADLINE_RE, pages)),
    }
    seen = set()
    for p in pages:
        for m in DATE_RE.finditer(p.text):
            if ("date", m.group(0)) not in seen:
                seen.add(("date", m.group(0))); ents.append({"type": "date", "value": m.group(0), "page": p.number, "evidence": m.group(0)})
        for m in AMOUNT.finditer(p.text):
            if ("amount", m.group(0)) not in seen:
                seen.add(("amount", m.group(0))); ents.append({"type": "amount", "value": m.group(0), "page": p.number, "evidence": m.group(0)})
    words = Counter(w for p in pages for w in re.findall(r"[A-Za-z]{5,}", p.text.lower()) if w not in STOP)
    info["keywords"] = [w for w, _ in words.most_common(8)]
    info["entities"] = ents
    return info
