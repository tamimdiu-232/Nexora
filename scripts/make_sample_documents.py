#!/usr/bin/env python3
"""
Generate synthetic demo PDF documents for Nexora test and demonstration scenarios.
Uses ReportLab and Pillow to create digital and scanned PDFs.
Every document displays a prominent "SYNTHETIC DEMO DOCUMENT" disclaimer header.
"""

import os
import sys
from reportlab.lib.pagesizes import letter
from reportlab.lib import colors
from reportlab.pdfgen import canvas
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle
from PIL import Image, ImageDraw, ImageFont

OUTPUT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "sample-documents"))


def draw_watermark(canvas_obj, doc):
    canvas_obj.saveState()
    canvas_obj.setFont("Helvetica-Bold", 8)
    canvas_obj.setFillColor(colors.HexColor("#EF4444"))
    canvas_obj.drawCentredString(letter[0] / 2.0, letter[1] - 25, "SYNTHETIC DEMO DOCUMENT — FOR DEMONSTRATION PURPOSES ONLY — NOT AN OFFICIAL GOVERNMENT RECORD")
    canvas_obj.setStrokeColor(colors.HexColor("#E2E8F0"))
    canvas_obj.setLineWidth(0.5)
    canvas_obj.line(54, letter[1] - 32, letter[0] - 54, letter[1] - 32)
    canvas_obj.restoreState()


def create_riverside_progress_report():
    """Doc 1: Infrastructure project progress report"""
    path = os.path.join(OUTPUT_DIR, "demo_riverside_connector_report.pdf")
    doc = SimpleDocTemplate(path, pagesize=letter, leftMargin=54, rightMargin=54, topMargin=54, bottomMargin=54)
    styles = getSampleStyleSheet()
    story = []

    story.append(Paragraph("<b>Government of Bangladesh (Synthetic Demo)</b>", styles['Normal']))
    story.append(Paragraph("<b>Ministry of Infrastructure — Planning Division</b>", styles['Normal']))
    story.append(Spacer(1, 15))
    story.append(Paragraph("<b>PROJECT PROGRESS REPORT: FY 2025-26</b>", styles['Title']))
    story.append(Spacer(1, 10))

    meta_data = [
        ["Project Name:", "Demo Riverside Connector Project"],
        ["Reference Number:", "PD/DRCP/2026/001"],
        ["Issuing Authority:", "Ministry of Infrastructure, Planning Division"],
        ["Document Date:", "15 March 2026"],
        ["Total Approved Budget:", "BDT 50,000,000"],
        ["Completion Deadline:", "30 June 2027"],
    ]
    t = Table(meta_data, colWidths=[160, 340])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#F8FAFC')),
        ('TEXTCOLOR', (0, 0), (-1, -1), colors.HexColor('#0F172A')),
        ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#CBD5E1')),
    ]))
    story.append(t)
    story.append(Spacer(1, 15))

    story.append(Paragraph("<b>Executive Summary & Milestones</b>", styles['Heading2']))
    story.append(Paragraph(
        "The Demo Riverside Connector Project is aimed at establishing a four-lane bypass connecting eastern economic zones. "
        "The total approved budget for the project is BDT 50,000,000. Physical earthwork and pillar casting have achieved "
        "forty percent completion as of the first quarter of 2026. All procurement packages have been sanctioned. "
        "The final completion deadline for handing over the completed connector road is 30 June 2027.",
        styles['Normal']
    ))
    story.append(Spacer(1, 10))
    story.append(Paragraph(
        "Quarterly expenditure stands at BDT 18,500,000. Regular site safety inspections are conducted weekly by "
        "the engineering supervision team. Construction quality tests for concrete compressive strength meet all designated specifications.",
        styles['Normal']
    ))

    doc.build(story, onFirstPage=draw_watermark, onLaterPages=draw_watermark)
    print(f"Created: {path}")


def create_budget_report():
    """Doc 2: FY 2026-27 budget report"""
    path = os.path.join(OUTPUT_DIR, "demo_fy26_budget_report.pdf")
    doc = SimpleDocTemplate(path, pagesize=letter, leftMargin=54, rightMargin=54, topMargin=54, bottomMargin=54)
    styles = getSampleStyleSheet()
    story = []

    story.append(Paragraph("<b>Ministry of Infrastructure — Finance Wing</b>", styles['Normal']))
    story.append(Paragraph("<b>ANNUAL BUDGET ALLOCATION REPORT (FY 2026-27)</b>", styles['Title']))
    story.append(Spacer(1, 15))

    meta_data = [
        ["Project Title:", "Demo Riverside Connector Project"],
        ["Ref:", "MOI/FIN/BUDGET/2026-04"],
        ["Date:", "01 April 2026"],
        ["Fiscal Allocation:", "BDT 50,000,000"],
    ]
    t = Table(meta_data, colWidths=[160, 340])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#F8FAFC')),
        ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#CBD5E1')),
    ]))
    story.append(t)
    story.append(Spacer(1, 15))

    story.append(Paragraph(
        "This financial statement outlines the verified fiscal allocation for ongoing capital works. "
        "The approved financial ceiling for the Demo Riverside Connector Project remains fixed at BDT 50,000,000. "
        "Funds are disbursed in four equal installments contingent upon field verification.",
        styles['Normal']
    ))

    doc.build(story, onFirstPage=draw_watermark, onLaterPages=draw_watermark)
    print(f"Created: {path}")


def create_circular_digital_filing():
    """Doc 3: Circular on digital filing (Bangla and English cues)"""
    path = os.path.join(OUTPUT_DIR, "circular_digital_filing.pdf")
    doc = SimpleDocTemplate(path, pagesize=letter, leftMargin=54, rightMargin=54, topMargin=54, bottomMargin=54)
    styles = getSampleStyleSheet()
    story = []

    story.append(Paragraph("<b>Government of Bangladesh — Ministry of Infrastructure</b>", styles['Normal']))
    story.append(Spacer(1, 10))
    story.append(Paragraph("<b>OFFICIAL CIRCULAR: DIGITAL FILING PROTOCOL</b>", styles['Title']))
    story.append(Paragraph("<b>স্মারক নং: ০৫.০০.০০০০.১০১.২২.০০১.২৬</b>", styles['Normal']))
    story.append(Paragraph("Date: 15 January 2026", styles['Normal']))
    story.append(Spacer(1, 15))

    story.append(Paragraph(
        "All departmental officers and administrative personnel are hereby notified that physical paper routing "
        "for project documentation is transitioning to electronic records. "
        "Every project report, memo, and progress sheet must be archived in digital format. "
        "The submission deadline for full electronic compliance is 15 April 2026. "
        "Failure to adhere to this guideline shall result in administrative review.",
        styles['Normal']
    ))
    story.append(Spacer(1, 10))
    story.append(Paragraph(
        "প্রতিটি আঞ্চলিক কার্যালয়কে অবশ্যই ১৫ এপ্রিল ২০২৬ এর মধ্যে ডিজিটাল ফাইলিং প্রতিবেদন জমা দিতে হবে।",
        styles['Normal']
    ))

    doc.build(story, onFirstPage=draw_watermark, onLaterPages=draw_watermark)
    print(f"Created: {path}")


def create_procurement_policy():
    """Doc 4: Procurement documentation policy"""
    path = os.path.join(OUTPUT_DIR, "procurement_policy_guideline.pdf")
    doc = SimpleDocTemplate(path, pagesize=letter, leftMargin=54, rightMargin=54, topMargin=54, bottomMargin=54)
    styles = getSampleStyleSheet()
    story = []

    story.append(Paragraph("<b>STANDARD OPERATING PROCEDURE: PROCUREMENT POLICY</b>", styles['Title']))
    story.append(Paragraph("Policy Guideline Ref: SOP/PROC/2026", styles['Normal']))
    story.append(Spacer(1, 15))
    story.append(Paragraph(
        "This policy guideline establishes mandatory competitive bidding procedures for goods and civil works exceeding "
        "BDT 10,000,000. Tender evaluation committees must publish technical scores prior to opening financial proposals. "
        "All documentation must be retained for at least seven fiscal years.",
        styles['Normal']
    ))

    doc.build(story, onFirstPage=draw_watermark, onLaterPages=draw_watermark)
    print(f"Created: {path}")


def create_meeting_minutes():
    """Doc 5: Meeting minutes"""
    path = os.path.join(OUTPUT_DIR, "minutes_steering_committee_14.pdf")
    doc = SimpleDocTemplate(path, pagesize=letter, leftMargin=54, rightMargin=54, topMargin=54, bottomMargin=54)
    styles = getSampleStyleSheet()
    story = []

    story.append(Paragraph("<b>MINUTES OF THE 14TH PROJECT STEERING COMMITTEE MEETING</b>", styles['Title']))
    story.append(Paragraph("Date of Meeting: 12 March 2026 | Venue: Conference Hall B", styles['Normal']))
    story.append(Spacer(1, 15))
    story.append(Paragraph(
        "The 14th meeting of the Steering Committee reviewed physical progress of ongoing infrastructure schemes. "
        "The Project Director presented progress for the Demo Riverside Connector Project. "
        "Resolved that land acquisition compensation shall be accelerated in sector 4. "
        "Next review meeting scheduled for 10 May 2026.",
        styles['Normal']
    ))

    doc.build(story, onFirstPage=draw_watermark, onLaterPages=draw_watermark)
    print(f"Created: {path}")


def create_revised_cost_estimate():
    """Doc 6: Revised cost estimate (creates the conflicting evidence case: BDT 65,000,000 vs 50,000,000)"""
    path = os.path.join(OUTPUT_DIR, "revised_cost_estimate_riverside.pdf")
    doc = SimpleDocTemplate(path, pagesize=letter, leftMargin=54, rightMargin=54, topMargin=54, bottomMargin=54)
    styles = getSampleStyleSheet()
    story = []

    story.append(Paragraph("<b>Ministry of Infrastructure — Planning Division</b>", styles['Normal']))
    story.append(Paragraph("<b>REVISED ADMINISTRATIVE COST ESTIMATE</b>", styles['Title']))
    story.append(Spacer(1, 10))

    meta_data = [
        ["Project Name:", "Demo Riverside Connector Project"],
        ["Reference:", "PD/DRCP/REV/2026/088"],
        ["Document Date:", "20 August 2026"],
        ["Original Approved Budget:", "BDT 50,000,000"],
        ["Revised Project Budget:", "BDT 65,000,000"],
        ["Extended Deadline:", "31 December 2027"],
    ]
    t = Table(meta_data, colWidths=[160, 340])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#FEF2F2')),
        ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
        ('TEXTCOLOR', (1, 4), (1, 4), colors.HexColor('#DC2626')),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#CBD5E1')),
    ]))
    story.append(t)
    story.append(Spacer(1, 15))

    story.append(Paragraph(
        "Due to inflationary price escalation in reinforcing steel, structural cement, and additional riverbank piling, "
        "the revised project budget for the Demo Riverside Connector Project is officially amended to BDT 65,000,000. "
        "The project completion deadline has been formally extended to 31 December 2027.",
        styles['Normal']
    ))

    doc.build(story, onFirstPage=draw_watermark, onLaterPages=draw_watermark)
    print(f"Created: {path}")


def create_org_b_road_budget():
    """Doc 7: Organization B document (isolated tenant case)"""
    path = os.path.join(OUTPUT_DIR, "org_b_municipal_road_budget.pdf")
    doc = SimpleDocTemplate(path, pagesize=letter, leftMargin=54, rightMargin=54, topMargin=54, bottomMargin=54)
    styles = getSampleStyleSheet()
    story = []

    story.append(Paragraph("<b>Demo Local Government Authority (Synthetic)</b>", styles['Normal']))
    story.append(Paragraph("<b>Works Department — Municipal Maintenance Budget</b>", styles['Title']))
    story.append(Spacer(1, 15))

    meta_data = [
        ["Authority:", "Demo Local Government Authority"],
        ["Scheme:", "Rural Wards Road Maintenance 2026"],
        ["Budget Allocation:", "BDT 8,500,000"],
        ["Deadline:", "15 November 2026"],
    ]
    t = Table(meta_data, colWidths=[160, 340])
    t.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#F8FAFC')),
        ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#CBD5E1')),
    ]))
    story.append(t)
    story.append(Spacer(1, 15))

    story.append(Paragraph(
        "Budget allocation for internal brick-soling and bituminous overlay across rural wards in Sector 2. "
        "Total municipal allocation is BDT 8,500,000. Strictly isolated to Local Government Authority operations.",
        styles['Normal']
    ))

    doc.build(story, onFirstPage=draw_watermark, onLaterPages=draw_watermark)
    print(f"Created: {path}")


def create_scanned_image_notice():
    """Doc 8: Scanned image-only notice (Requires OCR via pdftoppm + Tesseract)"""
    path = os.path.join(OUTPUT_DIR, "scanned_official_notice.pdf")

    # Render a high-resolution 300 DPI text image with Pillow
    img = Image.new("RGB", (2479, 3508), color=(255, 255, 255))
    draw = ImageDraw.Draw(img)

    # Header banner
    draw.rectangle([0, 0, 2479, 120], fill=(239, 68, 68))
    draw.text((300, 40), "SYNTHETIC DEMO DOCUMENT — SCANNED NOTICE (OCR TEST CASE)", fill=(255, 255, 255))

    # Document body text
    body_text = (
        "GOVERNMENT OF BANGLADESH (SYNTHETIC DEMO)\n"
        "MINISTRY OF INFRASTRUCTURE — PLANNING DIVISION\n\n"
        "OFFICIAL NOTICE REGARDING CONTRACTOR REGISTRATION\n"
        "Ref No: MOI/PROC/REG/2026/09\n"
        "Date: 10 February 2026\n\n"
        "Notice is hereby given that all contractors registered under Class-1 categories\n"
        "must update their tax clearance and equipment audit records.\n"
        "The submission deadline for contractor renewals is 25 May 2026.\n"
        "Total license renewal processing fee is BDT 25,000 per firm.\n"
        "Failure to submit renewal documents by the due date shall result in forfeiture."
    )
    draw.text((200, 400), body_text, fill=(15, 23, 42), spacing=30)

    # Save as image-only PDF
    img.save(path, "PDF", resolution=300.0)
    print(f"Created: {path}")


def create_corrupted_pdf():
    """Doc 9: Corrupted PDF file (Failed processing case)"""
    path = os.path.join(OUTPUT_DIR, "corrupt_sample.pdf")
    with open(path, "wb") as f:
        # Invalid PDF header and truncated corrupt data
        f.write(b"%PDF-1.4\nCorrupted content with invalid xref and truncated binary data \x00\xff\xfe\xca\xfe")
    print(f"Created: {path}")


def create_scanned_bangla_notice():
    """Doc 10: Scanned Bangla notice"""
    path = os.path.join(OUTPUT_DIR, "scanned_bangla_notice.pdf")

    img = Image.new("RGB", (2479, 3508), color=(255, 255, 255))
    draw = ImageDraw.Draw(img)

    draw.rectangle([0, 0, 2479, 120], fill=(239, 68, 68))
    draw.text((200, 40), "SYNTHETIC DEMO DOCUMENT — BANGLA SCANNED NOTICE", fill=(255, 255, 255))

    # Look for a Bengali font on macOS
    font_candidates = [
        "/System/Library/Fonts/Supplemental/Bangla Sangam MN.ttc",
        "/System/Library/Fonts/Supplemental/KohinoorBangla.ttc",
        "/Library/Fonts/NotoSansBengali-Regular.ttf",
        "/System/Library/Fonts/Apple Color Emoji.ttc",
    ]
    bengali_font = None
    for fc in font_candidates:
        if os.path.exists(fc):
            try:
                bengali_font = ImageFont.truetype(fc, 60)
                break
            except Exception:
                continue

    if bengali_font:
        bengali_text = (
            "গণপ্রজাতন্ত্রী বাংলাদেশ সরকার (সিন্থেটিক ডেমো)\n"
            "পরিকল্পনা বিভাগ — ঢাকা\n\n"
            "জরুরি অফিস বিজ্ঞপ্তি\n"
            "স্মারক নং: ৪৯.০০.০০০০.১০২.২৬.০১৫\n"
            "তারিখ: ১০ মার্চ ২০২৬\n\n"
            "এতদ্বারা সংশ্লিষ্ট সকলের অবগতির জন্য জানানো যাচ্ছে যে,\n"
            "ডেমো রিভারসাইড কানেক্টর প্রকল্পের মোট অনুমোদিত বাজেট ৫০,০০,০০০ টাকা।\n"
            "প্রকল্পের কাজ সমাপ্তির চূড়ান্ত সময়সীমা ৩০ জুন ২০২৭ পর্যন্ত নির্ধারিত রয়েছে।\n"
            "সকল কর্মকর্তা ও কর্মচারীকে নির্ধারিত সময়সীমার মধ্যে কাজ সম্পন্ন করার অনুরোধ করা হলো।"
        )
        draw.text((200, 400), bengali_text, fill=(15, 23, 42), font=bengali_font, spacing=40)
    else:
        # Fallback to English notice with Bangla transliteration
        fallback_text = (
            "BANGLADESH GOVERNMENT (SYNTHETIC NOTICE)\n"
            "PLANNING DIVISION\n\n"
            "OFFICE NOTICE: DEMO RIVERSIDE CONNECTOR PROJECT\n"
            "Project Budget: BDT 50,000,000 (50 Lakh Taka)\n"
            "Completion Deadline: 30 June 2027\n"
            "Somoy-sima: 30 June 2027."
        )
        draw.text((200, 400), fallback_text, fill=(15, 23, 42), spacing=30)

    img.save(path, "PDF", resolution=300.0)
    print(f"Created: {path}")


def main():
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    print(f"Generating synthetic demo documents in: {OUTPUT_DIR}")

    create_riverside_progress_report()
    create_budget_report()
    create_circular_digital_filing()
    create_procurement_policy()
    create_meeting_minutes()
    create_revised_cost_estimate()
    create_org_b_road_budget()
    create_scanned_image_notice()
    create_corrupted_pdf()
    create_scanned_bangla_notice()

    print("All 10 synthetic demo documents generated successfully.")


if __name__ == "__main__":
    main()
