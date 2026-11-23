# Phase 6 — Advanced Subscription Billing

Implemented in the Laravel 13 POS Cloud application, main branch.
Uses incremental migrations only. Existing tenants, subscriptions, payment
reviews, trials, staff, notifications and administrator security are retained.

## What this phase does

- Every **new uploaded payment proof** creates a numbered immutable
  subscription invoice (INV-00000001, etc.) in the same DB transaction.
  The invoice snapshots plan name, list price, existing plan percentage
  discount, coupon savings, tax and payable total in integer minor units.
- The new **price quote** endpoint shows the actual amount before an
  uploaded receipt is submitted. The server independently recalculates
  values at submission. Plan/coupon prices are never trusted from the browser.
- The existing payment reviewer independently checks bank/acquirer
  records and enters the exact verified amount and unique external reference.
  Only **then** is the subscription activated, invoice marked paid and a
  numbered receipt (RCT-00000001, etc.) issued. Rejection voids the invoice.
- **Card checkout remains non-operational** until a verified acquiring
  integration exists. Enabling "card" in Payment Setup does not activate
  the gateway. The existing card-capture verification guard is preserved.
- Business owners / authorized settings managers can view and download
  private invoices, receipts and credit notes. Cross-tenant documents 404.
- PDF downloads contain the invoice/receipt/credit-note breakdown and
  verified payment references. A compact plain-text PDF generator avoids
  requiring new Composer packages. Existing uploads remain private files.
- Owners may choose a **renewal reminder preference**, or cancellation at
  period end. This is NOT unattended card payment or authorization to collect
  money. Period extension requires another independently verified payment
  or an explicit administrator free extension with a reason.
- Platform billing managers may record an **externally verified refund**
  against a paid invoice, limited to the remaining amount. This produces a
  numbered credit note (CN-00000001) with a unique bank/acquirer refund
  reference. It does not send funds, initiate a card reversal or silently
  cancel the subscription.
- **Tax settings:** a configurable platform subscription tax percentage
  applied to the post-discount subtotal of future invoices (default 0%).
  Invoice due days and the lifecycle classification grace days are configurable.
  This is not a country-specific tax engine; seek applicable tax guidance.
- **Discount coupons:** percentage or fixed value with currency, activation,
  optional validity dates, total usage and per-tenant limits. A pending
  payment reserves a coupon seat to avoid overselling; approval redeems it
  and rejection releases it. Coupons never bypass bank verification.
- Reports show revenue and refunds **separately for LKR and USD**, by plan,
  business and month, plus two Excel-compatible CSV exports (currency
  summary and invoice-level ledger). There is not yet a native .xlsx report.
- A legacy migration creates historical invoice/receipt rows for existing
  approved/rejected payment requests, preserving their requested totals;
  unknown historical tax/discount values are deliberately not invented.
- Phase 5 provides invoice issue/refund in-app notifications and opt-in
  emails. The invoice resend operation uses the selected template and
  recipient rules and **emails a link**, not a PDF attachment. Outbound email
  is still disabled by default. A scheduled reviewer-only alert identifies
  old pending invoices without asking customers to pay twice.

## Admin URLs

| Action | Path |
| --- | --- |
| Invoice directory | /platform/billing/invoices |
| Invoice details | /platform/billing/invoices/{invoice} |
| Invoice / receipt PDF | /platform/billing/invoices/{invoice}/pdf?type=invoice (or receipt) |
| Coupons | /platform/billing/coupons |
| Tax / grace settings | /platform/billing/settings |
| Revenue report / CSV | /platform/billing/reports |
| Individual payment review | /platform/payment-requests/{payment} |
| Subscription registry / extensions | /platform/subscriptions |

- Billing or Super Admin may view financial records.
- **Only permanent Billing/Super Admins** can change coupon/tax settings,
  issue verified refund records or grant manual free subscription extensions.
  Development-only temporary Super Admin accounts cannot approve funds or
  record refunds. All financial mutations are audited.

## Business URLs

- /app/{tenant-slug}/subscription — plan and new price quote + coupon
- /app/{tenant-slug}/billing — documents and renewal preferences
- /app/{tenant-slug}/billing/invoices/{invoice} — invoice breakdown
- /app/{tenant-slug}/billing/invoices/{invoice}/pdf — invoice PDF
- /app/{tenant-slug}/billing/invoices/{invoice}/pdf?type=receipt — verified receipt
- /app/{tenant-slug}/billing/credit-notes/{refund}/pdf — verified credit note

These routes require active membership and the business settings permission;
they prevent cross-tenant resource access.

## Safe local update (Windows WAMP64)

Run:

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test
    php artisan serve

Use Node.js 24 LTS for Vite. Do **not** run migrate:fresh on
a populated database. Back up MySQL and your Laravel APP_KEY first.

## Scheduler and email

Phase 6 adds two daily safe tasks in routes/console.php:

- billing:maintain at 00:15 (scheduler/server timezone): transitions
  ended paid subscriptions to past_due, expired or cancelled according
  to the configured grace days and cancellation preference. This does
  not charge any payment instrument. Feature access is already denied
  after the subscription end timestamp, even during past_due classification.
- billing:remind-invoices at 09:15: alerts platform finance reviewers
  to pending proof reviews older than 2 days. It does NOT ask the
  customer to pay another time. Alerts are deduplicated per invoice.

Run locally in a separate terminal:

    php artisan schedule:work

Production cron should run php artisan schedule:run every minute.
For Phase 5 email delivery, run a supervised queue worker:

    php artisan queue:work --queue=communications,default --tries=3

To evaluate state changes without writing data:

    php artisan billing:maintain --dry-run

To review the pending-payment alert command:

    php artisan billing:remind-invoices --days=2

Gmail .env credentials are not included. You can add them later as
documented in docs/PHASE5-COMMUNICATION-CENTER.md, then enable test mode
with allowlisted recipients before allowing external email delivery.

## Important financial boundaries

1. Uploaded receipts are claims, not bank confirmation.
2. The admin must manually reconcile and approve the verified amount.
3. Card checkouts and automatic charges have NOT been implemented.
4. "Auto-renew" is a reminder-only preference; no automatic collection.
5. Refunds/credit notes record money **already refunded externally**;
   they cannot execute a transfer or reverse a card charge.
6. Invoices and their tax/discount snapshots are not recomputed when
   settings or plan prices subsequently change.
7. CSV uses minor units for precision; import with the currency column
   when opening in Excel. Invoice and credit-note PDF files are available
   directly through the application.
8. Backfilled historical invoice totals come from original payment
   requests, not a reconstruction of tax laws or plan prices.
