@php
    // Limit supplier presentation only; keep the synchronized workflow unchanged.
    $phases = collect($workflow['stages'] ?? [])->sortBy('sequence')->take(5)->values();
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
                <div class="supplier-flow-table-scroll">
                    <div class="supplier-flow-columns" aria-hidden="true"><span></span><span>Task</span><span>Assignee</span><span>Due date</span><span>Status / files</span><span>Action</span></div>
                    @forelse($stage['tasks'] as $task)
                        <article class="supplier-flow-task {{ $task['mode'] }}" style="--task-color:{{ $stage['color'] }}">
                            <div class="supplier-flow-task-icon">{{ $task['mode'] === 'done' ? '✓' : ($task['mode'] === 'active' ? '●' : '⌁') }}</div>
                            <div class="supplier-flow-task-copy">
                                <span class="supplier-flow-task-code">TASK {{ $task['code'] }}</span>
                                <strong>{{ $task['title'] }}</strong>
                                @if(!$task['required']) <span class="supplier-flow-optional">Optional</span> @endif
                            </div>
                            <div class="supplier-flow-assignee"><span class="supplier-flow-avatar">{{ mb_substr((string) $task['assignee'], 0, 1) }}</span> {{ $task['assignee'] }}</div>
                            <div class="supplier-flow-date">{{ !empty($task['due_date']) ? \Illuminate\Support\Carbon::parse($task['due_date'])->format('M j, Y') : '—' }}</div>
                            <div class="supplier-flow-task-status"><span class="supplier-flow-pill {{ $task['mode'] }}">{{ $task['mode'] === 'locked' ? 'Locked' : $task['status'] }}</span></div>
                            <div class="supplier-flow-action">
                                @if(($task['can_act'] ?? false) && ($task['assigned_to_supplier'] ?? false) && ($task['action_key'] ?? '') !== 'PROD_SET_ESTIMATED_DELIVERY')
                                    @php
                                        $actionKey = (string) ($task['action_key'] ?? '');
                                        $isUpload = in_array($actionKey, ['NEW_UPLOAD_PO','ART_PREPARE_UPLOAD','ART_SAMPLE_APPROVAL'], true);
                                        $actionText = match ($actionKey) {
                                            'NEW_UPLOAD_PO' => 'Upload Purchase Order',
                                            'NEW_SEND_PO_ARTWORK' => 'Send to Artwork Team',
                                            'ART_PREPARE_UPLOAD' => 'Upload Artwork',
                                            'ART_INTERNAL_REVIEW' => 'Review Artwork',
                                            'ART_SEND_ORDER_TEAM' => 'Send Artwork',
                                            'ART_CLIENT_ERP_DECISION' => str_contains(strtolower((string) $task['status']), 'waiting for client') ? 'Record client decision' : 'Confirm ERP upload',
                                            'ART_SAMPLE_APPROVAL' => 'Upload sample approval',
                                            'PROD_START' => 'Start production',
                                            'PROD_FINISH' => 'Finish production',
                                            'QC_CHECK' => 'Open QC check',
                                            'QC_APPROVE_SHIPMENT' => 'Approve shipment',
                                            'SHIP_CONFIRM_INFO' => 'Confirm shipment info',
                                            'SHIP_LABEL' => 'Add tracking',
                                            'SHIP_PACKAGE' => 'Dispatch shipment',
                                            default => 'Take action',
                                        };
                                    @endphp
                                    <button type="button" class="supplier-flow-action-btn" data-supplier-modal-open="supplier-flow-dialog-{{ (int) $task['id'] }}">{{ $actionText }}</button>
                                    <dialog class="supplier-flow-dialog" aria-modal="true" id="supplier-flow-dialog-{{ (int) $task['id'] }}" aria-labelledby="supplier-flow-dialog-title-{{ (int) $task['id'] }}" data-supplier-action-dialog>
                                        <div class="supplier-flow-dialog-head">
                                            <div><h4 id="supplier-flow-dialog-title-{{ (int) $task['id'] }}">{{ $actionKey === 'ART_CLIENT_ERP_DECISION' ? (str_contains(strtolower((string) $task['status']), 'waiting for client') ? 'Record Client Artwork Decision' : 'Confirm Client ERP Upload') : $actionText }}</h4>
                                            <p>Task {{ $task['code'] }} · {{ $task['title'] }}</p></div>
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
                                            @elseif($actionKey === 'QC_CHECK')
                                                <label>Decision <select name="decision"><option value="pass">QC passed</option><option value="issue">Report issue</option></select></label>
                                            @elseif(in_array($actionKey, ['PROD_ISSUE','QC_ISSUE']))
                                                <label>Decision <select name="decision">
                                                    @if($actionKey === 'PROD_ISSUE') <option value="confirm">Complete monitoring</option><option value="issue">Report issue</option> @endif
                                                    <option value="resolve">Resolve issue</option>
                                                </select></label>
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
                                            @if($actionKey === 'QC_CHECK')
                                                <div class="supplier-flow-quantities">
                                                    <label>Received <input name="qty_received" type="number" min="0" required></label>
                                                    <label>Inspected <input name="qty_inspected" type="number" min="0" required></label>
                                                    <label>Accepted <input name="qty_accepted" type="number" min="0" value="0"></label>
                                                    <label>Rejected <input name="qty_rejected" type="number" min="0" value="0"></label>
                                                </div>
                                            @endif
                                            @if(in_array($actionKey, ['PROD_ISSUE', 'QC_ISSUE']))
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
                                            @else
                                                <label>{{ $isUpload ? 'Upload task files' : 'Attach task evidence (optional)' }}
                                                    <input type="file" name="files[]" multiple @if($isUpload) required @endif
                                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.gif,.ico,.zip,.txt,.csv,.ai,.eps,.esp,.cdr">
                                                </label>
                                            @endif
                                            <small>{{ in_array($actionKey, ['ART_PREPARE_UPLOAD','ART_SAMPLE_APPROVAL'], true) ? 'Up to 400 MB per file' : 'Up to 20 MB per file' }} · max 5 files. Saved in FlowTracker on this task.</small>
                                            <label>Comment <span class="supplier-flow-muted">(optional)</span><textarea name="comment" rows="2" maxlength="2000" placeholder="Add task notes if needed">{{ old('_supplier_workflow_task') == $task['id'] ? old('comment') : '' }}</textarea></label>
                                            <div class="supplier-flow-dialog-actions">
                                                <button type="button" class="supplier-flow-cancel" data-supplier-modal-close>Cancel</button>
                                                <button type="submit" class="supplier-flow-action-btn" data-supplier-action-submit>{{ $isUpload ? 'Upload and complete task' : ($actionKey === 'ART_CLIENT_ERP_DECISION' ? ((str_contains(strtolower((string) $task['status']), 'waiting for client') || str_contains(strtolower((string) $task['status']), 'client approval')) ? 'Save client decision' : 'Confirm ERP upload') : 'Confirm action') }}</button>
                                            </div>
                                        </form>
                                    </dialog>
                                @elseif($task['date_controlled_by_flowtracker'] ?? false)
                                    <span class="supplier-flow-no-action">FlowTracker sets date</span>
                                @elseif(($task['assigned_to_supplier'] ?? false) && $task['mode'] === 'locked')
                                    <span class="supplier-flow-no-action" title="The previous workflow task or stage must complete before this action can be performed.">Waiting for previous task</span>
                                @elseif(($task['assigned_to_supplier'] ?? false) && $task['mode'] === 'done')
                                    <span class="supplier-flow-no-action">Completed</span>
                                @elseif(($task['assigned_to_supplier'] ?? false))
                                    <span class="supplier-flow-no-action">Managed in FlowTracker</span>
                                @else <span class="supplier-flow-no-action">—</span> @endif
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
