# Platform Super Admin feature requirements & implementation status

This file tracks the full 15-section admin specification supplied for POS Cloud.
Statuses mean actual working backend flows, not menu placeholders.

| Requirement group | Implemented or partially implemented | Still to develop |
| --- | --- | --- |
| 1 Dashboard & Overview | Live tenant/active/trial, user/store counts, verified subscription receipt summary, activity feed, basic DB/storage health and quick links | Live POS sales/orders, online-user heartbeat, API performance and reliable uptime monitoring |
| 2 Tenant / Business Management | Create and edit businesses, owner verification, branches, status, trial, assigned plan, search, staff list and audit | Country, logo, soft-delete/archive/export, internal notes, onboarding stages, explicit audited temporary tenant support access, advanced filters |
| 3 Subscription Plans & Billing | Plan editing, monthly/optional yearly pricing, limits, module mapping, trials, manual plan assignment, payment-method toggles, uploaded proof submissions and verified admin decisions | Actual card gateway, billing invoices, coupons, refunds, tax invoices, renewal automation, billing reminders, partial payments, reconciliation ledger |
| 4 Modules | Catalog, plan inclusion, manual tenant override, runtime release-state gating | Runtime global switches, deployment version/changelog, module default settings, tenant beta flags |
| 5 Admin Users & Roles | Permanent Super, Operations, Billing, Support and Auditor scopes; verified-user promotion, revocation and expiry-limited local admin | Custom platform roles/permission builder, MFA, sessions, device history, password age policies |
| 6 Store / Branch Management | Per-business branch CRUD-lite, activation, default branch and plan limits | Global stores directory, manager assignment, POS devices/printers/scanners, store settings override |
| 7 Tenant Users | Searchable user directory, verified email, memberships, staff invitations and tenant role editor | Forced password resets, central account blocking, login/activity session history |
| 8 System Configuration | Registration toggle, trial default, default currency and timezone, light/dark styling, payment methods and instructions | Platform branding, SMTP/SMS/push provider management, notification templates, multilingual admin, advanced tax/receipt themes, safe maintenance controls |
| 9 Security & Audit | Tenant isolation, CSRF, scoped roles, audit log filters and CSV, verified admin credentials, private receipt access | MFA, IP policy, login-attempt monitoring, API keys/webhooks, privacy export/delete request workflow |
| 10 Content & Communication | Announcements with schedule, tenant-facing support desk, assignment, reply, internal notes | Targeted announcements, email/SMS templates and delivery, help center, feedback, push notifications |
| 11 Reporting | Actual tenant growth, trial conversion, plan counts, support volumes and receipt review amount by currency | Retail revenue/orders, payments from a reconciled gateway, SaaS churn and cohort metrics, PDF/XLSX export, custom time ranges |
| 12 Monitoring | Basic DB/storage status and app health indications | CPU/RAM/disk telemetry, real uptime, API latency/error rates, queue dashboard, log viewer |
| 13 Backup/Restore | Existing data preserved by incremental migrations | Backups, restore testing, retention, tenant export and purging |
| 14 Developer Tools | Repo-based releases and CI tests/builds | Safe deploy/rollback dashboard, release notes, integration marketplace, API reference |
| 15 Quality-of-life | Dark/light mode, responsive lists, filters, pagination, pending payment review indicator | Global search, broader bulk actions, more alert types, multilingual nav, profile photo, contextual help |

## Payment-specific target

The current SaaS subscription workflow has two preferences: card gateway and
uploaded receipt/asset. A permanent Super Admin can toggle each On or Off.
Uploaded proof works when enabled: a customer submits a private receipt, a
billing admin reviews bank records separately, enters the exact verified amount
and unique transaction reference, then approves or rejects. Payment review
records are immutable after decision and audit-tracked.

The card preference can be switched on, but the method remains unavailable
for customer checkout until a compliant provider and signed capture-verification
service are implemented. No card numbers are collected. Card approval without
server-verified capture is rejected.

This subscription workflow is *not* POS retail customer collection or a merchant
wallet/settlement service. Those require separate approved bank/acquirer
integrations, ledger accounting and compliance.

## Recommended next development order

1. Permanent admin MFA, login/session tracking and scoped platform permissions.
2. Tenant profile extensions: country/logo/notes, lifecycle/archive and exports;
   support access must be explicitly authorized and audited.
3. Verified card provider checkout and payment-intent/webhook integration,
   then invoices/taxes/refunds/renewals and periodic reconciliation.
4. Global modules, tenant feature flags and license limit enforcement.
5. Monitoring/queues, backups with restore drills and security alerts.
6. Product/stock/sales engines to unlock operational dashboards and real
   retail financial metrics. Mobile/desktop and e-commerce follow those engines.

Every release should include database migration tests, permission and cross-tenant
security tests, manual UI validation and clear production-safety boundaries.
