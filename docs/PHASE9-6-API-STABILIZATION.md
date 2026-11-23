# Phase 9.6 — Shared API Stabilization & Desktop Preparation

## Implemented

- Refactored POS cash register **open / cash in-out / close** into `App\Services\PosShiftService`, used by the unchanged web register controller and the versioned `ShiftController`. Tenant-row and register-row locks serialize sensitive shift operations. The shift API accepts the same validation fields as web.
- `GET /api/v1/tenants/{slug}/shifts`: current cashier's open shifts, restricted to assigned branches, with live expected cash.
- `POST /api/v1/tenants/{slug}/shifts`: open cashier shift, requiring `pos.shifts.operate`, active subscription/trial.
- `POST /api/v1/tenants/{slug}/shifts/{id}/cash-movements`: record cash in/out, requiring own shift or tenant ownership, sufficient expected cash, and active subscription/trial.
- `POST /api/v1/tenants/{slug}/shifts/{id}/close`: close with counted cash, expected total and signed difference. This is permitted during business suspension for authorized members to reconcile an existing cash drawer; no new sales or cash movements are allowed during suspension.
- Store owner / explicitly authorized staff device control: web route `/app/{slug}/application-devices`, view device owner, platform, last activity and revoke lost devices. Dedicated permission `pos.devices.manage` (not automatically granted to employee roles; tenant owner bypasses). Revocation deletes matching bearer token(s), marks device inactive and logs an audit entry.
- Checkout idempotency now stores SHA-256 of canonical transaction inputs in `pos_sales.request_hash`. Identical retries reuse the prior sale; mismatching details or different cashier/branch/shift cannot reuse a UUID. Old sale rows with NULL hash remain intact and enforce originating cashier/branch/shift.
- `GET /api/v1/tenants/{slug}/sync/manifest?branch_id=...` exposes server time, a stock-movement watermark and explicit `offline_sales_enabled=false`. The existing stock-movements endpoint can be replayed in ascending IDs; clients must not infer safe local stock adjustments or offline write authorization.
- Feature tests added for: device login/revocation, cross-tenant access, cashier shift lifecycle, reconciling the drawer, idempotent cash checkout, insufficient stock, and owner revocation UI. Tests exercise real API requests on an isolated in-memory test database.

## Desktop integration order

1. Sign in once per device using `POST /api/v1/auth/device-login` and store the Bearer token in the operating system credential vault, never in localStorage or plaintext.
2. Fetch tenant profile, assigned branches, `/sync/manifest`, paginated products, stock, registers.
3. Open shift with the shared API and retain returned shift ID.
4. Submit **online** sales with a fresh UUID for each intended transaction; on network uncertainty, retry **the exact same payload and UUID**. Never reuse the key for a different sale.
5. Reconcile cash at shift close. The server owns stock, prices, expected cash, and discount calculations.
6. Periodically refresh catalog and pull stock movements. Refresh full products to detect changes while tombstones are not available. Do not post offline orders.

## CI and rollout

The focused Phase 9 route tests and device workflow tests have passed on GitHub Actions runs during development. MySQL 8.4 migrations, PHP syntax lint and Blade compilation also passed on those runs. Verify the **latest commit** CI before rollout.

The existing broad suite still fails, including legacy authenticator-app/TOTP scenarios that contradict the current administrator email-code flow, plus billing and administration assumptions. These require careful modernization and integration diagnosis. They have **not** been deleted or bypassed.

Safe commands for a backed-up local development database:

```powershell
cd C:\wamp64\www\POS
git checkout main
git pull origin main
composer install
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=api
php artisan test --filter=Phase9ApiRoutesTest
php artisan test --filter=Phase9DeviceIntegrationTest
php artisan test
npm run build
```

The repo currently does not track `composer.lock`; if necessary, generate and review one through a local Composer update, then check it in. Do not run `migrate:fresh` on any live database.

## Current limits

- No desktop executable, mobile executable, offline sale submission, customer booking or direct printer driver is included in Phase 9.6.
- Device management is a protected browser page, not a platform-wide remote MDM system.
- Stock movement high-water marks are **not** an offline transactional ledger; product deletion tombstones and conflict resolution remain future work.
- Tests do not substitute for a two-device concurrency load test against production-equivalent MySQL. Run this and the full suite before public rollout.
