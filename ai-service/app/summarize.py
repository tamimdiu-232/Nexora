"""Extractive hierarchical summarizer: best sentence per page first, then global ranking.
No LLM involved; output is a convenience aid, never verified fact."""
import math, re
from collections import Counter
from .extraction import Page
from .rag import AMOUNT, sanitize
from .info import DATE_RE, STOP

ACTION = re.compile(r"\b(shall|must|is requested to|are requested to|should|are required to)\b", re.I)


def _sents(page: Page):
    t = sanitize(page.text)
    return [(s.strip(), page.number) for s in re.split(r"(?<=[.!?।])\s+|\n", t) if len(s.strip()) > 25]


def summarize(pages: list[Page], info: dict) -> dict:
    sents = [x for p in pages for x in _sents(p)]
    if not sents:
        return {"short": [], "detailed": [], "key_points": [], "dates": [], "amounts": [], "action_items": [],
                "generated_by": "extractive-baseline"}
    freq = Counter(w for s, _ in sents for w in re.findall(r"\w+", s.lower()) if w not in STOP and len(w) > 3)

    def score(s):
        words = re.findall(r"\w+", s.lower())
        base = sum(freq[w] for w in words) / math.sqrt(len(words) + 1)
        return base + (2 if AMOUNT.search(s) else 0) + (1 if DATE_RE.search(s) else 0)

    ranked = sorted(range(len(sents)), key=lambda i: -score(sents[i][0]))
    per_page = {}
    for i in ranked:
        per_page.setdefault(sents[i][1], i)
    detailed_idx = sorted(set(list(per_page.values()) + ranked[:8]))[:12]
    short_idx = sorted(ranked[:2])
    kp_idx = ranked[:5]
    item = lambda i: {"text": sents[i][0], "page": sents[i][1]}
    return {
        "short": [item(i) for i in short_idx],
        "detailed": [item(i) for i in detailed_idx],
        "key_points": [item(i) for i in kp_idx],
        "dates": [{"value": e["value"], "page": e["page"]} for e in info["entities"] if e["type"] == "date"],
        "amounts": [{"value": e["value"], "page": e["page"]} for e in info["entities"] if e["type"] == "amount"],
        "action_items": [item(i) for i in range(len(sents)) if ACTION.search(sents[i][0])][:8],
        "generated_by": "extractive-baseline",
    }
