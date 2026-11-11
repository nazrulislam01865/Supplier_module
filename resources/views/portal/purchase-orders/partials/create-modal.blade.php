{{-- Same Single / Split PO choice, product selection, and optional carton/payment sections as FlowTracker's original generator. --}}
<div class="po-create-overlay" role="presentation">
    <section class="po-create-dialog" role="dialog" aria-modal="true" aria-labelledby="supplier-create-po-title">
        <header class="po-create-header">
            <div><h2 id="supplier-create-po-title">Create Supplier Purchase Order</h2><p>Choose how you want to create the Purchase Order for this order.</p></div>
            <a href="{{ route('supplier.purchase-orders.show', $order->id) }}" class="po-create-close" aria-label="Close Purchase Order dialog">×</a>
        </header>
        <form method="POST" action="{{ route('supplier.purchase-orders.save', $order->id) }}" id="supplier-po-create-form">
            @csrf
            <input type="hidden" name="po_id" value="">
            <div class="ft-supplier-po-choices" role="group" aria-label="Purchase Order type">
                <label class="ft-supplier-po-option is-selected" data-po-choice>
                    <input type="radio" name="mode" value="single" checked>
                    <span class="ft-supplier-po-option-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h8"/></svg></span>
                    <span class="ft-supplier-po-option-label"><strong>Single Purchase Order</strong><small>One PDF with all {{ count($rows) }} {{ count($rows) === 1 ? 'product' : 'products' }}</small></span>
                    <span class="ft-supplier-po-option-check" aria-hidden="true">✓</span>
                </label>
                <label class="ft-supplier-po-option" data-po-choice @if(count($rows) < 2) title="At least two products are needed to split a PO" @endif>
                    <input type="radio" name="mode" value="split" @disabled(count($rows) < 2)>
                    <span class="ft-supplier-po-option-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="11" height="15" rx="1.5"/><path d="M8 8h4M8 12h4M17 8h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9a2 2 0 0 1 0-2v-1"/></svg></span>
                    <span class="ft-supplier-po-option-label"><strong>Split Purchase Orders</strong><small>Separate PDFs for selected products</small></span>
                    <span class="ft-supplier-po-option-check" aria-hidden="true">✓</span>
                </label>
            </div>
            <section class="ft-supplier-po-product-section" id="po-split-panel" hidden>
                <div class="ft-supplier-po-section-heading"><strong>Which products need separate POs?</strong><span id="po-split-count">0 of {{ count($rows) }} selected</span></div>
                <p>Select the products that should each have their own PO. Other products will be grouped by supplier automatically.</p>
                <div class="ft-supplier-po-product-list">
                @foreach($rows as $line)
                    <label class="ft-supplier-po-product"><input type="checkbox" name="split_item_ids[]" value="{{ (int) $line['item_id'] }}"><span class="ft-supplier-po-product-name"><b>{{ $line['name'] }}</b><small>{{ number_format((int) $line['quantity']) }} pcs · Your company</small></span></label>
                @endforeach
                </div>
            </section>
            <div class="po-create-summary">
                <label>Linked order<span>{{ $order->external_reference }}</span></label>
                <label>PO delivery date<input type="date" name="delivery_date" value="{{ old('delivery_date', $payload['delivery_date'] ?? '') }}" @if(!empty($payload['delivery_date'])) max="{{ $payload['delivery_date'] }}" @endif></label>
            </div>
            <section class="ft-po-items-edit" aria-label="Editable purchase order products">
                <div class="ft-po-items-edit-head"><strong>Purchase Order details</strong><small>Only these PO values change. The original Order stays unchanged.</small></div>
                <div class="po-table-scroller"><table class="po-table po-edit-table">
                    <thead><tr><th>Product / description</th><th>Model / SKU</th><th>Quantity</th><th>Supplier unit price</th></tr></thead>
                    <tbody>
                    @foreach($rows as $i => $line)
                        <tr>
                            <td><input type="hidden" name="items[{{ $i }}][item_id]" value="{{ $line['item_id'] }}"><input type="text" name="items[{{ $i }}][name]" aria-label="Product name {{ $loop->iteration }}" required maxlength="255" value="{{ old('items.'.$i.'.name', $line['name']) }}"></td>
                            <td><input type="text" name="items[{{ $i }}][sku]" aria-label="Model or SKU {{ $loop->iteration }}" maxlength="100" value="{{ old('items.'.$i.'.sku', $line['sku'] ?? '') }}"></td>
                            <td><input type="number" name="items[{{ $i }}][quantity]" aria-label="Quantity {{ $loop->iteration }}" required min="1" max="999999999" step="1" value="{{ old('items.'.$i.'.quantity', $line['quantity']) }}"></td>
                            <td><input type="number" name="items[{{ $i }}][supplier_unit_cost]" aria-label="Supplier unit price {{ $loop->iteration }}" min="0" max="999999999.99" step="0.01" value="{{ old('items.'.$i.'.supplier_unit_cost', $line['supplier_unit_cost'] ?? '') }}" placeholder="Not set"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>
            <details class="ft-supplier-po-extra">
                <summary><span><strong>Carton marks &amp; payment details</strong><small>Optional — leave anything unknown blank</small></span><span aria-hidden="true">⌄</span></summary>
                <div class="ft-supplier-po-extra-body">
                    <div class="ft-supplier-po-extra-heading"><strong>4. Carton marks requirement</strong><small>These values appear in section 4 of the supplied Excel template.</small></div>
                    <div class="ft-supplier-po-extra-grid">
                        @foreach(['carton_buyer' => 'Carton buyer', 'carton_style' => 'Style number', 'carton_colour' => 'Colour', 'carton_size' => 'Size', 'carton_gross_weight' => 'Gross weight (kg)', 'carton_net_weight' => 'Net weight (kg)', 'carton_measurement' => 'Carton measurement (cm)', 'carton_other' => 'Other carton notes', 'carton_qty' => 'Carton mark quantity (PCS)'] as $field => $label)
                            <label class="ft-supplier-po-extra-field"><span>{{ $label }}</span><input type="text" name="template[{{ $field }}]" maxlength="255" placeholder="Optional"></label>
                        @endforeach
                        <label class="ft-supplier-po-extra-field"><span>Mark type</span><select name="template[carton_mark_type]"><option value="">Not specified</option><option value="side">Side mark only</option><option value="two">Two sides</option><option value="four">Four sides</option><option value="barcode">Barcode mark</option><option value="fragile">Fragile / this side up</option></select></label>
                        <label class="ft-supplier-po-extra-field"><span>Outer carton strength</span><select name="template[carton_strength]"><option value="">Not specified</option><option value="5ply">5-ply</option><option value="3ply">3-ply</option><option value="export">Strong export carton</option></select></label>
                        <label class="ft-supplier-po-extra-field"><span>Inner packing</span><select name="template[inner_packing]"><option value="">Not specified</option><option value="polybag">Polybag per piece</option><option value="tissue">Tissue paper</option><option value="desiccant">Desiccant</option></select></label>
                        @foreach(['pieces_per_inner' => 'Pieces per inner carton','carton_strength_other'=>'Outer carton other','inner_packing_other'=>'Inner packing other'] as $field => $label)
                            <label class="ft-supplier-po-extra-field"><span>{{ $label }}</span><input name="template[{{ $field }}]" maxlength="255" placeholder="Optional"></label>
                        @endforeach
                    </div>
                    <div class="ft-supplier-po-extra-heading"><strong>5. Payment method</strong><small>Leave unspecified terms and bank details blank.</small></div>
                    <div class="ft-supplier-po-extra-grid">
                        <label class="ft-supplier-po-extra-field"><span>Payment method</span><select name="template[payment_method]" id="po-create-payment-method"><option value="">Not specified</option><option value="tt">T/T (bank transfer)</option><option value="lc">L/C at sight</option><option value="dp">D/P</option><option value="other">Other</option></select></label>
                        <label class="ft-supplier-po-extra-field"><span>Payment terms</span><select name="template[payment_term]"><option value="">Not specified</option><option value="30_70">30% deposit / 70% before shipment</option><option value="50_50">50% / 50%</option><option value="net30">Net 30 days</option><option value="net60">Net 60 days</option></select></label>
                        <label class="ft-supplier-po-extra-field" data-po-payment-tt hidden><span>Deposit (%)</span><input name="template[deposit_percent]" maxlength="5" placeholder="Optional"></label>
                        <label class="ft-supplier-po-extra-field" data-po-payment-tt hidden><span>Balance (%)</span><input name="template[balance_percent]" maxlength="5" placeholder="Optional"></label>
                        <label class="ft-supplier-po-extra-field" data-po-payment-other hidden><span>Other payment method</span><input name="template[payment_other]" maxlength="100" placeholder="Specify method"></label>
                        @foreach(['beneficiary'=>'Beneficiary','bank_name'=>'Bank name','bank_address'=>'Bank address','account_number'=>'Account number','swift_code'=>'SWIFT code'] as $field => $label)
                            <label class="ft-supplier-po-extra-field"><span>{{ $label }}</span><input name="template[{{ $field }}]" maxlength="255" placeholder="Optional"></label>
                        @endforeach
                    </div>
                    <p class="ft-supplier-po-extra-hint">The supplied PO template stays unchanged. Unknown carton, payment, and bank details remain blank in the PDF.</p>
                </div>
            </details>
            <footer class="ft-supplier-po-actions"><a class="po-button po-button-outline" href="{{ route('supplier.purchase-orders.show', $order->id) }}">Cancel</a><button class="po-button" type="submit" id="po-generate-submit">Generate Single PO</button></footer>
        </form>
    </section>
</div>
<script>
(() => {
 const form = document.getElementById('supplier-po-create-form');
 if (!form) return;
 const panel = document.getElementById('po-split-panel');
 const submit = document.getElementById('po-generate-submit');
 const update = () => {
   const split = form.querySelector('input[name="mode"]:checked')?.value === 'split';
   panel.hidden = !split;
   form.querySelectorAll('[data-po-choice]').forEach(label => label.classList.toggle('is-selected', !!label.querySelector('input:checked')));
   const selected = panel.querySelectorAll('input:checked').length;
   document.getElementById('po-split-count').textContent = selected + ' of {{ count($rows) }} selected';
   submit.textContent = split ? 'Generate Split POs' : 'Generate Single PO';
   panel.querySelectorAll('input').forEach(input => {input.disabled = !split;});
 };
 form.addEventListener('change', update);
 form.addEventListener('submit', event => {
   if (form.querySelector('input[name="mode"]:checked')?.value === 'split' && !panel.querySelector('input:checked')) {
     event.preventDefault(); panel.querySelector('input')?.focus(); return;
   }
   submit.disabled = true;
 });
 const payment = document.getElementById('po-create-payment-method');
 payment?.addEventListener('change', () => {
   document.querySelectorAll('[data-po-payment-tt]').forEach(x=>{ x.hidden = payment.value !== 'tt'; x.querySelector('input').disabled = payment.value !== 'tt'; });
   document.querySelectorAll('[data-po-payment-other]').forEach(x=>{ x.hidden = payment.value !== 'other'; x.querySelector('input').disabled = payment.value !== 'other'; });
 });
 update(); payment?.dispatchEvent(new Event('change'));
})();
</script>
