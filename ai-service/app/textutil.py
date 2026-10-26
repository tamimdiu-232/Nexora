"""Bilingual text processing utilities for English and Bengali."""
import re
from typing import List, Set

# Regex word pattern covering both Latin/alphanumeric and Bengali Unicode ranges (U+0980 to U+09FF)
# Using \w alone splits Bengali vowel signs and conjuncts (e.g., কবে -> কব + ে)
WORD_PATTERN = re.compile(r'[\w\u0980-\u09FF]+', re.UNICODE)

# Common English stop words
ENGLISH_STOPWORDS: Set[str] = {
    "a", "about", "above", "after", "again", "against", "all", "am", "an", "and", "any", "are",
    "aren't", "as", "at", "be", "because", "been", "before", "being", "below", "between", "both",
    "but", "by", "can't", "cannot", "could", "couldn't", "did", "didn't", "do", "does", "doesn't",
    "doing", "don't", "down", "during", "each", "few", "for", "from", "further", "had", "hadn't",
    "has", "hasn't", "have", "haven't", "having", "he", "he'd", "he'll", "he's", "her", "here",
    "here's", "hers", "herself", "him", "himself", "his", "how", "how's", "i", "i'd", "i'll",
    "i'm", "i've", "if", "in", "into", "is", "isn't", "it", "it's", "its", "itself", "let's", "me",
    "more", "most", "mustn't", "my", "myself", "no", "nor", "not", "of", "off", "on", "once",
    "only", "or", "other", "ought", "our", "ours", "ourselves", "out", "over", "own", "same",
    "shan't", "she", "she'd", "she'll", "she's", "should", "shouldn't", "so", "some", "such",
    "than", "that", "that's", "the", "their", "theirs", "them", "themselves", "then", "there",
    "there's", "these", "they", "they'd", "they'll", "they're", "they've", "this", "those",
    "through", "to", "too", "under", "until", "up", "very", "was", "wasn't", "we", "we'd", "we'll",
    "we're", "we've", "were", "weren't", "what", "what's", "when", "when's", "where", "where's",
    "which", "while", "who", "who's", "whom", "why", "why's", "with", "won't", "would",
    "wouldn't", "you", "you'd", "you'll", "you're", "you've", "your", "yours", "yourself",
    "yourselves", "the", "is", "at", "which", "on", "for", "with", "as", "by", "an"
}

# Common Bengali stop words
BENGALI_STOPWORDS: Set[str] = {
    "এই", "একটি", "ও", "এবং", "বা", "কিন্তু", "হয়", "হয়েছে", "হবে", "হলে", "ছিল", "থাকে",
    "আছে", "নেই", "না", "নি", "থেকে", "দ্বারা", "দিয়ে", "জন্য", "করে", "করা", "করতে",
    "তিনি", "তার", "তাকে", "সে", "তারা", "তাদের", "আমি", "আমরা", "আমাদের", "তুমি", "তোমরা",
    "আপনার", "আপনাদের", "যিনি", "যা", "যাহা", "যে", "যাচ্ছে", "কোন", "কোনো", "কী", "কি",
    "কেন", "কখন", "কোথায়", "কিভাবে", "সব", "সকল", "কিছু", "অনেক", "একই", "এমন", "মতো",
    "পর", "পূর্বে", "আগে", "সাথে", "সহিত", "প্রতি", "উপর", "নিচে", "মাধ্যমে"
}

# Ordered Bengali inflectional suffixes (longest first)
BENGALI_SUFFIXES = [
    "গুলো", "গুলি", "দের", "ের", "টির", "টি", "টা", "কে", "তে", "ে", "র", "য়"
]

def clean_text(text: str) -> str:
    """Conservatively clean extracted text: collapse whitespace and drop null bytes."""
    if not text:
        return ""
    # Remove null bytes and non-printable control chars except tab/newline
    text = text.replace("\x00", "")
    text = re.sub(r'[\r\f\v]+', '\n', text)
    # Collapse 3+ newlines to 2
    text = re.sub(r'\n{3,}', '\n\n', text)
    # Collapse multiple spaces
    text = re.sub(r'[ \t]+', ' ', text)
    return text.strip()

def tokenize(text: str, lowercase: bool = True, remove_stopwords: bool = False) -> List[str]:
    """Tokenize text into words preserving Bengali graphemes."""
    if not text:
        return []
    if lowercase:
        text = text.lower()
    tokens = WORD_PATTERN.findall(text)
    if remove_stopwords:
        tokens = [
            t for t in tokens 
            if t.lower() not in ENGLISH_STOPWORDS and t not in BENGALI_STOPWORDS
        ]
    return tokens

def is_bengali_word(word: str) -> bool:
    """Check if word contains Bengali characters (U+0980 to U+09FF)."""
    return any('\u0980' <= c <= '\u09ff' for c in word)

def stem_bengali(word: str) -> str:
    """Strip common Bengali inflectional suffixes if at least 2 characters remain."""
    for suffix in BENGALI_SUFFIXES:
        if word.endswith(suffix):
            stemmed = word[:-len(suffix)]
            if len(stemmed) >= 2:
                return stemmed
    return word

def stem_english(word: str) -> str:
    """Light English stemming for inflected forms (plurals, past, participle)."""
    w = word.lower()
    if len(w) <= 3:
        return w

    # Past participles / past tense
    if w.endswith("ed") and len(w) > 4:
        stem = w[:-2]
        # Doubled consonant fix (e.g., submitted -> submit, planned -> plan, stopped -> stop)
        if len(stem) >= 3 and stem[-1] == stem[-2] and stem[-1] not in "aeioulsz":
            stem = stem[:-1]
        elif stem.endswith("i"):  # e.g., modified -> modify
            stem = stem[:-1] + "y"
        return stem

    # Present continuous
    if w.endswith("ing") and len(w) > 5:
        stem = w[:-3]
        if len(stem) >= 3 and stem[-1] == stem[-2] and stem[-1] not in "aeioulsz":
            stem = stem[:-1]
        return stem

    # Plurals
    if w.endswith("ies") and len(w) > 4:
        return w[:-3] + "y"
    if w.endswith("es") and len(w) > 4:
        return w[:-2]
    if w.endswith("s") and not w.endswith("ss") and len(w) > 3:
        return w[:-1]

    return w

def stem(word: str) -> str:
    """Stem a word using Bengali or English stemmer based on characters."""
    if is_bengali_word(word):
        return stem_bengali(word)
    return stem_english(word)

def is_garbled_legacy_bangla(text: str) -> bool:
    """
    Detect legacy Bangla fonts (e.g. Bijoy / SutonnyMJ) that store Bangla as Latin-1 supplement symbols.
    Checks share of characters in U+00A1-U+00FF, U+0152-U+0178, U+2013-U+2122.
    If the proportion exceeds 12%, the text is garbled and requires OCR.
    """
    if not text or len(text.strip()) == 0:
        return False
    
    clean_chars = [c for c in text if not c.isspace()]
    if not clean_chars:
        return False
    
    garbled_count = 0
    for c in clean_chars:
        code = ord(c)
        if (0x00A1 <= code <= 0x00FF) or (0x0152 <= code <= 0x0178) or (0x2013 <= code <= 0x2122):
            garbled_count += 1
            
    ratio = garbled_count / len(clean_chars)
    return ratio > 0.12
