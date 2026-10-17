"""Rule-based baseline classifier. Prototype categories only, not an official standard.
`confidence` is a heuristic score share, NOT a calibrated probability or measured accuracy."""
import re

CATEGORIES = ["Circular", "Official Notice", "Project Report", "Budget or Financial Document",
              "Policy or Guideline", "Meeting Minutes", "Administrative Order", "Other"]

RULES = {
    "Circular": [(r"\bcircular\b", 4), (r"all (divisions|departments|offices)", 1), (r"পরিপত্র", 4)],
    "Official Notice": [(r"\bnotice\b", 4), (r"\bnotification\b", 1), (r"বিজ্ঞপ্তি", 4),
                        (r"is hereby (notified|informed)", 2)],
    "Project Report": [(r"project (progress )?report|project summary", 4), (r"\bproject\b", 1),
                       (r"implementation|milestone|contractor", 1)],
    "Budget or Financial Document": [(r"\bbudget( report)?\b", 2), (r"expenditure|allocation|fiscal|revenue", 2),
                                     (r"\b(BDT|Tk)\b|৳", 1), (r"বাজেট", 4)],
    "Policy or Guideline": [(r"\b(policy|guidelines?|standard operating procedure)\b", 4), (r"\bshall\b", 1)],
    "Meeting Minutes": [(r"minutes of (the )?meeting|meeting minutes", 6), (r"\b(attendees|agenda|resolutions?)\b", 2),
                        (r"সভার কার্যবিবরণী", 6)],
    "Administrative Order": [(r"office order|administrative order", 6), (r"is hereby (appointed|assigned|directed)", 2),
                             (r"অফিস আদেশ", 6)],
}
MIN_SCORE = 4


def classify(text: str) -> dict:
    head = text[:300]
    scores = {}
    for cat, rules in RULES.items():
        s = 0
        for pat, w in rules:
            s += w * len(re.findall(pat, text, re.I))  # whole text
            s += w * 2 * len(re.findall(pat, head, re.I))  # title area counts extra
        scores[cat] = s
    top = max(scores, key=scores.get)
    if scores[top] < MIN_SCORE:
        return {"category": "Other", "confidence": 0.0, "scores": scores, "method": "rule-based"}
    total = sum(scores.values())
    return {"category": top, "confidence": round(scores[top] / total, 2), "scores": scores, "method": "rule-based"}
