# POS Cloud

Laravel 13 multi-tenant SaaS for an advanced POS and commerce platform.

## Admin foundation implemented

- Secure authentication, verified registration, temp local admin and user invitations
- Tenant management, branches, employee/permission scaffolding, trials
- Plans, modules, manually assigned subscription entitlements
- Scoped platform admins: Super, Operations, Billing, Support, Auditor
- Customer directory, single-use verified account invitations
- Branch/profile operations, company status control and audit trail
- Subscription registry, operational analytics and CSV audit export
- Tenant support tickets, internal notes, response management, announcements
- Registration/trial/platform defaults in Settings
- Responsive light/dark SaaS console

All changes use PHP validation, authorization, Laravel CSRF protections and
audited server operations. GitHub Actions checks PHP, Blade, PHPUnit,
frontend assets, and MySQL 8.4 migrations.

## Documentation

- [Installation](docs/FOUNDATION.md)
- [Subscription and staff foundation](docs/PHASE2.md)
- [Platform admin operations](docs/PLATFORM-ADMIN.md)

To update your existing local project, pull main and run php artisan migrate.
Do NOT use migrate:fresh unless you intend to delete all existing data.
Vite 8 needs a current Node.js release, e.g. Node 24 LTS.

### Not yet live

This is the platform admin layer. Actual POS sales/inventory, mobile/desktop
clients, customer storefronts, online billing, merchant settlement, KYC and
payment gateway integrations remain separate development phases. Manual
subscription activation does not imply a payment has occurred.

## Subscription payment setup

See [payment preferences, uploads, review and security](docs/PAYMENT-REVIEW.md). Both payment methods start disabled. Uploaded proofs can be enabled by Super Admin and reviewed by Billing; card checkout awaits an approved provider. These are SaaS subscription fees, not retail sales or merchant settlements.

## Plan discounts & trial operations

See [Plan Discounts & Trial Administration](docs/PLAN-DISCOUNTS-AND-TRIALS.md) for the checkbox/percentage discount, discounted payment snapshots, creating trials for verified owners, inactivating/reactivating and extending trials.

## Advanced tenant management

[Business profiles, country/logo, filters, internal notes, onboarding, lifecycle controls, reversible archiving and tenant export](docs/ADVANCED-TENANT-MANAGEMENT.md).

Requires incremental migrations and backup of private logos. No existing business data is deleted by these changes.

## Global Stores & Tenant Staff

See [Global Stores, tenant staff, device registry, login audit, session revocation, and Gmail SMTP setup](docs/GLOBAL-STORES-AND-USERS.md). Add private credentials only in your local `.env` file; the repository defaults to logged emails until configured.

## Advanced Platform Security

[Authenticator 2FA, recovery codes, revocable sessions, login alerts, and least-privilege roles](docs/ADVANCED-PLATFORM-SECURITY.md). The production policy requires 2FA for permanent Super Admins. Keep APP_KEY safe, as it encrypts TOTP secrets.

## Phase 5 — Communication Center

See [Phase 5 Communication Center](docs/PHASE5-COMMUNICATION-CENTER.md) for editable event templates, recipient rules, encrypted delivery logs, test-mode SMTP safeguards, notification inbox, queue worker and scheduled reminders. Outbound email is disabled until enabled by the platform administrator.

## Phase 6 — Advanced Subscription Billing

[Invoices, verified receipts, coupons, tax defaults, externally reconciled refunds/credit notes, renewal controls and financial reports](docs/PHASE6-ADVANCED-BILLING.md). Payment activation still requires independent administrator approval; card checkout and automatic charging remain disconnected.

## Phase 7 — POS operations foundation (partial)

Initial [product catalog, branch inventory, cash sales terminal and receipts](docs/PHASE7-POS-FOUNDATION.md) are implemented. Advanced register shifts, refunds, retail taxes, barcode printer integrations and stock analytics are still pending. This is **not** a completed Phase 7. Apply incremental migrations after backing up your database.


## Phase 7 cashier operations (new)
[Register shifts, cash in/out, reconciliations, partial cash refunds, inventory reports, CSV and browser-based barcode/thermal printing](docs/PHASE7-OPERATIONS.md) are implemented in the codebase. Granular POS permissions must be explicitly granted to staff. Local migrations, automated tests and actual printer acceptance tests are still required before production deployment.

## Phase 8 — Purchasing & Inventory (first release)

[Supplier directory, purchase orders, GRNs, payment evidence, stock counts, reorder controls and purchasing reports](docs/PHASE8-PURCHASING.md) are implemented with tenant-specific data, branch assignments and granular role permissions. Apply additive migrations and run the feature/unit test suite on a backed-up local MySQL database. Advanced batch expiry, purchase tax, supplier returns, aging, and gateway reconciliations remain future work; the first release is not production-validated.

## Phase 9 — Shared API v1

A [versioned Laravel Sanctum device API](docs/PHASE9-SHARED-API.md) is provided for future POS desktop/mobile clients. It includes tenant-bound device tokens, revocation, assigned-store read endpoints, stock delta feeds, and **one shared checkout service** for web and API. No customer booking, offline sales, or full desktop/mobile application has been delivered by this phase. Local `composer update laravel/sanctum --with-all-dependencies`, migrations, tests, and deployment verification are still required before use.

### Phase 9.6 — Stabilization and desktop groundwork
[Shared cashier shift service and API, browser device management, strict checkout retry fingerprints, and online-only sync manifest](docs/PHASE9-6-API-STABILIZATION.md). Targeted CI tests run separately from the still-failing legacy suite. Do not deploy before a full regression and transaction concurrency review.
