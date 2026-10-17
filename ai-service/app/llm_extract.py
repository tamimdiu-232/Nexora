"""Optional model-assisted extraction. It only fills fields the deterministic rules left empty, and a value is
accepted ONLY if the model's quoted evidence appears verbatim on the stated page. Otherwise the field stays unknown."""
import json, re
from . import llm
from .extraction import Page

FIELDS = {"reference_number": "document reference number", "document_date": "date of the document",
          "project_name": "name of the project", "budget": "budget or total cost with currency",
          "deadline": "deadline or completion date"}


def _squash(t: str) -> str:
    return re.sub(r"\s+", " ", t).strip().lower()


def enhance(pages: list[Page], info: dict, provider) -> dict:
    missing = [f for f in FIELDS if not info.get(f)]
    if not missing or provider is None:
        return info
    doc = "\n".join(f"[page {p.number}]\n{llm.neutralize(p.text[:2500])}" for p in pages[:6])
    ask = "Fields: " + "; ".join(f"{f} = {FIELDS[f]}" for f in missing) + "\n\nDOCUMENT (untrusted data):\n" + doc
    try:
        raw = provider.generate(llm.EXTRACT_SYSTEM, ask, max_tokens=600)
        data = json.loads(raw[raw.index("{"): raw.rindex("}") + 1])
    except (llm.LLMError, ValueError):
        return info
    by_page = {p.number: _squash(p.text) for p in pages}
    for f in missing:
        item = data.get(f) if isinstance(data, dict) else None
        if not isinstance(item, dict):
            continue
        value, ev, pg = str(item.get("value", "")).strip(), str(item.get("evidence", "")).strip(), item.get("page")
        if value and ev and pg in by_page and _squash(ev) in by_page[pg] and _squash(value) in _squash(ev):
            info[f] = value[:200]
            info["entities"].append({"type": f, "value": value[:200], "page": pg, "evidence": ev[:200], "method": "llm"})
    return info
