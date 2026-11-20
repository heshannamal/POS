# POS Cloud — Advanced Tenant Management

## Business profile

- Admins can create a workspace for an existing verified owner.
- Business fields: name, contact person, contact email, phone, address, country (ISO two-letter code), website, registration number, currency and timezone.
- JPEG/PNG/WebP logo uploads up to 2 MB are stored privately; viewable only by permitted tenant members and platform staff. Replacing a logo removes the old file.
- Updating the name does not change the stable tenant slug.
- Existing accounts preserve their data after incremental migrations.

## Business directory

- Search business name, slug, contact email, contact person and member email.
- Filter by plan, ISO country, registration date range and statuses: running/inactive/expired trial, active, inactive or suspended business.
- Sort registration date, name, country or status; 12/25/50 rows per page.
- Show plan, members, stores, onboarding progress and status.
- Archived businesses are in a dedicated list, not the normal business directory.

## Internal notes and onboarding

- Operations and Super Admin can add/delete private notes.
- Note author and timestamp are saved. Mutations produce audit events but note text is never copied into audit metadata.
- Support/Billing staff and business users cannot read these private notes.
- Admin-managed onboarding stages: Incomplete 0%, Pending 25%, Verified 65%, Completed 100%.
- Each stage change requires a reason; completed accounts retain their prior completed status through a migration backfill.

## Inactive, suspended and restored access

- Deactivate or suspend a business to pause licensed feature access without deleting its records or changing paid plan source and expiry.
- Restore returns to the stored previous trial/active state without extending entitlements or creating new payments.
- Status changes require reason and audit. Inactive/suspended tenants cannot regain access through billing activation, payment approvals or module grants.
- Trial-specific inactivation remains a separate operation in Trial Management.
- Account dashboard/support may stay reachable for assistance, but licensed feature routes remain gated.

## Safe archiving and restoration

- Requires permanent Super Admin status, the exact business slug, a reason and the administrator password.
- Archive is soft-delete, not permanent deletion. Normal tenant routes stop resolving the workspace.
- Members, stores, trial history, subscription and payment records are retained.
- The archived list supports password-confirmed restoration with the exact same status as before archiving.
- No irreversible purge functionality has been added.

## Tenant data export

- A permanent Super Admin can download a password-confirmed, audit-logged JSON snapshot.
- The snapshot includes business profile, branches, memberships (no passwords), trial, plan, module grants, payment-review summary and customer-visible support messages.
- This is not a full SQL backup. Private admin notes, internal support messages, password hashes, auth tokens, provider secrets and private payment evidence files are omitted.
- Treat exported JSON as confidential business information.

## Setup and safe upgrade on WAMP

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test
    php artisan serve

Use Node 24 LTS. Never use migrate:fresh on an existing production database: it removes data.

Pages:
- /platform/businesses
- /platform/businesses/create
- /platform/businesses/{slug}
- /platform/businesses/archived

Production: ensure HTTPS, private file permissions, backups of the database AND private logo storage, and tested recovery procedures. Archiving is not legal data erasure.
