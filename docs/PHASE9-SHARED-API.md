# Phase 9 — Shared API (first release)

Laravel 13, Laravel Sanctum v4, and the existing POS Cloud tenant / branch / role model.

## Delivery scope

- `/api/v1/auth/device-login`: issue expiring tenant-bound Bearer token after verified email/password and active business membership; **never allow platform administrator credentials**.
- `/api/v1/tenants/{slug}/me`, `/branches`, `/products`, `/stock`, `/registers`, `/sales`, `/sales/{id}`: read APIs.
- `/api/v1/tenants/{slug}/sync/stock-movements?branch_id=...&after_id=...`: ordered stock movement cursor feed.
- `POST /api/v1/tenants/{slug}/sales`: online cash checkout using **exactly the same PosCheckoutService** as browser POS. Requires open shift of logged-in cashier, allowed store, stock, active tenant/subscription, unique UUID idempotency key.
- `GET /devices`, `DELETE /devices/{id}`, `POST /auth/logout`: inspect and revoke individual device tokens.
- `pos_api_devices` and `personal_access_tokens` tables are additive. Token issued for max 30 days (default 14) and scoped to a single tenant and device. Browser cookies alone cannot authenticate device endpoints.
- Every client request validates active tenant membership, current device status and `auth_session_version`; sales endpoints check current `pos.sales` permission and branch assignment.

## Installation and upgrade

**Back up database and do this first in local development, not on production.**

The repository did not previously have Sanctum or API routing; its `composer.json` now requires `laravel/sanctum:^4.5`. Its `composer.lock` was not tracked in the GitHub repository inspected. Install and generate/update the Composer lockfile locally:
```powershell
cd C:\wamp64\www\POS
git checkout main
git pull origin main
composer update laravel/sanctum --with-all-dependencies
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=api
php artisan test --filter=Phase9ApiRoutesTest
php artisan test
```
If your local project tracks `composer.lock`, commit it after verifying dependency resolution. **Do not run `php artisan install:api` afterward**, because the route and migration files are already provided and generating another migration can cause duplication.

Optional `.env`:
```
POS_API_TOKEN_EXPIRY_DAYS=14
POS_API_MAX_DEVICES=10
```

## Device login JSON

```http
POST /api/v1/auth/device-login
Accept: application/json
Content-Type: application/json
```
```json
{
  "tenant_slug": "YOUR_TENANT_SLUG",
  "email": "cashier@example.com",
  "password": "USER_PASSWORD",
  "device_uuid": "8b8e8198-d319-4445-ab43-800b2770a780",
  "device_name": "Cashier Desktop",
  "platform": "desktop",
  "app_version": "0.1.0"
}
```
Keep the returned `access_token` in the OS secure credential store, **never in logs, browser localStorage, public GitHub, a QR code, or plaintext database**.

Read with:
```http
GET /api/v1/tenants/YOUR_TENANT_SLUG/products?branch_id=1
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```
Sales:
```http
POST /api/v1/tenants/YOUR_TENANT_SLUG/sales
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```
```json
{
  "branch_id": 1,
  "shift_id": 1,
  "idempotency_key": "ef59fb71-b1ee-435a-b032-8abf26704418",
  "items": [{"product_id": 1, "quantity": 2}],
  "discount_minor": 0,
  "payment_method": "cash"
}
```

## Critical boundaries

- The API currently supports **online** desktop/mobile operations. Stock sync is a feed for future sync design, not a complete offline synchronization engine. Do not accept offline cash sales until server-side reservation/conflict resolution is designed and tested.
- The client **must** open its shift from the existing web register UI for this first release. Add shared shift services and desktop shift open/close endpoints next.
- Returns, purchase orders, customer booking, push notifications, platform administration, multi-tender payment, and per-device remote management UI are not implemented as API write endpoints yet.
- Do not use platform admin credentials. Existing platform admin email OTP still protects the web login separately.
- Prevent logins on unverified or forced-reset accounts. A revoked device identity cannot be reactivated using the same UUID. Users can register a fresh UUID after security review.
- Revoke a lost device via its `DELETE /devices/{id}` endpoint while signed in on another active device, or revoke its tokens and device record through an authorized administrator workflow. A staff member cannot revoke another user's devices via the v1 API.
- Migration, PHP tests, API permissions, mobile secure storage and device acceptance must be tested locally prior to any production deployment.
