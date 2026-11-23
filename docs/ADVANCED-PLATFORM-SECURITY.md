# POS Cloud — Advanced Platform Security

## Screens

- /security/setup — authenticator setup for platform admins
- /security/challenge — verify a TOTP or single-use recovery code
- /security/recovery — view newly generated codes once / regenerate codes
- /security/sessions — personal sessions and password-confirmed revocation
- /platform/security — login attempts, alerts and security KPIs
- /platform/security/sessions — permanent Super Admin: all sessions
- /platform/security/roles — permanent Super Admin: custom roles
- /platform/administrators — assign built-in and custom roles
- /platform/audit — existing immutable audit trail and CSV export

## Authenticator 2FA

TOTP follows RFC 6238: HMAC-SHA1, 30-second steps and six digits. Common
authenticator apps work using the manually displayed key or otpauth URI.
The secret is encrypted using APP_KEY; never copy it into public QR services.

Enrollment requires your password and a valid authenticator code. Eight
single-use recovery codes are generated, stored as password hashes and
shown only once. Regenerate them using a valid authenticator code and
password. Each code can be consumed only once.

Once configured, an administrator cannot reach protected platform pages
without completing 2FA. Permanent Super Admin accounts must enroll when
PLATFORM_REQUIRE_2FA=true (production default); they cannot disable it
while the policy is on. Existing admins are prompted to enroll on login
instead of having their accounts deleted or their passwords replaced.
Non-Super Admins may enroll voluntarily. Temporary local development
admins retain their restricted access.

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
your APP_KEY. APP_KEY encrypts TOTP secrets; rotating it without a
re-encryption plan makes stored secrets unreadable. Use HTTPS and reliable
time synchronization (NTP).

A sensible rollout: create two independent permanent Super Admin accounts,
enroll both, securely save recovery codes, then enable production policy.
Test one browser login and one session-revocation operation on staging.

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
