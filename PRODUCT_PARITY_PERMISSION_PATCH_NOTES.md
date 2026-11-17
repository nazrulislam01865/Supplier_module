# FlowTracker ↔ Supplier Portal — Product UI & Manage permission update

## Scope

This update uses the provided FlowTracker archive (`Archive 3(2).zip`) as the UI reference and the provided Supplier Portal archive (`Archive(8).zip`) as the portal baseline. **The existing Supplier Portal sidebar, signed Product API service, FlowTracker Product API controller, login, and unrelated modules remain untouched.**

### Supplier Portal

- Product Details uses the FlowTracker Product Detail header, identity/details, classification, supplier-based primary and additional-currency disclosures (expanded by default), client pricing, options/shipment, and documents/audit area.
- Product Edit follows the FlowTracker section order, styling and reusable sections: Product information, Category hierarchy, Product pricing, Product options, Shipping urgencies, Supporting documents. Supplier records remain scoped to the signed-in company; linked suppliers are shown read-only instead of granting cross-supplier access.
- Client availability is shown in Product information, with its own signed API save form when editing is allowed.
- Product-image selection/upload remains a separate, explicit signed API operation and is only offered where Documents → Edit is allowed. File size and type are rechecked by the FlowTracker backend.
- Product record submission is separate from product subsection pricing, assets, shipment, options, and availability. Each uses the existing signed API route. This prevents saving one section from replacing another.
- Users with section-specific Edit access, but without Products → Edit, can open the editor for permitted sections. Base product fields and the base-record Save button stay disabled.
- Supplier cards/currency tables default expanded but can be collapsed.

### FlowTracker permissions

- Selecting **Manage** in Products or Categories automatically checks **all actions available in that same row**: View, Create, Edit, Delete, Manage.
- Unselecting Manage clears those row actions. Unselecting a granular permission removes the Manage shortcut; unselecting View removes writes that require View.
- Existing stored Manage-only permission data is expanded when loaded. The saved policy is normalized on the server, not only reflected visually.
- Manage on Products or Categories **does not** grant Supplier Pricing, Client Pricing, Documents, Shipment, Options or Availability by itself. Those rows require their own explicit grants.
- FlowTracker still enforces the global enable switch, per-action section permission, active supplier status and product-tag isolation on its signed API.

## Installation

Back up each project and test database first. Extract the corresponding **Changed Files** ZIP directly into the project root, preserving directory structure. Do not mix up FlowTracker and Supplier Portal archives.

Run in each project:

```bash
php artisan view:clear
php artisan optimize:clear
```

No migration or `.env` changes are needed for this patch. Existing Product API credentials are not changed.

## Tests

In FlowTracker:

```bash
php artisan test --filter=SupplierProductGlobalAccessContractTest
php artisan test --filter=SupplierProductManageMatrixContractTest
php artisan test --filter=SupplierNativeProductPresentationContractTest
```

In Supplier Portal:

```bash
php artisan test --filter=SupplierProductNativeUiRegressionTest
php artisan test --filter=SupplierProductCurrencyUiContractTest
php artisan test --filter=SupplierProductDetailEditParityContractTest
```

Browser acceptance checks:

1. Open the same supplier-tagged product in FlowTracker and Supplier Portal at equal viewport widths, compare details/edit sections side by side, and confirm the Supplier Portal sidebar is intact.
2. Test product image, currency tables, read-only data, options, documents and shipping in view and edit modes.
3. In FlowTracker global Product module permissions, check Products → Manage: all permitted actions in that row should be checked. Save and reload. Uncheck Products → Edit: Manage must clear, but other checked granular actions should remain.
4. Test section-level permission grants independently. For example, leave Products → View selected, disable Products → Edit, enable Supplier Pricing → View/Edit, and confirm only allowed pricing edits work. Denied product-record edits must return 403 when sent to the FlowTracker API.
5. Test with two supplier accounts linked to different products: changing an ID in an API request must never expose another supplier's products; list/detail/mutation operations are scoped by FlowTracker.
6. Verify that changing an additional currency price table does not affect the primary pricing table or existing order prices.

## Verification and limitations

Static source checks: **212 passed, 0 failed**, including PHP lint, JavaScript syntax, Blade directive/tag balance, include paths, CSS checks, permission normalization and byte-for-byte checks of the existing FlowTracker API controller, Supplier Portal bridge, and sidebar.

A direct PHP behavior check also confirmed Manage expands only its row, while Supplier Pricing → Edit grants only View/Edit for that subsection.

Full Laravel tests, database-backed authorization tests and pixel-level browser comparisons **were not executed** in this environment because the provided source archives do not include Composer `vendor/` dependencies or working databases. Validate on local/staging before production.
