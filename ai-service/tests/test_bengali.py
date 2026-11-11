"""Test Bengali tokenization, stemming, and lexicon expansion."""
import pytest
from app.lexicon import expand_query, find_lexicon_matches, get_intent_flags, topic_groups
from app.textutil import is_garbled_legacy_bangla, stem, tokenize


def test_bengali_tokenizer_preserves_vowel_signs():
    # Critical test: Python \w splits Bengali at vowel signs (e.g. কবে -> কব)
    text = "এই project-এর budget কত এবং deadline কবে?"
    tokens = tokenize(text, lowercase=False)
    assert "কবে" in tokens, f"Expected 'কবে' to be preserved whole, got: {tokens}"
    assert "project-এর" not in tokens  # should split on hyphen
    assert "project" in tokens or "Project" in tokens


def test_bengali_suffix_stemmer():
    # Suffixes: ের, গুলো, কে, তে, র
    assert stem("প্রকল্পের") == "প্রকল্প"
    assert stem("প্রতিবেদনগুলো") == "প্রতিবেদন"
    assert stem("বাজেটে") == "বাজেট"
    assert stem("কর্মকর্তাকে") == "কর্মকর্তা"
    assert stem("সড়কে") == "সড়" or stem("সড়কে") == "সড়ক"
    # Word with less than 2 characters remaining should not be stripped
    assert stem("কে") == "কে"
    assert stem("যে") == "যে"


def test_english_stemmer():
    assert stem("reports") == "report"
    assert stem("submitted") == "submit"
    assert stem("planning") == "plan"
    assert stem("stopped") == "stop"


def test_lexicon_matches_and_expansion():
    # Bangla term matches English government concepts
    matches = find_lexicon_matches("বাজেট")
    assert "budget" in matches
    assert "allocation" in matches

    # Query expansion
    query = "এই project-এর budget কত এবং deadline কবে?"
    expanded = expand_query(query)
    # Original terms retained
    assert "project" in expanded
    assert "budget" in expanded
    assert "deadline" in expanded
    assert "কবে" in expanded
    # Added equivalents from lexicon
    assert "বাজেট" in expanded or "সময়সীমা" in expanded or "date" in expanded


def test_topic_groups_excludes_intent_words():
    question = "এই project-এর budget কত এবং deadline কবে?"
    groups = topic_groups(question)
    
    # Intent words (budget, deadline, কত, কবে) must NOT be topic anchors
    all_grouped_words = {w for grp in groups for w in grp}
    assert "কত" not in all_grouped_words
    assert "কবে" not in all_grouped_words
    assert "budget" not in all_grouped_words
    assert "deadline" not in all_grouped_words

    # The actual topic anchor is 'project'
    assert any("project" in grp for grp in groups)


def test_intent_flags():
    b_flag, d_flag = get_intent_flags("বাজেট কত?")
    assert b_flag is True
    assert d_flag is False

    b_flag2, d_flag2 = get_intent_flags("জমাদানের শেষ সময়সীমা কবে?")
    assert b_flag2 is False
    assert d_flag2 is True


def test_legacy_bijoy_detection():
    # Clean Unicode Bengali should NOT trigger garbled detection
    unicode_bengali = "গণপ্রজাতন্ত্রী বাংলাদেশ সরকার অর্থনৈতিক সম্পর্ক বিভাগ"
    assert is_garbled_legacy_bangla(unicode_bengali) is False

    # Clean English should NOT trigger
    english = "This is a clean English document report."
    assert is_garbled_legacy_bangla(english) is False

    # SutonnyMJ/Bijoy encoded Bangla text (represented as Latin-1 supplement symbols)
    # e.g., 'MYcÖRvZš¿x evsjv‡`k miKvi'
    garbled_text = "MYcÖRvZš¿x evsjv‡`k miKvi A_©‰bwZK m¤úK© wefvM"
    assert is_garbled_legacy_bangla(garbled_text) is True
