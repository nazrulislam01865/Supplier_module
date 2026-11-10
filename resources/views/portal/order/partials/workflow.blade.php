@php
    // Limit supplier presentation only; keep the synchronized workflow unchanged.
    $phases = collect($workflow['stages'] ?? [])->sortBy('sequence')->take(5)->values();
    // Defaults match FlowTracker's QC modal using this supplier's order lines;
    // the synchronized snapshot already contains the required quantities.
    $qcUnits = collect($order->payload['items'] ?? [])->sum(fn ($item) => max(0, (int) ($item['quantity'] ?? 0)));
    $qcReceived = $qcUnits ?: 1;
    $qcInspected = $qcUnits > 0 ? max(1, min($qcUnits, (int) ceil($qcUnits * 0.10))) : 1;
    $activeId = (int) ($workflow['current_phase_id'] ?? 0);
    $selected = $phases->first(fn ($phase) => (int) $phase['id'] === $activeId)
        ?? $phases->first(fn ($phase) => ($phase['state'] ?? '') === 'active')
        ?? $phases->last(fn ($phase) => ($phase['state'] ?? '') === 'completed')
        ?? $phases->first();
    $selectedId = (int) ($selected['id'] ?? 0);
@endphp
<section class="supplier-flow-original" id="supplierOrderWorkflow" data-supplier-workflow>
    <div class="supplier-flow-process-head">
        <div>
            <h2>Order process &amp; tasks</h2>
            <div class="supplier-flow-sub">{{ $phases->count() }} stages · status changes save automatically · conditional tasks appear only when required</div>
        </div>
    </div>
    @error('workflow') <p class="supplier-flow-error" role="alert">{{ $message }}</p> @enderror
    @if($errors->any() && ! $errors->has('workflow')) <p class="supplier-flow-error" role="alert">{{ $errors->first() }}</p> @endif
    @if(session('success')) <p class="supplier-flow-success" role="status">{{ session('success') }}</p> @endif
    @if($phases->isEmpty())
        <div class="supplier-flow-empty">The workflow has not synchronized yet. It will appear after FlowTracker delivers the latest order.</div>
    @else
        <div class="supplier-flow-stages" role="tablist" aria-label="Order workflow stages">
            @foreach($phases as $stage)
                @php
                    $stageState = $stage['state'] ?? 'locked';
                    $isSelected = (int) $stage['id'] === $selectedId;
                    $locked = $stageState === 'locked';
                @endphp
                <button type="button" class="supplier-flow-stage {{ $stageState }} {{ $isSelected ? 'viewing' : '' }}"
                    style="--stage:{{ $stage['color'] }}"
                    role="tab" id="supplier-flow-tab-{{ (int) $stage['id'] }}"
                    data-flow-stage="{{ (int) $stage['id'] }}" aria-controls="supplier-flow-panel-{{ (int) $stage['id'] }}"
                    aria-disabled="{{ $locked ? 'true' : 'false' }}" aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                    title="{{ $locked ? ($stage['dependency'] ?? '') : 'Open '.$stage['name'].' tasks' }}">
                    @if($stageState === 'completed') <span class="supplier-flow-stage-check">✓</span> @endif
                    <span class="supplier-flow-stage-num">STAGE {{ $stage['sequence'] }}</span>
                    <strong class="supplier-flow-stage-name">{{ $stage['name'] }}</strong>
                    <span class="supplier-flow-stage-state">{{ $stage['state_label'] }}</span>
                    <span class="supplier-flow-progress"><i style="width:{{ (int) $stage['progress'] }}%"></i></span>
                    <span class="supplier-flow-owner"><span class="supplier-flow-avatar">{{ mb_substr((string) $stage['owner'], 0, 1) }}</span> {{ $stage['owner'] }}</span>
                    <span class="supplier-flow-dependency">{{ $stage['dependency'] }}</span>
                </button>
            @endforeach
        </div>
        @foreach($phases as $stage)
            <section class="supplier-flow-task-panel" id="supplier-flow-panel-{{ (int) $stage['id'] }}"
                role="tabpanel" aria-labelledby="supplier-flow-tab-{{ (int) $stage['id'] }}"
                data-flow-panel="{{ (int) $stage['id'] }}" @if((int) $stage['id'] !== $selectedId) hidden @endif>
                <header class="supplier-flow-task-head">
                    <div>
                        <h3>{{ $stage['name'] }} tasks</h3>
                        <p>{{ match ($stage['state']) { 'completed' => 'This stage is complete', 'active' => 'Complete the active task to continue the workflow', 'cancelled' => 'Order cancelled — workflow actions are blocked', default => 'This stage is locked' } }}</p>
                    </div>
                    <strong class="supplier-flow-required">{{ (int) $stage['required_complete'] }} of {{ (int) $stage['required_total'] }} required complete</strong>
                </header>
                @if((int) $stage['sequence'] === 3)
                    <div class="supplier-flow-delivery-target" role="note">
                        <span>Estimated delivery date <small>· Set by FlowTracker</small></span>
                        @if(!empty($order->payload['estimated_delivery_date']))
                            <strong>{{ \Illuminate\Support\Carbon::parse($order->payload['estimated_delivery_date'])->format('M j, Y') }}</strong>
                            <span>Follow this target. Contact the FlowTracker team if it needs to change.</span>
                        @else
                            <strong>Awaiting FlowTracker date</strong>
                        @endif
                    </div>
                @endif
                <div class="supplier-flow-table-scroll">
                    <div class="supplier-flow-columns" aria-hidden="true"><span></span><span>Task</span><span>Assignee</span><span>Due date</span><span>Status / files</span><span>Action</span></div>
                    @forelse($stage['tasks'] as $task)
                        <article class="supplier-flow-task {{ $task['mode'] }}" style="--task-color:{{ $stage['color'] }}">
                            <div class="supplier-flow-task-icon">{{ $task['mode'] === 'done' ? '✓' : ($task['mode'] === 'active' ? '●' : '⌁') }}</div>
                            <div class="supplier-flow-task-copy">
                                <span class="supplier-flow-task-code">TASK {{ $task['code'] }}</span>
                                <strong>{{ $task['title'] }}</strong>
                                @if(!empty($task['sample_description'])) <small class="supplier-flow-muted">{{ $task['sample_description'] }}</small> @endif
                                @if(($task['sample']['kind'] ?? '') !== '') <small class="supplier-flow-muted">{{ $task['sample']['kind'] === 'physical' ? 'Physical sample · shipment required' : 'Sample images · email only' }}</small> @endif
                                @if(($task['sample']['state'] ?? '') === 'supplier_revision') <small class="supplier-flow-muted">Revision requested: {{ $task['sample']['review_note'] ?? '' }}</small> @endif
                                @if(!$task['required'] && empty($task['sample'])) <span class="supplier-flow-optional">Optional</span> @endif
                            </div>
                            <div class="supplier-flow-assignee"><span class="supplier-flow-avatar">{{ mb_substr((string) $task['assignee'], 0, 1) }}</span> {{ $task['assignee'] }}</div>
                            <div class="supplier-flow-date">{{ !empty($task['due_date']) ? \Illuminate\Support\Carbon::parse($task['due_date'])->format('M j, Y') : '—' }}</div>
                            <div class="supplier-flow-task-status">
                                <span class="supplier-flow-pill {{ $task['mode'] }}">{{ !empty($task['sample']) ? ($task['display_status'] ?? $task['status']) : ($task['mode'] === 'locked' ? 'Locked' : ($task['display_status'] ?? $task['status'])) }}</span>
                                @include('portal.partials.qc-task-documents')
                            </div>
                            <div class="supplier-flow-action">
                                @if(($task['can_act'] ?? false) && ($task['assigned_to_supplier'] ?? false) && ($task['action_key'] ?? '') !== 'PROD_SET_ESTIMATED_DELIVERY')
                                    @php
                                        $actionKey = (string) ($task['action_key'] ?? '');
                                        $isUpload = in_array($actionKey, ['NEW_UPLOAD_PO','ART_PREPARE_UPLOAD'], true);
                                        $actionText = match ($actionKey) {
                                            'NEW_UPLOAD_PO' => 'Upload Purchase Order',
                                            'NEW_SEND_PO_ARTWORK' => 'Send to Artwork Team',
                                            'ART_PREPARE_UPLOAD' => 'Upload Artwork',
                                            'ART_INTERNAL_REVIEW' => 'Review Artwork',
                                            'ART_SEND_ORDER_TEAM' => 'Send Artwork',
                                            'ART_CLIENT_ERP_DECISION' => str_contains(strtolower((string) $task['status']), 'waiting for client') ? 'Record client decision' : 'Confirm ERP upload',
                                            'ART_SAMPLE_APPROVAL' => ($task['sample']['state'] ?? '') === 'supplier_revision' ? 'Prepare revised sample' : (($task['sample']['kind'] ?? '') === 'physical' ? 'Confirm sample prepared' : 'Record sample image email'),
                                            'PROD_START' => 'Start production',
                                            'PROD_FINISH' => 'Finish production',
                                            'QC_CHECK' => 'Open QC check',
                                            'QC_ISSUE' => 'Issue Resolved',
                                            'QC_APPROVE_SHIPMENT' => 'Proceed to Shipment',
                                            'SHIP_CONFIRM_INFO' => 'Confirm shipment info',
                                            'SHIP_LABEL' => 'Add tracking',
                                            'SHIP_PACKAGE' => 'Dispatch shipment',
                                            default => 'Take action',
                                        };
                                    @endphp
                                    <button type="button" class="supplier-flow-action-btn" data-supplier-modal-open="supplier-flow-dialog-{{ (int) $task['id'] }}">{{ $actionText }}</button>
                                    <dialog class="supplier-flow-dialog" @if($actionKey === 'QC_CHECK') data-supplier-qc-dialog @endif @if($actionKey === 'ART_SAMPLE_APPROVAL') data-supplier-sample-dialog @endif aria-modal="true" id="supplier-flow-dialog-{{ (int) $task['id'] }}" aria-labelledby="supplier-flow-dialog-title-{{ (int) $task['id'] }}" data-supplier-action-dialog>
                                        <div class="supplier-flow-dialog-head">
                                            <div><h4 id="supplier-flow-dialog-title-{{ (int) $task['id'] }}" @if($actionKey === 'QC_CHECK') data-supplier-qc-title @endif>{{ match ($actionKey) {
                                                'QC_CHECK' => 'Product Quality Check',
                                                'QC_ISSUE' => 'Resolve QC Issue',
                                                default => $actionKey === 'ART_CLIENT_ERP_DECISION' ? (str_contains(strtolower((string) $task['status']), 'waiting for client') ? 'Record Client Artwork Decision' : 'Confirm Client ERP Upload') : $actionText,
                                            } }}</h4>
                                            <p @if($actionKey === 'QC_CHECK') data-supplier-qc-copy @endif>{{ match ($actionKey) {
                                                'QC_CHECK' => 'Record received/inspected quantities and either pass QC or report an issue.',
                                                'QC_ISSUE' => 'Record the corrective action before Shipment can be activated.',
                                                default => 'Task '.$task['code'].' · '.$task['title'],
                                            } }}</p></div>
                                            <button type="button" class="supplier-flow-dialog-close" data-supplier-modal-close aria-label="Close action dialog">×</button>
                                        </div>
                                        @if(old('_supplier_workflow_task') == $task['id'] && $errors->any())
                                            <p class="supplier-flow-error" role="alert">{{ $errors->first() }}</p>
                                        @endif
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('supplier.orders.workflow.action', [$order->id, $task['id']]) }}" class="supplier-flow-action-form" data-supplier-action-form>
                                            @csrf
                                            <input type="hidden" name="_supplier_workflow_task" value="{{ (int) $task['id'] }}">
                                            @if($isUpload)
                                                <input type="hidden" name="decision" value="upload">
                                            @elseif($actionKey === 'ART_SAMPLE_APPROVAL')
                                                <input type="hidden" name="decision" value="sample_prepared">
                                                <p class="supplier-flow-sample-kind">{{ ($task['sample']['kind'] ?? '') === 'physical' ? 'Physical sample · shipment required' : 'Sample images · email only' }}</p>
                                                @if(($task['sample']['state'] ?? '') === 'supplier_revision')
                                                    <p class="supplier-flow-helper"><strong>Revision instructions:</strong> {{ $task['sample']['review_note'] ?? '' }}</p>
                                                @elseif(!empty($task['sample']['request_notes']))
                                                    <p class="supplier-flow-helper"><strong>Preparation instructions:</strong> {{ $task['sample']['request_notes'] }}</p>
                                                @endif
                                                @if(($task['sample']['kind'] ?? '') === 'physical')
                                                    <p class="supplier-flow-helper">Supplier Portal · Preparation only. No shipping method or tracking fields here. After preparation FlowTracker will confirm receipt.</p>
                                                @else
                                                    <p class="supplier-flow-helper">Supplier Portal · Email-only option. Send the sample image to the specified recipient.</p>
                                                    <label>Email recipient <input type="email" name="email_to" maxlength="255" value="{{ old('email_to', $task['sample']['email_to'] ?? '') }}" required placeholder="orderteam@example.com"></label>
                                                    <label>Sample image file <input type="file" name="files[]" accept="image/*" required></label>
                                                @endif
                                                <label>Preparation note (optional) <textarea name="supplier_note" maxlength="1000" rows="3" placeholder="Sample is ready and sent to FlowTracker.">{{ old('supplier_note') }}</textarea></label>
                                            @elseif($actionKey === 'QC_CHECK')
                                                <input type="hidden" name="decision" value="{{ old('_supplier_workflow_task') == $task['id'] ? old('decision', 'pass') : 'pass' }}" data-supplier-qc-decision>
                                            @elseif($actionKey === 'QC_ISSUE')
                                                <input type="hidden" name="decision" value="resolve">
                                            @elseif($actionKey === 'PROD_ISSUE')
                                                <label>Decision <select name="decision"><option value="confirm">Complete monitoring</option><option value="issue">Report issue</option><option value="resolve">Resolve issue</option></select></label>
                                            @elseif($actionKey === 'ART_INTERNAL_REVIEW')
                                                <label>Decision <select name="decision"><option value="confirm">Artwork confirmed</option><option value="revise">Request revision</option><option value="cancel_artwork">Cancel artwork</option></select></label>
                                            @elseif($actionKey === 'ART_CLIENT_ERP_DECISION')
                                                @if(str_contains(strtolower((string) $task['status']), 'waiting for client') || str_contains(strtolower((string) $task['status']), 'client approval'))
                                                    <input type="hidden" name="decision" value="{{ old('_supplier_workflow_task') == $task['id'] ? old('decision', '') : '' }}" data-supplier-client-choice required>
                                                    <div class="supplier-flow-decision-stage" data-client-decision-stage="choice">
                                                        <p class="supplier-flow-helper">Choose the decision received from the client for the latest artwork.</p>
                                                        <div class="supplier-flow-choice-grid">
                                                            @if(!empty($task['artwork_files']))
                                                                <button type="button" class="supplier-flow-choice" data-client-action="revise"><span class="supplier-flow-choice-icon">↻</span><strong>Client Requested Revision</strong><small>Start another artwork revision</small></button>
                                                            @endif
                                                            <button type="button" class="supplier-flow-choice" data-client-action="approved"><span class="supplier-flow-choice-icon">✓</span><strong>Client Approved Artwork</strong><small>Continue to sample decision</small></button>
                                                        </div>
                                                    </div>
                                                    <div class="supplier-flow-decision-stage" data-client-decision-stage="sample" hidden>
                                                        <p class="supplier-flow-helper">Does the client require a sample or swatch before Production?</p>
                                                        <div class="supplier-flow-choice-grid">
                                                            <button type="button" class="supplier-flow-choice" data-client-action="sample"><span class="supplier-flow-choice-icon">✓</span><strong>Sample Required</strong><small>Continue to sample approval</small></button>
                                                            <button type="button" class="supplier-flow-choice" data-client-action="confirm"><span class="supplier-flow-choice-icon">✓</span><strong>No Sample Required</strong><small>Approve artwork and continue</small></button>
                                                        </div>
                                                        <button class="supplier-flow-back" type="button" data-client-back>← Back to decision</button>
                                                    </div>
                                                    <div class="supplier-flow-decision-stage" data-client-decision-stage="revision" hidden>
                                                        <p class="supplier-flow-helper">Select the artwork files needing revision, and describe the changes.</p>
                                                        @foreach(($task['artwork_files'] ?? []) as $artwork)
                                                            <div class="supplier-flow-revision-item">
                                                                <label class="supplier-flow-checkbox"><input type="checkbox" name="revision_document_ids[]" value="{{ (int) $artwork['id'] }}" @checked(old('_supplier_workflow_task') == $task['id'] && in_array((int) $artwork['id'], array_map('intval', (array) old('revision_document_ids', []))))><span>{{ $artwork['name'] }}</span></label>
                                                                <label>Requested change<textarea name="revision_notes[{{ (int) $artwork['id'] }}]" rows="2" maxlength="2000" placeholder="Describe the correction for this artwork">{{ old('_supplier_workflow_task') == $task['id'] ? old('revision_notes.'.$artwork['id']) : '' }}</textarea></label>
                                                            </div>
                                                        @endforeach
                                                        <button class="supplier-flow-back" type="button" data-client-back>← Back to decision</button>
                                                    </div>
                                                @else
                                                    <input type="hidden" name="decision" value="erp_uploaded">
                                                    <p class="supplier-flow-helper">Confirm the artwork has been uploaded to the client ERP. The task will then wait for the client's approval or revision decision.</p>
                                                    <label>ERP reference <span class="supplier-flow-muted">(optional)</span><input name="erp_reference" maxlength="150" value="{{ old('_supplier_workflow_task') == $task['id'] ? old('erp_reference') : '' }}" placeholder="Enter ERP reference"></label>
                                                @endif

                                            @elseif($actionKey === 'SHIP_LABEL')
                                                <input type="hidden" name="decision" value="complete">
                                            @else
                                                <input type="hidden" name="decision" value="confirm">
                                            @endif
                                            @if($actionKey === 'PROD_ISSUE')
                                                <p class="supplier-flow-helper">The supplier ship date is set in FlowTracker. You can report an issue, but you cannot change the date.</p>
                                            @endif
                                            @if(in_array($actionKey, ['QC_CHECK', 'QC_ISSUE'], true))
                                                @include('portal.partials.qc-cycle-fields')
                                            @endif
                                            @if($actionKey === 'PROD_ISSUE')
                                                <label>Issue category (optional) <input name="issue_category" maxlength="100"></label>
                                            @endif
                                            @if(in_array($actionKey, ['SHIP_LABEL','SHIP_PACKAGE']))
                                                <label>Shipping provider <input name="carrier" maxlength="150" required></label>
                                                <label>Tracking number <input name="tracking_number" maxlength="150" required></label>
                                                @if($actionKey === 'SHIP_PACKAGE')
                                                    <p class="supplier-flow-helper">Dispatch is recorded using the FlowTracker server date. Follow the supplier ship date shown on this order.</p>
                                                @endif
                                            @endif
                                            @if($actionKey === 'SHIP_CONFIRM_INFO')
                                                <label>Client name <input name="client_name" maxlength="255" required></label>
                                                <label>Contact person <input name="contact_name" maxlength="255" required></label>
                                                <label>Phone country code <input name="phone_country_code" maxlength="50" required></label>
                                                <label>Phone number <input name="phone_number" maxlength="70" required></label>
                                                <label>Shipping address <input name="address" maxlength="500" required></label>
                                                <label>City <input name="city" maxlength="150"></label>
                                                <label>State <input name="state" maxlength="150"></label>
                                                <label>Country <input name="country" maxlength="150" required></label>
                                                <label>Postal code <input name="postal_code" maxlength="40" required></label>
                                            @endif
                                            @if($actionKey === 'ART_INTERNAL_REVIEW' && !empty($task['artwork_files']))
                                                <fieldset>
                                                    <legend>Artwork selection (for revision or cancellation)</legend>
                                                    @foreach($task['artwork_files'] as $artwork)
                                                        <label><input type="checkbox" name="revision_document_ids[]" value="{{ (int) $artwork['id'] }}">
                                                            {{ $artwork['name'] }}</label>
                                                        <label>Requested change (if revising this file)
                                                            <textarea name="revision_notes[{{ (int) $artwork['id'] }}]" rows="2" maxlength="2000"></textarea>
                                                        </label>
                                                    @endforeach
                                                </fieldset>
                                            @endif
                                            @if($actionKey === 'ART_PREPARE_UPLOAD' && !empty($task['revision_sources']))
                                                @foreach($task['revision_sources'] as $source)
                                                    <label>Replace {{ $source['name'] }}
                                                        <input type="file" name="files[{{ (int) $source['id'] }}]" required
                                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.gif,.ico,.zip,.txt,.csv,.ai,.eps,.esp,.cdr">
                                                    </label>
                                                @endforeach
                                            @elseif(!in_array($actionKey, ['QC_CHECK', 'QC_ISSUE', 'QC_APPROVE_SHIPMENT', 'ART_SAMPLE_APPROVAL'], true))
                                                <label>{{ $isUpload ? 'Upload task files' : 'Attach task evidence (optional)' }}
                                                    <input type="file" name="files[]" multiple @if($isUpload) required @endif
                                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.gif,.ico,.zip,.txt,.csv,.ai,.eps,.esp,.cdr">
                                                </label>
                                            @endif
                                            @if(!in_array($actionKey, ['QC_CHECK', 'QC_ISSUE', 'QC_APPROVE_SHIPMENT', 'ART_SAMPLE_APPROVAL'], true))
                                                <small>{{ in_array($actionKey, ['ART_PREPARE_UPLOAD','ART_SAMPLE_APPROVAL'], true) ? 'Up to 400 MB per file' : 'Up to 20 MB per file' }} · max 5 files. Saved in FlowTracker on this task.</small>
                                                <label>Comment <span class="supplier-flow-muted">(optional)</span><textarea name="comment" rows="2" maxlength="2000" placeholder="Add task notes if needed">{{ old('_supplier_workflow_task') == $task['id'] ? old('comment') : '' }}</textarea></label>
                                            @endif
                                            @if($actionKey !== 'QC_CHECK')
                                            <div class="supplier-flow-dialog-actions">
                                                <button type="button" class="supplier-flow-cancel" data-supplier-modal-close>Cancel</button>
                                                @php
                                                    $submitLabel = match (true) {
                                                        $isUpload => 'Upload and complete task',
                                                        $actionKey === 'ART_SAMPLE_APPROVAL' => (($task['sample']['kind'] ?? '') === 'physical' ? 'Confirm sample prepared' : 'Record image email'),
                                                                                                                $actionKey === 'QC_ISSUE' => 'Issue Resolved',
                                                        $actionKey === 'QC_APPROVE_SHIPMENT' => 'Proceed to shipment',
                                                        $actionKey === 'ART_CLIENT_ERP_DECISION' => str_contains(strtolower((string) $task['status']), 'waiting for client') || str_contains(strtolower((string) $task['status']), 'client approval') ? 'Save client decision' : 'Confirm ERP upload',
                                                        default => 'Confirm action',
                                                    };
                                                @endphp
                                                <button type="submit" class="supplier-flow-action-btn" data-supplier-action-submit>{{ $submitLabel }}</button>
                                            </div>
                                            @endif
                                        </form>
                                    </dialog>
                                @else
                                    @if(!empty($task['sample']) && ($task['assigned_to_supplier'] ?? false))
                                        @php $sample = $task['sample']; @endphp
                                        <button type="button" class="supplier-flow-action-btn" data-supplier-modal-open="supplier-flow-detail-{{ (int) $task['id'] }}">{{ ($sample['state'] ?? '') === 'approved' ? 'View approval' : 'View request' }}</button>
                                        <dialog class="supplier-flow-dialog" id="supplier-flow-detail-{{ (int) $task['id'] }}" aria-modal="true" aria-labelledby="supplier-flow-detail-title-{{ (int) $task['id'] }}" data-supplier-action-dialog>
                                            <div class="supplier-flow-dialog-head">
                                                <div><h4 id="supplier-flow-detail-title-{{ (int) $task['id'] }}">Sample request &amp; activity</h4><p>Task {{ $task['code'] }} · Single conditional sample task</p></div>
                                                <button type="button" class="supplier-flow-dialog-close" data-supplier-modal-close aria-label="Close">×</button>
                                            </div>
                                            <div class="supplier-flow-sample-details">
                                                <dl>
                                                    <dt>Sample type</dt><dd>{{ ($sample['kind'] ?? '') === 'physical' ? 'Physical sample · shipment required' : 'Sample images · email only' }}</dd>
                                                    <dt>Current state</dt><dd>{{ $task['display_status'] ?? $task['status'] }}</dd>
                                                    <dt>Revision count</dt><dd>{{ (int) ($sample['revisions'] ?? 0) }}</dd>
                                                    @if(!empty($sample['request_notes']))<dt>Supplier instructions</dt><dd>{{ $sample['request_notes'] }}</dd>@endif
                                                    @if(!empty($sample['review_note']))<dt>Last feedback</dt><dd>{{ $sample['review_note'] }}</dd>@endif
                                                    @if(!empty($sample['received_on']))<dt>Received</dt><dd>{{ $sample['received_on'] }}</dd>@endif
                                                    @if(!empty($sample['email_to']))<dt>Email recipient</dt><dd>{{ $sample['email_to'] }}</dd>@endif
                                                    @if(!empty($sample['image_name']))<dt>Sample image</dt><dd>{{ $sample['image_name'] }}</dd>@endif
                                                    @if(!empty($sample['shipping']))
                                                        <dt>Shipment</dt><dd>{{ ($sample['shipping']['method'] ?? '') . ' · ' . ($sample['shipping']['carrier'] ?? '') }}</dd>
                                                        <dt>Tracking ID</dt><dd>{{ $sample['shipping']['tracking'] ?? '' }}</dd>
                                                    @endif
                                                </dl>
                                                <div class="supplier-flow-dialog-actions"><button type="button" class="supplier-flow-cancel" data-supplier-modal-close>Close</button></div>
                                            </div>
                                        </dialog>
                                    @elseif($task['date_controlled_by_flowtracker'] ?? false)
                                        <span class="supplier-flow-no-action">FlowTracker sets date</span>
                                    @elseif(($task['assigned_to_supplier'] ?? false) && ($task['mode'] ?? '') === 'locked' && ($stage['state'] ?? '') === 'active')
                                        <span class="supplier-flow-no-action" title="This task will be available after earlier required tasks are completed in FlowTracker.">Waiting for previous task</span>
                                    @else <span class="supplier-flow-no-action">—</span> @endif
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="supplier-flow-empty">No tasks configured for this stage.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    @endif
</section>
