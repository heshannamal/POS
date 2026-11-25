# Phase 10.1–10.4 — Windows Desktop Cashier

The desktop application is under [`desktop/`](../desktop/README.md), using Tauri 2 + React + TypeScript. Its light, blue-accent POS layout follows the requested retail cashier screenshot.

Delivered in this first release:
- Responsive Windows desktop window, login and tenant/branch selector.
- Tenant-bound Sanctum device authentication bridged through Rust, token storage in Windows Credential Manager.
- Product search with server-side SKU/barcode matching, categories, branch-specific inventory and pagination.
- Online-only stock-aware shopping cart, discounts, cashier cash tender, safe checkout retry UUID, sale receipt preview and 80mm browser printing CSS.
- Open shift, cash movements, expected cash and close/reconcile through shared Phase 9.6 Laravel API.
- In-session hold/resume carts and F2/F8/F10/F12 shortcuts.
- Recent sales, product inventory and read-only dashboard/reports snapshot with accurate limitations.
- Light/dark desktop appearance and dedicated desktop CI build check.
- Windows NSIS installer build job in GitHub Actions, with artifact upload if compilation succeeds.

No fake sample transactions, inventory, tax, card payment integration or offline transaction mutation was introduced.

Acceptance gates before calling it production ready:
1. TypeScript/Vite desktop CI passes.
2. Windows Rust/Tauri NSIS job passes and produces an installer artifact.
3. Existing Laravel CI suite and MySQL migrations pass.
4. Verify store assignments, branch filtering, cashier shifts, payment retries, real barcode scanners and thermal receipt printing on Windows 11.
5. Review Windows signing, deployment, update policy, and local credential protection.

The codebase retains the original SaaS platform and business web interface; the desktop folder is a separate client using shared backend services.
