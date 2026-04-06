# SKYmanager runbook — routers, portal, payments, repair

## 1. Customer claims a router

1. Customer registers/logs in at `/customer/register` (or login).
2. **Routers → Claim router**: creates a `routers` row with `onboarding_status = claimed`.
3. Admin (or automation) provides the **full MikroTik setup script** from the admin Routers UI so the device gets VPN/WireGuard, hotspot pages, walled garden, and API user `sky-api` + `ztp_api_password`.

## 2. Router becomes “ready”

1. Script applied on the router; tunnel up; VPS can open RouterOS API to the router’s `wg_address` (preferred) or fallback IP.
2. Customer opens **My Plans → Generate Login.html** (or Preview). This:
   - Ensures `local_portal_token` on the router row.
   - Sets `portal_bundle_version` from `config/skymanager.php` (`SKYMANAGER_PORTAL_BUNDLE_VERSION`).
3. Customer uploads the file to MikroTik as `hotspot/login.html` (and keeps the standard popup page set: `login.html`, `rlogin.html`, etc., as already deployed).
4. Optional: run `php artisan app:router-health {router_ulid}` on the VPS to record API/tunnel checks on the router row.

## 3. End-user payment flow

1. Client joins Wi‑Fi; captive opens the standalone `login.html` served by the router.
2. JS calls `POST /api/local-portal/payment/initiate` (with `X-SKY-Portal-Token` when the router has a token).
3. ClickPesa USSD push; webhook hits `POST /api/local-portal/payment/callback` (sign when `CLICKPESA_WEBHOOK_SECRET` is set).
4. Browser polls `GET /api/local-portal/payment/status/{reference}`.
5. On provider success, row moves to `success` and `AuthorizeHotspotPaymentJob` runs (retries until MikroTik accepts or max attempts).
6. Status becomes `authorized`; user continues browsing.

## 4. Customer vouchers (captive portal)

Issue codes tied to **customer** plans (not admin `BillingPlan`):

```bash
php artisan skymanager:issue-customer-vouchers {customer_ulid} {customer_billing_plan_ulid} 25 --batch="Shop counter" --prefix=SHOP
```

Redeem via `POST /api/local-portal/voucher/redeem` (same portal token rules as payment).

## 5. Diagnosing errors

| Symptom | Check |
|---------|--------|
| 403 `portal_token_mismatch` | Regenerate and re-upload `login.html` from My Plans. |
| Payment stuck on `success` | `php artisan app:reconcile-stuck-hotspot-payments`; ensure queue worker running. |
| Webhook 401 | Align `CLICKPESA_WEBHOOK_SECRET` and provider signature header with `CLICKPESA_WEBHOOK_SIGNATURE_HEADER`. |
| Router API failures | `routers.last_api_error`, run `app:router-health`. |
| Wrong voucher on hotspot | Ensure codes come from `skymanager:issue-customer-vouchers`, not admin `BillingPlan` batches, for local portal. |

## 6. Scheduled tasks

- `app:reconcile-expired-access` — every minute (subscriptions / WiFi users).
- Queue worker — must process `AuthorizeHotspotPaymentJob`.

## 7. Rollback notes

- Migrations add columns/tables only (`hotspot_payments` extras, `customer_vouchers`, router health/token fields). Rollback: `php artisan migrate:rollback` for the new migration batch only.
- If portal token causes issues, temporarily clear `routers.local_portal_token` for a router (mutating API calls then work without header until the next HTML download).
