<!-- STEP 2: PRICING & TERMS -->
                  <section class="quote-step-panel" data-quote-panel="2">
                    <div class="quote-stage-error hidden" data-quote-errors="2" role="alert"></div>
                    <div class="quote-card quote-carried-summary">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">1</span>RFQ and contact details <span class="optional-caption">Live from Product Details</span></div><button class="edit-link" type="button" data-next-quote="1">Edit details</button></div>
                      <div class="quote-card-body tight">
                        <div class="carried-detail-grid" aria-label="Details entered in the first step">
                          <div class="carried-detail"><small>Inquiry / RFQ</small><strong id="pricingContextRefs">--</strong></div>
                          <div class="carried-detail"><small>Supplier company</small><strong>{{ $company->name }}</strong></div>
                          <div class="carried-detail"><small>Contact person</small><strong id="pricingContextContact">--</strong></div>
                          <div class="carried-detail"><small>Email</small><strong id="pricingContextEmail">--</strong></div>
                          <div class="carried-detail"><small>Phone</small><strong id="pricingContextPhone" aria-live="polite">--</strong></div>
                          <div class="carried-detail"><small>Quotation due</small><strong id="pricingContextDue">--</strong></div>
                        </div>
                      </div>
                    </div>

                    <div class="quote-card">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">2</span>Product to quote</div></div>
                      <div class="quote-card-body tight">
                        <div class="quote-product-card">
                          <div class="quote-product-main">
                            <div class="product-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 9c4-2 10-2 14 0v7c-4 2-10 2-14 0V9Z"/><path d="M5 11 2 9M19 11l3-2M8 11h8M8 14h8"/></svg></div>
                            <div><h3>Select a requested product</h3><div class="meta"><strong>Product code</strong> &nbsp; --</div></div>
                            <div class="qty-box"><small>Requested quantity</small><strong>--</strong></div>
                          </div>
                          <div class="buyer-requirements"><strong>Buyer requirements</strong>Review the product details and buyer documents in your RFQ.</div>
                          <div class="rfq-item-summary"></div>
                        </div>
                        <div class="carried-file-status">Product documents: <strong id="pricingContextDocCount">0</strong> attached <button class="edit-link" type="button" data-next-quote="1">Manage files</button></div>
                      </div>
                    </div>

                    <div class="quote-card">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">3</span>Pricing <span class="pricing-header-note">Enter your unit price and commercial terms for the requested quantity.</span></div></div>
                      <div class="quote-card-body tight">
                        <div class="pricing-grid">
                          <div class="q-field"><label>Requested quantity</label><input value="" readonly></div>
                          <div class="q-field"><label>Currency</label><select id="qCurrency"><option>USD</option><option>EUR</option><option>CNY</option><option>GBP</option><option>BDT</option></select></div>
                          <div class="q-field" id="singleUnitPriceField"><label>Unit price <span class="req">*</span></label><input id="qUnitPrice" type="number" step="0.01" min="0.01" inputmode="decimal" placeholder="0.00" value=""></div>
                          <div class="q-field" id="singleMoqField"><label>Minimum order quantity (MOQ) <span class="req">*</span></label><input id="qMoq" type="number" min="1" step="1" inputmode="numeric" placeholder="Minimum quantity" value=""></div>
                          <div class="q-field"><label>Product subtotal</label><div class="pricing-total-box" id="qSubtotalTop">USD 0.00</div></div>

                        </div>
                        <div id="multiItemPrices" class="hidden" aria-label="Pricing per requested product"></div>
                        <div class="pricing-grid secondary">
                          <div class="q-field"><label>Tooling / setup cost</label><input id="qTooling" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0.00" value="0.00"></div>
                          <div class="q-field"><label>Sample cost</label><input id="qSample" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0.00" value="0.00"></div>
                          <div class="q-field"><label>Discount</label><input id="qDiscount" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0.00" value="0.00"></div>
                          <div class="q-field"><label>Tax <span class="req">*</span></label><select id="qTax"><option>Excluded</option><option>Included</option></select></div>
                        </div>
                        <p class="form-section-hint">Enter the price for the requested quantity. Optional costs and discounts will be included in the total automatically.</p>
                      </div>
                    </div>


                    <div class="quote-card quote-attachments-card">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">4</span>Pricing files <span class="optional-caption">Optional</span></div></div>
                      <div class="quote-card-body tight">
                        <p class="upload-helper">You can attach a price sheet or proposal as supporting evidence. The prices entered above are still required.</p>
                        @include('portal.quotation.components.uploader', ['kind' => 'pricing'])
                      </div>
                    </div>

                    <div class="quote-card">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">5</span>Production and delivery</div></div>
                      <div class="quote-card-body tight">
                        <div class="production-grid">
                          <div class="q-field"><label>Production lead time <span class="req">*</span></label><div class="q-field-with-unit"><input id="qProductionLead" type="number" min="1" step="1" inputmode="numeric" placeholder="Days to produce" value=""><span class="q-unit">days</span></div></div>
                          <div class="q-field"><label>Sample lead time <span class="req">*</span></label><div class="q-field-with-unit"><input id="qSampleLead" type="number" min="1" step="1" inputmode="numeric" placeholder="Days for sample" value=""><span class="q-unit">days</span></div></div>
                          <div class="q-field"><label>Incoterm <span class="req">*</span></label><select id="qIncoterm"><option>FOB</option><option>CIF</option><option>EXW</option><option>DDP</option></select></div>
                          <div class="q-field"><label>Shipping port <span class="req">*</span></label><input id="qShippingPort" placeholder="e.g. Shanghai, China" value=""></div>
                          <div class="q-field"><label>Estimated delivery date <span class="req">*</span></label><input id="qDeliveryDate" type="date" value=""></div>
                        </div>
                        <div class="production-grid second">
                          <div class="q-field"><label>Quote validity <span class="req">*</span></label><div class="q-field-with-unit"><input id="qValidity" type="number" min="1" step="1" inputmode="numeric" placeholder="Validity in days" value=""><span class="q-unit">days</span></div></div>
                          <div class="q-field"><label>Can meet requested specification? <span class="req">*</span></label><select id="qCompliance"><option>Yes, fully compliant</option><option>Partially compliant</option><option>No - alternative proposed</option></select></div>
                          <div class="q-field"><label>Deviations or alternatives (optional)</label><textarea id="qDeviations" placeholder="Describe any differences from the requested specification..."></textarea></div>
                        </div>
                      </div>
                    </div>

                    <div class="quote-card">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">6</span>Supplier notes</div></div>
                      <div class="quote-card-body tight"><div class="q-field"><label>Pricing / production notes (optional)</label><textarea id="qSupplierNotes" placeholder="Any relevant pricing or production notes..."></textarea></div></div>
                    </div>

                    <div class="quote-warning-note"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>You can save a draft and return before the deadline. Attach an optional pricing file above. Check your commercial terms before continuing to review.</div>

                    <div class="quote-bottom-actions">
                      <button class="btn btn-outline-teal" type="button" data-next-quote="1">← Back to product details</button>
                      <div class="right"><button class="btn btn-outline-teal" type="button" data-save-quote>▣ Save draft</button><button class="btn btn-primary" type="button" data-next-quote="3">Continue to review <span>›</span></button></div>
                    </div>
                  </section>
