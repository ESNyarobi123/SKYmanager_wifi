# SKYmanager — architecture diagnosis (2026-04)

## Root causes of fragile behavior

1. **Dual plan domains**  
   Admin hotspot traffic uses `BillingPlan` + `WifiUser` + `Subscription` + `Payment`. Customer captive portals use `CustomerBillingPlan` + `HotspotPayment`. The local-portal voucher path previously redeemed `Voucher` records tied to `BillingPlan`, so codes, MikroTik profiles, and customer expectations could diverge.

2. **Immediate router authorization**  
   Payment success triggered a single synchronous `connectZtp` + `authorizeHotspotMac` call. Any transient VPN/API glitch produced “paid but no access” with no automatic retry.

3. **Non-idempotent payment finalization**  
   Webhook and polling could race; duplicate callbacks could re-enter authorization logic. There was no single locked transition from provider-pending to provider-confirmed.

4. **Router “online” vs reality**  
   Claiming a router only created a database row. Reachability depended on correct `wg_address`, `ztp_api_password`, and VPN without a structured onboarding or health state.

5. **Duplicate subscription expiry**  
   Both a scheduled command and a queued job expired `Subscription` rows, increasing the chance of double work and inconsistent logs.

6. **Public local-portal API**  
   Endpoints must rely on validation, optional per-router portal tokens (after HTML regeneration), rate limits, and structured errors instead of generic failures.

## Design decisions implemented

- **Option A (separation) reinforced**: Customer captive vouchers are now `CustomerVoucher` → `CustomerBillingPlan`. Admin `Voucher` → `BillingPlan` remains for the admin / hosted portal flows. Local portal redeem no longer touches `BillingPlan`.

- **WireGuard-first operations**: Production guidance is to complete the full setup script so `wg_address` and `ztp_api_password` are populated. `connectZtp` prefers the WireGuard IP. SSTP remains in the short ZTP script for legacy paths; new deployments should standardize on the full WireGuard script. Documented in the runbook.

- **Durable authorization**: `HotspotPayment` moves to `success` (provider confirmed) under row lock, then `AuthorizeHotspotPaymentJob` retries MikroTik authorization with backoff until success or max attempts.

- **Single expiry pipeline**: Only `app:reconcile-expired-access` is scheduled (merged behavior of the former command + job).

## Failure points addressed in code

| Area | Change |
|------|--------|
| Payment callback / poll | `HotspotPayment::markProviderConfirmedByReference()` with `lockForUpdate()` |
| Authorization | `AuthorizeHotspotPaymentJob` + `HotspotPaymentAuthorizationService` |
| Vouchers on router HTML | `CustomerVoucher` + redeem validates router owner |
| Portal hardening | `local_portal_token` on router, `X-SKY-Portal-Token` on mutating API calls when token is set |
| Observability | Structured logs; `last_api_error` / `last_api_success_at` on routers |
| Stuck payments | `app:reconcile-stuck-hotspot-payments` |
| Health | `app:router-health` |

## Remaining operational requirements

- Set `CLICKPESA_WEBHOOK_SECRET` and have ClickPesa send the matching HMAC header (`X-ClickPesa-Signature` by default, overridable) when you want webhook authentication enforced.
- After downloading a new `login.html`, upload it to the router so the embedded `PORTAL_TOKEN` matches the database.
- Run queue workers in production so `AuthorizeHotspotPaymentJob` can retry (`QUEUE_CONNECTION=redis` recommended).
