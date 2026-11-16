# POS Cloud - Laravel foundation

## Implemented

- Laravel 13 skeleton with session-based login, logout, registration, verified email and password reset.
- Real multi-tenant model: tenants, tenant users, default branch and tenant-scoped role/permission records.
- Atomic business onboarding with a configurable trial and no separate demo database.
- Platform administration: dashboard, business search/details, manually provision registered owners, trial extensions and status overrides.
- Business owner dashboard; dark/light theme with responsive pill navigation and pastel cards.
- Audit records for provisioning and platform changes, plus tenant isolation tests.

Not yet implemented: checkout, products UI, advanced inventory, paid subscriptions, payment gateway, merchant settlements, employee invitation UI, storefront themes, mobile/desktop apps.

## Requirements

- PHP 8.3+ (8.4 recommended), Composer 2
- MySQL 8 or PostgreSQL, or SQLite for automated tests
- Node.js 20+ and npm
- PHP extensions including mbstring, openssl, pdo_mysql or pdo_sqlite, xml and tokenizer

## Installation (Windows WAMP example)

From C:\wamp64\www open your terminal:

    git clone -b feature/foundation-tenancy-onboarding https://github.com/heshannamal/POS.git
    cd POS
    composer install
    copy .env.example .env
    php artisan key:generate

Create the MySQL database named pos_cloud and configure the DB credentials in .env.

    php artisan migrate
    npm install
    npm run build
    php artisan platform:make-admin admin@example.com --name="Platform Owner"
    php artisan serve

The admin command prompts for a strong password. There is no hard-coded admin login.

Visit http://127.0.0.1:8000 for the site; /platform/dashboard is reserved for administrators. Run npm run dev in another terminal for live frontend development.

## Email

Business owners must verify their email before creating a tenant. The sample environment uses MAIL_MAILER=log; local verification and password reset links appear in storage/logs/laravel.log. Configure a real SMTP or email API provider for production.

## Tests

    php artisan test

The test suite uses SQLite in memory, independent of local MySQL settings.

## Security and architecture

- All tenant-specific data uses tenant_id and/or explicit tenant membership.
- The tenant.member middleware prevents accessing another business dashboard.
- The platform.admin middleware checks a separate super-admin flag; registration never sets it.
- Roles/permissions are tenant-scoped and checked through TenantAuthorization.
- A business trial uses the same records as the eventual paying account; an expired trial retains its data.
- New business, main branch, membership, roles and trial are created in one database transaction.
- Sessions regenerate after authentication, web forms include CSRF protection, and sensitive auth endpoints are rate-limited.
- The super admin is provisioned only through a server-side CLI command.
- Trial extensions and status overrides are logged.

### Tenant onboarding lifecycle

1. Register user.
2. Verify email.
3. Complete business profile.
4. Create tenant + main branch + owner membership + default roles + trial.
5. Start business management dashboard.
6. When billing is implemented, upgrade by changing subscription entitlements on this tenant. No data copy or deletion.

### Application security for future modules

Every new business resource must use both tenant_id filtering and authorization on each API endpoint. Never trust a client-supplied tenant_id alone. Enforce server-side permissions and active module entitlements separately. Devices and payment gateways will be implemented in later phases.

## Recommended next milestones

1. Subscription plans, modules and feature entitlements.
2. Employee management, role permission editing and invitation workflow.
3. Products and inventory movement ledger with tests.
4. POS sales and receipt printing; offline-capable Flutter apps.
5. Storefront themes, approved merchant payments and settlements.

## Deployment

Serve only from public/, use HTTPS, configure real mail, queues, scheduled jobs, backup and restore procedures, monitoring, rate limiting, and APP_DEBUG=false. Keep .env, credentials and merchant secrets out of Git. Build assets and run database migrations during deployment.
