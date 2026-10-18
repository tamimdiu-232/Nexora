# User manual

1. **Log in** with your account. Deactivated accounts cannot log in.
2. **Dashboard**: counts are limited to documents you may see; failed processing and recent activity are listed.
3. **Upload** (Officer and above): choose a PDF (max 20 MB), title, optional category/tags. Officers and Department Admins upload into their own department. Processing starts immediately; the document page shows *pending → processing → completed/failed*. If failed, read the error and press **Retry processing**.
4. **Document page**: metadata and extracted fields (unknown values stay "Unknown"), preview, download, **Generate summary** (AI-generated, unverified, with page links), **Related documents**. Editing lets you correct the AI category (your correction is kept on reprocessing).
5. **Search**: *Keyword* searches titles, tags, extracted text; *Semantic* ranks passages by meaning. Both only cover documents you can access; click "Open page N" to see the source.
6. **Ask AI**: create a conversation and ask a question in Bangla or English. Answers show numbered citations (document, page, snippet, open-source link). "Insufficient evidence" means nothing supported an answer. "Conflicting evidence" means sources disagree — check them.
7. **Submit for human review**: sends an answer to the review queue. It is never marked verified automatically.
8. **Reviewers**: open *Reviews*, open the cited source, then record **Verified / Needs correction / Rejected** with a comment (required unless verified). You cannot review your own submission.
9. **Administrators**: Users, Departments, Organizations (Super Admin only), Audit log.

## Platform administrators (Super Admin)
Sign in at the same page. You will see **Platform overview**, **Organizations**, **Organization admins**, **Account recovery**, **Security incidents**, **System health** and **Audit log**. You cannot open documents, search results or AI conversations of any organization.
- *Create an organization*: Organizations → Add organization, then Organization admins → Add to create its first admin.
- *Suspend an organization*: edit it and untick Active (its users are signed out on their next request).
- *Help someone get back in*: Account recovery → Reset link / Sign out / Deactivate.
- *Track an incident*: Security incidents → report, update status, close with a resolution note.
- *Check health*: System health shows the AI service, database, storage, queue and recent errors.
