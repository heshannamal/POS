# Phase 7 — POS Foundation (in progress)

The first implementation slice adds tenant-scoped category and product CRUD, barcode/SKU entry, branch inventory with immutable movements, stock adjustments/transfers, a basic cashier terminal, server-authoritative checkout, sales history and printable receipts.

## Safety guarantees
- Incremental migrations. Do not run migrate:fresh on a populated database.
- Every product and stock lookup is tenant-scoped.
- Store and inventory modifications require the existing settings.manage business permission for this initial release.
- Sales submitted under the same UUID do not duplicate a purchase.
- Stock updates and sales operate in DB transactions with row locking.
- A sale snapshots product name, SKU and unit price for historical integrity.
- Manual external card and bank options require a verified reference: these do **not** initiate a gateway payment.
- Existing SaaS subscription billing is separate from retail sales.

## Deploy

Back up SQL and .env / APP_KEY, then run:
```shell
git pull origin main
php artisan migrate
php artisan optimize:clear
npm install
npm run build
php artisan test
```

## Remaining Phase 7 items
- Cashier shifts/register open-close, reconciliation, roles for cashier/stock staff.
- Safe returns, partial refunds, tax computation, inventory valuation and low-stock alerts.
- Product variants, images, bulk import/export, multi-tender payments, barcode hardware integrations and fiscal printer formats.
- Comprehensive feature tests and CI migration verification.

Treat this as the POS foundation, **not a fully completed Phase 7**.
