# EPR System — API Design

> All endpoints prefixed with `/api/v1`. JSON in/out. Authentication via Laravel Sanctum personal-access tokens issued on successful OTP login.
> Every mutation runs through a `FormRequest` (validation + duplicate detection) and a `Policy` (RBAC).
> Every state change also writes to `audits` (model row diff) and, for verifiable entities, to `verifications` (workflow event).

## 0. Conventions

| Aspect | Choice |
|---|---|
| Auth | `Authorization: Bearer <sanctum_token>` |
| Errors | `{ "message": "...", "errors": { "field": ["..."] } }` (Laravel default) |
| Pagination | `?page=1&per_page=25`, response wraps `data`, `meta`, `links` |
| Sorting | `?sort=-created_at,name` |
| Filtering | Per-resource query params (documented inline) |
| Idempotency | Use `Idempotency-Key` header on POST `/payments` and `/invoices/submit` |
| Timestamps | ISO-8601 UTC |

## 1. Auth & OTP

| Method | Path | Body | Description | Roles |
|---|---|---|---|---|
| `POST` | `/auth/otp/request` | `{ "phone": "9876543210", "purpose": "login" }` | Issues OTP; same response whether or not user exists (no enumeration). | public |
| `POST` | `/auth/otp/verify` | `{ "phone": "...", "code": "123456" }` | Returns `{ token, user }` on success. Creates user with role `candidate` if first login. | public |
| `POST` | `/auth/logout` | — | Revokes the current Sanctum token. | authenticated |
| `GET`  | `/auth/me`     | — | Current user + roles + permissions. | authenticated |

**Controls embedded:**
- `OtpService` rate-limits per phone (default 5 attempts, then forces re-issue).
- OTP TTL configurable (`services.otp.ttl_minutes`, default 5).
- Audit trail logs every `request` and `verify` event with IP + user agent.

## 2. Vendor (Training Partner)

| Method | Path | Body | Description | Roles |
|---|---|---|---|---|
| `POST` | `/vendors` | full vendor payload | 1.1 + 1.2 — Self-registration with center/org details. Status set to `pending_verification` automatically. | `vendor` (own row) / `admin` |
| `GET` | `/vendors` | — | List (admin sees all; vendor sees only own). | `admin`, `inspector`, `finance` |
| `GET` | `/vendors/{id}` | — | Detail with eager-loaded centers, KYC, verifications, invoices. | `admin`, `inspector`, `finance`, owning `vendor` |
| `PATCH` | `/vendors/{id}` | partial | Update editable fields. | `admin`, owning `vendor` (only when status `draft`) |
| `POST` | `/vendors/{id}/transition` | `{ "to": "verified", "remarks": "..." }` | 1.3 + 1.5 — Workflow transitions. Remarks mandatory on `rejected`/`suspended`. | `admin` (verify/reject), `inspector` (inspect-related) |
| `POST` | `/vendors/{id}/centers` | center payload | 1.2 + 1.4 — Add a center; status `pending_inspection`. | `admin`, owning `vendor` |
| `POST` | `/vendors/{id}/centers/{centerId}/inspect` | `{ "score": 0..100, "report": file }` | 1.4 — Inspector records scoring. | `inspector`, `admin` |
| `POST` | `/vendors/{id}/centers/{centerId}/transition` | `{ "to": "approved", "remarks": "..." }` | Center workflow. | `inspector`, `admin` |
| `PUT`  | `/vendors/{id}/kyc` | KYC payload (incl. account number, IFSC, file uploads) | 1.6 — Submit KYC; account number is encrypted at rest. | owning `vendor` |
| `POST` | `/vendors/{id}/kyc/transition` | `{ "to": "verified" }` | Finance verifies KYC. | `finance`, `admin` |

**Controls embedded:** PAN/GST uniqueness (DB constraint + FormRequest), entity-type whitelist, mandatory documents before `pending_verification`, audit trail on every change, Spatie Policy on every endpoint.

## 3. Trainer

| Method | Path | Description | Roles |
|---|---|---|---|
| `POST` | `/trainers` | 2.1 + 2.2 — Self-register (with TOT, qualification). | owning `vendor`, `admin` |
| `GET`/`PATCH` | `/trainers/{id}` | View / update profile. | `admin`, owning vendor, self-trainer |
| `POST` | `/trainers/{id}/transition` | 2.2 — Workflow. | `admin` |
| `POST` | `/trainers/{id}/assignments` | `{ "batch_id", "assigned_at", "meta": {...} }` | 2.3 — Assign trainer to batch (admin-only). Eligibility checked against scheme requirements. | `admin` |
| `DELETE` | `/trainer-assignments/{id}` | Unassign (sets `unassigned_at`). | `admin` |

## 4. Candidate

| Method | Path | Description | Roles |
|---|---|---|---|
| `POST` | `/candidates` | 3.1 — Register (raw Aadhaar accepted only here — server tokenizes + last4 + drops). | owning `vendor`, self via OTP login |
| `POST` | `/candidates/bulk-import` | CSV upload. Bulk dedup on `(aadhaar_token, phone)`. | owning `vendor`, `admin` |
| `GET` | `/candidates` | Paginated; vendor sees own only; admin sees all. | `admin`, `vendor` |
| `GET`/`PATCH` | `/candidates/{id}` | Profile (Personal/Assessment/OJT/Placement tabs). | `admin`, owning `vendor`, self |
| `POST` | `/candidates/{id}/eligibility-check` | `{ "scheme_id": ... }` | 3.4 — Calls `SchemeLogicEngine` and returns blocking + soft reasons. | `admin`, `vendor` |
| `POST` | `/candidates/{id}/enrollments` | `{ "batch_id", "enrolled_at" }` | Enroll into a batch. Engine validates eligibility before persisting. | `vendor`, `admin` |
| `PATCH` | `/enrollments/{id}` | Update attendance / OJT. | `vendor`, `trainer` (own batch only), `admin` |
| `PUT`  | `/enrollments/{id}/assessment` | Submit assessment scores + agency. | `trainer`, `admin` |
| `POST` | `/enrollments/{id}/assessment/transition` | `{ "to": "verified" }` | Verify. | `admin` |
| `PUT`  | `/enrollments/{id}/certification` | Issue / record certificate. | `admin` |
| `PUT`  | `/enrollments/{id}/placement` | Record placement w/ employer details + proof. | `vendor`, `admin` |
| `POST` | `/enrollments/{id}/placement/transition` | `{ "to": "verified" }` | Placement verification. | `admin` |
| `POST` | `/enrollments/{id}/progress` | — | Engine auto-progresses candidate status if conditions are met. | `admin`, scheduled job |

**Controls embedded:**
- Aadhaar duplicate prevention via the unique index on `aadhaar_token` (set by `Candidate::setAadhaarAttribute`).
- `mobile` indexed but non-unique (multiple candidates may share a family number).
- Validation: `Aadhaar::isValid()` (12 digits) before tokenization.
- Status auto-progression via `SchemeLogicEngine::nextStatus()`.

## 5. Scheme Module (the "system brain")

| Method | Path | Description | Roles |
|---|---|---|---|
| `GET`/`POST` | `/schemes` | List / create. | `admin` |
| `GET`/`PATCH` | `/schemes/{id}` | Detail / update. | `admin`; read-only for others |
| `PUT` | `/schemes/{id}/eligibility-rules` | Replace rule set. | `admin` |
| `PUT` | `/schemes/{id}/payment-milestones` | Replace milestone set. | `admin` |
| `PUT` | `/schemes/{id}/job-roles` | Sync job roles + payable amounts. | `admin` |
| `GET`/`POST` | `/job-roles` | NSQF/QP-NOS job roles. | `admin` |
| `GET`/`POST` | `/batches` | Create / list batches. | `vendor`, `admin` |
| `PATCH` | `/batches/{id}` | Update. | `vendor` (own center), `admin` |

## 6. Documents

| Method | Path | Description | Roles |
|---|---|---|---|
| `POST` | `/documents` | multipart upload — `{ documentable_type, documentable_id, category, file }`. Server stores SHA-256 checksum. | role with edit rights on the parent |
| `GET` | `/documents/{id}` | Returns signed URL (5 min TTL) — never the file directly. | role with view rights |
| `POST` | `/documents/{id}/transition` | `{ "to": "verified", "remarks": "..." }` | `admin`, `finance`, `inspector` |

**Controls embedded:** files saved to `private` disk; max size 10 MB; content-type whitelist; checksum-based duplicate detection.

## 7. Invoice & Payment

| Method | Path | Description | Roles |
|---|---|---|---|
| `POST` | `/invoices` | 1.8 — Vendor creates draft invoice for `(scheme_id, period)`. Engine pre-populates eligible items from `payableMilestones()`. | `vendor`, `admin` |
| `GET` | `/invoices` | List. Vendor sees own; admin/finance see all. | all auth |
| `GET`/`PATCH` | `/invoices/{id}` | Detail / edit (only when `draft`). | `vendor`, `admin` |
| `POST` | `/invoices/{id}/submit` | Status `draft → submitted`. Idempotent via header. | `vendor` |
| `POST` | `/invoices/{id}/transition` | `{ "to": "approved" / "rejected" / "under_review", "remarks": "..." }` | `finance`, `admin` |
| `POST` | `/invoices/{id}/payments` | `{ amount, mode, utr, paid_on }` — records payment, transitions invoice to `paid` / `partially_paid`. | `finance`, `admin` |

**Controls embedded:**
- `(candidate_enrollment_id, scheme_payment_milestone_id)` unique → no double-billing per milestone.
- `invoice_no` unique.
- All financial mutations are audited; `payments` rows are append-only at the application layer.

## 8. Reports & Audit

| Method | Path | Description | Roles |
|---|---|---|---|
| `GET` | `/reports/mis` | 4.1 — Real-time MIS counters (counts by status across all modules). | `admin`, `finance`, `inspector` |
| `GET` | `/reports/scheme/{id}/payouts` | Scheme-wise payout summary. | `admin`, `finance` |
| `GET` | `/audits` | Filter by `auditable_type`, `auditable_id`, `user_id`, date range. | `admin`, `finance`, `inspector` |
| `GET` | `/verifications` | Filter by `verifiable_type`, `verifier_id`, `to_status`. | same |

## 9. Sample Request / Response

### `POST /api/v1/auth/otp/verify`

```json
// request
{ "phone": "9876543210", "code": "123456" }

// 200 response
{
  "token": "1|abc...",
  "user": {
    "id": 42,
    "name": "Ramesh K.",
    "phone": "9876543210",
    "roles": ["candidate"],
    "permissions": ["candidates.view", "candidates.update", ...]
  }
}
```

### `POST /api/v1/candidates/{id}/eligibility-check`

```json
// request
{ "scheme_id": 1 }

// 200 response (eligible)
{ "eligible": true, "blocking_reasons": [], "soft_warnings": [] }

// 200 response (rejected)
{
  "eligible": false,
  "blocking_reasons": [
    "Eligibility rule failed: age_max lte {\"value\":45}"
  ],
  "soft_warnings": []
}
```

### `POST /api/v1/vendors/{id}/transition`

```json
// request
{ "to": "verified", "remarks": null, "meta": { "checklist_score": 92 } }

// 200 response
{
  "id": 17,
  "status": "verified",
  "verified_at": "2026-05-04T10:55:01Z",
  "verifications_count": 3
}
```

## 10. Versioning

All API routes live under `/api/v1`. Breaking changes require `/api/v2`. Internal Filament admin routes (`/admin/...`) bypass the API entirely.
