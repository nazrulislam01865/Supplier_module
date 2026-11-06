{{-- QC Document is a separate task attachment, just as in FlowTracker. --}}
@if(($task['assigned_to_supplier'] ?? false) && in_array($task['document_action_key'] ?? '', ['QC_CHECK', 'QC_ISSUE'], true))
    <button class="supplier-flow-qc-document-link" type="button" data-supplier-modal-open="supplier-flow-qc-files-{{ (int) $task['id'] }}">📎 QC Document</button>
    <dialog class="supplier-flow-dialog supplier-flow-qc-document-dialog" id="supplier-flow-qc-files-{{ (int) $task['id'] }}" data-supplier-action-dialog aria-modal="true" aria-labelledby="supplier-flow-qc-files-title-{{ (int) $task['id'] }}">
        <div class="supplier-flow-dialog-head">
            <div>
                <h4 id="supplier-flow-qc-files-title-{{ (int) $task['id'] }}">QC Document</h4>
                <p>Files attached to Task {{ $task['code'] }} in FlowTracker.</p>
            </div>
            <button type="button" class="supplier-flow-dialog-close" data-supplier-modal-close aria-label="Close">×</button>
        </div>
        <div class="supplier-flow-dialog-body">
            @forelse($task['document_files'] ?? [] as $document)
                <p class="supplier-flow-qc-existing-file">
                    <a href="{{ route('supplier.orders.qc-document', [$order->id, (int) $document['id']]) }}">{{ $document['name'] }}</a>
                    <small>{{ number_format(max(0, (int) ($document['size'] ?? 0)) / 1048576, 2) }} MB</small>
                </p>
            @empty
                <p class="supplier-flow-helper">No QC documents attached yet.</p>
            @endforelse
            @if($task['can_attach'] ?? false)
                <form method="post" action="{{ route('supplier.orders.workflow.action', [$order->id, $task['id']]) }}" enctype="multipart/form-data" class="supplier-flow-action-form" data-supplier-action-form>
                    @csrf
                    <input type="hidden" name="_supplier_workflow_task" value="{{ (int) $task['id'] }}">
                    <input type="hidden" name="decision" value="attach">
                    <label>Add QC documents
                        <input type="file" name="files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.gif,.ico,.zip,.txt,.csv,.ai,.eps,.esp,.cdr">
                    </label>
                    <small>Up to 5 files · 20 MB each. Saved on this QC task without changing its status.</small>
                    <div class="supplier-flow-dialog-actions">
                        <button type="button" class="supplier-flow-cancel" data-supplier-modal-close>Cancel</button>
                        <button type="submit" class="supplier-flow-action-btn" data-supplier-action-submit>Upload QC Document</button>
                    </div>
                </form>
            @else
                <div class="supplier-flow-dialog-actions"><button type="button" class="supplier-flow-cancel" data-supplier-modal-close>Close</button></div>
            @endif
        </div>
    </dialog>
@endif
