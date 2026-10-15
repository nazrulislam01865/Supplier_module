# Supplier Portal — Notification drawer UX update

## Changed

- `resources/views/portal/index.blade.php`: a compact, accessible notification drawer with status/type filters, one concise footer action, and a header mark-read action.
- `resources/views/portal/partials/notifications.blade.php`: clearer, compact RFQ/order rows showing reference, product, status, timestamp, and an unread indicator.
- `public/assets/supplier.css`: responsive drawer styles scoped to the bell overlay; the paginated notification center remains unchanged.
- `public/assets/portal.js`: client-side filtering of the *existing* 20-item preview; Escape/overlay close, focus trapping, and focus return.
- `public/assets/supplier-dashboard-realtime.js`: preserve active filters when a live dashboard snapshot refreshes and update the unread indicators after marking read.
- `tests/Feature/SupplierNotificationDrawerUxTest.php`: regression coverage for drawer controls and supplier isolation.

No API, database schema, query, or notification-delivery changes.

## Install on Mac

Back up locally modified files. Extract this archive over the existing Supplier Portal source:

`/Applications/XAMPP/xamppfiles/htdocs/laravel/flowtrack/supplier-portal`

Preserve your existing `.env`, `vendor`, `storage/app` files, and local database. The archive deliberately omits them. Empty Laravel writable folders are included.

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/laravel/flowtrack/supplier-portal
php artisan optimize:clear
```

Reload the supplier dashboard with `Cmd + Shift + R`.

## Test

1. Open the bell: compact rows should show RFQ/Order references, titles, statuses, relative times, and a dot for unread notifications.
2. Select **Unread**, then **RFQs**, then **Orders**: filtering is immediate; the notification center is unaffected.
3. Select a row: it opens the RFQ or order using the original destination.
4. Select **Mark all as read**: unread badges and the preview update after the server confirms it.
5. Select **View all notifications** to access the existing paginated center.
6. Verify desktop, phone widths, keyboard Escape, Tab and Shift+Tab, outside click, and returning focus to the bell.

Optional feature test using a configured *testing* database:

`php artisan test --filter=SupplierNotificationDrawerUxTest`

## Validation

JavaScript syntax, PHP test-file syntax, CSS stylesheet parsing, and ZIP integrity were checked. Full Laravel and browser interaction tests must run in the user's installed project environment.
