"""Hierarchical extractive summarization with OCR noise filtering."""
import re
from typing import Any, Dict, List
from app.extraction import ExtractedPage
from app.info import extract_amounts, extract_dates
from app.rag import split_sentences
from app.textutil import stem, tokenize

OBLIGATION_PATTERNS = [
    r'করতে\s*হবে',
    r'দেবেন',
    r'জমা\s*(?:দিতে|দেওয়া|দিন)',
    r'অনুরোধ\s*(?:করা\s*হলো|করা\s*যাচ্ছে)',
    r'বাধ্যতামূলক',
    r'নির্দেশ\s*প্রদান\s*করা\s*হলো',
    r'\bshall\b',
    r'\bmust\b',
    r'\brequired\s+to\b',
    r'\bis\s+directed\s+to\b',
    r'\binstructed\s+to\b',
    r'\bhereby\s+ordered\b'
]


def is_valid_content_sentence(sentence: str) -> bool:
    """
    Filter out OCR noise and junk lines:
    1. At least 4 words.
    2. At least 60% of non-whitespace characters are valid letters, digits, or Bengali characters.
    """
    tokens = tokenize(sentence, lowercase=False)
    if len(tokens) < 4:
        return False

    clean_chars = [c for c in sentence if not c.isspace()]
    if not clean_chars:
        return False

    valid_count = 0
    for c in clean_chars:
        code = ord(c)
        # Latin, digits, Bengali U+0980-09FF
        if c.isalnum() or (0x0980 <= code <= 0x09FF):
            valid_count += 1

    ratio = valid_count / len(clean_chars)
    return ratio >= 0.60


def summarize_document(pages: List[ExtractedPage]) -> Dict[str, Any]:
    """
    Hierarchical extractive document summarization.
    Extracts short summary, detailed summary, key points with page refs, and action items.
    """
    if not pages:
        return {
            "short": "Empty document.",
            "detailed": "No extractable text was found in the document.",
            "key_points": [],
            "dates": [],
            "amounts": [],
            "action_items": []
        }

    # 1. Gather all valid sentences per page
    page_sentences: Dict[int, List[str]] = {}
    all_valid_sentences: List[tuple[int, str]] = []

    for page in pages:
        raw_sents = split_sentences(page.text)
        valid_sents = [s for s in raw_sents if is_valid_content_sentence(s)]
        page_sentences[page.number] = valid_sents
        for s in valid_sents:
            all_valid_sentences.append((page.number, s))

    if not all_valid_sentences:
        return {
            "short": "Document contains insufficient structured text for summarization.",
            "detailed": "The document could not be summarized due to low content density.",
            "key_points": [],
            "dates": [],
            "amounts": [],
            "action_items": []
        }

    # 2. Extract action items (sentences containing explicit obligation phrases)
    obligation_rx = re.compile("|".join(OBLIGATION_PATTERNS), re.IGNORECASE | re.UNICODE)
    action_items = []
    seen_actions = set()

    for page_num, sent in all_valid_sentences:
        if obligation_rx.search(sent):
            clean_s = sent.strip()
            if clean_s not in seen_actions:
                seen_actions.add(clean_s)
                action_items.append({
                    "page": page_num,
                    "text": clean_s
                })

    # 3. Score sentences based on frequency of content word stems
    full_text = " ".join(p.text for p in pages)
    tokens = tokenize(full_text, lowercase=True, remove_stopwords=True)
    stem_freq: Dict[str, int] = {}
    for t in tokens:
        st = stem(t)
        if len(st) >= 3:
            stem_freq[st] = stem_freq.get(st, 0) + 1

    def score_sentence(sent: str) -> float:
        s_tokens = tokenize(sent, lowercase=True, remove_stopwords=True)
        if not s_tokens:
            return 0.0
        score = sum(stem_freq.get(stem(t), 0) for t in s_tokens)
        # Normalize by length to prevent favoring excessively long sentences
        return score / (len(s_tokens) ** 0.5)

    # 4. Hierarchical selection: best sentence from each page
    best_per_page: List[tuple[int, str, float]] = []
    for page_num, sents in page_sentences.items():
        if not sents:
            continue
        scored = [(sent, score_sentence(sent)) for sent in sents]
        scored.sort(key=lambda x: x[1], reverse=True)
        best_per_page.append((page_num, scored[0][0], scored[0][1]))

    # Global ranking of sentences across the whole document
    globally_scored = [(page_num, sent, score_sentence(sent)) for page_num, sent in all_valid_sentences]
    globally_scored.sort(key=lambda x: x[2], reverse=True)

    # Short summary: Top 1-2 most informative sentences
    short_sentences = [sent for _, sent, _ in globally_scored[:2]]
    short_summary = " ".join(short_sentences)

    # Detailed summary: Best sentences from each page (up to 5 pages), or top 5 globally
    if len(best_per_page) >= 2:
        detailed_sentences = [sent for _, sent, _ in best_per_page[:5]]
    else:
        detailed_sentences = [sent for _, sent, _ in globally_scored[:5]]
    detailed_summary = " ".join(detailed_sentences)

    # Key points: Top sentences with their respective page numbers
    key_points = []
    seen_kp = set()
    for page_num, sent, _ in (best_per_page if len(best_per_page) > 1 else globally_scored)[:6]:
        if sent not in seen_kp:
            seen_kp.add(sent)
            key_points.append({
                "page": page_num,
                "text": sent
            })

    # Dates and amounts
    dates = extract_dates(pages)
    amounts = extract_amounts(pages)

    return {
        "short": short_summary,
        "detailed": detailed_summary,
        "key_points": key_points,
        "dates": dates[:10],
        "amounts": amounts[:10],
        "action_items": action_items[:8]
    }
