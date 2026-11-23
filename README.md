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
