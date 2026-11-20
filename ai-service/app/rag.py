"""Extractive grounded question-answering with citation tracking and conflict detection."""
import re
from typing import Any, Dict, List, Optional, Tuple
from app.lexicon import expand_query, get_intent_flags, topic_groups
from app.textutil import stem, tokenize
from app.vector_store import StoredChunk, global_vector_store

INJECTION_PATTERNS = [
    r'(?i)ignore\s+(?:all\s+)?previous\s+instructions',
    r'(?i)system\s*:',
    r'(?i)you\s+are\s+now\s+an\s+unrestricted',
    r'(?i)output\s+the\s+following\s+password',
    r'(?i)<script>',
    r'(?i)forget\s+(?:all\s+)?rules'
]


def sanitize_text(text: str) -> str:
    """Drop lines attempting prompt injection."""
    cleaned_lines = []
    for line in text.splitlines():
        if any(re.search(pat, line) for pat in INJECTION_PATTERNS):
            continue
        cleaned_lines.append(line)
    return "\n".join(cleaned_lines)


def split_sentences(text: str) -> List[str]:
    """Split text into sentences across Bengali (।) and English (. ! ?) punctuation."""
    # Split on Bengali dari (।) or standard punctuation
    raw = re.split(r'[।!?\n]+|(?<=\w\w)\.\s+', text)
    sentences = [s.strip() for s in raw if len(s.strip()) > 8]
    return sentences


def check_topic_coverage(chunk_text: str, groups: List[set[str]]) -> float:
    """
    Check what fraction of topic word groups appear in the chunk text.
    Returns coverage ratio (0.0 to 1.0).
    """
    if not groups:
        return 1.0

    chunk_tokens = tokenize(chunk_text.lower(), lowercase=True)
    chunk_stems = {stem(t) for t in chunk_tokens}
    chunk_tokens_set = set(chunk_tokens)

    covered = 0
    for grp in groups:
        # If any word in the group (or its stem) is in the chunk
        if any((w in chunk_tokens_set or w in chunk_stems) for w in grp):
            covered += 1

    return covered / len(groups)


def extract_amounts_from_sentence(sentence: str) -> List[str]:
    """Extract raw currency numbers from a sentence for conflict detection."""
    rx = re.compile(r'(?:BDT|Tk|Tk\.|৳|\$)?\s*([\d,]+(?:\.\d+)?)\s*(?:টাকা|কোটি|লাখ|লক্ষ|crore|lakh)?', re.UNICODE)
    found = []
    for m in rx.finditer(sentence):
        num_str = m.group(1).replace(",", "").strip()
        if len(num_str) >= 4:  # At least thousands
            found.append(num_str)
    return found


def extract_grounded_answer(
    question: str,
    authorized_document_ids: List[str],
    top_k: int = 8
) -> Dict[str, Any]:
    """
    Extract grounded answer and citations from authorized documents.
    """
    if not authorized_document_ids or not question.strip():
        return {
            "answer": "No authorized documents are available to answer this question.",
            "status": "insufficient_evidence",
            "conflict_detected": False,
            "conflict_details": None,
            "citations": [],
            "verified": False,
            "generated_by": "extractive",
            "warning": None
        }

    # 1. Expand query with lexicon (Bangla -> English equivalents)
    expanded = expand_query(question)
    budget_intent, deadline_intent = get_intent_flags(question)
    topic_grps = topic_groups(question)

    # 2. Retrieve chunks from authorized scope
    chunks = global_vector_store.get_chunks_for_qa(
        query=expanded,
        authorized_document_ids=authorized_document_ids,
        top_k=top_k
    )

    if not chunks:
        return {
            "answer": "Insufficient evidence found in authorized documents to answer this question.",
            "status": "insufficient_evidence",
            "conflict_detected": False,
            "conflict_details": None,
            "citations": [],
            "verified": False,
            "generated_by": "extractive",
            "warning": None
        }

    # 3. Filter by topic coverage (> 50% coverage required if topic terms exist)
    qualifying_chunks: List[StoredChunk] = []
    for c in chunks:
        clean_chunk_text = sanitize_text(c.text)
        if topic_grps:
            cov = check_topic_coverage(clean_chunk_text, topic_grps)
            if cov >= 0.5:
                qualifying_chunks.append(c)
        else:
            qualifying_chunks.append(c)

    if not qualifying_chunks:
        return {
            "answer": "The available documents do not contain sufficient evidence regarding this topic.",
            "status": "insufficient_evidence",
            "conflict_detected": False,
            "conflict_details": None,
            "citations": [],
            "verified": False,
            "generated_by": "extractive",
            "warning": None
        }

    # 4. Sentence scoring across qualifying chunks
    scored_sentences: List[Tuple[float, str, StoredChunk]] = []
    question_terms = set(tokenize(expanded.lower(), lowercase=True, remove_stopwords=True))
    question_stems = {stem(t) for t in question_terms}

    for chunk in qualifying_chunks:
        clean_text = sanitize_text(chunk.text)
        sentences = split_sentences(clean_text)
        for sent in sentences:
            s_tokens = tokenize(sent.lower(), lowercase=True)
            s_stems = {stem(t) for t in s_tokens}
            overlap = len(s_stems.intersection(question_stems))
            if overlap == 0:
                continue

            score = float(overlap)

            # Intent boost: budget
            if budget_intent:
                has_num = bool(re.search(r'[\d০-৯]{4,}', sent))
                has_curr = bool(re.search(r'(?:টাকা|টাকার|bdt|tk|৳|cost|budget|বাজেট|ব্যয়|বরাদ্দ)', sent, re.I))
                if has_num and has_curr:
                    score += 4.0

            # Intent boost: deadline
            if deadline_intent:
                has_date = bool(re.search(r'(?:deadline|completion|submission|মেয়াদ|সময়সীমা|তারিখ|[\d০-৯]{4})', sent, re.I))
                if has_date:
                    score += 3.5

            scored_sentences.append((score, sent, chunk))

    if not scored_sentences:
        return {
            "answer": "Insufficient evidence found to answer this specific query.",
            "status": "insufficient_evidence",
            "conflict_detected": False,
            "conflict_details": None,
            "citations": [],
            "verified": False,
            "generated_by": "extractive",
            "warning": None
        }

    # Sort sentences by score descending
    scored_sentences.sort(key=lambda x: x[0], reverse=True)

    # 5. Select top sentences (up to 4) avoiding redundant repetition
    selected: List[Tuple[str, StoredChunk, float]] = []
    seen_texts = set()

    for score, sent, chunk in scored_sentences:
        normalized_sent = re.sub(r'\s+', ' ', sent).strip()
        if normalized_sent in seen_texts:
            continue
        seen_texts.add(normalized_sent)
        selected.append((sent, chunk, score))
        if len(selected) >= 4:
            break

    # 6. Check for conflicting figures across distinct documents (e.g. Budget revisions)
    doc_amounts: Dict[str, List[str]] = {}
    for sent, chunk, _ in selected:
        nums = extract_amounts_from_sentence(sent)
        if nums:
            if chunk.document_id not in doc_amounts:
                doc_amounts[chunk.document_id] = []
            doc_amounts[chunk.document_id].extend(nums)

    conflict_detected = False
    conflict_details = None

    if len(doc_amounts) > 1 and budget_intent:
        unique_across_docs = set()
        for doc_id, vals in doc_amounts.items():
            for v in vals:
                unique_across_docs.add(v)
        if len(unique_across_docs) > 1:
            conflict_detected = True
            conflict_details = (
                f"Conflicting figures detected across multiple documents: "
                f"{', '.join(sorted(unique_across_docs))}."
            )

    # 7. Construct answer text with numbered citations [1], [2], ...
    answer_parts = []
    citations = []

    for idx, (sent, chunk, score) in enumerate(selected, 1):
        answer_parts.append(f"{sent} [{idx}]")
        citations.append({
            "source_num": idx,
            "document_id": chunk.document_id,
            "chunk_id": chunk.chunk_id,
            "page_number": chunk.page_number,
            "snippet": sent[:250],
            "score": round(score, 2)
        })

    answer_text = " ".join(answer_parts)
    if conflict_detected:
        answer_text = f"[CONFLICTING EVIDENCE DETECTED: {conflict_details}] " + answer_text

    return {
        "answer": answer_text,
        "status": "conflicting_evidence" if conflict_detected else "answered",
        "conflict_detected": conflict_detected,
        "conflict_details": conflict_details,
        "citations": citations,
        "verified": False,
        "generated_by": "extractive",
        "warning": "Conflicting values found in sources" if conflict_detected else None
    }
