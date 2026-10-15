# FlowTracker / Supplier Portal - Inquiry costing upload reliability

## Root cause

1. Supplier task POST sat inside `/api/v1`'s `throttle:60,1` group and
   also had `throttle:10,1`. Laravel's *default* throttle signature for
   an authenticated user is shared across routes, so other portal API
   activity consumed the supplier task's allowance. Requests hit HTTP 429
   before the upload controller or FlowTracker document write could run.
2. FlowTracker's signed inquiry task POST shared an IP throttle counter
   with unrelated signed bridge requests, creating a second possible 429.
3. The live RFQ list refresh replaced list objects but kept the open inquiry
   details pointing at an older object, potentially showing 0 files after
   FlowTracker sent the authoritative document manifest.

## Scope

- Supplier Portal: independent rate limits for inquiry task POST and its
  authenticated download; improved retry message; refresh an open inquiry
  detail when the RFQ list updates.
- FlowTracker: independent rate limits for signed inquiry task actions and
  document downloads.
- No migrations, new tables, synchronization services, polling jobs, or
  new queries on ordinary page loads. Existing HMAC, supplier scopes, file
  MIME/size checks and FlowTracker-owned document IDs remain in force.
- Source ZIP excludes `.env`, logs, cached views, and private upload bytes;
  do not overwrite or delete your existing storage/database when deploying.

## Local deployment

1. Unzip the Supplier Portal ZIP over the existing supplier-portal root.
2. Unzip the FlowTracker ZIP over the existing FlowTracker root.
3. On each project, run `php artisan optimize:clear` and
   `php artisan queue:restart`.
4. Restart local `php artisan serve` processes if using them.
5. Keep the existing supplier-critical queue worker running.
6. In FlowTracker, for an existing RFQ invitation ID (example `7`):

   ```bash
   php artisan tinker --execute='app(App\Services\SupplierBridge\DeliverRfq::class)->queue(App\Models\InquiryRfqInvitation::findOrFail(7));'
   php artisan flowtrack:sync-supplier-rfqs
   ```

7. Refresh the supplier RFQ page, upload a supported file (<=20 MB),
   verify a successful receipt with a real document ID, then use View/Download.

## Verify files in FlowTracker (read-only)

For an actual invitation ID (example `7`), from FlowTracker:

```bash
php artisan tinker --execute='$i=App\Models\InquiryRfqInvitation::findOrFail(7); $t=App\Models\InquiryTask::where("inquiry_id",$i->inquiry_id)->orderBy("sequence")->orderBy("id")->firstOrFail(); dump($t->documents()->where("note","like","Supplier RFQ ".$i->id." |%")->count());'
```

**If there is no matching database record**, a previously rejected HTTP 429
upload did not reach the FlowTracker task; retry uploading the original file.
If a document already exists, resync the invitation rather than creating a
second copy. Retrying the same file uses the existing SHA-256 idempotency key.

## Validation

PHP syntax and JavaScript syntax can be checked without a running DB:

```bash
php -l app/Providers/AppServiceProvider.php
node --check public/assets/portal.js  # Supplier Portal only
```

Suggested Laravel tests (require PHP mbstring + DOM/XML extensions, Composer
vendor and a test DB):

- Supplier Portal: `php artisan test --filter=SupplierInquiryTask`
- FlowTracker: `php artisan test --filter=SupplierInquiryTaskBridge`
