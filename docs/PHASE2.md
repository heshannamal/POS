# POS Cloud — Phase 2: Commercial catalog, staff and trial administration

This release extends the original multi-tenant Laravel foundation. **There is no live payment gateway, checkout, merchant collection, subscription auto-renewal or merchant payout in Phase 2.**

## Upgrade an existing installation

Do not run migrate:fresh — it would delete existing tenant data.

From the POS project root on WAMP:

    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test

The migration seeds the Starter, Business, and Commerce catalog plans with default monthly prices **LKR 0.00**, marked unpublished. These are templates to be configured by the platform administrator, not public sale offers. The new module registry includes **staff.core** as the only released module. Other modules are listed as PLANNED and cannot be enabled until their implementations ship.

Existing businesses automatically receive a Starter subscription record. Active businesses retain their active status and receive a manual source marker; trial tenants continue the same real trial, preserving all prior data.

## Platform administrator

After logging in as the CLI-created super administrator:

- /platform/dashboard — platform summary
- /platform/plans — manage plan catalog and module inclusions
- /platform/plans/create — add a plan
- /platform/trials — view running, expired, converted and suspended trials
- /platform/businesses — inspect tenant information, licensing and trial details
- /platform/businesses/{tenant-slug} — activate a plan manually, set an explicit feature override, extend trials or suspend access

**Manual plan activation is not a payment.** It records a manually assigned subscription and an audit event with payment_collected=false. Do not represent this as a paid transaction.

Editing included modules changes the current entitlements of all tenants assigned to that plan, subject to released-module availability, tenant status, trial expiry and explicit overrides.

Trial default duration is editable under Trial Management and applies only to new tenants. Existing trial dates remain unchanged. Extension adds days to the current end date or, when expired, to the current time.

## Business workspace

- /app/{tenant-slug}/dashboard — core overview (readable even after trial expiry)
- /app/{tenant-slug}/subscription — plan and feature status (read-only)
- /app/{tenant-slug}/employees — employee roster, invitations and status management
- /app/{tenant-slug}/roles — permission matrix and custom roles

Access to employee and role screens requires active membership, staff.core entitlement and corresponding permission. The business owner has all permissions. A Manager receives common employee-view, POS, inventory and report permissions by default; Cashier receives POS and inventory-view permissions. POS and inventory applications are not yet implemented.

Only the owner or a role explicitly granted roles.manage can send invitations, assign roles and edit permissions. The Owner role is protected against mutation. All role/member mutations are tenant-scoped, validated and audited.

## Invitations

Employees are invited by email to a specific tenant and role. Invitations use 64-character random tokens stored as SHA-256 hashes and expire after seven days. The employee must sign in with that invited address and verify their email. An invitation can be accepted once, and role assignment is checked against the invitation tenant. Suspended memberships cannot be reactivated through invites.

During local development, MAIL_MAILER=log writes the invitation URL to storage/logs/laravel.log. Configure an external mail transport for real users. Test in a private browser session to avoid signing in as the wrong account.

## Feature licensing

FeatureAccess::allows($tenant, $moduleCode) checks:

1. The tenant is active or has a currently valid trial.
2. The module has been released (is_available).
3. The tenant has an active/manual or current trial subscription.
4. The subscription has not expired.
5. Explicit tenant module overrides, or the module's inclusion in the plan.

The staff module route also requires tenant membership and permission checks. All future POS modules must do the same on the server; hiding UI links is not authorization.

## Configuration and money

Plan prices and module prices are catalog placeholders only. Final prices, renewal policy, taxes, gateway fees, payment verification, automatic entitlement issuance and merchant settlement need separate implementations and an appropriate payment-partner arrangement.

## Testing

GitHub Actions runs:
- PHP syntax lint across app, routes and migrations
- Blade template compilation
- PHPUnit feature and security tests (SQLite in-memory)
- Vite production build
- A separate MySQL 8.4 database migration check

Do not run migration rollback or fresh on production without backups and a reviewed schema change.
