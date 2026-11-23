# POS Cloud — Phase 5 Communication Center

## Implemented screens

Permanent Super Admin:
- /platform/communications/templates — event template library
- /platform/communications/templates/create — custom manual drafts
- /platform/communications/templates/{id}/edit — formatting toolbar, placeholders, HTML preview
- /platform/communications/recipients — per-event recipients and channel controls
- /platform/communications/settings — global email switch, test mode, SMTP test
- /platform/communications/deliveries — queued, accepted, logged, skipped, failed and retry states

Platform users: /platform/notifications
Business members: /app/{tenant}/notifications

The sidebar and topbar notification bell show unread messages for the signed-in user.
Users cannot mark other users' messages read; business routes also restrict the tenant.
Normal users can select per-event email and in-app preferences. Critical platform
security alerts remain available in-app regardless of personal opt-out.

## Templates

Built-in editable event templates include business registration, payment
submission/approval/rejection, trial expiry, subscription renewal reminders,
support tickets/replies, security alerts and staff invitation notices.
Subjects and message bodies use allowlisted variables such as
{{business_name}}, {{payment_id}} and {{trial_end_date}}.

The editor supports Markdown formatting with a server-rendered visual HTML
preview. It strips embedded HTML and unsafe links, escapes dynamic variables,
and does not execute Blade or PHP. Placeholders are preserved in previews.
Edits do not change the encrypted snapshots of messages already queued.

Custom templates may be created and edited but are saved as manual drafts:
they do not automatically create a recipient event or send emails.
Legacy login/password reset, account invitation and staff invitation emails
still use config/pos_emails.php and the same Laravel mail transport.

## Events and delivery

The service uses existing audit and security events after the transaction
commits. Failures are reported separately, so mail delivery does not affect
payment approval or a business transaction. Internal support notes are never
forwarded as customer notifications. Scheduled trial/subscription reminders
are idempotent per expiry date and recipient.

- Email sending starts DISABLED.
- Test mode starts ENABLED.
- Configure a test-recipient allowlist of up to five email addresses.
- When test mode is enabled, automatic emails are redirected to the
  allowlist. The worker rechecks the allowlist before actual sending.
- To send to real users, configure a live SMTP/transactional provider and
  deliberately disable test mode.
- Delivery bodies are encrypted in the database; logs show non-secret
  metadata and status.
- The worker reports accepted (not guaranteed delivered), logged,
  failed, skipped, queued or retrying. SMTP outages can be retried.
- Mailing lists and transactional delivery do not imply marketing consent.
- No SMS or external mobile push provider is integrated in this phase.

## Gmail App Password (private .env)

Enable 2-Step Verification in your Google Account, then create a Google
App Password at https://myaccount.google.com/apppasswords. Never use
your regular Gmail account password or commit secrets to GitHub.

In C:\wamp64\www\POS\.env:

    MAIL_MAILER=smtp
    MAIL_HOST=smtp.gmail.com
    MAIL_PORT=587
    MAIL_SCHEME=smtp
    MAIL_USERNAME=youraddress@gmail.com
    MAIL_PASSWORD=YOUR_GOOGLE_APP_PASSWORD
    MAIL_FROM_ADDRESS=youraddress@gmail.com
    MAIL_FROM_NAME="POS Cloud"

Run php artisan config:clear. Under Email Settings, enter your own test
email, enable email sending while TEST MODE remains on, then use the SMTP
diagnostic form. A transport accepting an email does not guarantee inbox
delivery. Gmail personal accounts have sending limits; use a dedicated
transactional mail provider at greater scale.

## Queue and scheduler

For development, run a second PowerShell terminal:

    cd C:\wamp64\www\POS
    php artisan queue:work --queue=communications,default --tries=3

For a third terminal, run php artisan schedule:work, or manually:

    php artisan communications:send-reminders --days=3

For production, configure a supervised long-running queue worker and a
cron job running php artisan schedule:run every minute. The daily reminder
runs at 09:00 according to your scheduler/server timezone. Configure a
persistent cache driver for withoutOverlapping scheduler locks.

## Safe update

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test
    php artisan serve

Use Node.js 24 LTS for the installed Vite version. Do NOT run
php artisan migrate:fresh on an existing database. Back up MySQL and
APP_KEY before deployment: APP_KEY encrypts email snapshots and existing
2FA secrets.

## Scope and operational limitations

This is not a full bulk-marketing campaign system. Custom templates are
drafts until explicitly wired to an event in a later phase. No bounce/open
webhooks, geolocation, SMS, third-party push notifications or independent
provider delivery confirmation are claimed.
