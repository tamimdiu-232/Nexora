"""Bilingual administrative and government lexicon for query expansion and topic grouping."""
from typing import Dict, List, Set, Tuple
from app.textutil import stem, tokenize, is_bengali_word

# Government and administrative term mappings (Bangla stem -> English concepts)
GOVERNMENT_LEXICON: Dict[str, List[str]] = {
    # Project & Infrastructure
    "প্রকল্প": ["project", "initiative", "scheme"],
    "উন্নয়ন": ["development", "improvement"],
    "পরিকল্পনা": ["plan", "planning"],
    "অবকাঠামো": ["infrastructure"],
    "বাস্তবায়ন": ["implementation", "execution"],
    "পূর্ত": ["works", "construction"],
    "সড়ক": ["road", "highway"],
    "সেতু": ["bridge"],
    "ভবন": ["building"],
    
    # Financial & Budget
    "বাজেট": ["budget", "allocation", "financial"],
    "বরাদ্দ": ["allocation", "allotted", "grant"],
    "ব্যয়": ["expenditure", "cost", "spending"],
    "খরচ": ["cost", "expense", "expenditure"],
    "অর্থ": ["fund", "finance", "money"],
    "টাকা": ["bdt", "taka", "amount"],
    "প্রাক্কলিত": ["estimated", "estimate"],
    "সংশোধিত": ["revised", "amended"],
    
    # Timeline & Deadlines
    "সময়সীমা": ["deadline", "timeline", "timeframe"],
    "মেয়াদ": ["tenure", "duration", "period", "deadline"],
    "তারিখ": ["date"],
    "সমাপ্তি": ["completion", "finish", "conclusion"],
    "শুরু": ["commencement", "start", "beginning"],
    
    # Documentation & Administrative
    "প্রতিবেদন": ["report", "statement"],
    "পরিপত্র": ["circular"],
    "বিজ্ঞপ্তি": ["notice", "notification"],
    "স্মারক": ["reference", "ref", "memo"],
    "কার্যবিবরণী": ["minutes", "proceedings"],
    "আদেশ": ["order", "directive"],
    "নির্দেশিকা": ["guideline", "instruction"],
    "নীতিমালা": ["policy", "guidelines"],
    "প্রকিউরমেন্ট": ["procurement", "purchase"],
    "টেন্ডার": ["tender", "bid", "bidding"],
    "চুক্তি": ["contract", "agreement"],
    
    # Administrative Actions
    "জমা": ["submit", "submission", "deposit"],
    "অনুমোদন": ["approval", "approved", "sanction"],
    "দাখিল": ["submission", "submit"],
    "যাচাই": ["verification", "verify", "audit"],
    "পর্যালোচনা": ["review", "scrutiny"],
    "কমিটি": ["committee", "board"],
    "সভা": ["meeting", "conference"],
    "মন্ত্রণালয়": ["ministry"],
    "বিভাগ": ["division", "department"],
    "অধিদপ্তর": ["directorate", "department"],
    "কার্যালয়": ["office"],
    "কর্তৃপক্ষ": ["authority"],
    "পরিদর্শন": ["inspection", "visit"]
}

# Reverse lookup dictionary (English -> Bangla stems)
ENGLISH_TO_BANGLA: Dict[str, List[str]] = {}
for b_term, e_terms in GOVERNMENT_LEXICON.items():
    for e_term in e_terms:
        if e_term not in ENGLISH_TO_BANGLA:
            ENGLISH_TO_BANGLA[e_term] = []
        ENGLISH_TO_BANGLA[e_term].append(b_term)

# Intent indicator words (asking for quantity, date, budget, status)
# These are treated separately from topic identifiers so they do not count as topic anchors.
INTENT_WORDS: Set[str] = {
    # Question interrogatives
    "কত", "কবে", "কী", "কি", "কোন", "কোথায়", "কেমন", "কেন", "কার",
    "what", "when", "where", "how", "much", "many", "which", "who", "why",
    # Target intent indicators
    "বাজেট", "খরচ", "ব্যয়", "টাকা", "বরাদ্দ",
    "budget", "cost", "expenditure", "allocation", "price",
    "সময়সীমা", "মেয়াদ", "তারিখ", "ডেডলাইন", "শেষ",
    "deadline", "date", "timeline", "duration", "submission"
}

def find_lexicon_matches(term: str) -> List[str]:
    """Find matching English concepts for a Bangla term using prefix/stem matching."""
    matches = set()
    stemmed = stem(term)
    
    # Direct match
    if term in GOVERNMENT_LEXICON:
        matches.update(GOVERNMENT_LEXICON[term])
    if stemmed in GOVERNMENT_LEXICON:
        matches.update(GOVERNMENT_LEXICON[stemmed])
        
    # Prefix match across keys
    for b_key, e_vals in GOVERNMENT_LEXICON.items():
        if term.startswith(b_key) or b_key.startswith(stemmed):
            matches.update(e_vals)
            
    # Also check if it's an English term mapping to Bangla
    lower_term = term.lower()
    if lower_term in ENGLISH_TO_BANGLA:
        matches.update(ENGLISH_TO_BANGLA[lower_term])
        
    return list(matches)

def expand_query(question: str) -> str:
    """
    Expand a query by retaining all original tokens and appending bilingual equivalents.
    Ensures queries like 'এই project-এর budget কত এবং deadline কবে?' retrieve both English and Bangla chunks.
    """
    tokens = tokenize(question, lowercase=True, remove_stopwords=False)
    expanded_terms = list(tokens)
    
    for t in tokens:
        matches = find_lexicon_matches(t)
        for m in matches:
            if m not in expanded_terms:
                expanded_terms.append(m)
                
    return " ".join(expanded_terms)

def get_intent_flags(question: str) -> Tuple[bool, bool]:
    """Check if question has budget/cost intent or deadline/date intent."""
    tokens = [stem(t.lower()) for t in tokenize(question)]
    
    budget_intent = any(
        t in {"বাজেট", "খরচ", "ব্যয়", "টাকা", "বরাদ্দ", "budget", "cost", "expenditure", "allocation", "amount"}
        for t in tokens
    )
    deadline_intent = any(
        t in {"সময়সীমা", "মেয়াদ", "তারিখ", "ডেডলাইন", "deadline", "date", "timeline", "due", "when", "কবে"}
        for t in tokens
    )
    return budget_intent, deadline_intent

def topic_groups(question: str) -> List[Set[str]]:
    """
    Group each content word of the question with its synonyms/translations,
    excluding pure intent/interrogative words and stopwords.
    Used for topic-coverage filtering in RAG.
    """
    tokens = tokenize(question, lowercase=True, remove_stopwords=True)
    groups = []
    
    for t in tokens:
        stemmed = stem(t)
        # Skip pure intent and interrogative words
        if stemmed in INTENT_WORDS or t in INTENT_WORDS:
            continue
            
        group = {t, stemmed}
        # Add lexicon matches
        equivalents = find_lexicon_matches(t)
        for eq in equivalents:
            group.add(eq)
            group.add(stem(eq))
            
        if group:
            groups.append(group)
            
    return groups
