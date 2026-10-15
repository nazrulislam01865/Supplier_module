<!-- STEP 3: REVIEW & SUBMIT -->
                  <section class="quote-step-panel" data-quote-panel="3">
                    <div class="quote-stage-error hidden" data-quote-errors="3" role="alert"></div>
                    <div class="quote-card">
                      <div class="review-intro"><h2>Review your quotation</h2><p>Check all information before submitting. You can return to any section to make changes.</p><div class="quote-warning-note"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>Submitting finalizes your quotation and queues secure delivery of every attachment to FlowTracker. You cannot edit it after submission.</div></div>
                    </div>

                    <div class="quote-card">
                      <div class="review-card-head"><div class="review-card-title"><span class="section-check">✓</span>Supplier and RFQ details</div><button class="edit-link" type="button" data-next-quote="1">✎ Edit</button></div>
                      <div class="review-kv-grid"><div class="review-kv"><small>Supplier company</small><strong>{{ $company->name }}</strong></div><div class="review-kv"><small>Contact person</small><strong id="reviewContact">{{ $user->name }}</strong></div><div class="review-kv"><small>Email</small><strong id="reviewEmail">{{ $user->email }}</strong></div><div class="review-kv"><small>Phone</small><strong id="reviewPhone">--</strong></div><div class="review-kv"><small>Inquiry reference</small><strong>--</strong></div><div class="review-kv"><small>RFQ reference</small><strong>--</strong></div></div>
                    </div>

                    <div class="quote-card">
                      <div class="review-card-head"><div class="review-card-title"><span class="section-check">✓</span>Product and pricing</div><button class="edit-link" type="button" data-next-quote="2">✎ Edit</button></div>
                      <div class="review-product-price"><div class="review-product"><div class="product-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 9c4-2 10-2 14 0v7c-4 2-10 2-14 0V9Z"/><path d="M5 11 2 9M19 11l3-2M8 11h8M8 14h8"/></svg></div><div><strong>Select a requested product</strong><span>--</span></div></div><div class="review-price-table"><div class="review-price-head"><span>QUANTITY</span><span>CURRENCY</span><span>UNIT PRICE</span><span>MOQ</span><span>SUBTOTAL</span></div><div class="review-price-row"><span>--</span><span id="reviewCurrency">USD</span><span id="reviewUnitPrice">--</span><span id="reviewMoq">--</span><span id="reviewSubtotal">USD 0.00</span></div></div></div>
                      <div id="reviewLinePrices" class="rfq-item-summary" style="padding:0 12px"></div><div class="review-costs"><div class="review-kv"><small>Tooling / setup</small><strong id="reviewTooling">USD 0.00</strong></div><div class="review-kv"><small>Sample cost</small><strong id="reviewSample">USD 0.00</strong></div><div class="review-kv"><small>Discount</small><strong id="reviewDiscount">USD 0.00</strong></div><div class="review-kv"><small>Tax</small><strong id="reviewTax">Excluded</strong></div><div class="review-total"><small>Total quoted value</small><strong id="reviewTotal">USD 0.00</strong></div></div>
                    </div>

                    <div class="quote-card">
                      <div class="review-card-head"><div class="review-card-title"><span class="section-check">✓</span>Production and delivery</div><button class="edit-link" type="button" data-next-quote="2">✎ Edit</button></div>
                      <div class="review-kv-grid review-production-grid"><div class="review-kv"><small>Production lead time</small><strong id="reviewProductionLead">--</strong></div><div class="review-kv"><small>Sample lead time</small><strong id="reviewSampleLead">--</strong></div><div class="review-kv"><small>Incoterm</small><strong id="reviewIncoterm">FOB</strong></div><div class="review-kv"><small>Shipping port</small><strong id="reviewShippingPort">--</strong></div><div class="review-kv"><small>Estimated delivery</small><strong id="reviewDeliveryDate">--</strong></div><div class="review-kv"><small>Quote validity</small><strong id="reviewValidity">--</strong></div><div class="review-kv"><small>Specification compliance</small><strong id="reviewCompliance">Yes, fully compliant</strong></div></div>
                      <div style="padding:0 12px 8px;color:#65738a;font-size:9.7px" id="reviewSupplierNotes"></div>
                    </div>

                    <div class="quote-card">
                      <div class="review-card-head"><div class="review-card-title"><span class="section-check">✓</span>Documents (<span id="reviewDocCount">0</span>)</div><button class="edit-link" type="button" data-next-quote="1">✎ Edit</button></div>
                      <div class="review-doc-list" id="reviewDocumentList"></div>
                      <div class="review-document-note" id="reviewDocumentNotes"></div>
                    </div>

                    <div class="declaration-box"><strong>Final declaration</strong><label class="declaration-check"><input type="checkbox" id="declarationAccuracy">I confirm that the information provided is accurate and that this quotation is valid for <span id="declarationValidity">--</span> days.</label><label class="declaration-check"><input type="checkbox" id="declarationAuthority">I am authorized to submit this quotation on behalf of <strong style="display:inline">{{ $company->name }}</strong>.</label><div class="submitted-by">Submitted by <strong>{{ $user->name }}</strong> &nbsp; · &nbsp; {{ $user->email }}</div></div>

                    <div class="quote-bottom-actions">
                      <button class="btn btn-outline-teal" type="button" data-next-quote="2">← Back to pricing</button>
                      <div class="right"><button class="btn btn-outline-teal" type="button" data-save-quote>▣ Save draft</button><button class="btn btn-primary" type="button" data-submit-quote>Submit quotation <span>›</span></button></div>
                    </div>
                    <div class="quote-secure-submission"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span id="quoteBuyerSyncStatus">Submissions are securely delivered to FlowTracker; pending transfers are retried.</span></div>
                  </section>
