# Customer client sessions — how state is derived

This note is for operators and engineers maintaining **Customer → Client sessions** (`CustomerClientSessionService`, `ClientSessionRouterLiveEnricher`, `App\Data\ClientSessionView`, Livewire `ClientSessions`) and the **RouterOS snapshot** pipeline.

## Data sources (billing / access)

| Source | Model | What it represents |
|--------|--------|-------------------|
| Subscription | `Subscription` + `WifiUser` + `BillingPlan` | Access window: `expires_at`, `status`, `data_used_mb` (billing counter), plan quota. |
| Hotspot payment | `HotspotPayment` + `CustomerBillingPlan` | Portal flow: `pending` → `success` → `authorized` (or `failed`); `expires_at` after authorization. |

Customer vouchers and other flows are **not** merged into this read model yet.

## RouterOS live layer (separate from billing)

| Component | Role |
|-----------|------|
| `RouterActiveSessionSyncService` | Connects via `MikrotikApiService`, runs `/ip/hotspot/active/print`, **replaces** rows in `router_hotspot_active_sessions` for that router on success. |
| `router_hotspot_active_sessions` | One row per active session line (aggregated by normalized MAC). Stores bytes in/out, IP, hotspot user, uptime fields, `synced_at`. |
| `routers.hotspot_sessions_synced_at` / `hotspot_sessions_sync_error` | Last **successful** sync time; error text when the last attempt failed (old snapshot may remain). |
| `hotspot_payments.router_bytes_*`, `router_usage_synced_at` | Filled only when **exactly one** authorized, non-expired payment matches the session MAC on that router (ambiguous matches are skipped). |

**On API failure**, the service does **not** clear the previous snapshot (data becomes **stale**; UI labels it as such).

### Commands & scheduling

- `php artisan skymanager:sync-router-hotspot-sessions {router_ulid?}` — sync one router or batch.
- `php artisan skymanager:sync-router-hotspot-sessions --all-ready` — only `onboarding_status = ready` and claimed routers.
- `php artisan skymanager:prune-router-hotspot-sessions --days=14` — delete snapshot rows older than N days.
- Optional schedule: set `SKYMANAGER_SCHEDULE_HOTSPOT_SESSION_SYNC=true` (see `config/skymanager.php`). Default is **off** to avoid surprise API load.

### Freshness (“live” vs stale)

`SKYMANAGER_ROUTER_HOTSPOT_SESSIONS_FRESH_SECONDS` (default **300**): if `hotspot_sessions_synced_at` is **newer** than this window, router-derived session bytes / “online” badges are treated as **fresh**. Older syncs are labeled **stale** even if the MAC still appears in the last snapshot.

## Tenancy

Customer session lists use `whereIn('router_id', $customer->routers()->pluck('id'))`. Snapshots are keyed by `router_id`; enrichers only load snapshots for those routers.

## Enrichment merge (`ClientSessionRouterLiveEnricher`)

Applied **after** billing rows are built and filtered. Priority for a row with a known `clientMac`:

1. **MAC in snapshot** → `RouterLiveSnapshot` state `live_fresh` or `live_stale` (by sync age).
2. Else **fresh sync, active access, MAC not in list** → `not_listed_fresh` (honest “likely not on Wi‑Fi right now”).
3. Else **hotspot payment with stored `router_bytes_*`** → `cached_payment`.
4. Else **sync error and never succeeded** → `unknown`.

**Billing counters are not overwritten** for subscriptions: `data_used_mb` stays the subscription field; router bytes appear under **Router session** and in `RouterLiveSnapshot`.

For **hotspot** rows, merged display usage (MB) from router bytes is only applied for states `live_fresh`, `live_stale`, or `cached_payment` (see `ClientSessionView::mergeRouterLive`).

## “Active” vs “History” tabs (unchanged)

- **Active**: `segment === 'active'` (subscription active + future expiry; hotspot authorized + future expiry or recent pending/success).
- **History**: all other rows for the customer’s routers.

## Remaining time (`remainingLabel`)

Unchanged: derived from access windows / billing, not from RouterOS uptime.

## Exports

CSV includes `router_live_state`, `router_live_bytes_in`, `router_live_bytes_out`, `router_live_synced_at`, `router_live_freshness` when enrichment ran (same filters as the UI).

## Admin visibility

- **Router operations → router detail**: stored snapshot table, last sync / error, **Sync hotspot sessions** (repair permission).
- **Router operations list**: “Hotspot sessions” column shows last successful sync age.

## Changing behavior safely

- Keep RouterOS I/O in **`RouterActiveSessionSyncService`** / **`MikrotikApiService`**; keep merge rules in **`ClientSessionRouterLiveEnricher`**.
- Do not claim “online” without a **fresh** sync and a **MAC match** in the snapshot (`live_fresh`).
