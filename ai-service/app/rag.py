"""Grounded QA. Default provider is extractive (no LLM): returns best supporting sentences
with citations, states insufficient evidence, flags conflicting amounts. Retrieved text is
untrusted: instruction-like lines are dropped before use."""
import re
from . import llm
from .config import settings
from .textutil import stem

WORD = re.compile(r"[\w\u0980-\u09FF]+")  # \w alone splits Bengali words at vowel signs
INJECTION = re.compile(
    r"(ignore (all |any )?(previous|prior|above) (instructions|rules)|disregard .{0,30}instructions|"
    r"system prompt|you are now|reveal .{0,30}(secret|prompt|password))", re.I)
AMOUNT = re.compile(r"(?:BDT|Tk\.?|৳)\s?[\d,]+(?:\.\d+)?(?:\s?(?:crore|lakh|million))?", re.I)
STOP = set("the a an of is are what when how much was for and to in on this that does do which who there কত কবে এই এবং এর কি কী শেষ কোন কোনো জন্য থেকে আছে হবে হয় ও".split())
NO_EVIDENCE = {"answer": "I could not find sufficient evidence in the documents you can access to answer this.",
               "status": "insufficient_evidence", "citations": [], "conflicts": []}


def sanitize(text: str) -> str:
    return "\n".join(l for l in text.splitlines() if not INJECTION.search(l))


def _sentences(text):
    return [s.strip() for s in re.split(r"(?<=[.!?।])\s+|\n", text) if len(s.strip()) > 15]


MONTHS = "January|February|March|April|May|June|July|August|September|October|November|December"
DATE_RX = re.compile(rf"\d{{1,2}}\s+(?:{MONTHS})\s+\d{{4}}|\d{{4}}-\d{{2}}-\d{{2}}|\d{{1,2}}/\d{{1,2}}/\d{{4}}", re.I)
Q_BUDGET = re.compile(r"budget|cost|amount|allocation|expenditure|কত|taka|\btk\b", re.I)
Q_DEADLINE = re.compile(r"deadline|due|when|date|কবে|কখন", re.I)
S_DEADLINE = re.compile(r"deadline|due|complet|submit|by ", re.I)


_stem = stem


def _stems(text: str) -> set:
    return {_stem(w) for w in WORD.findall(text.lower())}


def _boost(question: str, sentence: str) -> float:
    b = 0.0
    if Q_BUDGET.search(question) and AMOUNT.search(sentence):
        b += 3
    if Q_DEADLINE.search(question) and DATE_RX.search(sentence):
        b += 4 if S_DEADLINE.search(sentence) else 1
    return b


def answer(question: str, hits: list) -> dict:
    hits = [h for h in hits if h[0] >= settings.min_score]
    if not hits:
        return dict(NO_EVIDENCE)
    qwords = {w for w in WORD.findall(question.lower()) if w not in STOP}
    # topic words = question words that are not generic budget/date intent words
    content = {_stem(w) for w in qwords if not (Q_BUDGET.fullmatch(w) or Q_DEADLINE.fullmatch(w))}
    qstems = {_stem(w) for w in qwords}
    cands = []
    for score, ch, meta in hits:
        # a chunk must cover more than half of the topic words, otherwise it is off-topic
        if content and len(content & _stems(ch.text)) / len(content) <= 0.5:
            continue
        for sent in _sentences(sanitize(ch.text)):
            overlap = len(qstems & _stems(sent))
            boost = _boost(question, sent)
            if overlap == 0 and boost == 0:
                continue
            cands.append((overlap + boost + score * 2, sent, ch, meta, score))
    cands.sort(key=lambda c: -c[0])
    picked, cites, seen = [], [], set()
    for _, sent, ch, meta, score in cands:
        if sent in seen:
            continue
        seen.add(sent)
        picked.append(sent)
        cites.append({"document_id": ch.document_id, "chunk_id": ch.chunk_id, "page": ch.page,
                      "title": meta.get("title"), "snippet": sent[:300], "score": round(score, 3)})
        if len(picked) == 5:
            break
    if not picked:
        return dict(NO_EVIDENCE)
    conflicts = []
    if Q_BUDGET.search(question):
        by_amt = {}
        for c in cites:
            for a in AMOUNT.findall(c["snippet"]):
                by_amt.setdefault(re.sub(r"[\s,]", "", a.lower()), set()).add(c["document_id"])
        if len(by_amt) > 1:
            conflicts = [{"amount": k, "documents": sorted(v)} for k, v in by_amt.items()]
    text = " ".join(f"{s} [{i + 1}]" for i, s in enumerate(picked[:4]))
    if conflicts:
        text = "Sources disagree on this value; please verify against the originals. " + text
    return {"answer": text, "status": "conflicting_evidence" if conflicts else "answered",
            "citations": cites[:4], "conflicts": conflicts, "generated_by": "extractive-baseline", "verified": False}


def validate_citations(store, authorized_ids, citations) -> list[dict]:
    """Keep only citations that exist in the store, match page, and are authorized."""
    ok = []
    for c in citations:
        ch = store.get_chunk(c["document_id"], c["chunk_id"])
        if c["document_id"] in authorized_ids and ch and ch.page == c["page"]:
            ok.append(c)
    return ok


def _norm(t: str) -> str:
    return re.sub(r"[\s,]", "", t.lower())


def unsupported_values(answer_text: str, cited_texts: list[str]) -> list[str]:
    """Amounts/dates in a generated answer that do not appear in the cited sources (hallucination guard)."""
    src = _norm(" ".join(cited_texts))
    return [v for v in AMOUNT.findall(answer_text) + DATE_RX.findall(answer_text) if _norm(v) not in src]


def _withheld(reason: str) -> dict:
    return {"answer": "The generated answer could not be verified against the sources, so it was withheld.",
            "status": "insufficient_evidence", "citations": [], "conflicts": [], "warning": reason}


def generate_answer(question: str, hits: list, retrieval_question: str | None = None) -> dict:
    """Uses the configured LLM when available, otherwise the extractive baseline. Every LLM answer is verified:
    citation markers must exist, and amounts/dates must appear in the cited passages."""
    provider, warning = llm.get_provider()
    if provider is None:
        res = answer(retrieval_question or question, hits)
        if warning:
            res["warning"] = warning
        return res
    hits = [h for h in hits if h[0] >= settings.min_score][:6]
    if not hits:
        return dict(NO_EVIDENCE)
    passages = [sanitize(ch.text)[:1200] for _, ch, _ in hits]
    try:
        raw = provider.generate(llm.SYSTEM_PROMPT, llm.build_prompt(question, passages)).strip()
    except llm.LLMError as e:
        res = answer(retrieval_question or question, hits)
        res["warning"] = f"LLM call failed ({e}); extractive baseline used."
        return res
    if "INSUFFICIENT_EVIDENCE" in raw:
        return dict(NO_EVIDENCE)
    nums = {int(n) for n in re.findall(r"\[(\d{1,2})\]", raw)}
    if not nums:
        return _withheld("The model answer had no source citations.")
    if any(n < 1 or n > len(hits) for n in nums):
        return _withheld("The model cited a source that does not exist.")
    cited = sorted(nums)
    bad = unsupported_values(raw, [hits[n - 1][1].text for n in cited])
    if bad:
        return _withheld("The model stated values not found in its cited sources: " + ", ".join(bad[:3]))
    cites = [{"document_id": hits[n - 1][1].document_id, "chunk_id": hits[n - 1][1].chunk_id, "page": hits[n - 1][1].page,
              "title": hits[n - 1][2].get("title"), "snippet": hits[n - 1][1].text[:300], "score": round(hits[n - 1][0], 3)}
             for n in cited]
    by_amt = {}
    for n in cited:
        for a in AMOUNT.findall(hits[n - 1][1].text):
            by_amt.setdefault(_norm(a), set()).add(hits[n - 1][1].document_id)
    conflicts = [{"amount": k, "documents": sorted(v)} for k, v in by_amt.items()] if len(by_amt) > 1 else []
    return {"answer": raw, "status": "conflicting_evidence" if conflicts else "answered", "citations": cites,
            "conflicts": conflicts, "generated_by": "llm:" + provider.name, "verified": False}
