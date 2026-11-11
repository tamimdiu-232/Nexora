"""Test document classification, entity extraction, summarization, and related documents."""
import pytest
from app.chunking import chunk_document
from app.classify import classify_document
from app.extraction import ExtractedPage
from app.info import extract_document_info
from app.summarize import summarize_document
from app.vector_store import global_vector_store


def test_classification_categories():
    # Test circular
    circular_page = ExtractedPage(
        number=1,
        text="পরিপত্র\nস্মারক নং: ০৫.০০.০০০০\nসকল কর্মকর্তা ও কর্মচারীদের ডিজিটাল ফাইলিং নির্দেশিকা অনুসরণ করতে হবে।",
        method="text"
    )
    res = classify_document([circular_page])
    assert res["category"] == "Circular"
    assert res["confidence"] > 0.5

    # Test meeting minutes
    minutes_page = ExtractedPage(
        number=1,
        text="Minutes of the 14th Project Steering Committee Meeting held on 12 March 2026.",
        method="text"
    )
    res_m = classify_document([minutes_page])
    assert res_m["category"] == "Meeting Minutes"


def test_info_extraction_unknown_stays_none():
    empty_page = ExtractedPage(number=1, text="General meeting discussion notes.", method="text")
    info = extract_document_info([empty_page])
    # Budget, deadline, project_name must stay None, not guessed
    assert info["budget"] is None
    assert info["deadline"] is None
    assert info["project_name"] is None
    assert info["reference_number"] is None


def test_info_extraction_populated():
    page = ExtractedPage(
        number=1,
        text=(
            "Ministry of Infrastructure\n"
            "Project: Demo Riverside Connector Project\n"
            "Ref: PD/DRCP/2026/001\n"
            "Date: 15 March 2026\n"
            "Total estimated project budget: BDT 50,000,000\n"
            "Project completion deadline: 30 June 2027\n"
        ),
        method="text"
    )
    info = extract_document_info([page])
    assert info["project_name"] == "Demo Riverside Connector Project"
    assert info["reference_number"] == "PD/DRCP/2026/001"
    assert "50,000,000" in info["budget"]
    assert "30 June 2027" in info["deadline"]
    assert len(info["keywords"]) > 0


def test_summarization_hierarchical_and_action_items():
    pages = [
        ExtractedPage(
            number=1,
            text=(
                "Ministry of Environment Circular 2026.\n"
                "All regional offices are directed to submit quarterly environmental compliance reports.\n"
                "The target completion date for solar installation is 15 October 2026."
            ),
            method="text"
        ),
        ExtractedPage(
            number=2,
            text=(
                "Budget allocation for the green initiative is BDT 12,000,000.\n"
                "প্রতিটি আঞ্চলিক কার্যালয়কে অবশ্যই ১৫ ডিসেম্বরের মধ্যে প্রতিবেদন জমা দিতে হবে।"
            ),
            method="text"
        )
    ]
    summary = summarize_document(pages)
    assert len(summary["short"]) > 10
    assert len(summary["detailed"]) > 10
    assert len(summary["key_points"]) >= 1

    # Check action items extraction (obligation phrases)
    assert len(summary["action_items"]) >= 1
    # Check that either English or Bangla obligation sentence is captured
    actions_text = " ".join(a["text"] for a in summary["action_items"])
    assert "directed to submit" in actions_text or "জমা দিতে হবে" in actions_text


def test_atomic_reprocessing_no_duplicate_chunks():
    pages = [ExtractedPage(number=1, text="Document version 1 text content.", method="text")]
    chunks = chunk_document("doc-test-1", pages)
    
    # Process first time
    global_vector_store.add_or_replace_document(
        document_id="doc-test-1",
        title="Test Doc",
        category="Policy or Guideline",
        keywords=["policy"],
        chunks=chunks
    )
    assert len(global_vector_store.chunks) == 1

    # Reprocess with updated text
    pages_v2 = [
        ExtractedPage(number=1, text="Document version 2 text content.", method="text"),
        ExtractedPage(number=2, text="Document version 2 page 2.", method="text")
    ]
    chunks_v2 = chunk_document("doc-test-1", pages_v2)
    global_vector_store.add_or_replace_document(
        document_id="doc-test-1",
        title="Test Doc V2",
        category="Policy or Guideline",
        keywords=["policy", "v2"],
        chunks=chunks_v2
    )

    # Must have exactly 2 chunks, not 1 + 2 = 3
    assert len(global_vector_store.chunks) == 2
    assert len(global_vector_store.documents) == 1


def test_related_documents_centroid_similarity():
    # Doc A: Procurement policy
    chunks_a = chunk_document("doc-a", [ExtractedPage(1, "Procurement policy rules for tender bidding and vendor selection.", "text")])
    global_vector_store.add_or_replace_document("doc-a", "Procurement Policy", "Policy or Guideline", ["procurement", "tender", "vendor"], chunks_a)

    # Doc B: Tender evaluation guidelines (same category, shared keywords)
    chunks_b = chunk_document("doc-b", [ExtractedPage(1, "Tender evaluation guidelines and vendor selection criteria.", "text")])
    global_vector_store.add_or_replace_document("doc-b", "Tender Guidelines", "Policy or Guideline", ["tender", "vendor", "guidelines"], chunks_b)

    # Doc C: Unrelated agriculture report
    chunks_c = chunk_document("doc-c", [ExtractedPage(1, "Wheat and paddy harvesting statistics for rural farmers.", "text")])
    global_vector_store.add_or_replace_document("doc-c", "Harvesting Statistics", "Project Report", ["wheat", "paddy", "farming"], chunks_c)

    # Find related for Doc A within authorized [doc-a, doc-b, doc-c]
    related = global_vector_store.find_related_documents(
        document_id="doc-a",
        authorized_document_ids=["doc-a", "doc-b", "doc-c"]
    )
    assert len(related) > 0
    # Doc B must be ranked higher than Doc C
    assert related[0]["document_id"] == "doc-b"
    assert "tender" in related[0]["shared_keywords"] or "vendor" in related[0]["shared_keywords"]
