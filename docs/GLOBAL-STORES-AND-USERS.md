# POS Cloud — Global Stores & Tenant Users

## Platform screens

- **All Stores:** /platform/stores
- **Store Details:** /platform/stores/{store_id}
- **Tenant Staff:** /platform/tenant-users
- **Tenant Member Details:** /platform/tenant-users/{tenant_slug}/{user_id}
- **Existing customer accounts:** /platform/users
- **Existing business/branch provisioning:** /platform/businesses/{tenant_slug}

The sidebar includes All Stores and Tenant Staff. Operations/Super Admin can
write store and user settings; Support is read-only; platform Billing cannot
manage store or membership operations.

## Global store management

- Search or filter by store name, business, manager email, country, status.
- Manager must be an active tenant owner or a member with the tenant Manager
  role. Assigning a manager adds that manager to the store's assigned staff.
- Change store name, address, phone, timezone, active/closed/suspended status.
- The default store cannot be closed/suspended until another active default is
  selected in Business > Branches. Reactivation enforces plan store limits.
- Add/remove active business members from specific stores. An assignment
  **cannot** create tenant membership.
- Device registry: POS terminal, receipt printer, barcode scanner. Serial
  numbers are unique per tenant. Registered/inactive/retired are **inventory
  states**, not live device connectivity. No heartbeat, driver or remote
  terminal connection is implemented in this phase.
- Store override settings: receipt footer, stock threshold and print
  preference. They are persisted for future modules, but no receipt printing
  or stock logic is activated by this screen.
- Changes are audit logged and don't alter other tenant records.

## Tenant user management

- Directory lists memberships (a user can appear once per business).
- Filter by business, store assignment, role, membership status and name/email.
- Admin can assign a tenant role and set access active/inactive/blocked. Owners,
  platform administrators, and self-editing are protected. A blocked membership
  does not block the same user from other business memberships.
- Staff assigned to a store can be removed without deleting their membership.
- Last-login tracking stores timestamp and IP; login events store outcome, IP,
  and truncated user-agent. Location is not inferred.
- Database sessions can be listed when SESSION_DRIVER=database. Other session
  drivers are supported for global revocation but cannot list individual
  sessions through the database viewer.
- Permanent Super Admin can require a password reset or revoke all sessions;
  their own password and an internal reason are required. Operations and
  temporary admins cannot perform those global account-security changes.
- Requiring a reset revokes existing sessions and sends a secure, expiring
  email reset link. No default or emailed plaintext password is created.
- Login middleware checks a per-user session version, so revocation takes
  effect even when sessions use a file/Redis driver instead of database.
  Setting force_password_reset_at prevents re-login until password reset.

## Email variables

The project defaults to MAIL_MAILER=log and does not commit any secret.

Configure your own SMTP transport via **.env** (not GitHub). Editable email
text and variables live in config/pos_emails.php.

- password_reset: platform_name, user_name, user_email, reset_url,
  expires_minutes.
- staff_invitation: platform_name, business_name, role_name,
  employee_email, invitation_url, expires_days.
- account_invitation: platform_name, user_name, user_email,
  invitation_url, expires_days.

When you want approved text, edit those config entries and keep the tokens
(e.g. {{invitation_url}}). Raw PHP or arbitrary template code is never
evaluated. SMTP mail sending is **not** tested until real .env credentials are
provided. Standard email-verification notices still use Laravel's built-in
verification notification.

### Gmail App Password instructions

Google requires 2-Step Verification for App Passwords. See:
https://support.google.com/accounts/answer/185833

1. Enable 2-Step Verification: https://myaccount.google.com/security
2. Create App Password: https://myaccount.google.com/apppasswords
3. Label it "POS Cloud Laravel" and copy the generated 16-character password
   once. Google may hide App Passwords for organization-controlled accounts,
   Advanced Protection or security-key-only verification.
4. In **C:\wamp64\www\POS\.env**, set:

    MAIL_MAILER=smtp
    MAIL_HOST=smtp.gmail.com
    MAIL_PORT=587
    MAIL_SCHEME=smtp
    MAIL_USERNAME=youraddress@gmail.com
    MAIL_PASSWORD=YOUR_GOOGLE_APP_PASSWORD
    MAIL_FROM_ADDRESS=youraddress@gmail.com
    MAIL_FROM_NAME="POS Cloud"

5. Run php artisan config:clear, send a test invitation or reset to an email
   you own, and check Laravel logs for delivery failures.
6. Use a dedicated sender account for production. Gmail personal accounts
   have sending limits; use an approved transactional provider at scale.

Never send the App Password through chat or commit .env. Gmail app-password
access is distinct from a user's normal Google password.

## Local setup

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test
    php artisan serve

Use Node 24 LTS (Node 18 is unsupported by the installed Vite release).
Do **not** run migrate:fresh on an existing database.
