# Capstone demonstration script (≈10 minutes)

Preparation: setup done, both servers running, `php artisan govdoc:process` completed (seeded documents show "completed", the corrupted one "failed").

1. Log in as **officer.planning@demo-a.test**. Show the dashboard (counts, one failed job).
2. **Upload** `sample-documents/01_infrastructure_project_report.pdf` again under a new title. Watch status → completed. Open it: classification, reference number, budget, deadline (with "AI, unverified" labels).
3. **Search** "bridge" (keyword) then "project cost and deadline" (semantic). Click "Open page N".
4. **Ask AI**: `এই project-এর budget কত এবং deadline কবে?` → answer with citations. Expect **conflicting evidence** (BDT 50,000,000 vs 65,000,000 from the revised estimate). Open a citation → PDF opens at the page.
5. Ask `What is the cafeteria menu price?` → **insufficient evidence**.
6. Click **Submit for human review**.
7. Log out; log in as **reviewer@demo-a.test** → Reviews → open source → **Verified** with a comment.
8. Log in as **viewer.finance@demo-a.test**: Planning-department documents are absent from lists, search and answers; open a Planning document URL directly → **Access denied**.
9. Log in as **orgadmin@demo-b.test**: ask about the Riverside project → insufficient evidence (Org A documents are invisible); asking about road maintenance works.
10. Log in as **orgadmin@demo-a.test** → Audit log: uploads, previews, chat events, review decision.
11. Show the failed corrupted document and **Retry processing**; show the scanned notice (OCR) page text via keyword search "closed".

Talking points: architecture (docs/ARCHITECTURE.md), permission-filtered retrieval, double citation validation, human-in-the-loop, honesty about limits (docs/KNOWN_LIMITATIONS.md).
