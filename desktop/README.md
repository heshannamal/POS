# POS Cloud Desktop — Phase 10 (Windows online POS)

A Windows-first Tauri 2 + React 19 + TypeScript cashier application built from the selected **light retail POS reference**: left navigation, tenant/branch header, full searchable product catalog, category tabs, right-hand cash cart, shift controls, shortcuts and print receipt.

The desktop application lives under `desktop/` in the existing repository and connects **only through the versioned Laravel API**. It does not connect to MySQL directly.

## First-time Windows setup

Requirements:
- Windows 10/11, WebView2 runtime, Microsoft Visual C++ Build Tools (Desktop development with C++).
- Rust stable toolchain from https://rustup.rs (MSVC target).
- Node.js 24 LTS and npm (Node 22.12+ also supports the chosen Vite toolchain).
- Laravel POS Cloud backend running with Phase 9.6 API migrations and permissions.
- A business account with verified email and active tenant membership, plus the correct store assignment and POS roles.

```powershell
git clone https://github.com/heshannamal/POS.git
cd POS\desktop
npm install

# Required once before the first Tauri dev/build (generates .ico and PNG icons).
npx tauri icon src-tauri/icons/app-icon.svg

# Run the desktop application (not just a browser preview).
npm run dev:desktop
```

Build a Windows installer:

```powershell
cd POS\desktop
npm run build:desktop
```

Look in `desktop/src-tauri/target/release/bundle/nsis/` for the installer. GitHub Actions also builds and uploads an unsigned Windows NSIS installer as the `pos-cloud-desktop-windows` artifact **when its Windows build job succeeds**.

**Do not deploy/redistribute a production installer until it is signed and independently tested on Windows.** The Tauri output has no automatic release signing configured.

## Connection and sign in

The default API address is `https://pos.web7softwares.com/api/v1`. You can change it in **Connection and device settings** on the sign-in screen, including for localhost development (`http://127.0.0.1:8000/api/v1`). Non-localhost HTTP is rejected.

Enter the **business slug**, employee email/password, and a descriptive device name. Use a business owner or authorized cashier; a platform administrator cannot sign into the desktop app. The backend controls the active user permissions and branch assignment. The backend device login rate limit remains in effect.

The Rust layer performs network requests and stores the Bearer token in **Windows Credential Manager** via `keyring`. The React renderer never receives or stores the token. The local settings JSON contains only non-secret API URL, tenant slug, and device UUID. Revoking a device from **Store Owner Web → Connected Devices** makes future authenticated calls fail.

## Supported workflow

1. Login and select an assigned store/branch.
2. Open a cashier shift from **Dashboard → Open register**, or from the banner above the POS cart.
3. Search products by name, SKU, or barcode. Scan USB keyboard-wedge barcodes by focusing the search field. Press Enter to add an exact SKU/barcode match.
4. Browse category tabs and live server-side product pagination. Product stock and price are read from the Laravel API. Cards use placeholders because the current API does not provide product images.
5. Add products, set discounts in **currency units**, adjust quantities, or hold a cart temporarily. Held carts are **in memory only**: they disappear when the application closes and cannot sync between devices.
6. Press **F12** (or Pay Cash), enter received cash, confirm payment. Checkout uses the same server-side validation, real prices, current stock, cashier shift, tenant permissions, and idempotency checks as the web POS. Network errors preserve the transaction's UUID and exact payload for a safe retry. Confirm a possibly submitted sale in Sales History before discarding the retry key.
7. Review the transaction receipt, and use the Windows WebView print dialog. CSS targets 80mm paper, but actual thermal printing and ESC/POS integration require hardware testing.
8. Close and reconcile cash in **Dashboard** with counted amount. The backend owns the expected cash calculation.

Keyboard shortcuts: `F2` search; `F8` hold; `F10` clear cart; `F12` pay cash; `Ctrl+F` search.

## Important boundaries

- This release is an **online-only desktop cashier client**. `GET /sync/manifest` and stock movement feeds do **not** authorize offline sale creation. Failed checkout requests are *retried online* with the same idempotency UUID.
- Cash is the only retail payment method the Laravel POS API currently accepts. A cash tender and change amount are displayed in the client but the backend records the actual sale total; cash tender/change are not new database fields.
- The current retail API does not calculate a store VAT/tax percentage. The UI displays **Tax: Not configured** rather than fabricating tax amounts.
- No customer loyalty, supplier purchase UI, card gateway, suspended-sale persistence, real image upload, auto-updater, native receipt printer, or offline-first conflict resolution has been implemented in this client.
- Dashboard/Reports display a clearly labeled snapshot of loaded sales, **not** accounting totals.
- API catalog refresh/pagination is backed by the server; product cards contain an illustrated placeholder until the backend exposes safe product image URLs.
- Server-side product changes may invalidate cached product details between requests. All financial calculations must be revalidated server-side at checkout.
- Verify device access, keyboard scanners, shift reconciliation, duplicate checkout behavior, printing, and Windows install/uninstall on real machines before production.
- The Windows binary has not been independently security-audited. Protect the Windows user profile and sign out/revoke lost devices.

## Development commands

```powershell
cd POS\desktop
npm install
npm run build              # TypeScript + Vite
npm run dev:desktop        # Tauri window + Vite dev server
npm run build:desktop      # Tauri Windows NSIS installer
```

Never commit `.env` secrets, Windows credential-store contents, or personal access tokens.
