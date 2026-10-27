FlowTrack Supplier Portal - Orders filter redirect fix

Cause:
Laravel generated the portal home URL without a trailing slash when deployed
under /supplier/. GET form submissions and stage links could hit /supplier?...
which the production web server redirected to /supplier/ without the query string.
The portal consequently defaulted to Dashboard rather than Orders.

Modified application files:
app/Services/SupplierOrderListing.php
resources/views/portal/partials/orders/filters.blade.php
resources/views/portal/partials/orders/stage-cards.blade.php
resources/views/portal/partials/sidebar.blade.php
resources/views/portal/order/show.blade.php

Regression tests:
tests/Feature/SupplierOrderListFiltersTest.php
tests/Feature/SupplierPortalNavigationTest.php

Deployment:
1. Back up existing server files; merge the patch paths into the Laravel
   supplier-portal project root, preserving existing directories.
2. From the supplier portal Laravel project root run:
   php artisan optimize:clear
3. If your deployment caches views/routes, regenerate via normal deployment.
4. Open https://your-domain/supplier/?view=orders, select stage, hold, dates,
   search, pagination, All/Clear and back from an order. The URL should keep
   /supplier/?view=orders and the selected filters after a server reload.

No database migrations, new packages, JavaScript or CSS changes required.
PHP lint passed for all modified PHP and Blade files. This upload did not
include vendor/ or an executable PHPUnit environment, so the PHPUnit tests
could not be run here. Run the two tests on a DEVELOPMENT/CI installation
using an isolated test database ONLY. They use RefreshDatabase; NEVER run
them against production data:
php artisan test --filter=SupplierOrderListFiltersTest
php artisan test --filter=SupplierPortalNavigationTest
