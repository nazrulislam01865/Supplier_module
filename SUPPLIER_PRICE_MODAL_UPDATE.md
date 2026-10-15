# Supplier price-table popup update

Only the Supplier Portal Products & Prices list view has been changed.

- Replaces expanding per-row price tables with one responsive accessible modal dialog.
- Clicking "View price table" shows product name, code and approved supplier price tiers.
- The price data still comes only from the existing `supplier_prices` field, synchronized from FlowTracker.
- Uses the existing supplier tier-table Blade partial and does not make extra HTTP requests.
- Product Details, price edits/approvals, authentication and supplier synchronization are unchanged.
- Keyboard: Escape closes the popup and focus returns to the row's button.

## Install

Extract this archive into the existing Supplier Portal project root:
`/Applications/XAMPP/xamppfiles/htdocs/laravel/flowtrack/supplier-portal`

Preserve your existing `.env`, `vendor/`, database and private storage.
The ZIP contains source files and empty Laravel writable directory placeholders, not these runtime assets.

Then run from the Supplier Portal project root:

```sh
php artisan optimize:clear
```

Hard refresh Products & Prices. Click "View price table" for a priced product.
The existing "View" action continues to open Product Details.

## Verification

`node --check public/assets/supplier-pricing.js`
`php artisan test --filter=SupplierPriceTierDisclosureTest`

PHP tests require Composer dependencies and a configured test database.
No FlowTracker project changes, migrations or resynchronization are needed for this UI-only change.
