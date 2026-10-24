# Nexora — User Manual

## 1. Getting Started
Nexora provides a unified interface for document intelligence. Access the application by navigating to `http://localhost:8000` (or the configured host).

---

## 2. Default Demo Accounts

> **Development Notice:** The default password for all demo accounts is `Password@123`.

| Email | Role | Scope | Purpose |
|---|---|---|---|
| `super@nexora.test` | Super Admin | Platform Plane | Platform health, recovery, incidents, tenant management |
| `orgadmin@demo-a.test` | Organization Admin | Org A (Ministry of Infra) | Full administrative control of Org A |
| `deptadmin.planning@demo-a.test` | Department Admin | Org A (Planning Dept) | Manages Planning department documents & users |
| `officer.planning@demo-a.test` | Officer | Org A (Planning Dept) | Uploads and edits Planning documents |
| `reviewer@demo-a.test` | Reviewer | Org A | Verifies and validates AI-generated answers |
| `viewer.finance@demo-a.test` | Viewer | Org A (Finance Dept) | Read-only access to Finance documents only |
| `orgadmin@demo-b.test` | Organization Admin | Org B (Local Govt) | Manages Org B (isolated from Org A) |
| `officer@demo-b.test` | Officer | Org B (Works Dept) | Uploads Org B documents |

---

## 3. Core Workflows

### 3.1 Document Ingestion & Processing
1. Log in as an Officer or Admin.
2. Navigate to **Documents** -> **Upload New Document**.
3. Select a PDF file (digital or scanned, English or Bangla).
4. Enter an optional title and description, or let the AI extract them.
5. Click **Upload and Process**.
6. The document enters the processing pipeline (`pending` -> `processing` -> `completed`).

### 3.2 Information Retrieval & Search
1. Navigate to **Search**.
2. Select **Keyword Search** for exact lexical matching across metadata, title, and page text.
3. Select **Semantic Search** for vector-based relevance matching.
4. Filter by category, department, or date range.
5. Click **Open Page N** on any search result to inspect the exact PDF page.

### 3.3 Ask AI (Grounded Q&A)
1. Navigate to **Ask AI**.
2. Enter your question in English, Bangla, or mixed (Banglish).  
   *Example:* `এই project-এর budget কত এবং deadline কবে?`
3. Nexora retrieves relevant passages, scores them, detects conflicting values, and generates a grounded response.
4. Each claim displays a numbered citation card linking directly to the PDF page snippet.
5. If the AI detects conflicting figures from different documents (e.g. original estimate vs. revised cost), a **Conflicting Evidence** warning is displayed with both sources.

### 3.4 Human Verification Workflow
1. An Officer or Admin can click **Submit for Human Review** on any AI response.
2. A Reviewer logs in, reviews the cited evidence against the PDF, and issues a decision:
   - **Verified**: Confirms answer matches source documents.
   - **Needs Correction**: Specifies what needs correction.
   - **Rejected**: Inaccurate or unsupported answer.
3. Reviewers cannot decide on their own submissions.

### 3.5 Platform Administration (Super Admin)
1. Log in as `super@nexora.test`.
2. Access the **Platform Overview** dashboard showing active organizations, active users, system health, and open incidents.
3. Super Admins do not have access to organization document content or search queries.
4. Manage organizations, review anonymized error logs, investigate security incidents, or trigger account recovery actions.
