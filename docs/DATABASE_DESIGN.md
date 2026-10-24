# Nexora — Database Design & Entity Relationship

## 1. Relational Schema Architecture

All relational tables enforce strict foreign key constraints (`restrictOnDelete` for critical organizational units to prevent cascading data loss), primary keys, and performance indexes.

---

## 2. Table Specifications

### 2.1 `organizations`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name`: VARCHAR(255) NOT NULL
- `code`: VARCHAR(50) NOT NULL UNIQUE
- `is_active`: BOOLEAN DEFAULT TRUE
- `created_at`, `updated_at`: TIMESTAMP

### 2.2 `departments`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `organization_id`: BIGINT UNSIGNED NOT NULL (FK -> `organizations.id`, RESTRICT)
- `name`: VARCHAR(255) NOT NULL
- `is_active`: BOOLEAN DEFAULT TRUE
- `created_at`, `updated_at`: TIMESTAMP
- UNIQUE(`organization_id`, `name`)

### 2.3 `users`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `organization_id`: BIGINT UNSIGNED NULLABLE (FK -> `organizations.id`, RESTRICT)
- `department_id`: BIGINT UNSIGNED NULLABLE (FK -> `departments.id`, RESTRICT)
- `name`: VARCHAR(255) NOT NULL
- `email`: VARCHAR(255) NOT NULL UNIQUE
- `password`: VARCHAR(255) NOT NULL
- `role`: ENUM('super_admin', 'org_admin', 'dept_admin', 'officer', 'reviewer', 'viewer') NOT NULL
- `is_active`: BOOLEAN DEFAULT TRUE
- `remember_token`: VARCHAR(100) NULLABLE
- `created_at`, `updated_at`: TIMESTAMP

### 2.4 `documents`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `uuid`: CHAR(36) NOT NULL UNIQUE (Route key, public identifier)
- `organization_id`: BIGINT UNSIGNED NOT NULL (FK -> `organizations.id`, RESTRICT)
- `department_id`: BIGINT UNSIGNED NULLABLE (FK -> `departments.id`, RESTRICT)
- `uploaded_by`: BIGINT UNSIGNED NOT NULL (FK -> `users.id`, RESTRICT)
- `title`: VARCHAR(255) NOT NULL
- `description`: TEXT NULLABLE
- `category`: VARCHAR(100) NOT NULL DEFAULT 'Other'
- `category_confidence`: DECIMAL(5,4) DEFAULT 0.0000
- `category_source`: ENUM('pending', 'ai', 'manual') DEFAULT 'pending'
- `tags`: JSON NULLABLE
- `original_name`: VARCHAR(255) NOT NULL
- `stored_path`: VARCHAR(255) NOT NULL (Hidden from serialization)
- `mime_type`: VARCHAR(100) NOT NULL DEFAULT 'application/pdf'
- `size_bytes`: BIGINT UNSIGNED NOT NULL
- `sha256`: CHAR(64) NOT NULL
- `page_count`: INT UNSIGNED DEFAULT 0
- `processing_status`: ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending'
- `processing_error`: TEXT NULLABLE
- `processed_at`: TIMESTAMP NULLABLE
- `archived_at`: TIMESTAMP NULLABLE
- `deleted_at`: TIMESTAMP NULLABLE (SoftDeletes)
- `created_at`, `updated_at`: TIMESTAMP
- INDEX(`organization_id`, `department_id`)
- INDEX(`processing_status`)

### 2.5 `document_metadata` (1:1 with `documents`)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `document_id`: BIGINT UNSIGNED NOT NULL UNIQUE (FK -> `documents.id`, CASCADE)
- `issuing_organization`: VARCHAR(255) NULLABLE
- `reference_number`: VARCHAR(255) NULLABLE
- `document_date`: DATE NULLABLE
- `project_name`: VARCHAR(255) NULLABLE
- `budget`: VARCHAR(100) NULLABLE
- `deadline`: VARCHAR(100) NULLABLE
- `keywords`: JSON NULLABLE
- `summary`: JSON NULLABLE (short, detailed, key_points, dates, amounts, action_items)
- `summary_generated_at`: TIMESTAMP NULLABLE
- `created_at`, `updated_at`: TIMESTAMP

### 2.6 `document_pages`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `document_id`: BIGINT UNSIGNED NOT NULL (FK -> `documents.id`, CASCADE)
- `page_number`: INT UNSIGNED NOT NULL
- `text`: LONGTEXT NOT NULL
- `extraction_method`: ENUM('text', 'ocr', 'empty') NOT NULL
- `created_at`, `updated_at`: TIMESTAMP
- UNIQUE(`document_id`, `page_number`)

### 2.7 `document_chunks`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `document_id`: BIGINT UNSIGNED NOT NULL (FK -> `documents.id`, CASCADE)
- `chunk_id`: VARCHAR(100) NOT NULL UNIQUE
- `page_number`: INT UNSIGNED NOT NULL
- `chunk_index`: INT UNSIGNED NOT NULL
- `char_count`: INT UNSIGNED NOT NULL
- `created_at`, `updated_at`: TIMESTAMP

### 2.8 `processing_jobs`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `document_id`: BIGINT UNSIGNED NOT NULL (FK -> `documents.id`, CASCADE)
- `status`: ENUM('pending', 'running', 'completed', 'failed') NOT NULL
- `attempt`: INT UNSIGNED DEFAULT 1
- `error_code`: VARCHAR(100) NULLABLE
- `error_message`: TEXT NULLABLE
- `started_at`: TIMESTAMP NULLABLE
- `finished_at`: TIMESTAMP NULLABLE
- `created_at`, `updated_at`: TIMESTAMP

### 2.9 `extracted_entities`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `document_id`: BIGINT UNSIGNED NOT NULL (FK -> `documents.id`, CASCADE)
- `type`: VARCHAR(50) NOT NULL
- `value`: TEXT NOT NULL
- `page_number`: INT UNSIGNED NOT NULL
- `evidence`: TEXT NULLABLE
- `created_at`, `updated_at`: TIMESTAMP

### 2.10 `chat_sessions` & `chat_messages` & `citations`
- `chat_sessions`: `id`, `uuid`, `user_id`, `organization_id`, `title`, timestamps.
- `chat_messages`: `id`, `chat_session_id`, `role` (user/assistant), `content`, `status` (answered, insufficient_evidence, conflicting_evidence, error), `generated_by` (extractive, openai, anthropic), timestamps.
- `citations`: `id`, `chat_message_id`, `document_id`, `chunk_id`, `page_number`, `snippet`, `score`, timestamps.

### 2.11 `reviews`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `chat_message_id`: BIGINT UNSIGNED NOT NULL (FK -> `chat_messages.id`, CASCADE)
- `requested_by`: BIGINT UNSIGNED NOT NULL (FK -> `users.id`, RESTRICT)
- `reviewer_id`: BIGINT UNSIGNED NULLABLE (FK -> `users.id`, RESTRICT)
- `status`: ENUM('pending', 'verified', 'rejected', 'needs_correction') DEFAULT 'pending'
- `comment`: TEXT NULLABLE
- `decided_at`: TIMESTAMP NULLABLE
- `created_at`, `updated_at`: TIMESTAMP

### 2.12 `audit_logs` & `security_incidents`
- `audit_logs`: `id`, `user_id` (nullable), `organization_id` (nullable), `action`, `subject_type`, `subject_id`, `ip_address`, `metadata` (JSON sanitized), `created_at`.
- `security_incidents`: `id`, `organization_id` (nullable), `reported_by`, `title`, `description`, `severity` (low, medium, high, critical), `status` (open, investigating, resolved), `resolution_note`, `resolved_at`, timestamps.
