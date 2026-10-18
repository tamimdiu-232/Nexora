from conftest import H, upload
from app.lexicon import translate
from app.rag import _stem

DOC = ["Project: Riverside Link Project\nThe approved budget for the project is BDT 50,000,000.\nThe completion deadline is 30 June 2027.",
       "Reports must be submitted by 15 April 2026."]


def qa(client, q, ids=("d",)):
    return client.post("/v1/qa", headers=H, json={"question": q, "authorized_document_ids": list(ids)}).json()


def test_translate_handles_inflected_bangla_and_keeps_unknown_words():
    t = translate("এই প্রকল্পের বাজেট কত এবং সময়সীমা কবে? ZZZ")
    assert "project" in t and "budget" in t and "deadline" in t and "ZZZ" in t
    assert translate("নীতিমালা").startswith("policy")      # longest key wins over "নীতি"


def test_stemmer_matches_submit_and_submitted():
    assert _stem("submitted") == _stem("submit")


def test_pure_bangla_question_is_answered_from_english_document(client):
    upload(client, "d", DOC, "Report")
    r = qa(client, "এই প্রকল্পের বাজেট কত এবং সময়সীমা কবে?")
    assert r["status"] == "answered" and "50,000,000" in r["answer"] and "30 June 2027" in r["answer"]


def test_bangla_question_about_submission_date(client):
    upload(client, "d", DOC, "Report")
    r = qa(client, "প্রতিবেদন জমার শেষ তারিখ কবে?")
    assert r["status"] == "answered" and "15 April 2026" in r["answer"]


def test_bangla_question_without_evidence_is_still_insufficient(client):
    upload(client, "d", DOC, "Report")
    assert qa(client, "ক্যান্টিনের খাবারের দাম কত?")["status"] == "insufficient_evidence"
    assert qa(client, "সড়ক রক্ষণাবেক্ষণের বাজেট কত?")["status"] == "insufficient_evidence"


def test_bangla_question_respects_authorization(client):
    upload(client, "secret", ["Project: Hidden Project\nThe budget for the project is BDT 999,999,999."], "S")
    upload(client, "d", DOC, "Report")
    assert "999,999,999" not in str(qa(client, "প্রকল্পের বাজেট কত?", ids=("d",)))
