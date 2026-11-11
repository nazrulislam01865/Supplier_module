<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SupplierPoActionsLayoutTest extends TestCase
{
    public function test_optional_task_has_two_distinct_accessible_actions_without_changing_the_po_modal(): void
    {
        $view = file_get_contents(resource_path('views/portal/order/partials/workflow.blade.php'));
        $css = file_get_contents(public_path('assets/supplier-workflow.css'));

        $this->assertStringContainsString('role="group" aria-label="Optional Purchase Order actions"', $view);
        $this->assertStringContainsString('supplier-po-task-actions', $view);
        $this->assertStringContainsString("'create' => 1", $view);
        $this->assertStringContainsString('type="hidden" name="decision" value="skip"', $view);
        $this->assertStringContainsString('confirm(\'Skip the optional Purchase Order', $view);
        $this->assertStringContainsString('.supplier-po-task-actions{display:grid;', $css);
        $this->assertStringContainsString('.supplier-po-create-btn:hover', $css);
        $this->assertStringContainsString('.supplier-po-skip-btn:hover', $css);
    }
}
