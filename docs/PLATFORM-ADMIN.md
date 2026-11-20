# POS Cloud — Platform Super Admin

## Included admin modules

| Page | Purpose | Roles |
| --- | --- | --- |
| /platform/dashboard | Tenant, trial and activity overview | All platform admins |
| /platform/businesses | Business management and branches | Operations, Super |
| /platform/businesses/{slug} | Business profile, trial, manual licensing | Operations, Billing, Support, Super |
| /platform/users | Customer directory and one-time account invitations | Operations, Support, Super (invitations: Operations, Super) |
| /platform/subscriptions | Manual subscription assignment registry | Billing, Super |
| /platform/plans | Plans, prices, module inclusions, branch/employee limits | Billing, Super |
| /platform/modules | Planned and released module catalog | Billing, Super |
| /platform/payment-setup | Gateway/settlement readiness checklist | Billing, Super |
| /platform/trials | Active, expired and converted trials | Operations, Super |
| /platform/reports | Real registration and conversion statistics | Auditor, Operations, Billing, Super |
| /platform/support | Tenant support, replies, private notes, assignment | Support, Operations, Super |
| /platform/announcements | Published and scheduled business announcements | Support, Operations, Super |
| /platform/audit | Filtered audits and capped CSV export | Auditor, Super |
| /platform/administrators | Admin promotion, scoping and revocation | Permanent Super only |
| /platform/settings | Registration toggle, trial, currency, timezone | Permanent Super only |

Existing administrators without a role are grandfathered as super_admin.
New roles: super_admin, operations, billing, support, auditor.
Temporary local admins cannot manage permanent administrators or platform settings.

## Tenant and user management

Tenant data remains isolated. Operations can edit business profiles, create branches,
activate and deactivate locations, and designate a default branch. Branch updates
verify tenant ownership. Deactivating the default branch requires replacing it first.
Plan maximum branch/employee counts can be configured to limit new additions.

Operations can send new-user invitation emails through the Customers & Users page.
A random 64-character token is hashed in the database, expires after seven days
and can be used once to create a verified account with a user-selected password.
The invitation route works when public registration is closed. On localhost,
MAIL_MAILER=log writes invitation URLs to storage/logs/laravel.log.

Permanent super-admins can promote existing verified users, change their scoped
platform role and revoke access. A correct current password is required.
Self-role changes, temporary admin promotions and revocation of the last
permanent super-admin are blocked.

## Trials, plans and payments

Trial dates belong to the actual business; upgrading a trial keeps all data.
Manual plan activation is an administrative entitlement grant, NOT a payment.
Module catalog entries remain unavailable until their feature implementation is
released in code, even if included in a plan.

No card payment, automatic subscription billing, merchant funds ledger, merchant
verification or settlement execution is currently available. The Payment Setup
screen is a readiness checklist, not a live financial dashboard. Obtain approval
from the acquiring/payment partner for the intended collection and payout model.

## Support and communication

Tenant owners (and members with settings.manage permission) can open support
requests at /app/{tenant-slug}/support. Platform support operators triage,
prioritize, assign and reply. Internal notes never appear in tenant responses.
Announcements can be drafted or published with start/end dates and appear on
business dashboards when visible. Outbound support-reply email notifications are
not yet implemented; messages are stored in the app.

## Activity and analytics

Reports show real business registrations, trials, assignments, conversions and
support volumes — not fabricated revenue. Audit history is read-only with
filterable CSV export limited to 5000 rows. Exports mitigate common CSV formulas.

## Update an existing Windows/WAMP installation

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test
    php artisan serve

Run with Node.js 24 LTS (Vite 8 requires modern Node). Do not use migrate:fresh:
it drops existing data. The new migrations append tables/columns.

For a permanent super admin, run:
    php artisan platform:make-admin your-email@example.com

Before production: HTTPS, real mail transport, backups, deployment secrets,
monitoring, queue workers, account-access review, financial partner agreement,
security testing and restore drills are required. POS checkout, inventory,
mobile/desktop apps and e-commerce storefronts are future phases.
