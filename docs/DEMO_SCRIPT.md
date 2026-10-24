# Nexora — Master Demonstration Script

This script serves as the complete demonstration and video walkthrough guide for Nexora.

---

## Step-by-Step Walkthrough

### Step 1: Officer Experience & Dashboard
- Sign in as `officer.planning@demo-a.test` with password `Password@123`.
- View the Planning department dashboard: document counts, recent uploads, and quick actions.

### Step 2: Upload & Extraction
- Navigate to **Documents** -> **Upload Document**.
- Upload synthetic PDF: `demo_riverside_connector_report.pdf`.
- Observe status indicator transition from `pending` -> `processing` -> `completed`.
- Open document details: review extracted category (`Project Report`), reference number (`PD/DRCP/2026/001`), budget (`50,000,000`), deadline (`30 June 2027`), and prominent `Extracted information (unverified)` labels.

### Step 3: Keyword & Semantic Search
- Navigate to **Search**.
- Perform a Keyword search for `riverside connector budget`.
- Switch toggle to **Semantic Search**.
- Observe relevant snippets and click **Open page 1** to inspect the PDF preview inline at page 1.

### Step 4: Bilingual Grounded Q&A with Conflicting Evidence
- Navigate to **Ask AI**.
- Submit the query: `এই project-এর budget কত এবং deadline কবে?`
- Inspect the AI output:
  - Answers with citations `[1]` and `[2]`.
  - Flags **Conflicting Evidence**: displays original estimate (BDT 50,000,000 / 30 June 2027) versus revised cost estimate (BDT 65,000,000 / 31 December 2027).
  - Click on citation `[1]` to jump directly to page 1 of the report.

### Step 5: Pure Bangla Query & Scanned Document OCR
- Submit pure Bangla query: `এই প্রকল্পের বাজেট কত এবং সময়সীমা কবে?`
- Verify grounded answer with Bengali page references.
- Upload scanned Bengali notice `scanned_bangla_notice.pdf` (processed via 300 DPI Tesseract OCR).
- Ask question regarding the notice to demonstrate OCR-derived Q&A.

### Step 6: Missing Evidence Handling
- Submit an unrelated question: `হিমালয়ের সর্বোচ্চ শৃঙ্গের উচ্চতা কত?` (or unrelated project question).
- Verify the system safely returns `insufficient_evidence` with no hallucinated figures.

### Step 7: Human Verification Workflow
- From the Ask AI answer bubble, click **Submit for human review**.
- Log out and sign in as `reviewer@demo-a.test`.
- Navigate to **Reviews**: inspect the submitted question, AI answer, and citations.
- Approve the review with status **Verified**.

### Step 8: Cross-Department Isolation
- Sign in as `viewer.finance@demo-a.test`.
- Verify Planning department documents are absent from the document list and search.
- Directly attempt to navigate to the Planning document URL (`/documents/{planning-uuid}`).
- Verify HTTP 403 / 404 Access Denied is enforced.

### Step 9: Cross-Organization Isolation
- Sign in as `orgadmin@demo-b.test`.
- Verify Organization A's documents, users, and departments are completely invisible.

### Step 10: Audit Trail Verification
- Sign in as `orgadmin@demo-a.test`.
- Navigate to **Audit Logs**.
- Review logged events: document uploads, search operations, AI questions (showing question length/status, never prompt text), and review approvals.

### Step 11: Platform Administration (Super Admin Separation)
- Sign in as `super@nexora.test`.
- Open **Platform Overview**: inspect active organization count, user count, system health, and OCR language availability (`eng`, `ben`).
- Notice the explicit header note: *"Platform administration: organization document content is isolated and not accessible."*
- Attempt to navigate directly to `/documents` or `/search`.
- Verify access is strictly blocked (403 Forbidden).
- Demonstrate Account Recovery (reset password link, revoke active sessions) and Security Incidents logging.

### Step 12: Corrupt Document Recovery
- Upload `corrupt_sample.pdf`.
- Observe error caught gracefully with status `failed` and error code `corrupt_pdf`.
- File remains safely in storage; click **Retry processing**.
