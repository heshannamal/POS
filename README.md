# POS Cloud

Modular Laravel 13 multi-tenant POS SaaS platform.

## Implemented

**Phase 1 — Foundation**
- Verified email registration, authentication, onboarding and secure platform administrator.
- Tenant separation, branches, owner roles, trial provisioning and audit logs.
- Responsive business and platform dashboards with matching light/dark themes.

**Phase 2 — Commercial & workforce administration**
- Plan catalog and module entitlements with server-side feature checks.
- Admin-managed manual plan activations, module overrides and trial duration.
- Team membership, secure email invitations, employee status management, custom roles and permission matrix.
- Tenant-scoped authorization and audit logging; automated feature and security tests.

## Setup and documentation

- [Installation and foundation](docs/FOUNDATION.md)
- [Phase 2 workflows and upgrade instructions](docs/PHASE2.md)

This remains a **development-stage SaaS platform**, not yet a complete POS product. Physical POS sales, inventory operations, payment processing and merchant settlements are not live. Prices in the default plan catalog are unpublished placeholders.


## Temporary login for local development

After pulling and migrating the current main branch, run:

    php artisan platform:temporary-login --hours=24

The command creates a random, email-verified platform administrator and prints its
unique email and password once. Open /login and use those credentials.
The account automatically loses access after 24 hours (configurable from 1 to 72 hours).
The command is disabled when APP_ENV is production. Never commit or share the
generated password. Regular admins can be created with platform:make-admin.
