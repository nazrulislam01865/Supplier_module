# PO supplier actions layout fix (2026-11-11)

- The Create PO and Skip PO actions are now adjacent, with visual primary/secondary hierarchy, spacing, responsive sizing, and focus outlines.
- Create PO still opens the original Single/Split modal and does not generate until confirmed.
- Skip PO still submits the original signed task workflow action with a confirmation.
- The PO list, PO detail screens, PO bridge and order synchronization have not been modified.
- No new database queries, migrations, or background workers.
- Clear Blade views (`php artisan view:clear`) after applying.
- Runtime browser verification is required in the user's PHP environment.
