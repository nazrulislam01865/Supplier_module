FLOWTRACK SUPPLIER PORTAL - DASHBOARD UX AND LOGIN SECURITY
14 October 2026

Apply to supplier portal ONLY, not the main FlowTracker project.

Changes:
- Dashboard Recent Activity: compact individual rows with status, timestamp, reference,
  product summary and mobile-safe wrapping. The existing order/RFQ links are preserved.
- Log out: added a POST+CSRF logout action inside the shared supplier sidebar, including
  standalone Order Details and Products & Prices pages.
- Login protection: max 5 failed attempts per normalized account identifier per minute,
  and 30 failed attempts per source IP per minute. Limits use Laravel's existing cache.
- Session: enforced 30-minute inactivity expiration on the server with 30-minute
  Laravel session lifetime and no persistent remember-me login.
- Active-browser heartbeat: at most one request every 5 minutes while users interact;
  dashboard/realtime background polling does NOT extend the inactivity clock.
- Older existing sessions lacking a recent activity value begin tracking on their
  next authenticated request; old remember-me sessions are not silently reinstated.

Installation (local macOS, run FROM your supplier-portal root):
  cd /Applications/XAMPP/xamppfiles/htdocs/laravel/flowtrack/supplier-portal
  # Extract PATCH into this folder, preserving paths and replacing those files.
  mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views
  php artisan optimize:clear
  php artisan test --filter=SupplierDashboardSessionSecurityTest

Manual verification:
  1. Sign in, open Dashboard: individual Recent Activity items do not merge text.
  2. Verify Recent Activity RFQ clicks and order links open their respective details.
  3. Sidebar Log out is visible on dashboard, order details and product pricing;
     submit logs out and returns to login.
  4. Try 5 wrong passwords for same account; the 6th attempt is blocked for ~60 seconds.
  5. Sign in normally after waiting, observe auto logout after 30 min inactivity.
  6. Stay active interacting with portal >30 min: session should persist via activity heartbeat.
  7. Check after 30 min even when notification/realtime background refresh is running:
     session must still expire.

No database migration and no npm build are needed for this patch.
Full integration tests require Composer vendor dependencies and your test database.
The full source zip excludes environment credentials, data, logs and compiled cache.
