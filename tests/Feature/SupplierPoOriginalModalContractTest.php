<?php
namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SupplierPoOriginalModalContractTest extends TestCase
{
    #[Test]
    public function optional_po_task_shows_create_and_skip_and_original_popup_has_single_split(): void
    {
        $workflow = file_get_contents(resource_path('views/portal/order/partials/workflow.blade.php'));
        $popup = file_get_contents(resource_path('views/portal/purchase-orders/partials/create-modal.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/SupplierPurchaseOrderController.php'));
        $this->assertStringContainsString('ART_SUPPLIER_PO_OPTIONAL', $workflow);
        $this->assertStringContainsString('Create PO', $workflow);
        $this->assertStringContainsString('Skip PO', $workflow);
        $this->assertStringContainsString('Single Purchase Order', $popup);
        $this->assertStringContainsString('Split Purchase Orders', $popup);
        $this->assertStringContainsString('Carton marks &amp; payment details', $popup);
        $this->assertStringContainsString('items[{{ $i }}][supplier_unit_cost]', $popup);
        $this->assertStringContainsString("'split_item_ids'", $controller);
        $this->assertStringContainsString("'pos'", $controller);
    }
}
