# Operations, reports, and exports

## Who can access what

- **Admin / super-admin**: Full platform reports (`reports.view`), CSV exports (`reports.export`), and the export center.
- **Reseller**: Same permissions, scoped automatically to **routers they own** (`routers.user_id = their user id`). No cross-tenant rows in revenue, hotspot payments, router snapshots, plan stats, invoices (own only), or vouchers (own only).
- **Customer (portal)**: No admin reports. They get an enhanced **dashboard** (KPIs + trust messages) and **Invoices → CSV (12 mo)** for **their** invoices only.

## UI entry points

1. **Reports** — `Billing → Reports` in the app sidebar (requires `reports.view`). Pick report type, date range, preview table, **Download CSV** if you have `reports.export`.
2. **Export center** — `Billing → Export center` (requires `reports.export`). Same CSVs with preset descriptions; set **From / To** once for all cards.
3. **Customer invoices CSV** — On **Invoices**, use **CSV (12 mo)** (optional `from` / `to` query params on `customer.invoices.export`).

## CSV types and filenames

| Type | Query key | Typical filename |
|------|-----------|------------------|
| Subscription revenue | `revenue` | `revenue-report-YYYY-MM-DD-to-YYYY-MM-DD.csv` |
| Hotspot payments | `hotspot_payments` | `hotspot-payments-…csv` |
| Router snapshot | `router_operations` | `router-operations-snapshot-YYYY-MM-DD.csv` |
| Plan performance | `plan_performance` | `plan-performance-…csv` |
| Incidents (one row) | `support_incidents` | `support-incidents-summary-…csv` |
| Invoices | `invoices` | `invoices-…csv` |
| Vouchers | `vouchers` | `customer-vouchers-…csv` |

Direct URL pattern (staff): `GET /admin/exports/{type}?from=YYYY-MM-DD&to=YYYY-MM-DD`

## Audit trail

Successful staff CSV downloads log **Report CSV exported** in **Activity log** with `export_type`, `from`, and `to`.

## Code map

- **Tenant scope**: `App\Services\TenantReportingScope`
- **Customer KPIs / admin–reseller dashboard stats**: `App\Services\BusinessKpiService`
- **Report queries**: `App\Services\OperationsReportService`
- **CSV streaming**: `App\Services\ReportCsvExportService` + `App\Http\Controllers\ReportExportController`
- **Incident counts (scoped)**: `App\Services\PaymentIncidentSummaryService::summarize(?routerOwnerUserId)`

After pulling changes, run `php artisan db:seed --class=RolesAndPermissionsSeeder` (or full seed) so `reports.view` / `reports.export` exist on admin and reseller roles.
