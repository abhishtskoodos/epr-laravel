# EPR System — Controls & Compliance Mapping

This doc maps every control from the workflow spec to a concrete location in the codebase.

## Control 1 — Role-Based Access Control (RBAC)

| Concern | Implementation |
|---|---|
| Roles defined | `app/Enums/RoleName.php` (admin, vendor, trainer, candidate, inspector, finance) |
| Roles + permissions seeded | `database/seeders/RoleAndPermissionSeeder.php` |
| Per-action permission checks | `app/Policies/*` (one per resource), wired via `Gate::policy()` and `auth.permission` middleware |
| Filament panel access | `App\Models\User::canAccessPanel()` checks role; vendor/trainer/candidate panels filter resources by ownership |
| API endpoint protection | `Route::middleware(['auth:sanctum', 'permission:vendors.view'])` |

## Control 2 — Audit Trail (Everywhere)

| Concern | Implementation |
|---|---|
| Auto-recorded model changes | `OwenIt\Auditing\Auditable` trait on every domain model |
| Captured fields | `event`, `old_values`, `new_values`, `user_type`, `user_id`, `url`, `ip_address`, `user_agent`, `tags`, `created_at` |
| Login / logout events | `app/Listeners/RecordAuthEvent.php` (logs to `audits`) — registered via `EventServiceProvider` |
| Workflow transitions | Always logged a second time as `verifications` rows (immutable; updates/deletes blocked at the model layer) |
| Retention | App-layer enforcement: audits are never soft-deleted; financial-table rows likewise treated as append-only |

## Control 3 — Verification Workflow (Standardized)

| Concern | Implementation |
|---|---|
| Status enum | One enum per module (e.g. `App\Enums\VendorStatus`) |
| Allowed transitions | Static `statusTransitions()` on each model + `App\Models\Concerns\HasVerificationWorkflow::transitionStatus()` |
| Mandatory remarks | Trait throws if `to ∈ {rejected, suspended}` and `remarks` is empty |
| Verifier identity | `verifications.verifier_id` always recorded; FormRequest fills it from `auth()->id()` |
| Same pattern across modules | Vendor, VendorCenter, Trainer, Candidate, Document, Invoice all share the trait |

## Control 4 — Scheme Logic Engine

| Concern | Implementation |
|---|---|
| Eligibility rules | `scheme_eligibility_rules` table (data-driven, JSON values) |
| Eligibility evaluation | `App\Services\SchemeLogicEngine::checkEligibility()` |
| Status progression | `App\Services\SchemeLogicEngine::nextStatus()` — strictly stage-by-stage |
| Payment milestones | `scheme_payment_milestones` table |
| Auto-calc payable | `SchemeLogicEngine::payableMilestones()` — combines milestone % with `scheme_job_role.payable_per_candidate` |
| Hard vs soft rules | `scheme_eligibility_rules.is_blocking` boolean |

## Control 5 — Data Validation & Duplicate Control

| Concern | Implementation |
|---|---|
| Phone uniqueness | `users.phone UNIQUE` |
| PAN uniqueness | `vendors.pan UNIQUE`, `trainers.pan UNIQUE` |
| GST uniqueness | `vendors.gst UNIQUE` |
| Aadhaar uniqueness (candidates) | `candidates.aadhaar_token UNIQUE` (token is salted SHA-256; raw value never persisted) |
| Invoice number uniqueness | `invoices.invoice_no UNIQUE` |
| No double-billing per milestone | `invoice_items.(candidate_enrollment_id, scheme_payment_milestone_id) UNIQUE` |
| No double-enrollment in same batch | `candidate_enrollments.(candidate_id, batch_id) UNIQUE` |
| Document duplicate detection | `documents.checksum_sha256` index; FormRequest rejects exact-match duplicates per category |
| Mandatory fields per module | Enforced in module `FormRequest` classes (e.g. `StoreVendorRequest`, `StoreCandidateRequest`) |

## Control 6 — Compliance Layer

| Concern | Implementation |
|---|---|
| Aadhaar masking / tokenization | `App\Support\Aadhaar` — SHA-256(salt + value); only `last4` stored for display. Raw value enters via `Candidate::setAadhaarAttribute` and is dropped immediately. |
| KYC mandatory before activation | Vendor cannot transition to `active` without `vendor_kyc.status = verified` (enforced in `VendorPolicy::transition()`) |
| Encrypted bank account | `vendor_kyc.account_number_encrypted` via Laravel `Crypt`; `account_number_last4` for display |
| Secure file storage | `private` disk; signed URLs only (5-minute TTL) |
| Audit-ready logs | `audits` + `verifications` form a complete forensic trail |

## Cross-Reference: Workflow Spec → Implementation

| Spec section | Where it lives |
|---|---|
| 1.1 Vendor Registration | `POST /api/v1/auth/otp/*`, `POST /api/v1/vendors`; OTP via `OtpService` |
| 1.2 Application Submission | `StoreVendorRequest` validates PAN/GST format & uniqueness |
| 1.3 Document Verification | `POST /api/v1/documents/{id}/transition` + `DocumentPolicy::transition` |
| 1.4 Center Inspection | `POST /api/v1/vendors/{id}/centers/{centerId}/inspect`, role `inspector` |
| 1.5 Approval / Rejection | `transitionStatus()` trait; remarks mandatory |
| 1.6 KYC & Agreement | `PUT /api/v1/vendors/{id}/kyc` + `vendor_kyc` table |
| 1.7 Vendor Dashboard | Filament panel (vendor) — auto-filtered by `user_id` |
| 1.8 Invoice & Payment | Whole §7 of API doc |
| 2.x Trainer | §3 of API doc |
| 3.x Candidate | §4 of API doc; Aadhaar tokenization in `App\Support\Aadhaar` |
| 4.x Admin Panel | Filament admin (`/admin/...`) |
| 5.1 RBAC | Spatie Permission + Policies |
| 5.2 Audit Trail | OwenIt Auditing + `verifications` |
| 5.3 Verification Workflow | `HasVerificationWorkflow` trait |
| 5.4 Scheme Logic Engine | `App\Services\SchemeLogicEngine` |
| 5.5 Data Validation | DB constraints + FormRequests |
| 5.6 Compliance Layer | `App\Support\Aadhaar`, encrypted KYC, signed URLs |
