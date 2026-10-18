"""Small Bangla -> English glossary for common government-document terms.
Why: the default embedder is hash-based (not multilingual), so a Bangla question cannot match English documents by
itself. Recognised Bangla words are replaced by their English equivalents for RETRIEVAL only; unknown Bangla words
are kept unchanged (so Bangla documents still match by their own words). A multilingual embedding model is the real fix."""
import re

WORD = re.compile(r"[\w\u0980-\u09FF]+")

GLOSSARY = {
    "প্রকল্প": "project", "বাজেট": "budget", "ব্যয়": "cost expenditure", "খরচ": "cost expenditure",
    "টাকা": "BDT taka", "বরাদ্দ": "allocation budget", "সময়সীমা": "deadline", "মেয়াদ": "deadline",
    "তারিখ": "date", "জমা": "submit", "প্রতিবেদন": "report", "রিপোর্ট": "report", "পরিপত্র": "circular",
    "বিজ্ঞপ্তি": "notice", "সভা": "meeting", "কার্যবিবরণী": "minutes", "নীতিমালা": "policy guideline",
    "নীতি": "policy", "নির্দেশিকা": "guideline", "আদেশ": "order", "অনুমোদিত": "approved", "অনুমোদন": "approved approval",
    "সংশোধিত": "revised", "ঠিকাদার": "contractor", "সেতু": "bridge", "সড়ক": "road", "রাস্তা": "road",
    "রক্ষণাবেক্ষণ": "maintenance", "মন্ত্রণালয়": "ministry", "বিভাগ": "division", "অফিস": "office", "বন্ধ": "closed",
    "ক্রয়": "procurement", "অগ্রগতি": "progress", "মাইলফলক": "milestone", "ঝুঁকি": "risk", "সম্পন্ন": "completion",
    "সমাপ্তি": "completion", "নথি": "file record", "সিদ্ধান্ত": "resolution decision",
}
_KEYS = sorted(GLOSSARY, key=len, reverse=True)  # longest first so "নীতিমালা" wins over "নীতি"


def translate(question: str) -> str:
    """Replace recognised Bangla words (including inflected forms like প্রকল্পের) with English equivalents."""
    out = []
    for tok in WORD.findall(question):
        key = next((k for k in _KEYS if tok.startswith(k)), None)
        out.append(GLOSSARY[key] if key else tok)
    return " ".join(out)
