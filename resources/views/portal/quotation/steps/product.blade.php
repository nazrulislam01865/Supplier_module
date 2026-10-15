<!-- STEP 1: PRODUCT DETAILS -->
                  <section class="quote-step-panel active" data-quote-panel="1">
                    <div class="quote-stage-error hidden" data-quote-errors="1" role="alert"></div>
                    <div class="quote-card rfq-context-card">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">1</span>RFQ and supplier details</div><button type="button" class="q-inline-action" data-portal-edit-contact>Edit contact</button></div>
                      <div class="quote-card-body">
                        <div class="q-grid cols-4 quote-contact-grid">
                          <div class="q-field"><label>Inquiry reference</label><div class="q-context-value locked">--</div></div>
                          <div class="q-field"><label>RFQ reference</label><div class="q-context-value locked">--</div></div>
                          <div class="q-field"><label>Requested by</label><div class="q-context-value locked">FlowTracker Buyer</div></div>
                          <div class="q-field"><label>Quotation due</label><div class="q-context-value locked">Awaiting RFQ selection</div></div>
                          <div class="q-field"><label>Supplier company <span class="req">*</span></label><input id="qSupplier" value="{{ $company->name }}" readonly></div>
                          <div class="q-field"><label for="qContact">Contact person <span class="req">*</span></label><input id="qContact" autocomplete="name" placeholder="Full contact name" value="{{ $user->name }}"></div>
                          <div class="q-field"><label for="qEmail">Email <span class="req">*</span></label><input id="qEmail" type="email" autocomplete="email" placeholder="name@example.com" value="{{ $user->email }}"></div>
                          <div class="q-field"><label for="qPhone">Phone <span class="req">*</span></label><input id="qPhone" type="tel" autocomplete="tel" inputmode="tel" placeholder="e.g. +880 1XXXXXXXXX" value="{{ $profile['phone'] ?? '' }}" maxlength="40"><small class="quote-field-hint">Include your country code.</small></div>
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
                        <div class="quote-buyer-files" id="quoteBuyerFilesInline"></div>
                        <div class="quote-info-note" style="margin-top:9px"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>Your quotation applies to the products listed in this RFQ.</div>
                      </div>
                    </div>


                    <div class="quote-card quote-attachments-card">
                      <div class="quote-card-head no-border"><div class="quote-card-title"><span class="section-num">3</span>Product files <span class="optional-caption">Optional · shared limit of 10 files</span></div></div>
                      <div class="quote-card-body tight">
                        <p class="upload-helper">Attach any specifications, images or supporting documents. All uploads stay private and are included when you submit.</p>
                        @include('portal.quotation.components.uploader', ['kind' => 'product'])
                        <div class="q-field" style="margin-top:12px"><label for="qDocumentNotes">Product document notes (optional)</label><textarea id="qDocumentNotes" placeholder="Anything the buyer should know about these files..."></textarea></div>
                        <div class="q-field" style="margin-top:11px"><label>Additional supporting information (optional)</label><div class="optional-support-row" id="quoteSupportingOptions"><label class="support-check"><input type="checkbox" value="Product certification"> Product certification</label><label class="support-check"><input type="checkbox" value="Factory audit report"> Factory audit report</label><label class="support-check"><input type="checkbox" value="Sample images"> Sample images</label></div></div>
                      </div>
                    </div>

                    <div class="quote-bottom-actions">
                      <button class="btn btn-outline-teal" type="button" data-portal-back-rfq>← Back to request</button>
                      <div class="right"><button class="btn btn-outline-teal" type="button" data-save-quote>▣ Save draft</button><button class="btn btn-primary" type="button" data-next-quote="2">Continue to pricing <span>›</span></button></div>
                    </div>
                  </section>
