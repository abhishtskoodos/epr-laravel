# EPR System — Database Design

> Stack: **Laravel 13 + MySQL 8** (works on SQLite for local dev).
> All controls from the workflow spec are mapped to columns, indexes, observers, or services. See `docs/03-controls-and-compliance.md` for the control-to-table mapping.

## 1. Module Map

| Module | Tables | Notes |
|---|---|---|
| **Identity & RBAC** | `users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `otp_codes` | Spatie/laravel-permission. OTP login for vendor/trainer/candidate. |
| **Audit** | `audits` | OwenIt/laravel-auditing — auto-logs all model changes. |
| **Vendor (Training Partner)** | `vendors`, `vendor_centers`, `vendor_kyc` | Lifecycle stages: registration → submission → verification → inspection → approval → KYC → active. |
| **Trainer** | `trainers`, `trainer_assignments` | Eligibility per scheme; assignment pivot to batches. |
| **Candidate** | `candidates`, `candidate_enrollments`, `candidate_assessments`, `candidate_certifications`, `candidate_placements` | Aadhaar tokenized; lifecycle status auto-progressed by Scheme Logic Engine. |
| **Scheme (Logic Engine)** | `schemes`, `scheme_eligibility_rules`, `scheme_payment_milestones`, `job_roles`, `batches` | Rules drive eligibility, status progression, and payment release. |
| **Documents & Verification** | `documents`, `verifications` | Polymorphic — same pattern for vendor/trainer/candidate/invoice. |
| **Invoice & Payment** | `invoices`, `invoice_items`, `payments` | Items reference enrollments + milestones for auto-calculation. |

## 2. ERD (textual)

```
users 1───* vendors          ──┐
                              │
vendors 1───* vendor_centers ──┼──* batches *───1 schemes 1───* scheme_eligibility_rules
vendors 1───1 vendor_kyc       │                schemes 1───* scheme_payment_milestones
                              │                schemes *───* job_roles  (pivot)
trainers 1───* trainer_assignments ──* batches
                              │
candidates 1───* candidate_enrollments ──* batches
candidate_enrollments 1───1 candidate_assessments
candidate_enrollments 1───1 candidate_certifications
candidate_enrollments 1───1 candidate_placements

documents       (polymorphic: documentable)   ──> vendors / trainers / candidates / vendor_kyc / invoices
verifications   (polymorphic: verifiable)     ──> documents / vendors / trainers / candidates / invoices

invoices 1───* invoice_items     (item references candidate_enrollment + scheme_payment_milestone)
invoices 1───* payments
```

## 3. Verification Workflow (standardized)

Every verifiable entity exposes a `status` ENUM column:

```
draft  →  pending_verification  →  verified
                                 ↘  rejected (remarks mandatory)
verified → active                ↘  suspended (remarks)
```

The `verifications` table records every transition:

| col | type | notes |
|---|---|---|
| `verifiable_type` | string | morph |
| `verifiable_id` | bigint | morph |
| `from_status` | string |  |
| `to_status` | string |  |
| `verifier_id` | FK users | who acted (RBAC enforced) |
| `remarks` | text | mandatory on `rejected` / `suspended` |
| `meta` | json | scheme-specific evidence |
| `created_at` | timestamp |  |

Combined with the `audits` table, every status change is doubly logged: at the row level (`audits.new_values`) and as an immutable workflow event (`verifications`).

## 4. Tables (column-level)

### 4.1 `users` (extended)
Base Laravel users + EPR fields:
- `id`, `name`, `email` (unique, nullable for OTP-only accounts), `email_verified_at`
- `phone` (string 15, **unique**, indexed) — primary identifier for OTP login
- `phone_verified_at` (timestamp)
- `password` (nullable for OTP-only)
- `is_active` (bool, default true)
- `last_login_at`, `last_login_ip`
- `remember_token`, `created_at`, `updated_at`, `deleted_at` (soft deletes)

Roles via Spatie: `admin`, `vendor`, `trainer`, `candidate`, `inspector`, `finance`.

### 4.2 `otp_codes`
- `id`, `phone` (string 15, indexed), `code_hash` (string 60), `purpose` (enum: `login`, `phone_verify`)
- `attempts` (tinyint default 0), `expires_at`, `consumed_at`, `created_at`
- Index: `(phone, purpose, expires_at)`.

### 4.3 `vendors`
- `id`, `user_id` (FK users — owner / primary contact)
- `legal_name` (string), `trade_name` (string nullable)
- `pan` (string 10, **unique**, indexed)
- `gst` (string 15, unique, nullable, indexed)
- `cin` (string 21, nullable)
- `email`, `phone`, `address_line1`, `address_line2`, `city`, `state`, `pincode`, `country` (default 'IN')
- `entity_type` (enum: `private_ltd`, `public_ltd`, `partnership`, `llp`, `proprietorship`, `society`, `trust`, `section_8`)
- `status` (enum: `draft`, `pending_verification`, `verified`, `rejected`, `active`, `suspended`) default `draft`
- `verified_at`, `verified_by` (FK users)
- `created_at`, `updated_at`, `deleted_at`
- Indexes: `pan`, `gst`, `status`, `(state, city)`.

### 4.4 `vendor_centers`
- `id`, `vendor_id` (FK)
- `name`, `code` (unique within vendor)
- `address_line1`, `address_line2`, `city`, `state`, `pincode`
- `latitude`, `longitude` (decimal 10,7)
- `capacity` (int — total seats)
- `infrastructure_score` (decimal 5,2 nullable — set by inspection)
- `status` (enum: `pending_inspection`, `inspected`, `approved`, `rejected`, `active`, `suspended`)
- `inspected_at`, `inspected_by` (FK users), `inspection_report_path`
- timestamps + soft deletes

### 4.5 `vendor_kyc`
- `id`, `vendor_id` (FK, **unique**)
- `bank_name`, `account_holder_name`
- `account_number_encrypted` (text — Laravel Crypt)
- `account_number_last4` (string 4 — for display)
- `ifsc` (string 11)
- `pan_image_document_id` (FK documents nullable)
- `cancelled_cheque_document_id` (FK documents nullable)
- `agreement_document_id` (FK documents nullable)
- `status` (enum: `pending`, `verified`, `rejected`)
- `verified_at`, `verified_by`
- timestamps

### 4.6 `trainers`
- `id`, `user_id` (FK)
- `full_name`, `pan` (string 10, unique nullable)
- `aadhaar_token` (string 64, indexed) — tokenized Aadhaar (see compliance doc)
- `qualification`, `tot_certificate_no` (string)
- `experience_years` (decimal 4,1)
- `status` (enum: `draft`, `pending_verification`, `verified`, `rejected`, `active`, `suspended`)
- `verified_at`, `verified_by`
- timestamps + soft deletes

### 4.7 `trainer_assignments`
- `id`, `trainer_id` (FK), `batch_id` (FK)
- `assigned_by` (FK users), `assigned_at`
- `unassigned_at` (nullable)
- `meta` (json — hours allocated, role)
- Unique: `(trainer_id, batch_id, assigned_at)`.

### 4.8 `candidates`
- `id`, `user_id` (FK nullable — created on first OTP login)
- `aadhaar_token` (string 64, **unique**, indexed) — see compliance
- `aadhaar_last4` (string 4) — for display only
- `full_name`, `gender` (enum), `dob` (date), `category` (enum: `gen`, `obc`, `sc`, `st`, `pwd`, `minority`, `ews`)
- `phone` (string 15, indexed), `email` (nullable), `address_line1`, `city`, `state`, `pincode`
- `education_level` (enum: `below_8`, `8_pass`, `10_pass`, `12_pass`, `iti`, `diploma`, `graduate`, `pg`)
- `status` (enum: `registered`, `training`, `assessed`, `certified`, `placed`, `dropped`) default `registered`
- `registered_via` (enum: `self`, `vendor`, `bulk_import`)
- `registered_by_vendor_id` (FK vendors nullable)
- timestamps + soft deletes
- Indexes: `aadhaar_token` (unique), `phone`, `status`, `(state, city)`.

### 4.9 `candidate_enrollments`
- `id`, `candidate_id` (FK), `batch_id` (FK)
- `enrolled_at`, `dropped_at` (nullable), `drop_reason` (string nullable)
- `attendance_percent` (decimal 5,2 nullable)
- `ojt_completed` (bool default false), `ojt_hours` (int nullable)
- timestamps + soft deletes
- Unique: `(candidate_id, batch_id)`.

### 4.10 `candidate_assessments`
- `id`, `candidate_enrollment_id` (FK, unique)
- `assessment_agency`, `assessor_name`
- `assessed_on` (date)
- `theory_score`, `practical_score`, `viva_score`, `total_score` (decimal 6,2)
- `result` (enum: `pass`, `fail`, `pending`)
- `status` (enum: `pending`, `verified`, `rejected`)
- timestamps

### 4.11 `candidate_certifications`
- `id`, `candidate_enrollment_id` (FK, unique)
- `certificate_no` (string, unique)
- `issued_on` (date), `valid_until` (date nullable)
- `certificate_document_id` (FK documents nullable)
- `status` (enum: `pending`, `issued`, `revoked`)
- timestamps

### 4.12 `candidate_placements`
- `id`, `candidate_enrollment_id` (FK, unique)
- `employer_name`, `employer_pan` (string 10 nullable)
- `designation`, `monthly_ctc` (decimal 10,2)
- `placement_type` (enum: `wage`, `self_employed`, `apprentice`)
- `placed_on` (date), `verified_on` (date nullable)
- `proof_document_id` (FK documents nullable)
- `status` (enum: `pending`, `verified`, `rejected`)
- timestamps

### 4.13 `schemes`
- `id`, `code` (string, **unique**), `name`
- `funding_agency` (string — e.g. NSDC, MSDE, State)
- `scheme_type` (enum: `short_term`, `long_term`, `apprenticeship`, `rpl`, `placement_linked`)
- `description` (text)
- `effective_from` (date), `effective_to` (date nullable)
- `is_active` (bool default true)
- `min_attendance_percent` (decimal 5,2 default 70)
- `requires_assessment` (bool default true), `requires_placement` (bool default false)
- timestamps + soft deletes

### 4.14 `scheme_eligibility_rules`
- `id`, `scheme_id` (FK)
- `rule_key` (enum: `age_min`, `age_max`, `gender`, `category`, `education_min`, `state`, `income_max`, `custom`)
- `operator` (enum: `eq`, `neq`, `gte`, `lte`, `in`, `not_in`, `between`)
- `value_json` (json) — flexible: `{"min": 18, "max": 35}` etc.
- `is_blocking` (bool default true) — hard vs soft rule
- `display_order` (int)
- timestamps

### 4.15 `scheme_payment_milestones`
- `id`, `scheme_id` (FK)
- `key` (enum: `enrollment`, `mid_training`, `assessment_pass`, `certification`, `placement_3m`, `placement_6m`, `custom`)
- `label` (string)
- `percent` (decimal 5,2) — % of total payable per candidate
- `amount` (decimal 10,2 nullable) — fixed alternative to percent
- `requires_status` (string) — candidate status that must be reached, e.g. `certified`
- `display_order` (int)
- timestamps

### 4.16 `job_roles`
- `id`, `qp_code` (string, unique — QP/NOS code), `name`
- `nsqf_level` (tinyint), `sector` (string)
- timestamps

### 4.17 `scheme_job_role` (pivot)
- `scheme_id`, `job_role_id`, `payable_per_candidate` (decimal 10,2)
- composite primary key.

### 4.18 `batches`
- `id`, `code` (string, unique), `name`
- `vendor_center_id` (FK), `scheme_id` (FK), `job_role_id` (FK)
- `start_date`, `end_date`, `seats` (int)
- `status` (enum: `planned`, `running`, `completed`, `cancelled`)
- timestamps + soft deletes
- Indexes: `(vendor_center_id, status)`, `(scheme_id, status)`.

### 4.19 `documents`
Polymorphic file storage with verification state.
- `id`, `documentable_type`, `documentable_id`
- `category` (string — e.g. `pan`, `gst`, `aadhaar`, `qualification`, `cancelled_cheque`, `placement_proof`)
- `original_name`, `path` (string), `mime_type`, `size_bytes` (int)
- `checksum_sha256` (string 64) — duplicate detection
- `status` (enum: `pending`, `verified`, `rejected`)
- `verified_at`, `verified_by` (FK users), `remarks` (text nullable)
- `uploaded_by` (FK users), timestamps + soft deletes
- Indexes: `(documentable_type, documentable_id)`, `checksum_sha256`.

### 4.20 `verifications`
Immutable transition log (already detailed in §3).

### 4.21 `invoices`
- `id`, `invoice_no` (string, **unique**), `vendor_id` (FK)
- `scheme_id` (FK)
- `period_from` (date), `period_to` (date)
- `subtotal`, `tax_amount`, `total` (decimal 12,2)
- `status` (enum: `draft`, `submitted`, `under_review`, `approved`, `rejected`, `paid`, `partially_paid`)
- `submitted_at`, `approved_at`, `paid_at`
- `remarks` (text)
- timestamps + soft deletes
- Unique: `(vendor_id, invoice_no)`; index on `(vendor_id, status)`.

### 4.22 `invoice_items`
- `id`, `invoice_id` (FK)
- `candidate_enrollment_id` (FK)
- `scheme_payment_milestone_id` (FK)
- `amount` (decimal 10,2)
- `is_eligible` (bool — set by Scheme Logic Engine)
- `eligibility_reason` (text)
- `status` (enum: `pending`, `approved`, `rejected`)
- timestamps
- Unique: `(candidate_enrollment_id, scheme_payment_milestone_id)` — prevents duplicate billing per milestone.

### 4.23 `payments`
- `id`, `invoice_id` (FK), `amount` (decimal 12,2)
- `mode` (enum: `neft`, `rtgs`, `imps`, `upi`, `cheque`)
- `utr` (string nullable, indexed)
- `paid_on` (datetime), `paid_by` (FK users)
- `status` (enum: `initiated`, `success`, `failed`, `reversed`)
- `bank_response_json` (json)
- timestamps

### 4.24 `audits`
Provided by `owen-it/laravel-auditing` — captures `event`, `auditable`, `old_values`, `new_values`, `user`, `url`, `ip_address`, `user_agent`, `tags`. All key models implement the `Auditable` contract.

## 5. Critical Indexes & Constraints

| Table | Constraint | Purpose |
|---|---|---|
| `users.phone` | UNIQUE | OTP login |
| `vendors.pan` | UNIQUE | Duplicate vendor prevention |
| `vendors.gst` | UNIQUE | Duplicate vendor prevention |
| `candidates.aadhaar_token` | UNIQUE | Duplicate candidate prevention |
| `(candidate_id, batch_id)` on enrollments | UNIQUE | No double-enrollment in same batch |
| `invoices.invoice_no` | UNIQUE | Duplicate invoice prevention |
| `(candidate_enrollment_id, scheme_payment_milestone_id)` on invoice_items | UNIQUE | No double-billing per milestone |
| `documents.checksum_sha256` | INDEX | Duplicate document detection |
| `verifications(verifiable_type, verifiable_id, created_at)` | INDEX | Workflow timeline lookup |

## 6. Soft Deletes & Retention

Soft deletes on: `users`, `vendors`, `vendor_centers`, `trainers`, `candidates`, `candidate_enrollments`, `documents`, `invoices`, `batches`, `schemes`. Audit log is **never** soft-deleted; financial tables (invoices, payments) are also append-only at the application layer.

## 7. Scheme Logic Engine (data-driven)

Eligibility, status progression, and payable calculation are **not hard-coded**. They are evaluated by `App\Services\SchemeLogicEngine` against:
- `scheme_eligibility_rules` (for candidate enrollment)
- `scheme_payment_milestones` (for invoice generation)
- `schemes.requires_assessment / requires_placement / min_attendance_percent` (for status progression)

This is the "system brain" called out in §5.4 of the workflow spec.

## 8. Compliance Layer

- **Aadhaar** — only stored as a SHA-256 token (`aadhaar_token`) plus the last 4 digits for display. Raw Aadhaar is never persisted; the encrypted full value lives only in the request lifecycle (validated, tokenized, discarded).
- **Bank account** — encrypted at rest via Laravel Crypt; only `last4` shown.
- **PAN/GST** — stored plaintext (publicly held identifiers) but uniquely indexed.
- **All sensitive uploads** are stored on the `private` disk (S3 / local `storage/app/private`) and served via signed URLs.
- **Audit log retention** — minimum 7 years (configurable).
