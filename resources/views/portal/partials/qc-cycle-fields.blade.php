{{-- Same QC decision stages as FlowTracker: choose pass or report, then capture issue details. --}}
@if($actionKey === 'QC_CHECK')
    <div class="supplier-flow-qc-step" data-supplier-qc-step="check">
        <div class="supplier-flow-quantities">
            <label>Quantity received <input name="qty_received" type="number" min="1" required value="{{ old('_supplier_workflow_task') == $task['id'] ? old('qty_received', $qcReceived) : $qcReceived }}"></label>
            <label>Quantity inspected <input name="qty_inspected" type="number" min="1" required value="{{ old('_supplier_workflow_task') == $task['id'] ? old('qty_inspected', $qcInspected) : $qcInspected }}"></label>
            <label>Accepted <input name="qty_accepted" type="number" min="0" required value="{{ old('_supplier_workflow_task') == $task['id'] ? old('qty_accepted', $qcInspected) : $qcInspected }}"></label>
            <label>Rejected <input name="qty_rejected" type="number" min="0" required value="{{ old('_supplier_workflow_task') == $task['id'] ? old('qty_rejected', 0) : 0 }}"></label>
        </div>
        <label>QC comments <textarea name="qc_comments" rows="4" maxlength="2000" placeholder="Record stitching, dimensions, packaging, print registration, or other QC notes...">{{ old('_supplier_workflow_task') == $task['id'] ? old('qc_comments') : '' }}</textarea></label>
        <div class="supplier-flow-qc-choices">
            <button type="button" class="supplier-flow-qc-choice supplier-flow-qc-choice--issue" data-supplier-qc-report-open>
                <span class="supplier-flow-qc-choice-icon" aria-hidden="true">!</span>
                <strong>Report Issue</strong><small>Open a supplier-resolution issue</small>
            </button>
            <button type="button" class="supplier-flow-qc-choice" data-supplier-qc-pass>
                <span class="supplier-flow-qc-choice-icon" aria-hidden="true">✓</span>
                <strong>QC Passed</strong><small>Continue toward Shipment</small>
            </button>
        </div>
    </div>
    <div class="supplier-flow-qc-step" data-supplier-qc-step="issue" hidden>
        <div class="supplier-flow-quantities">
            <label>Issue category
                <select name="issue_category">
                    @foreach(['Fabric color variance', 'Print color mismatch', 'Incorrect dimensions', 'Damaged items', 'Other'] as $category)
                        <option value="{{ $category }}" @selected(old('_supplier_workflow_task') == $task['id'] && old('issue_category', 'Other') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </label>
            <label>Supplier <input type="text" disabled value="{{ $company->name ?? 'Your company' }}"></label>
        </div>
        <label>Description
            <textarea name="comment" rows="5" maxlength="2000" placeholder="Describe the issue and corrective action required...">{{ old('_supplier_workflow_task') == $task['id'] ? old('comment') : '' }}</textarea>
        </label>
        <div class="supplier-flow-qc-file-note"><strong>Screenshot / photo</strong><span>Supporting images/documents can be added to the task after the issue is recorded in FlowTracker.</span></div>
        <div class="supplier-flow-qc-email-note"><strong>Email preview</strong><span>The issue notification will be recorded for {{ $company->name ?? 'your company' }} with this Order and task.</span></div>
        <div class="supplier-flow-dialog-actions">
            <button type="button" class="supplier-flow-cancel" data-supplier-modal-close>Cancel</button>
            <button type="submit" class="supplier-flow-action-btn supplier-flow-qc-report-submit" data-supplier-action-submit>Report Issue</button>
        </div>
    </div>
@elseif($actionKey === 'QC_ISSUE')
    <label>Resolution
        <textarea name="comment" rows="5" maxlength="2000" required placeholder="Describe the corrective action and resolution...">{{ old('_supplier_workflow_task') == $task['id'] ? old('comment') : '' }}</textarea>
    </label>
@endif
