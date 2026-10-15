SUPPLIER PORTAL - FLOWTRACKER SIDEBAR THEME PATCH

Scope: Supplier Portal only (shared Dashboard, RFQ, Orders, Product Pricing, standalone Order Detail sidebar).
Changes:
- Dark navy shared sidebar and desktop brand-header segment.
- Teal selected navigation state, readable light labels/icons and counters.
- Matching section separators, company details, and Logout button.
- Account details placed above Logout to follow FlowTracker sidebar order.
- Existing menu actions, links, company scoping, and mobile drawer behavior preserved.

Installation:
1) Back up the two files in this patch from your supplier portal.
2) Extract the patch at supplier-portal root, replacing same paths.
3) Run: php artisan optimize:clear
4) Hard refresh (Cmd+Shift+R). No migration or npm build required.

Verification: CSS parser and PHP file syntax passed; full Laravel browser/integration checks must run in the real installation.
