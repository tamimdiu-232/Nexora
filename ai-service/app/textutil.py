"""Tiny shared text helpers (kept dependency-free on purpose)."""
import re


def stem(w: str) -> str:
    """Very light English stemmer: reports -> report, submitted -> submit. Non-English words are left unchanged."""
    if len(w) <= 4 or not w.isascii():
        return w
    t = re.sub(r"(ing|ed|es|s)$", "", w)
    if t != w and len(t) > 3 and t[-1] == t[-2] and t[-1] not in "aeiou":
        t = t[:-1]  # submitted -> submitt -> submit
    return t
