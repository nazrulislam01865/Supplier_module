@if($perm('shipment'))
@php
    $remotePrices = $shipment['remote_surcharge'] ?? [];
    $urgencyEntries = $shipment['urgencies'] ?? [];
    $urgencyOptions = $lookups['shipment_urgencies'] ?? [];
    $canEditShipment = $perm('shipment', 'edit');
@endphp
<div class="ft-native-section ft-ux-shipment-workspace">
    <div class="ft-ux-section-intro">
        <div>
            <h3>Shipment &amp; surcharge</h3>
            <p>Configure quantity-based remote area charges and available shipping urgencies.</p>
        </div>
        @unless($canEditShipment)<span class="ft-ux-readonly-pill">View only</span>@endunless
    </div>

    @if($canEditShipment)
    <form method="POST" action="{{ route('supplier.products.module.action') }}">
        @csrf
        <input type="hidden" name="operation" value="save_pricing">
        <input type="hidden" name="group" value="product">
        <input type="hidden" name="id" value="{{ $recordId }}">
        <input type="hidden" name="section" value="shipment">
    @endif

    <div class="ft-ux-subsection">
        <div class="ft-ux-subsection-heading">
            <div>
                <h3>Remote area surcharge prices</h3>
                <p>Extra charge per unit at the specified quantity.</p>
            </div>
            <span class="ft-ux-counter">{{ count($remotePrices) }} {{ count($remotePrices) === 1 ? 'tier' : 'tiers' }}</span>
        </div>
        @if(empty($remotePrices))
            <div class="ft-ux-empty-panel">
                <span class="ft-ux-empty-icon" aria-hidden="true">＋</span>
                <div>
                    <strong>No remote area surcharges configured</strong>
                    <small>{{ $canEditShipment ? 'Enter a quantity and charge below, or leave these fields empty if this product has no surcharge.' : 'No quantity-based remote area surcharge prices have been set for this product.' }}</small>
                </div>
            </div>
        @endif
        @if($canEditShipment || !empty($remotePrices))
            @include('portal.product-native.matrix', ['section'=>'client_pricing', 'rows'=>$remotePrices, 'editable'=>$canEditShipment, 'title'=>'Remote area surcharge prices', 'fieldPrefix'=>'remote_surcharge'])
        @endif
    </div>

    <div class="ft-ux-subsection">
        <div class="ft-ux-subsection-heading">
            <div>
                <h3>Shipping urgencies</h3>
                <p>Available shipment speeds and their additional charges.</p>
            </div>
            <span class="ft-ux-counter">{{ count($urgencyEntries) }} selected</span>
        </div>
        @if(empty($urgencyEntries))
            <div class="ft-ux-empty-panel">
                <span class="ft-ux-empty-icon" aria-hidden="true">↗</span>
                <div>
                    <strong>No shipping urgencies added</strong>
                    <small>{{ $canEditShipment ? 'Use Add shipping urgency to select one of the available options.' : 'No urgency options are configured for this product.' }}</small>
                </div>
            </div>
        @endif
        @if($canEditShipment)
            <div class="ft-native-repeat ft-ux-urgency-repeat" data-repeat="urgencies">
                <div class="ft-native-repeat-items ft-ux-urgency-list">
                    @foreach($urgencyEntries as $i => $urgency)
                        <div class="ft-native-repeat-row ft-ux-urgency-row">
                            <label><span>Shipping urgency</span>
                                <select name="urgencies[{{ $i }}][shipment_urgency_id]" required>
                                    @foreach($urgencyOptions as $option)
                                        <option value="{{ $option['id'] }}" @selected((int) ($urgency['shipment_urgency_id'] ?? 0) === (int) $option['id'])>{{ $option['name'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label><span>Additional charge</span><input type="number" step="0.01" min="0" name="urgencies[{{ $i }}][extra_charge]" value="{{ $urgency['extra_charge'] ?? 0 }}" required placeholder="0.00"></label>
                            <button type="button" class="ft-ux-row-remove" data-native-remove-row aria-label="Remove shipping urgency">Remove</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="ft-product-page-btn is-secondary ft-ux-add-urgency" data-native-add-row @disabled(empty($urgencyOptions))>+ Add shipping urgency</button>
                @if(empty($urgencyOptions))<small class="ft-ux-muted-note">No shipping urgency options are available in FlowTracker master data.</small>@endif
                <template>
                    <div class="ft-native-repeat-row ft-ux-urgency-row">
                        <label><span>Shipping urgency</span>
                            <select name="urgencies[__INDEX__][shipment_urgency_id]" required>
                                @foreach($urgencyOptions as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach
                            </select>
                        </label>
                        <label><span>Additional charge</span><input type="number" step="0.01" min="0" name="urgencies[__INDEX__][extra_charge]" value="0" required placeholder="0.00"></label>
                        <button type="button" class="ft-ux-row-remove" data-native-remove-row aria-label="Remove shipping urgency">Remove</button>
                    </div>
                </template>
            </div>
        @else
            @if(!empty($urgencyEntries))
                <div class="ft-ux-urgency-summary">
                    @foreach($urgencyEntries as $urgency)
                        <div class="ft-ux-urgency-summary-row">
                            <strong>{{ $urgency['shipment_urgency_name'] ?? 'Shipping urgency' }}</strong>
                            <span>Additional charge: {{ number_format((float) ($urgency['extra_charge'] ?? 0), 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
    @if($canEditShipment)
        <div class="ft-ux-section-actions">
            <small>Saving updates only this product's shipment settings.</small>
            <button type="submit" class="ft-product-page-btn is-primary">Save shipment settings</button>
        </div>
    @else
        <p class="ft-ux-permission-note">Shipment settings are read-only for your supplier account. Editing requires Shipment → Edit permission in FlowTracker.</p>
    @endif
    @if($canEditShipment)</form>@endif
</div>
@endif
