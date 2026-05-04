# EPR System (Laravel + MySQL)

End-to-end Employment & Training Partner Response platform — vendor lifecycle, candidate enrollment, scheme logic, invoice/payment workflow — built on Laravel 13 with embedded RBAC, audit trail, verification workflow, scheme logic engine, data validation, and compliance controls.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.3) |
| DB | MySQL (local + prod); SQLite for tests |
| Admin UI | Filament 5 |
| RBAC | spatie/laravel-permission |
| Audit | owen-it/laravel-auditing + an immutable `verifications` table |
| Auth | Sanctum tokens (API) + Filament session (admin) + OTP login |

## Quick start (local)

```bash
git clone https://github.com/abhishtskoodos/epr-laravel.git
cd epr-laravel
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite           # or configure MySQL in .env
php artisan migrate:fresh --seed         # creates 22 tables + roles/permissions + default users + a sample scheme
php artisan serve
```

Default users (seeded):
- admin@epr.test / `password`  (role `admin`)
- finance@epr.test / `password` (role `finance`)
- inspector@epr.test / `password` (role `inspector`)

Filament admin: http://localhost:8000/admin
API base: http://localhost:8000/api/v1

OTP login (development):
```bash
curl -X POST http://localhost:8000/api/v1/auth/otp/request \
     -H 'Content-Type: application/json' \
     -d '{"phone":"9999999999"}'
# Then read the 6-digit code from storage/logs/laravel.log
curl -X POST http://localhost:8000/api/v1/auth/otp/verify \
     -H 'Content-Type: application/json' \
     -d '{"phone":"9999999999","code":"<code-from-log>"}'
```

## Documentation

- [`docs/01-database-design.md`](docs/01-database-design.md) — ERD, every column, every index
- [`docs/02-api-design.md`](docs/02-api-design.md) — full API surface (75 routes), sample requests/responses
- [`docs/03-controls-and-compliance.md`](docs/03-controls-and-compliance.md) — maps each compliance control to its implementation file

## Compliance controls (six)

| # | Control | Where |
|---|---|---|
| 1 | RBAC | `app/Enums/RoleName.php`, `database/seeders/RoleAndPermissionSeeder.php`, route middleware `permission:*`, Filament policies |
| 2 | Audit Trail | `OwenIt\Auditing\Auditable` on every model + immutable `verifications` table |
| 3 | Verification Workflow | `app/Models/Concerns/HasVerificationWorkflow.php` — same trait drives Vendor / Trainer / Candidate / Center / Document / Invoice |
| 4 | Scheme Logic Engine | `app/Services/SchemeLogicEngine.php` — eligibility, status progression, payable milestones |
| 5 | Data Validation & Dedup | DB unique constraints (PAN/GST/Aadhaar token/invoice no/enrollment) + `app/Http/Requests/*` |
| 6 | Compliance Layer | `app/Support/Aadhaar.php` (tokenization, masking), `app/Models/VendorKyc.php` (encrypted bank account), private disk + signed URLs for documents |

## OTP channel

The OTP service is pluggable via `App\Services\Otp\OtpChannel`. Default is `LogOtpChannel` (logs the code to `storage/logs/laravel.log`). To wire MSG91, set in `.env`:

```env
OTP_CHANNEL=msg91
MSG91_AUTH_KEY=...
MSG91_TEMPLATE_ID=...
```

Twilio / other providers: implement `OtpChannel` and bind it in `AppServiceProvider`.

## Tests

```bash
php artisan test
```

Covers OTP flow, vendor verification workflow, illegal-transition rejection, Aadhaar tokenization (no raw column, dedupe via SHA-256 token), and the Scheme Logic Engine (age / education rules).

## Dev notes

- Status transitions are guarded — illegal transitions throw `InvalidArgumentException`.
- Rejecting / suspending requires `remarks` (enforced in the trait).
- Raw Aadhaar enters via `Candidate::aadhaar` virtual setter and is dropped immediately; only `aadhaar_token` (SHA-256(salt|aadhaar)) and `aadhaar_last4` are stored.
- Bank account numbers are encrypted at rest via Laravel `Crypt` with a `_last4` column for display.
- Invoice double-billing is impossible at the DB level: `invoice_items` has a unique `(candidate_enrollment_id, scheme_payment_milestone_id)` constraint.

## Roadmap (post-MVP)

- Vendor / Trainer / Candidate Filament panels (separate from `/admin`) with auto-filtered ownership scopes
- File uploads via Filament SpatieMediaLibrary integration
- Real OTP integration (MSG91 / Twilio)
- Bulk candidate import (CSV) UI
- Reporting dashboards with date-range MIS
- Scheme builder UI (drag-and-drop rule + milestone editor)
