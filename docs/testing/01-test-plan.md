# Test Plan — EPR MVP (PR #1)

## What changed (user-visible)

- New Filament admin at `/admin` for the EPR system. Admins can browse Vendors / Trainers / Candidates / Schemes / Invoices and run a "Transition status" action that drives the verification workflow.
- New REST API at `/api/v1` with OTP-based login; verifying an OTP returns a Sanctum bearer token.
- Every status change writes an immutable row to the `verifications` table with `from_status`, `to_status`, `verifier_id`, optional `remarks`.

## Primary flow — Filament admin verifies a vendor

**Setup (already done; not part of recording):**
- DB seeded: `php artisan migrate:fresh --seed` creates `admin@epr.test / password`.
- Server running: `php artisan serve --host=127.0.0.1 --port=8000`.
- A `Demo Skill LLP` vendor record exists with status `pending_verification` (created via tinker before recording so the record is on the list).

**Steps & assertions (recorded):**

1. Navigate to `http://127.0.0.1:8000/admin/login`.
   - **Pass:** Filament login form renders with "Email address" + "Password" inputs.
   - **Fail:** 404, 500, blank page, or a different login surface.

2. Sign in with `admin@epr.test` / `password`.
   - **Pass:** redirect to `/admin` dashboard; left sidebar shows nav groups **Partners**, **Candidates**, **Schemes**, **Operations**.
   - **Fail:** "These credentials do not match" error, redirect back to login, or sidebar shows no resource items (means `canAccessPanel` returned false).

3. Click **Vendors** in the **Partners** group.
   - **Pass:** table shows at least one row with legal_name `Demo Skill LLP` and a status badge that reads exactly **pending verification** (lowercased, derived from the enum value `pending_verification`).
   - **Fail:** empty table, generic "Status" column without a colored badge, or status text not matching the enum.

4. On the `Demo Skill LLP` row, open the **Transition status** action (action button on the row).
   - **Pass:** modal opens with a `Move to` select pre-populated with **only** the legal next states for `pending_verification` — i.e. `verified` and `rejected`. (Source: `app/Enums/VendorStatus::transitions()`.)
   - **Fail:** the select shows draft / suspended / active / inactive (those are illegal from `pending_verification`).

5. Pick `verified` and click **Confirm** in the modal (no remarks).
   - **Pass:** modal closes, success toast "Status updated" appears, the row's status badge re-renders as **verified**.
   - **Fail:** modal stays open, "Transition failed" red toast, or the badge reads anything other than verified.

6. Open a fresh shell and query the DB to prove the audit row exists:
   ```bash
   php artisan tinker --execute "
     echo \App\Models\Verification::where('verifiable_type', \App\Models\Vendor::class)
       ->latest()->first()->toJson();
   "
   ```
   - **Pass:** JSON contains exactly `"from_status":"pending_verification","to_status":"verified","verifier_id":1` (admin user id).
   - **Fail:** no row, wrong from_status (e.g. blank), null verifier_id (means the action didn't pass `auth()->id()`), or to_status not `verified`.

7. Try to run **Transition status** again on the now-`verified` row and pick `verified` from the next-states list.
   - **Pass:** the select does **not** offer `verified` (because it's not in `transitions()['verified']`); only `active`, `suspended`, or similar legal next-states show. Per the trait, attempting an illegal transition would throw `InvalidArgumentException` and produce a red toast — but the dropdown should already prevent this.
   - **Fail:** dropdown still shows `pending_verification` or `verified`, breaking the state machine.

This flow is broken-detection-grade: if any of the workflow trait, the Filament action, the audit row creation, or the canAccessPanel check is missing/broken, one of the assertions above fails visibly.

## Secondary flow — OTP login API issues a working bearer token (shell)

This is run via `curl` in a side terminal. Captured as command output in the report (not in the screen recording).

```bash
# 1. Request OTP
curl -sS -X POST http://127.0.0.1:8000/api/v1/auth/otp/request \
     -H 'Content-Type: application/json' \
     -d '{"phone":"9000000001"}'
# Expected: {"message":"OTP issued. Check your SMS."}

# 2. Pick the code from the dev log
CODE=$(grep -oP '"phone":"9000000001"[^}]*"code":"\K[0-9]+' \
       storage/logs/laravel.log | tail -1)

# 3. Verify
TOKEN=$(curl -sS -X POST http://127.0.0.1:8000/api/v1/auth/otp/verify \
     -H 'Content-Type: application/json' \
     -d "{\"phone\":\"9000000001\",\"code\":\"$CODE\"}" \
   | python3 -c "import sys,json;print(json.load(sys.stdin)['token'])")

# 4. Hit /auth/me with it
curl -sS http://127.0.0.1:8000/api/v1/auth/me \
     -H "Authorization: Bearer $TOKEN"
# Expected: {"user":{"id":...,"phone":"9000000001",...},"roles":["candidate"],"permissions":[...]}

# 5. Negative: bad token rejected
curl -sS -o /dev/null -w "%{http_code}\n" \
     http://127.0.0.1:8000/api/v1/auth/me \
     -H "Authorization: Bearer obviously-not-a-real-token"
# Expected: 401
```

**Pass criteria:**
- `/auth/me` with the real token returns HTTP 200, `phone == "9000000001"`, `roles == ["candidate"]` (new users default to candidate role per `AuthController::verifyOtp`), and `permissions` is a non-empty array.
- `/auth/me` with a bogus token returns HTTP 401.

**Fail criteria (any of):**
- Step 1 returns anything other than 200 with the success message.
- Step 2 produces an empty `CODE` (means `LogOtpChannel` did not log).
- Step 3 returns 422 / 401 (means OTP verification logic is broken).
- Step 4 returns 401 (means Sanctum binding or the `auth:sanctum` middleware is broken).
- Step 5 returns anything other than 401 (means auth is being bypassed).

## Out of scope for this recording

- Full vendor self-registration → KYC → invoice → payment flow (that's a follow-up integration test; covered partially in `tests/Feature/`).
- Filament panels for Vendor / Trainer / Candidate self-service (called out as roadmap in README).
- Real MSG91 / Twilio OTP integration (only the `LogOtpChannel` is wired in the MVP).
