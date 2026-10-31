"""Rule-based bilingual document classification with weighted heuristic scoring."""
import re
from typing import Dict, List, Tuple
from app.extraction import ExtractedPage

# Category definitions with bilingual keywords and weights
CATEGORY_RULES: Dict[str, List[Tuple[str, float]]] = {
    "Circular": [
        (r'\bপরিপত্র\b', 4.0),
        (r'\bcircular\b', 3.5),
        (r'\bস্মারক\s*ন[ংম]', 1.5),
        (r'\bবিজ্ঞপ্তি\b', 1.0),
        (r'\bnotification\b', 1.5),
    ],
    "Official Notice": [
        (r'\bবিজ্ঞপ্তি\b', 4.0),
        (r'\bজরুরি\s*বিজ্ঞপ্তি\b', 5.0),
        (r'\bnotice\b', 3.5),
        (r'\bpublic\s*notice\b', 4.0),
        (r'\bpress\s*release\b', 3.0),
    ],
    "Project Report": [
        (r'\bপ্রকল্প\s*প্রতিবেদন\b', 5.0),
        (r'\bপ্রকল্পের\s*অগ্রগতি\b', 4.5),
        (r'\bবার্ষিক\s*প্রতিবেদন\b', 4.0),
        (r'\bproject\s*report\b', 4.5),
        (r'\bprogress\s*report\b', 4.5),
        (r'\bcompletion\s*report\b', 4.0),
        (r'\bproject\s*director\b', 2.0),
        (r'\bপ্রকল্প\s*পরিচালক\b', 2.0),
    ],
    "Budget or Financial Document": [
        (r'\bবাজেট\b', 4.0),
        (r'\bবরাদ্দ\b', 3.5),
        (r'\bআর্থিক\s*প্রতিবেদন\b', 4.5),
        (r'\bহিসাব\s*বিবরণী\b', 4.0),
        (r'\bbudget\b', 4.0),
        (r'\bfinancial\s*statement\b', 4.5),
        (r'\bexpenditure\b', 3.0),
        (r'\ballocation\b', 3.0),
        (r'\bপ্রাক্কলিত\s*ব্যয়\b', 4.0),
        (r'\bestimated\s*cost\b', 3.5),
    ],
    "Policy or Guideline": [
        (r'\bনীতিমালা\b', 5.0),
        (r'\bনির্দেশিকা\b', 4.5),
        (r'\bকর্মপরিকল্পনা\b', 3.5),
        (r'\bpolicy\b', 4.0),
        (r'\bguideline\b', 4.0),
        (r'\bframework\b', 3.0),
        (r'\bstandard\s*operating\s*procedure\b', 4.5),
        (r'\bsop\b', 2.5),
    ],
    "Meeting Minutes": [
        (r'\bসভার\s*কার্যবিবরণী\b', 5.0),
        (r'\bকার্যবিবরণী\b', 4.5),
        (r'\bসভার\s*সিদ্ধান্ত\b', 4.0),
        (r'\bminutes\s*of\s*(?:the\s*)?.*?meeting\b', 5.0),
        (r'\bmeeting\s*minutes\b', 4.5),
        (r'\bcommittee\s*meeting\b', 4.0),
        (r'\bproceedings\b', 3.0),
        (r'\bউপস্থিত\s*সদস্যবৃন্দ\b', 3.5),
        (r'\battendance\s*list\b', 2.5),
    ],
    "Administrative Order": [
        (r'\bঅফিস\s*আদেশ\b', 5.0),
        (r'\bপ্রশাসনিক\s*আদেশ\b', 4.5),
        (r'\bবদলি\s*আদেশ\b', 4.5),
        (r'\boffice\s*order\b', 4.5),
        (r'\badministrative\s*order\b', 4.5),
        (r'\bgovernment\s*order\b', 4.5),
        (r'\btransfer\s*order\b', 4.0),
    ],
}

MIN_CLASSIFICATION_THRESHOLD = 3.0


def classify_document(pages: List[ExtractedPage]) -> Dict:
    """
    Classify a document using weighted regex rules.
    First page / header area carries double weight.
    Returns: category, heuristic confidence score, and raw scores dictionary.
    """
    if not pages:
        return {"category": "Other", "confidence": 0.0, "scores": {}}

    page_1_text = pages[0].text
    # Title / header area: first 500 characters of page 1
    header_text = page_1_text[:500]
    rest_page_1 = page_1_text[500:]
    subsequent_text = "\n".join(p.text for p in pages[1:])

    scores: Dict[str, float] = {}

    for category, rules in CATEGORY_RULES.items():
        total_cat_score = 0.0
        for pattern, weight in rules:
            rx = re.compile(pattern, re.IGNORECASE | re.UNICODE)
            
            # Header area matches count double
            header_matches = len(rx.findall(header_text))
            if header_matches > 0:
                total_cat_score += header_matches * (weight * 2.0)
            
            # Rest of page 1
            p1_matches = len(rx.findall(rest_page_1))
            if p1_matches > 0:
                total_cat_score += p1_matches * weight

            # Subsequent pages count standard weight (capped to avoid long report dilution)
            sub_matches = len(rx.findall(subsequent_text))
            if sub_matches > 0:
                total_cat_score += min(sub_matches, 3) * (weight * 0.5)

        if total_cat_score > 0:
            scores[category] = round(total_cat_score, 2)

    if not scores:
        return {"category": "Other", "confidence": 0.0, "scores": {}}

    best_category = max(scores, key=scores.get)
    best_score = scores[best_category]

    if best_score < MIN_CLASSIFICATION_THRESHOLD:
        return {"category": "Other", "confidence": 0.0, "scores": scores}

    # Normalize heuristic confidence score between 0.5 and 0.98
    confidence = min(0.98, round(0.5 + (best_score / (best_score + 10.0)) * 0.48, 2))

    return {
        "category": best_category,
        "confidence": confidence,
        "scores": scores
    }
