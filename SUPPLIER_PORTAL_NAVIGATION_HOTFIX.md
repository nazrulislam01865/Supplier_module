# FlowTrack Supplier Portal — navigation and hover hotfix (2026-10-14)

Apply this patch to the **separate supplier-portal Laravel application**, not the main FlowTracker project.

## Changes

- Stabilize shared primary/outline button hover state: do not move the hit target or swap the primary gradient for a flat background.
- Turn the supplier workspace header logo into an actual dashboard link.
- Render the URL-selected workspace section on the server (for example, `/?view=orders`) so going back from `/orders/{id}` never falls back to the dashboard merely because a client script is late or fails. Keep the selected sidebar entry highlighted; handle paginated `?orders_page=...` links.
- Make the red notification indicator on the order details header reflect unread order/RFQ notifications for the signed-in supplier user. This is a single tenant-scoped indexed existence lookup, not a loading query for all notifications.
- Add automated regression coverage for the order-details-to-list link, deep links, logo and per-user unread indicator.

No migrations or changes to existing price tables, task synchronisation, workflow logic or quotation processing.

## Local installation

Back up your source first. From your supplier portal directory:

```sh
cd /Applications/XAMPP/xamppfiles/htdocs/laravel/flowtrack/supplier-portal
# Extract the HOTFIX ZIP here, preserving relative paths and replacing matching files.
php artisan optimize:clear
php artisan test --filter=SupplierPortalNavigationTest
```

For live deployment, apply only the changed files and clear caches in the **supplier** application:

```sh
cd /srv/flowtrack-supplier/current
sudo -u supplierportal php artisan optimize:clear
```

If deployment uses release directories, deploy the files to the new release and restart the application's PHP-FPM workers / clear OPcache as appropriate. All public asset URLs use `filemtime` for cache busting. Never overwrite `.env` or `storage` with runtime data.

## Smoke checks

1. Hover on primary and outlined buttons in dashboard, Products & Prices, RFQs and order details. No flicker/jump.
2. Open an order detail `/orders/{id}`, click Orders & Requests in the sidebar, and confirm **Orders & Requests** is immediately visible (also with JavaScript disabled). Repeat on live and mobile.
3. Click project logo in home and in order details. It should open the dashboard.
4. Mark notifications read in one supplier user account, reload an order detail and confirm that user's red dot disappears; other users' unread states remain independent.
5. Receive an order or RFQ sync and verify the notification list and unread count refresh when the supplier dashboard revision changes. The existing Reverb subscription and 60-second fallback remain unchanged.

Static checks: PHP syntax and JavaScript syntax passed. PHPUnit integration tests were not executed in this build environment because `vendor` and the configured test database were not supplied.
