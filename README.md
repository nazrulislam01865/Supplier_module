# FlowTrack Supplier Portal (independent Laravel application)

This directory is an independent Laravel 13 application in the same source repository as FlowTracker. It has **no PHP imports, Eloquent relations, authentication sessions, storage mounts, database credentials, or direct database connection** to the original FlowTracker application.

## Scope delivered

- Supplier-only invitation-based login, password reset, account disable checking, login throttling, and separate session cookie.
- Pixel-preserved layout and CSS from `supplier_portal_user_friendly_profile_sections.html`: login, dashboard, RFQ list, RFQ detail, four-step quotation workspace, orders/requests, document requests, bills/payments, company documents, company profile, notifications, support, modals, mobile navigation.
- Supplier-specific database, company-scoped queries, quotation drafts/declines/submissions with server validation and immutable finalized quotes, private file operations with hard-fail malware scanning, editable company profile with encrypted banking fields, independently uploaded private company compliance documents, local support requests.
- No test login credentials and no hardcoded FlowTracker links. Demo fixtures, when desired, are created **only by an explicit local CLI command**.
- RFQs/orders/bills/notifications will be empty until FlowTracker's future API integration assigns them. No fake success messages, no claim that buyer notification or synchronization is already working.

## Why directory separation is not enough

**Source-code directory separation alone is not a security boundary.** For breach isolation, build and deploy this subtree as an *independent* PHP application with its own operating-system identity and filesystem access controls. The supplier runtime must not have read access to FlowTracker's parent project, `.env`, database credentials, sessions, logs, or private storage. A dedicated VM/container or separated security context is recommended. A compromised PHP process with identical privileges or a shared DB user would defeat the isolation objective.

You may keep this directory inside the same Git repository, but the deployed artifact should contain only the supplier subtree. Run it via a separate PHP-FPM pool (`supplier_portal`), separate Unix user, separate vhost/hostname (`suppliers.example.com`), separate TLS certificate, and separate database account. Avoid any wildcard/cross-domain session cookie configuration.

## Installation on an isolated runtime

1. Copy **only** the `supplier-portal` directory to an isolated deploy path outside the FlowTracker document root, for example `/srv/flowtrack-supplier/current` (not a path mounted into FlowTracker PHP-FPM).
2. Install Composer dependencies for this project independently. Before production rollout, generate and commit a `composer.lock` through the trusted build process and run `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` from **this** directory. The provided archive does not include `vendor` or a new lockfile.
3. `cp .env.example .env`, then configure **only** supplier credentials and the supplier base URL. Never copy FlowTracker's `.env` or `APP_KEY`.
4. Generate a new independent key with `php artisan key:generate`. Use `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS with separate host, and a private `DB_DATABASE` / `DB_USERNAME` with no grants on FlowTracker tables.
5. Create the **supplier-only** database and run `php artisan migrate --force`. The migration uses no FlowTracker tables or foreign keys.
6. Ensure mail is configured for invitation/reset links. Install and configure the `clamscan` scanner in the supplier runtime; the application rejects files if scanning is unavailable.
7. Serve only `supplier-portal/public` through Nginx with your dedicated FPM pool. Never use `php artisan serve` publicly. Place both Laravel storage and the `.env` outside the document root.
8. Create a supplier company/account via `php artisan supplier:invite REF COMPANY_NAME EMAIL --contact="Contact Name"`. Invitation email is sent through the supplier app. No public registration exists.
9. Optional *local only*: run `php artisan supplier:seed-demo REF` to create isolated sample RFQs. Production use is blocked in code.

### Illustrative OS/database separation requirements

- A dedicated `supplier_portal` OS user/process, not FlowTracker's FPM user.
- A dedicated DB username allowed only on the `flowtrack_supplier_portal` database, for example SQL grants on `flowtrack_supplier_portal.*` and **none** on the FlowTracker database.
- Unique supplier APP_KEY, SMTP credentials, session cookie and session store.
- A supplier-only `public` vhost and supplier-only storage tree; no symlinks to FlowTracker, no shared queue connection, no shared writable filesystem tree.
- Externally reachable routes are only those in this application's `routes/web.php`. Do **not** mount these routes inside FlowTracker.
- Network firewall/egress policy should deny supplier-runtime access to the FlowTracker database port and private internal services. Grant only the eventual allowlisted API endpoint, when integration is approved.

## Current HTTP endpoints

All `/api/v1/*` routes are protected with independent supplier-session auth and server-side company checks.

- `GET /login`, `POST /login`, `POST /logout`
- `GET/POST /forgot-password`, `GET /reset-password/{token}`, `POST /reset-password`
- `GET /` supplier workspace
- `GET /api/v1/rfqs/{id}/quotation`
- `PUT /api/v1/rfqs/{id}/quotation` save draft
- `POST /api/v1/rfqs/{id}/submit` finalize local quotation after required clean files
- `POST /api/v1/rfqs/{id}/decline`
- `POST /api/v1/rfqs/{id}/documents` upload private, scanned file
- `GET /api/v1/documents/{id}/download`, `DELETE /api/v1/documents/{id}`
- `PUT /api/v1/profile` (company admin)
- `GET/POST /api/v1/company-documents`, `DELETE /api/v1/company-documents/{id}` (company admin for modifications)
- `POST /api/v1/support`

The UI is a genuine Blade view with centralized original prototype CSS in `public/assets/supplier.css` and reusable UI behavior in `public/assets/portal.js`. No SPA framework or external asset CDN is needed.

## API integration contract (next stage, intentionally not implemented)

Use an independently authenticated backend-to-backend API over TLS with distinct service credentials, rotation, narrow scopes, timeouts, request IDs, idempotent writes, replay protection, observability and audit logging. No browser may call internal FlowTracker credentials or its private APIs directly.

- **FlowTracker to supplier projection**: publish explicitly assigned supplier RFQs, order actions, document requests, invoice requests, and notifications, with only supplier-approved fields. Use opaque external references; never expose internal models or general query access.
- **Supplier to FlowTracker submission**: acknowledge RFQs, submit quotes, upload approved document objects or upload tickets, submit assigned invoice responses, and support messages. FlowTracker must independently authorize every service call, validate supplier assignment and version, and reject replayed or stale payloads.
- Prefer a **dedicated service gateway** and least-privilege credentials. Avoid exposing internal Eloquent primary keys, mass assignment of upstream fields, or remote query DSLs.
- Incoming projections must be **server validated and tenant scoped** before persisting them in `supplier_resources`. There is deliberately no public ingestion endpoint or FlowTracker DB connection yet.
- The supplier portal's local successful submission **does not mean** the buyer has received it. The UI explicitly states this while the integration is disabled.

## Checks and outstanding integration work

Static verification: PHP syntax checks, JavaScript syntax, DOM ID cross-checks, and structural integrity of the extracted prototype. Feature tests under `tests/Feature/SupplierIsolationTest.php` cover tenant denial, inactive suppliers, unauthenticated routes and blocked submission without required documents. **The full Laravel integration tests and visual browser render still need to be run in a build environment with Composer dependencies, PHP database extensions, and a usable browser.**

Orders, bills, externally assigned document requests, client-provided RFQ documents, payment events and notifications require the future FlowTracker API and remain read-only placeholders. Do not treat these placeholder actions as implemented backend workflows. Company profile, independent authentication, company document uploads, support requests and supplier-local quotation persistence already have local endpoints.

## New: read-only FlowTracker order-created API

The supplier app can optionally receive signed order-created projections through
`POST /api/v1/integrations/flowtrack/orders` without connecting to FlowTracker's database.
Configure `FLOWTRACK_BRIDGE_ENABLED` and `FLOWTRACK_SUPPLIER_BRIDGE_SECRET` in the
existing supplier `.env`, migrate the supplier DB, and use
`php artisan supplier:link-flowtrack SUP001 REAL_MASTER_RECORD_ID` to bind an
existing supplier account to its FlowTracker supplier ID. See the parent project's
`docs/SUPPLIER_ORDER_CREATED_API_INTEGRATION_2026-10-09.md` for full setup and
security notes. This phase is read-only; no supplier actions or write-back are enabled.
