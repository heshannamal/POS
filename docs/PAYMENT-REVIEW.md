# POS Cloud — Subscription payment setup and review

## Two payment methods

- Card gateway: Permanent Super Admin can set Enabled or Disabled. This records
  a preference only. Checkout stays unavailable until an approved acquirer is
  integrated, customer checkout is hosted/secure, and card capture is verified
  server-side. No form collects card numbers or CVV.
- Uploaded payment proof (asset): Permanent Super Admin can activate/deactivate
  receipt uploads and configure customer instructions. Customers upload JPG, PNG
  or PDF (maximum 5 MB), and a billing administrator verifies funds independently
  before approving or rejecting.

This initial workflow is for POS Cloud **subscription charges**, NOT retail POS
payments or merchant payout aggregation. Both methods default to disabled.
Only the uploaded-proof method is operational after configuration.

## Screens

- /platform/payment-setup — enable/disable and instructions, permanent Super
- /platform/payment-requests — billing admin review queue
- /platform/payment-requests/{id} — inspect proof, enter amount and bank reference,
  approve or reject with notes
- /app/{tenant-slug}/subscription — published plan and billing-cycle selection,
  private evidence upload and tenant-specific payment history

## Approval workflow

1. Business owner chooses a published plan and monthly or yearly billing cycle.
   The server snapshots the plan amount and currency. Untrusted customer amounts
   and method names are ignored.
2. Customer submits a proof file. It is kept in private local storage. Only
   one pending request per tenant is allowed; this does not activate the plan.
3. A Billing or Super administrator independently verifies the actual bank
   transaction and enters its verified amount and unique external reference.
4. For now the amount must match the invoice-plan snapshot exactly. Partial
   payments, overpayments and refunds require separate reconciliation.
5. The admin checks the explicit verification box and approves. Database changes
   are atomic; the subscription activates for one month/year, an audit is saved,
   and the original tenant/trial data remains.
6. A rejection needs a reason and does not alter entitlements. A reviewed request
   cannot be approved again. The same reference cannot be reused.
7. Card request approval is forbidden without server-verifiable provider capture
   data. Provider connection and webhook handling are not implemented.

If the plan changes while the receipt is under review, its original plan is
checked before activation so stale receipts cannot silently override changes.

## Security and production notes

- Payment method configuration is protected by permanent platform super-admin.
- Review and private proof downloads are limited to Billing/Super Admin.
- Customer proof downloads require same-tenant membership and settings.manage.
- Receipt files are private, never under public storage, and download as
  attachments. Use antivirus scanning, a retention policy and secure backups
  before handling production receipts.
- Live card payment needs a PCI-compliant payment provider, signed webhooks,
  idempotency, capture confirmation, refunds and settlement reconciliation.
- Merchant collection and payouts are a **separate approved finance model** with
  its own immutable ledger and bank/acquirer permissions. This workflow does not
  represent merchant proceeds, financial revenue reports or invoices.
- Approval is a documented manual reconciliation decision, not an automated
  transfer confirmation by the bank.

## Update local Windows/WAMP

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test

Use Node.js 24 LTS, not v18. Do not run migrate:fresh on an existing database.
Back up both the database and private uploaded files.
