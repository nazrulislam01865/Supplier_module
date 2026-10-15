# Supplier Portal: notification center pagination and layout

## Scope
The full Notifications page now shows 15 supplier-scoped RFQ/order updates per page, with All/Unread tabs, an All types/Orders/RFQs selector, page counts, and responsive cards. New items are distinguished visually. Clicking an order opens the existing supplier order details page; clicking an RFQ uses the existing quotation-detail interaction.

The bell drawer remains a lightweight preview of the 20 most recent updates. Realtime dashboard refreshes do **not** replace paginated notification results with the preview. Mark all as read continues to work per supplier user, and the currently visible notification page is refreshed after the action.

## Install
Use the patch archive from the supplier portal project root, retaining the included directory structure.

Local Mac:

```sh
cd /Applications/XAMPP/xamppfiles/htdocs/laravel/flowtrack/supplier-portal
# Extract the patch into this directory, replacing the included files.
php artisan optimize:clear
php artisan test --filter=SupplierNotificationPaginationTest
```

Server:

```sh
cd /srv/flowtrack-supplier/current
# Deploy the patch using your existing release procedure and backups.
sudo -u supplierportal php artisan optimize:clear
sudo -u supplierportal php artisan test --filter=SupplierNotificationPaginationTest
```

No database migration or npm build is needed. A browser hard refresh may be useful after deployment (CSS/JS assets are versioned using file modification times).

## Manual checks
1. Sign in as a supplier user and open Company > Notifications. Verify 15 rows per page and Older/Next pagination links. Open several pages.
2. Use Unread and the Orders/RFQs type dropdown. Confirm each page preserves filters, and that a different company's references are never displayed.
3. Click an Order and an RFQ update; verify their existing detail views open.
4. Use Mark all as read and verify both the header bell indicator and the Unread filter update.
5. Trigger a new signed order/RFQ update and verify the drawer refresh does not replace the currently paginated notification page.
6. Verify at 320px phone width and desktop widths.

## Validation constraints
PHP and JavaScript syntax checks passed. Standalone browser layout tests passed at 320, 375, 430, 768, 1024 and 1440px. Laravel feature tests are included, but must be run in a project installation with Composer vendor packages and database access; this source archive does not include vendor.
