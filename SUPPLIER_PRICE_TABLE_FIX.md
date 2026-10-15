Supplier Portal - full project source, supplier price-tier disclosure update

The Products & Prices list now exposes Show price table / Hide price table on
products with synchronized supplier-specific price tiers. The disclosure uses
native HTML details, CSS and a shared Blade partial with the detail page.
It makes no extra HTTP/API requests or database queries. Both pages display
only payload.supplier_prices from the signed supplier-scoped FlowTracker feed.
Products without supplier-specific price tiers display a clear empty state.

Deployment (preserve .env, vendor, user uploads, and database):
  cd /Applications/XAMPP/xamppfiles/htdocs/laravel/flowtrack/supplier-portal
  # Unzip the source ZIP to this directory without deleting existing files.
  mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs
  php artisan optimize:clear
  php artisan test --filter=SupplierPriceTierDisclosureTest

The full source archive contains no .env, vendor or runtime log files.
It is not a self-contained Composer installation. No migrations are needed.
