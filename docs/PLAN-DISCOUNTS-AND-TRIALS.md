# POS Cloud: Plan Discounts & Trial Administration

## Discount checkbox on subscription plans

Open Platform → Plans → Create/Edit.

- **Is discount available?** checkbox (off by default).
- When checked, **Discount percentage** is required: 0.01%–99.99%.
- Percentage applies to monthly and yearly catalog prices. A missing yearly price
  remains unavailable and is not manufactured by the discount feature.
- Frontend provides a live price preview. All actual prices are **calculated on
  the Laravel server using integer minor units** and round the saving once.
- Plan listing and customer subscription selection show discounted prices.
- Submitted payment requests store the net payable amount at the moment of
  submission, so editing the plan later does not change a pending payment.
- Turning the checkbox off sets discount_percentage to NULL and restores
  original billing amounts. Invalid stale values in the hidden field are ignored.
- Manual plan activation by an admin is still a license assignment, not a
  payment or invoice.

Example: Monthly LKR 2,500.00 with 10% off becomes LKR 2,250.00; yearly
LKR 30,000.00 with 10% off becomes LKR 27,000.00.

## Trial administration

Platform → Trials has the following workflows:

- **Add trial user**: opens a form to create an actual trial business for a
  registered, email-verified owner. Enter owner email, business details and
  a customized trial duration (1–90 days). Creates branch, ownership, roles,
  subscription and audit event. To onboard a new email address, use the
  Customers & Users invitation first; do not issue default passwords.
- **Running**: active, nonexpired trials.
- **Inactive**: trial feature access explicitly disabled by an Operations
  or Super Administrator. Business and trial records remain.
- **Expired**: active trials with past expiry dates.
- **Converted** and **Suspended**: existing business states remain visible.
- **Extend**: add 1–90 days to an existing free trial. If it has expired,
  extension begins from now; otherwise days add to the scheduled expiry.
  Extension does not automatically reactivate an inactive trial.
- **Deactivate/Reactivate**: require a reason and write both trial_events
  and audit_logs. Switching on preserves the old expiry date. If it is expired,
  also extend it.
- Converted subscriptions cannot be switched back into trials via these
  controls. Trials are isolated from paid subscription status.
- Trial expiry and inactive state prevent licensed module access via the
  same FeatureAccess permission checks used elsewhere in the application.

## Local upgrade

    cd C:\wamp64\www\POS
    git checkout main
    git pull origin main
    php artisan config:clear
    php artisan migrate
    npm install
    npm run build
    php artisan test

Do not run migrate:fresh unless you intend to erase your database. New columns
have backward-compatible defaults so existing plans and trials remain intact.
