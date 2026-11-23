# Phase 8 — Purchasing and Advanced Inventory (initial implementation)

Implemented in the existing POS Cloud business workspace, with tenant scoped data:
- Supplier directory: create/edit/archive, supplier code, contact details, payment terms, status.
- Purchase orders: multiple unique stock-tracked products, price/cost snapshots, quantity, discounts, draft approval and cancellation.
- Goods receiving: full/partial receipts, unique UUID idempotency keys, row locks, received quantity tracking, branch stock updates, cost tracking, immutable stock movement and audit.
- Supplier payments: manually record payment evidence and references against the **received** goods value only, no duplicate posting for the same UUID. These actions **do not initiate bank or card transfers**.
- Inventory controls: reorder threshold, count adjustment and recorded count differences.
- Purchasing and supplier balance reporting: PO totals, goods-received value, payment values, outstanding received-goods value, cost-based stock valuation, reorder list, CSV.
- Business roles: `pos.suppliers.manage`, `pos.purchases.manage`, `pos.receiving.manage`, `pos.payables.manage`, `pos.purchasing.reports`.
- Active tenant subscription required for mutations; branch-assignment restrictions apply to transactions. Existing retail sales/stock records are not reset.

## Roles and process
1. A business owner assigns purchasing roles to employees in Roles & Permissions.
2. Employees are assigned to stores through the existing platform All Stores interface.
3. Create supplier -> draft purchase order -> approve -> receive goods (partial or full) -> record independently confirmed supplier payments.
4. Inspect order details, stock movements and purchasing reports.

PO details can be viewed by purchasers, goods receivers, or payables staff through the `purchasing.view` middleware, but only `pos.purchases.manage` allows approval and cancellation.

## Safe local migration
Back up the MySQL database and .env first. **Never run `migrate:fresh` on live data**.
```shell
git checkout main
git pull origin main
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=app
php artisan test --filter=Phase8PurchasingRoutesTest
php artisan test
npm install
npm run build
```
The migrations add tables and permissions; existing product and POS sales records remain in place.

## Accounting assumptions and limitations
- Purchase costs and payment amounts use integer minor currency units (for LKR, two decimal places). Taxes, VAT, shipping allocation and supplier credit notes are not yet modeled.
- Discounts are allocated proportionally to the value of received stock for outstanding-payables reporting. Stock valuation is at latest recorded unit cost, not accounting FIFO/weighted-average cost.
- Supplier payment forms record a past payment, not execute/verify a payment. Confirm references externally.
- A cancelled order must be unreceived, and GRNs are immutable. Returns to suppliers, GRN reversals, purchase returns, and write-offs require separate future workflows.
- Reorder alerts currently operate on existing stock rows, not products with no stock row.
- Advanced batch/lot tracking, expiration dates, serialized goods, automated purchase suggestions, supplier invoices and due-date aging are still future enhancements.
- No database, SMTP, physical device, or end-to-end test has been run in the user's local environment from this GitHub connector. **Run migrations and test with data before production use**.
