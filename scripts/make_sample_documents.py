"""Generates SYNTHETIC demo documents. All names, amounts and dates are fictional."""
import io, os
from reportlab.pdfgen import canvas
from PIL import Image, ImageDraw, ImageFont

OUT = os.path.join(os.path.dirname(__file__), "..", "sample-documents")
BANNER = "SYNTHETIC DEMO DOCUMENT - FICTIONAL - NOT AN OFFICIAL GOVERNMENT RECORD"

DOCS = {
"01_infrastructure_project_report.pdf": [[
 "Demo Riverside Connector Project - Progress Report",
 "Demo Ministry of Infrastructure, Planning Division",
 "Ref: PD/DRCP/2026/001", "Date: 15 January 2026",
 "Project: Demo Riverside Connector Project",
 "The Demo Riverside Connector Project builds a fictional 4.2 km connector road and bridge.",
 "The approved budget for the project is BDT 50,000,000.",
 "The completion deadline is 30 June 2027.",
 "Milestone 1 (site survey) was completed on 20 December 2025.",
 "The contractor must submit monthly progress reports to the Planning Division."],
 ["Page 2 - Implementation status",
  "Earthwork is 35 percent complete as of 15 January 2026.",
  "Risk: monsoon delays may affect the bridge piling schedule.",
  "The Planning Division shall review the risk register every month."]],
"02_budget_report_fy2026_27.pdf": [[
 "Budget Report for Fiscal Year 2026-27",
 "Demo Ministry of Infrastructure, Finance Division",
 "Ref: FD/BUD/2026/014", "Date: 01 February 2026",
 "This budget report summarises the fictional allocation for the fiscal year 2026-27.",
 "Total allocation for infrastructure: BDT 320,000,000.",
 "The budget allocated to the Demo Riverside Connector Project is BDT 50,000,000.",
 "Total expenditure to date is BDT 12,500,000."]],
"03_circular_digital_filing.pdf": [[
 "CIRCULAR - Digital Filing of Progress Reports",
 "Demo Ministry of Infrastructure", "Ref: DMI/CIR/2026/003", "Date: 10 March 2026",
 "All divisions and departments are requested to submit quarterly progress reports in digital form.",
 "Reports must be submitted by 15 April 2026.",
 "Late submissions should be explained in writing to the Secretary's office."]],
"04_policy_procurement_documentation.pdf": [[
 "Policy Guideline: Procurement Documentation Standards",
 "Demo Ministry of Infrastructure", "Ref: DMI/POL/2026/002", "Date: 05 February 2026",
 "This guideline sets documentation standards for fictional procurement files.",
 "Every procurement file shall contain a signed requisition, a cost estimate and an approval record.",
 "Officers must retain procurement records for five years.",
 "Exceptions shall be approved in writing by the Division head."]],
"05_meeting_minutes_planning_mar2026.pdf": [[
 "MINUTES OF MEETING - Planning Division Coordination",
 "Demo Ministry of Infrastructure, Planning Division", "Ref: PD/MIN/2026/007", "Date: 12 March 2026",
 "Attendees: Director (Planning), Deputy Director, Project Officer, Finance Representative.",
 "Agenda: Demo Riverside Connector Project status and risk review.",
 "Resolution 1: The project officer shall update the risk register by 30 March 2026.",
 "Resolution 2: The Planning Division is requested to confirm bridge piling schedule.",
 "The meeting noted that the project deadline remains 30 June 2027."]],
"06_revised_cost_estimate_drcp.pdf": [[
 "Revised Cost Estimate - Demo Riverside Connector Project",
 "Demo Ministry of Infrastructure, Planning Division", "Ref: PD/DRCP/2026/009", "Date: 20 April 2026",
 "Project: Demo Riverside Connector Project",
 "After a design change, the revised budget for the project is BDT 65,000,000.",
 "The revised completion deadline is 31 December 2027.",
 "This revised estimate is pending approval and conflicts with earlier documents."]],
"07_orgB_road_maintenance_budget.pdf": [[
 "Road Maintenance Budget Report",
 "Demo Local Government Authority, Works Department", "Ref: WD/BUD/2026/002", "Date: 02 March 2026",
 "Project: Demo Town Road Maintenance Programme",
 "The budget for the Demo Town Road Maintenance Programme is BDT 8,000,000.",
 "Deadline for completion is 30 September 2026. This is a different organization's document."]],
}


def banner(c):
    c.setFont("Helvetica-Oblique", 7); c.drawString(40, 820, BANNER); c.setFont("Helvetica", 11)


def write_pdf(name, pages):
    c = canvas.Canvas(os.path.join(OUT, name))
    for page in pages:
        banner(c); y = 780
        for line in page:
            c.drawString(50, y, line); y -= 20
        c.showPage()
    c.save()


os.makedirs(OUT, exist_ok=True)
for n, pages in DOCS.items():
    write_pdf(n, pages)

# Scanned (image-only) notice -> exercises OCR
img = Image.new("RGB", (1240, 700), "white"); d = ImageDraw.Draw(img)
f = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 34) if os.path.exists(
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf") else ImageFont.load_default(34)
for i, line in enumerate(["OFFICIAL NOTICE", "Demo Ministry of Infrastructure", "Ref: DMI/NOT/2026/011",
                          "Date: 05 May 2026", "The office will remain closed on 20 May 2026.",
                          "SYNTHETIC DEMO DOCUMENT - FICTIONAL"]):
    d.text((60, 60 + i * 80), line, fill="black", font=f)
img.save(os.path.join(OUT, "08_scanned_notice.pdf"), "PDF")

# Corrupted file -> failed-processing fixture
with open(os.path.join(OUT, "09_corrupted_sample.pdf"), "wb") as fh:
    fh.write(b"%PDF-1.4\nthis file is intentionally corrupted for the failed-processing demo\n")
print("done")
