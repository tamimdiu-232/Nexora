import io
from PIL import Image, ImageDraw, ImageFont
from conftest import H, upload
from app.classify import classify
from app.extraction import extract_pdf
from app.info import extract_info
from app.extraction import Page

REPORT = ["Demo Riverside Connector Project - Progress Report\nRef: PD/DRCP/2026/001\nDate: 15 January 2026\n"
          "Project: Demo Riverside Connector Project\nThe estimated cost is BDT 50,000,000.\nThe completion deadline is 30 June 2027.\n"
          "Contractors must submit monthly reports to the Planning Division."]


def test_classifier_categories():
    assert classify("MINUTES OF MEETING\nAttendees: A, B\nAgenda: budget")["category"] == "Meeting Minutes"
    assert classify("CIRCULAR\nAll divisions are requested to submit")["category"] == "Circular"
    assert classify("Lunch menu for staff canteen")["category"] == "Other"


def test_info_extraction_unknown_stays_none():
    info = extract_info([Page(1, REPORT[0], "text")])
    assert info["reference_number"] == "PD/DRCP/2026/001" and info["deadline"] == "30 June 2027"
    assert info["budget"] and "50,000,000" in info["budget"]
    info2 = extract_info([Page(1, "Just some plain sentence without fields here.", "text")])
    assert info2["reference_number"] is None and info2["budget"] is None and info2["deadline"] is None


def test_process_returns_classification_and_pages_text(client):
    r = upload(client, "r1", REPORT, "Report").json()
    assert r["classification"]["category"] == "Project Report"
    assert r["pages"][0]["text"] and r["info"]["reference_number"]


def test_summary_and_authorization(client):
    upload(client, "r1", REPORT, "Report"); upload(client, "r2", ["Other doc text about schools and teachers."])
    ok = client.post("/v1/summarize", headers=H, json={"document_id": "r1", "authorized_document_ids": ["r1"]})
    assert ok.status_code == 200 and ok.json()["short"] and ok.json()["short"][0]["page"] == 1
    assert ok.json()["action_items"]
    assert client.post("/v1/summarize", headers=H, json={"document_id": "r2", "authorized_document_ids": ["r1"]}).status_code == 403
    assert client.post("/v1/summarize", headers=H, json={"document_id": "zz", "authorized_document_ids": ["zz"]}).status_code == 404


def test_related_only_authorized(client):
    upload(client, "a", ["Bridge project budget approved for the riverside connector road construction."], "A")
    upload(client, "b", ["Riverside connector road construction progress and bridge project budget revision."], "B")
    upload(client, "c", ["Riverside connector road bridge budget confidential other organization."], "C")
    r = client.post("/v1/related", headers=H, json={"document_id": "a", "authorized_document_ids": ["a", "b"]}).json()
    ids = [x["document_id"] for x in r["results"]]
    assert ids == ["b"]


def test_ocr_on_scanned_pdf():
    img = Image.new("RGB", (1240, 400), "white"); d = ImageDraw.Draw(img)
    try:
        f = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 40)
    except OSError:
        f = ImageFont.load_default(40)
    d.text((40, 100), "OFFICIAL NOTICE: Office closed on 5 May 2026", fill="black", font=f)
    buf = io.BytesIO(); img.save(buf, "PDF")
    pages = extract_pdf(buf.getvalue())
    assert pages[0].method == "ocr" and "NOTICE" in pages[0].text.upper()
