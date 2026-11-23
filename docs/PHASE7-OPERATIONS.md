# Phase 7 — Cashier Registers, Returns, Reports & Devices (implementation)

## Scope delivered

- Branch-specific registers with activation controls, one open shift per register and one per cashier within a tenant.
- Register opening cash; signed cash in/out entries; closing counted cash, expected cash and difference; audit logs.
- Cash POS checkout requires the logged-in user's open shift for the chosen branch. Price, stock and register are checked server-side.
- Partial quantity returns from recorded cash sales; refund is calculated from the original price and proportional discount. Repeated return quantities cannot exceed units sold. Cash refund is recorded as a negative shift movement, with optional inventory restocking.
- Sales by branch, cashier, top products, gross payments, separate refund totals, stock valuation at current cost, low-stock counts, and Excel-compatible CSV with integer minor units.
- Keyboard-wedge USB barcode scanning on the terminal by SKU/barcode and browser-printable 80mm thermal receipt CSS. No driver or direct network print command; browser's native print dialogue is used.
- Granular staff role permission keys:
  - `pos.sales`: terminal, sales history, receipt
  - `pos.inventory.manage`: catalog, inventory
  - `pos.register.manage`: register/shift creation, movements and close
  - `pos.returns.manage`: approve and issue cash refunds
  - `pos.reports.view`: reports and financial CSV

Permissions are deliberately **not automatically granted to existing staff roles**. Business owners can assign them in Roles & Permissions. Cashiers must be assigned BOTH `pos.sales` and `pos.register.manage` to open their shift and sell. A manager allowed to issue returns must have `pos.returns.manage`, plus an own open shift for the sale's branch; user-owner can close/adjust another cashier's shift.

## Safe upgrade

Keep the existing APP_KEY and database backups; on local WAMP:
```bash
git checkout main
git pull origin main
php artisan migrate
php artisan optimize:clear
npm install
npm run build
php artisan test
```
No destructive data migration and no migrate:fresh. Cashier mode begins with:
1. Owner creates branch registers.
2. Owner assigns POS role permissions to business employees.
3. Cashier opens a register shift and enters opening cash.
4. Cashier selects that branch in the POS terminal and completes cash sales.
5. Authorized staff can record returns during their own open shift.
6. Cashier logs cash in/out and closes shift with counted cash.
7. Owner reviews differences and reports.

## Limitations and safety

- Existing sales without a shift remain intact. **New cash checkouts require an open shift**.
- **Cash only**. This does not issue card reversals, bank transfers, external refunds, payment gateway debits or SaaS subscription charges.
- Browser printing requires a compatible configured printer. Barcode scanners need to act as USB keyboard devices; no Bluetooth pairing, printer SDKs, direct USB or ESC/POS driver was added.
- Monetary values are integer minor units and current checkout is limited to 1,000,000 currency units to protect arithmetic.
- No country-specific VAT/GST, accounting general ledger, customer ledger, tax invoices, or COGS-based net profit certification is claimed.
- For returns, damaged goods may be left un-restocked. Cash refund requires available expected cash in the currently open branch shift.
- Gross sales by sale date and refunds by refund creation date are separate; they may fall in different report date ranges.
- Stock valuation uses current product cost; known historical cost snapshots apply only to sales recorded after this migration.
- Limitations of the current POS foundation remain: product variants/images, advanced fiscal receipts, multi-tender settlement, hardware drivers, and end-to-end production acceptance tests.
- Do not consider these changes production-validated until `php artisan test`, migration check and real cashier UAT succeed.
