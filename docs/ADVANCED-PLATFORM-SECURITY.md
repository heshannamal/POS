# POS Cloud — Advanced Platform Security

## Screens

- /security/challenge — enter a code delivered to the administrator email
- POST /security/resend — request a replacement code (rate limited)
- /security/sessions — personal sessions and password-confirmed revocation
- /platform/security — login attempts, alerts and security KPIs
- /platform/security/sessions — permanent Super Admin: all sessions
- /platform/security/roles — permanent Super Admin: custom roles
- /platform/administrators — assign built-in and custom roles
- /platform/audit — existing immutable audit trail and CSV export

## Email-code 2FA (replaces authenticator TOTP)

When PLATFORM_REQUIRE_2FA=true, all platform administrators must enter a
six-digit one-time code sent to their account email address after password
authentication. A code expires in 10 minutes, is stored only as an HMAC,
is single-use, and permits at most five incorrect attempts. Resends are
rate limited to one per minute; generating a new code invalidates the old one.
Session protection, login auditing and role restrictions remain in force.

Do not use the `log` or `array` mail transport for real login codes:
those transports do not deliver security codes to a mailbox. Administrator
login fails closed if SMTP delivery errors.

The former authenticator setup, TOTP challenge and recovery-code pages
are no longer accessible through application routes. Existing legacy TOTP
columns remain untouched for safe data preservation and future migration
cleanup; there is no live authenticator-based verification path.

### cPanel mailbox on local WAMP

Use **the settings reported by cPanel** for info@web7softwares.com:
```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
MAIL_MAILER=smtp
MAIL_HOST=web7softwares.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=info@web7softwares.com
MAIL_PASSWORD="REPLACE_WITH_NEW_MAILBOX_PASSWORD"
MAIL_FROM_ADDRESS=info@web7softwares.com
MAIL_FROM_NAME="POS Cloud"
PLATFORM_REQUIRE_2FA=true
```

Keep one copy of each MAIL variable, never put the real password in a Git
commit or a support message, and preserve existing APP_KEY and DB settings.
The email recipient is the administrator's `users.email`, not automatically
the SMTP sender mailbox. Check inbox and spam on the administrator address.
If SSL certificates fail on WAMP, configure PHP CA certificates correctly:
do not disable TLS verification.

After backing up DB, run `php artisan migrate` and
`php artisan optimize:clear`. Sign out and sign back in to trigger the
new email-code challenge.

## Login security and sessions

- Repeated invalid credential attempts are tracked by hashed email and IP
  and generate dashboard security alerts.
- Existing route-level throttling remains; a 429 response can occur
  before the application's separate lock message.
- Login records contain outcome, IP, timestamp and truncated user agent;
  no plaintext passwords or geolocation data is stored.
- Session records use hashed IDs, last-seen time, IP and browser/OS user
  agent. The raw cookie/session token is never exposed to an admin.
- Users can revoke an individual personal session with their password.
- Permanent Super Admins can revoke a specific other user's session with
  their own password and a reason. Both actions are audit logged and
  enforced on the next authenticated request.
- Non-revoked sessions are not guaranteed live device connections.
- Existing global forced logout and password-reset controls continue.
- Minimum new-password length is configurable (minimum 12). Optional
  password age expiry redirects to secure emailed password reset.

## Scoped platform administrator roles

Built-in Super Admin, Operations, Billing, Support and Auditor continue
to work as before. A permanent Super Admin can create custom roles with
approved scopes and explicit per-section permissions.

Custom permissions include view/manage for businesses, stores, users,
trials, subscriptions, support, and view for dashboards, reports,
security and audit. Audit CSV export is separately grantable. Create
and edit screens need manage, not merely view. Custom roles do not
grant permanent Super Admin, payment approval, account-wide security
override or platform settings privileges.

Custom-role changes invalidate assigned administrators' login sessions.
Each role edit, security alert resolution and session revocation writes
to the existing audit log.

## Environment and rollout

Use the local .env file (not GitHub) to configure:

    PLATFORM_REQUIRE_2FA=true
    SECURITY_PASSWORD_MIN_LENGTH=12
    SECURITY_PASSWORD_EXPIRY_DAYS=0

APP_ENV=production enables mandatory permanent Super Admin 2FA by default.
Local environments can opt in to exercise the feature. The password-expiry
value 0 disables age-based expiration; any positive number specifies days.

Before deployment back up the populated MySQL database, private files and
your APP_KEY. APP_KEY is used for application encryption and OTP HMAC; rotating it without a
re-encryption plan can make existing encrypted data unreadable. Use HTTPS and reliable
time synchronization (NTP).

A sensible rollout: create two independent permanent Super Admin accounts,
confirm each can receive SMTP email, then enable the policy.
Test login, resend throttling and session revocation on staging.

## Safe local upgrade

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test
    php artisan serve

Use Node.js 24 LTS, not Node 18 for Vite. Never use migrate:fresh on
an existing database. Add your mail provider credentials to local .env
later to deliver email-based password resets.

## Limitations

This phase has in-app security alerts, not email/SMS push alerts;
no external SIEM, IP geolocation, physical device heartbeat or
WebAuthn hardware key integration is claimed. Custom roles include
section view/manage, not arbitrary SQL/object-level policy authoring.
If an administrator loses all authenticators and recovery codes,
use a documented independent server-operator account recovery process,
not an insecure public bypass.
