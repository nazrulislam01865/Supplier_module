'use strict';

    // Server-scoped RFQ projections; never include another supplier company.
    const quotations = window.supplierPortalConfig.quotations;
    window.supplierDashboardToast = showToast;
    window.updateSupplierRfqList = function (latest) {
      const openId = window.currentQuotation?.id;
      quotations.splice(0, quotations.length, ...latest);
      updateCounts(); renderQuotations();
      // Updating the RFQ list must also refresh an already open detail view.
      // Otherwise the detail page keeps its old object (and shows zero files)
      // even after FlowTracker has delivered the authoritative document IDs.
      if (openId && !document.getElementById('view-rfq')?.classList.contains('hidden')
        && document.getElementById('supplierInquiryTaskCard')?.getAttribute('aria-busy') !== 'true') {
        const fresh = quotations.find(row => row.id === openId);
        if (fresh) {
          window.currentQuotation = fresh;
          renderRfqDetails(fresh);
        }
      }
    };
    const supplierBasePath = (document.querySelector('meta[name="supplier-base-path"]')?.content || '').replace(/\/$/, '');
    const apiBase = `${supplierBasePath}/api/v1`;
    const supplierLoginUrl = `${supplierBasePath}/login`;

    const icons = {
      product: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 9c4-2 10-2 14 0v7c-4 2-10 2-14 0V9Z"/><path d="M5 11 2 9M19 11l3-2M8 11h8M8 14h8"/></svg>`,
      bag: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 8h12l1 12H5L6 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg>`,
      bottle: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6v4l2 3v10H7V10l2-3V3Z"/><path d="M9 7h6"/></svg>`
    };

    const el = (id) => document.getElementById(id);

    function showToast(text) {
      el('toastText').textContent = text;
      el('toast').classList.remove('hidden');
      clearTimeout(window.__toastTimer);
      window.__toastTimer = setTimeout(() => el('toast').classList.add('hidden'), 2600);
    }

    function switchView(name) {
      document.querySelectorAll('.app-view').forEach(v => v.classList.add('hidden'));
      const target = el(`view-${name}`);
      if (target) target.classList.remove('hidden');
      document.querySelectorAll('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.view === name));
      if (['rfq','quote-workspace'].includes(name)) document.querySelectorAll('.nav-item').forEach(n => n.classList.toggle('active', n.dataset.view === 'quotations'));
      document.body.classList.toggle('quote-focus', name === 'quote-workspace');
      el('sidebar').classList.remove('open');
      if(name==='company-documents') void loadCompanyDocuments();
      if (name === 'dashboard' || name === 'notifications') window.refreshSupplierDashboard?.();
      if (name === 'notifications') void window.loadSupplierNotificationCenter?.();
      window.scrollTo({top:0, behavior:'smooth'});
    }

    function statusBadge(status, syncStatus = null, syncAttempts = 0) {
      if (status === 'submitted' && syncStatus === 'sending') return '<span class="badge badge-amber">Sending to buyer</span>';
      if (status === 'submitted' && syncStatus === 'pending' && Number(syncAttempts) > 0) return '<span class="badge badge-amber">Delivery retry scheduled</span>';
      if (status === 'submitted' && syncStatus === 'pending') return '<span class="badge badge-amber">Awaiting buyer delivery</span>';
      if (status === 'open') return '<span class="badge badge-teal">Open</span>';
      if (status === 'draft') return '<span class="badge badge-amber">Draft</span>';
      if (status === 'submitted') return '<span class="badge badge-blue">Submitted</span>';
      return '<span class="badge badge-gray">Closed</span>';
    }

    function productIcon(index) {
      return index % 3 === 1 ? icons.bag : index % 3 === 2 ? icons.bottle : icons.product;
    }

    function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    function renderQuotations() {
      const activeStatus = document.querySelector('#quoteTabs .tab.active')?.dataset.status || 'all';
      const search = el('quoteSearch').value.trim().toLowerCase();
      const rows = quotations.filter(q => (activeStatus === 'all' || q.status === activeStatus) && `${escapeHtml(q.product)} ${escapeHtml(q.ref)} ${escapeHtml(q.code)} ${escapeHtml(q.buyer)}`.toLowerCase().includes(search));
      el('quoteTableBody').innerHTML = rows.length ? rows.map((q,index) => `
        <tr>
          <td><div class="table-product"><div class="product-thumb ${index%3===1?'alt':index%3===2?'dark':''}">${productIcon(index)}</div><div><strong>${escapeHtml(q.product)}</strong><span>${escapeHtml(q.ref)}</span></div></div></td>
          <td>${escapeHtml(q.buyer)}</td><td>${escapeHtml(q.qty)}</td><td class="due ${q.urgent?'urgent':''}">${escapeHtml(q.dueShort)}${q.urgent?' · Due soon':''}</td><td>${statusBadge(q.status, q.syncStatus, q.syncAttempts)}</td><td>${escapeHtml(q.updated)}</td>
          <td><div class="row-actions"><button class="btn ${q.status==='open'?'btn-primary':'btn-outline-teal'} btn-sm" data-open-rfq="${Number(q.id)}">${q.status==='open'?'Review':q.status==='draft'?'Continue':'View'}</button></div></td>
        </tr>`).join('') : `<tr><td colspan="7"><div class="empty-state" style="padding:34px"><h3>No quotations found</h3><p>Try a different status or search term.</p></div></td></tr>`;
    }

    window.openRfq = function(id) {
      const q = quotations.find(x => x.id === id);
      if (!q) {
        void requestApi('/dashboard/rfqs/'+id).then(result => {
          if (result.rfq && !quotations.some(item => item.id === id)) quotations.push(result.rfq);
          if (quotations.some(item => item.id === id)) window.openRfq(id);
        }).catch(error => showToast(error.message));
        return;
      }
      window.currentQuotation = q;
      el('supplierInquiryTaskFile').value = '';
      supplierTaskFeedback();
      // Never carry another RFQ's unsaved contact details or file list into this request.
      ['qContact','qEmail','qPhone'].forEach(id => { const input=el(id); if (input) input.value=input.defaultValue; });
      el('declarationAccuracy').checked=false;el('declarationAuthority').checked=false;
      renderUploadedFiles([]);
      ['productUploadFeedback','priceUploadFeedback'].forEach(id => { if(el(id)) el(id).textContent = ''; });
      updateSelectedRfq(q);
      syncCarriedDetails();
      const sequence = ++quotationLoadSequence;
      quotationLoading = true;
      el('startQuoteBtn').disabled = true;
      el('startQuoteBtn').textContent = 'Loading quotation...';
      el('savedText').textContent = 'Loading saved quotation...';
      void loadQuotation(q.id).then(loaded => {
        if (sequence !== quotationLoadSequence || window.currentQuotation?.id !== q.id) return;
        quotationLoading = false;
        el('startQuoteBtn').disabled = !loaded || q.status === 'declined';
        el('startQuoteBtn').textContent = !loaded ? 'Unable to load quotation' : q.status === 'draft' ? 'Continue quotation' : q.status === 'submitted' ? 'View submitted quotation' : 'Start quotation';
      });
      el('rfqTitle').textContent = q.product;
      el('rfqRef').textContent = `${q.ref} · Requested by ${q.buyer}`;
      el('rfqQty').textContent = q.qty;
      el('rfqDue').textContent = q.due;
      el('rfqProductName').textContent = q.product;
      el('rfqProductCode').textContent = `${q.code || q.inquiryRef || q.ref} · Requested ${q.qty}`;
      renderRfqDetails(q);
      renderItemPrices(q);
      el('summaryProductName').textContent = q.product;
      el('summaryQty').textContent = q.qty;
      el('summaryDue').textContent = q.due;
      el('rfqStatusBadge').className = 'badge ' + (q.status==='open'?'badge-teal':q.status==='draft'?'badge-amber':'badge-blue');
      el('rfqStatusBadge').textContent = q.status.charAt(0).toUpperCase()+q.status.slice(1);
      el('summaryStatusText').textContent = q.status === 'draft' ? 'Draft quotation in progress' : q.status === 'submitted' ? 'Quotation submitted' : 'No quotation submitted yet';
      switchView('rfq');
    }

    // Drawer filters work on the existing 20-item notification preview, without fetching again.
    const notificationPanel = el('notificationPanel');
    const notificationList = el('notificationDrawerList');
    let previousNotificationFocus = null;
    let notificationStateFilter = 'all';
    function filterNotificationDrawer() {
      const type = el('notificationDrawerTypeFilter').value;
      const rows = notificationList.querySelectorAll('[data-notice-type]');
      let showing = 0;
      rows.forEach(row => {
        const matchesType = type === 'all' || row.dataset.noticeType === type;
        const matchesUnread = notificationStateFilter === 'all' || row.dataset.noticeUnread === '1';
        const visible = matchesType && matchesUnread;
        row.classList.toggle('hidden', !visible);
        if (visible) showing++;
      });
      const hasResults = showing > 0;
      const hasNoNotifications = Boolean(notificationList.querySelector('.notice-empty-base'));
      el('notificationDrawerEmptyFilter').classList.toggle('hidden', hasResults || hasNoNotifications);
      el('notificationDrawerShowing').textContent = showing === 1 ? '1 recent update' : `${showing} recent updates`;
    }
    window.updateSupplierDrawerFilters = filterNotificationDrawer;
    function closeNotificationDrawer() {
      if (notificationPanel.classList.contains('hidden')) return;
      notificationPanel.classList.add('hidden');
      document.body.classList.remove('supplier-notification-open');
      if (previousNotificationFocus?.isConnected) previousNotificationFocus.focus();
      previousNotificationFocus = null;
    }
    function openNotificationDrawer() {
      previousNotificationFocus = document.activeElement;
      notificationPanel.classList.remove('hidden');
      document.body.classList.add('supplier-notification-open');
      filterNotificationDrawer();
      el('closeNotifications').focus();
      window.refreshSupplierDashboard?.();
    }
    el('notificationBtn').addEventListener('click', openNotificationDrawer);
    el('closeNotifications').addEventListener('click', closeNotificationDrawer);
    notificationPanel.addEventListener('click', event => { if (event.target === notificationPanel) closeNotificationDrawer(); });
    notificationPanel.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); closeNotificationDrawer(); return; }
      if (event.key !== 'Tab') return;
      const controls = [...notificationPanel.querySelectorAll('button:not(:disabled):not(.hidden), a[href]:not(.hidden), select:not(:disabled)')]
        .filter(node => node.getClientRects().length);
      if (!controls.length) return;
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    notificationPanel.querySelectorAll('[data-notification-filter]').forEach(button => button.addEventListener('click', () => {
      notificationStateFilter = button.dataset.notificationFilter;
      notificationPanel.querySelectorAll('[data-notification-filter]').forEach(node => {
        const active = node === button;
        node.classList.toggle('is-active', active);
        node.setAttribute('aria-pressed', String(active));
      });
      filterNotificationDrawer();
    }));
    el('notificationDrawerTypeFilter').addEventListener('change', filterNotificationDrawer);
    el('notificationQuotesBtn').addEventListener('click', () => { closeNotificationDrawer(); switchView('notifications'); });

    el('accountBtn').addEventListener('click', () => el('accountModal').classList.remove('hidden'));
    document.querySelectorAll('[data-close-account]').forEach(b => b.addEventListener('click', () => el('accountModal').classList.add('hidden')));

    document.querySelectorAll('.nav-item[data-view]').forEach(btn => btn.addEventListener('click', () => switchView(btn.dataset.view)));
    el('mobileMenuBtn').addEventListener('click', () => el('sidebar').classList.toggle('open'));

    document.querySelectorAll('#quoteTabs .tab').forEach(tab => tab.addEventListener('click', () => {
      document.querySelectorAll('#quoteTabs .tab').forEach(x => x.classList.remove('active'));
      tab.classList.add('active'); renderQuotations();
    }));
    el('quoteSearch').addEventListener('input', renderQuotations);

    el('backToQuotes').addEventListener('click', () => switchView('quotations'));
    el('backToRfq').addEventListener('click', () => switchView('rfq'));

    let currentQuoteStep = 1;
    let quotationLoadSequence = 0;
    let quotationLoading = false;

    // Single contact state for every quotation stage and the final API payload.
    // The data is only reset on selection of a different RFQ or loading its saved draft.
    let quotationContact = {contact:'', email:'', phone:''};
    function readQuotationContact() {
      quotationContact = {
        contact: el('qContact')?.value ?? '',
        email: el('qEmail')?.value ?? '',
        phone: el('qPhone')?.value ?? ''
      };
      return quotationContact;
    }
    function syncCarriedDetails() {
      const contact = readQuotationContact();
      const q = window.currentQuotation;
      const values = {
        pricingContextRefs: q ? `${q.inquiryRef || q.ref} / ${q.ref}` : '--',
        pricingContextContact: contact.contact.trim() || 'Not provided',
        pricingContextEmail: contact.email.trim() || 'Not provided',
        pricingContextPhone: contact.phone.trim() || 'Not provided',
        pricingContextDue: q?.due || '--',
      };
      for (const [id, value] of Object.entries(values)) {
        if (el(id)) el(id).textContent = value;
      }
    }
    ['qContact','qEmail','qPhone'].forEach(id => { el(id)?.addEventListener('input', syncCarriedDetails); el(id)?.addEventListener('change', syncCarriedDetails); });
    // Step transitions preserve browser state; drafts are stored only when Save draft is used.
    const quoteWorkspace = el('view-quote-workspace');
    const markUnsaved = (event) => {
      const field = event.target;
      if (!field.matches('input,select,textarea') || field.readOnly || field.type === 'file' || field.type === 'checkbox') return;
      if (window.currentQuotation && ['open','draft'].includes(window.currentQuotation.status)) el('savedText').textContent = 'Unsaved changes';
    };
    quoteWorkspace.addEventListener('input', markUnsaved);
    quoteWorkspace.addEventListener('change', markUnsaved);


    function money(value) {
      const n = Number(value || 0);
      return `${el('qCurrency')?.value || 'USD'} ${n.toFixed(2)}`;
    }

    function linePrices() {
      return Array.from(el('multiItemPrices')?.querySelectorAll('[data-rfq-line-id]') || []).map(row => ({
        item_id: Number(row.dataset.rfqLineId),
        unit_price: row.querySelector('[data-line-price]')?.value || '',
        moq: row.querySelector('[data-line-moq]')?.value || '',
      }));
    }
    function renderItemPrices(q, saved = []) {
      const multi = (q.items || []).length > 1;
      el('multiItemPrices').classList.toggle('hidden', !multi);
      el('singleUnitPriceField').classList.toggle('hidden', multi);
      el('singleMoqField').classList.toggle('hidden', multi);
      el('multiItemPrices').innerHTML = multi ? '<div class="uploaded-files-title">Price each requested product</div>' + q.items.map(item => {
        const found = saved.find(row => Number(row.item_id) === Number(item.id)) || {};
        return `<div class="rfq-price-line" data-rfq-line-id="${Number(item.id)}"><div><strong>${escapeHtml(item.product_name)}</strong><small>${escapeHtml(String(item.quantity))} ${escapeHtml(item.unit || 'units')}</small></div><label>Unit price <input data-line-price type="number" step="0.01" min="0.01" value="${escapeHtml(found.unit_price || '')}"></label><label>MOQ <input data-line-moq type="number" min="1" step="1" value="${escapeHtml(found.moq || '')}"></label></div>`;
      }).join('') : '';
      el('multiItemPrices').querySelectorAll('input').forEach(input => input.addEventListener('input', () => {
        updateQuoteTotals();
        input.classList.remove('quote-invalid'); input.removeAttribute('aria-invalid');
        input.closest('.rfq-price-line')?.querySelector('.quote-field-error')?.remove();
        el('view-quote-workspace').querySelectorAll('.quote-stage-error').forEach(node => node.classList.add('hidden'));
      }));
    }
    function getQuoteNumbers() {
      const qty = window.currentQuotation?.quantity || 0;
      const unit = Number(el('qUnitPrice')?.value || 0);
      const tooling = Number(el('qTooling')?.value || 0);
      const sample = Number(el('qSample')?.value || 0);
      const discount = Number(el('qDiscount')?.value || 0);
      const multi = (window.currentQuotation?.items || []).length > 1;
      const subtotal = multi ? linePrices().reduce((sum, row) => {
        const item = window.currentQuotation.items.find(x => Number(x.id) === row.item_id);
        return sum + Number(item?.quantity || 0) * Number(row.unit_price || 0);
      }, 0) : qty * unit;
      const other = Math.max(0, tooling - discount);
      const total = Math.max(0, subtotal + tooling + sample - discount);
      return { qty, unit, tooling, sample, discount, subtotal, other, total };
    }

    function updateQuoteTotals() {
      const n = getQuoteNumbers();
      if (el('qSubtotalTop')) el('qSubtotalTop').textContent = money(n.subtotal);
      if (el('sideSubtotal')) el('sideSubtotal').textContent = money(n.subtotal);
      if (el('sideSample')) el('sideSample').textContent = money(n.sample);
      if (el('sideOther')) el('sideOther').textContent = money(n.other);
      if (el('sideTotal')) el('sideTotal').textContent = money(n.total);
      if (el('reviewSubtotal')) el('reviewSubtotal').textContent = money(n.subtotal);
      if (el('reviewTooling')) el('reviewTooling').textContent = money(n.tooling);
      if (el('reviewSample')) el('reviewSample').textContent = money(n.sample);
      if (el('reviewDiscount')) el('reviewDiscount').textContent = money(n.discount);
      if (el('reviewTotal')) el('reviewTotal').textContent = money(n.total);
    }

    function renderReviewDocuments() {
      const rows = Array.from(document.querySelectorAll('#quoteUploadRows tr, #priceUploadRows tr')).filter(row => row.querySelector('a[href*="/download"]'));
      if (el('reviewDocCount')) el('reviewDocCount').textContent = rows.length;
      if (!el('reviewDocumentList')) return;
      el('reviewDocumentList').innerHTML = rows.map((row, index) => {
        const select = row.querySelector('select');
        const fileText = row.querySelector('.file-cell')?.textContent.trim() || `Quotation document ${index + 1}`;
        const size = row.children[2]?.textContent.trim() || '';
        const type = select?.value || row.children[0]?.textContent.trim() || 'Supporting document';
        const ext = (fileText.split('.').pop() || 'FILE').toUpperCase().slice(0,4);
        const xlsx = ext === 'XLSX' ? ' xlsx' : '';
        return `<div class="review-doc-row"><span class="mini-file-icon${xlsx}">${ext}</span><span>${escapeHtml(fileText)}</span><span>${escapeHtml(type)} · ${escapeHtml(size)}</span><span class="ready-label">● Ready</span><a class="table-action-link" href="${escapeHtml(row.querySelector('a[href*="/download"]')?.getAttribute('href') || '#')}" target="_blank" rel="noopener">Download</a></div>`;
      }).join('');
    }

    function renderReviewValues() {
      syncCarriedDetails();
      updateQuoteTotals();
      if (el('reviewContact')) el('reviewContact').textContent = quotationContact.contact || '--';
      if (el('reviewEmail')) el('reviewEmail').textContent = quotationContact.email || '--';
      if (el('reviewPhone')) el('reviewPhone').textContent = quotationContact.phone || '--';
      if (el('reviewCurrency')) el('reviewCurrency').textContent = el('qCurrency')?.value || 'USD';
      if (el('reviewUnitPrice')) el('reviewUnitPrice').textContent = (window.currentQuotation?.items || []).length > 1 ? 'Per product' : Number(el('qUnitPrice')?.value || 0).toFixed(2);
      if (el('reviewMoq')) el('reviewMoq').textContent = (window.currentQuotation?.items || []).length > 1 ? 'Per product' : Number(el('qMoq')?.value || 0).toLocaleString();
      if (el('reviewLinePrices')) el('reviewLinePrices').innerHTML = (window.currentQuotation?.items || []).length > 1
        ? linePrices().map(line => {
          const item = window.currentQuotation.items.find(x => Number(x.id) === line.item_id);
          return `<div class="rfq-summary-line"><strong>${escapeHtml(item?.product_name || 'Product')}</strong><span>${escapeHtml(item?.quantity || 0)} × ${money(Number(line.unit_price || 0))}, MOQ ${escapeHtml(line.moq || '—')}</span></div>`;
        }).join('') : '';
      if (el('reviewTax')) el('reviewTax').textContent = el('qTax')?.value || 'Excluded';
      if (el('reviewProductionLead')) el('reviewProductionLead').textContent = `${el('qProductionLead')?.value || '--'} days`;
      if (el('reviewSampleLead')) el('reviewSampleLead').textContent = `${el('qSampleLead')?.value || '--'} days`;
      if (el('reviewIncoterm')) el('reviewIncoterm').textContent = el('qIncoterm')?.value || 'FOB';
      if (el('reviewShippingPort')) el('reviewShippingPort').textContent = el('qShippingPort')?.value || '--';
      if (el('reviewDeliveryDate')) el('reviewDeliveryDate').textContent = el('qDeliveryDate')?.value || '--';
      if (el('reviewValidity')) el('reviewValidity').textContent = `${el('qValidity')?.value || '--'} days`;
      if (el('reviewCompliance')) el('reviewCompliance').textContent = el('qCompliance')?.value || '--';
      if (el('reviewSupplierNotes')) el('reviewSupplierNotes').textContent = el('qSupplierNotes')?.value || '';
      if (el('declarationValidity')) el('declarationValidity').textContent = el('qValidity')?.value || '--';
      renderReviewDocuments();
      if (el('reviewDocumentNotes')) el('reviewDocumentNotes').textContent = el('qDocumentNotes')?.value.trim() || '';
    }

    function summaryChecksForStep(step) {
      const docs = Number(el('uploadedFileCount')?.textContent || 0) + Number(el('priceUploadedFileCount')?.textContent || 0);
      const checks = [
        ['Contact details', step > 1], ['Pricing and delivery', step > 2], [`${docs} file${docs === 1 ? '' : 's'} attached (optional)`, docs > 0], ['Final confirmation', step === 3 && el('declarationAccuracy')?.checked && el('declarationAuthority')?.checked]
      ];
      el('quoteSummaryChecks').innerHTML = checks.map(([label, done]) => `<div class="summary-check ${done ? 'done' : ''}"><span class="summary-check-dot">${done ? '✓' : ''}</span><span>${label}</span></div>`).join('');
    }

    function clearStepErrors() {
      document.querySelectorAll('.quote-stage-error').forEach(node => { node.classList.add('hidden'); node.textContent=''; });
      document.querySelectorAll('.quote-invalid').forEach(node => { node.classList.remove('quote-invalid'); node.removeAttribute('aria-invalid'); });
      document.querySelectorAll('.quote-field-error').forEach(node => node.remove());
    }
    function stepError(step, input, message) {
      const summary=el('view-quote-workspace').querySelector(`[data-quote-errors="${step}"]`);
      summary.textContent=message;
      summary.classList.remove('hidden');
      if (input) {
        input.classList.add('quote-invalid');input.setAttribute('aria-invalid','true');
        const field=input.closest('.q-field') || input.closest('.rfq-price-line');
        if (field) {const hint=document.createElement('div');hint.className='quote-field-error';hint.textContent=message;field.appendChild(hint);}
        input.focus({preventScroll:true});
      }
      summary.scrollIntoView({block:'center',behavior:'smooth'});
      return false;
    }
    document.querySelectorAll('#view-quote-workspace .q-field input, #view-quote-workspace .q-field select, #view-quote-workspace .q-field textarea, #multiItemPrices input').forEach(field => {
      const clearCurrentError = () => {
        if (!field.classList.contains('quote-invalid')) return;
        field.classList.remove('quote-invalid');
        field.removeAttribute('aria-invalid');
        (field.closest('.q-field') || field.closest('.rfq-price-line'))?.querySelector('.quote-field-error')?.remove();
        el('view-quote-workspace').querySelectorAll('.quote-stage-error').forEach(node => node.classList.add('hidden'));
      };
      field.addEventListener('input', clearCurrentError);
      field.addEventListener('change', clearCurrentError);
    });
    function quoteStageValid(step) {
      clearStepErrors();
      const check=(id,valid,message) => valid ? true : stepError(step,el(id),message);
      const nonempty=(id) => Boolean(el(id)?.value.trim());
      if (step===1) {
        if (!check('qContact',nonempty('qContact'),'Enter the supplier contact person.'))return false;
        if (!check('qEmail',el('qEmail').validity.valid && nonempty('qEmail'),'Enter a valid contact email.'))return false;
        if (!check('qPhone',nonempty('qPhone'),'Enter a contact phone number.'))return false;
        return true;
      }
      if (step===2) {
        const priceValid=(val) => /^(?:\d+)(?:\.\d{1,2})?$/.test(String(val).trim()) && Number(val)>0 && Number(val)<=100000000;
        const moqValid=(val)=> /^\d+$/.test(String(val).trim()) && Number(val)>=1 && Number(val)<=100000000;
        const multi=(window.currentQuotation?.items||[]).length > 1;
        if(multi) {
          for(const row of el('multiItemPrices').querySelectorAll('[data-rfq-line-id]')) {
            const price=row.querySelector('[data-line-price]'),moq=row.querySelector('[data-line-moq]');
            if(!priceValid(price.value))return stepError(step,price,'Enter a valid unit price (up to 2 decimals) for every product.');
            if(!moqValid(moq.value))return stepError(step,moq,'Enter an integer MOQ of at least 1 for every product.');
          }
        } else {
          if (!check('qUnitPrice',priceValid(el('qUnitPrice').value),'Unit price must be greater than zero (maximum 2 decimal places).')) return false;
          if (!check('qMoq',moqValid(el('qMoq').value),'MOQ must be a positive whole number.'))return false;
        }
        for(const [id,label] of [['qTooling','Tooling cost'],['qSample','Sample cost'],['qDiscount','Discount']]){
          const val=el(id).value;
          if(!check(id,/^\d+(?:\.\d{1,2})?$/.test(val) && Number(val)<=100000000,`${label} must be zero or a positive amount with up to 2 decimal places.`))return false;
        }
        const n=getQuoteNumbers();
        if (n.discount>n.subtotal+n.tooling+n.sample)return stepError(step,el('qDiscount'),'Discount cannot exceed the quotation amount.');
        for(const [id,name,max] of [['qProductionLead','Production lead time',3650],['qSampleLead','Sample lead time',3650],['qValidity','Quotation validity',365]]){
          const value=el(id).value;
          if (!check(id,/^\d+$/.test(value) && Number(value)>=1 && Number(value)<=max,`${name} must be between 1 and ${max} days.`))return false;
        }
        if(!check('qShippingPort',nonempty('qShippingPort'),'Enter a shipping port.'))return false;
        if(!check('qDeliveryDate',nonempty('qDeliveryDate') && el('qDeliveryDate').validity.valid,'Choose an estimated delivery date.'))return false;
        if (el('qCompliance').value !== 'Yes, fully compliant' && !nonempty('qDeviations'))return stepError(step,el('qDeviations'),'Describe the deviations or the alternative you propose.');
        return true;
      }
      return true;
    }
    function advanceQuoteStep(next) {
      if (uploadingFiles) { showToast('Wait until file uploads finish.'); return; }
      if (next > currentQuoteStep) {
        for(let step=1;step<next;step++) {
          if (!quoteStageValid(step)) {goQuoteStep(step);return;}
        }
      } else clearStepErrors();
      goQuoteStep(next);
    }

    function goQuoteStep(step) {
      step = Math.max(1, Math.min(3, Number(step) || 1));
      currentQuoteStep = step;
      syncCarriedDetails();
      document.querySelectorAll('[data-quote-panel]').forEach(panel => panel.classList.toggle('active', Number(panel.dataset.quotePanel) === step));
      document.querySelectorAll('[data-quote-step]').forEach(btn => {
        const n = Number(btn.dataset.quoteStep);
        btn.classList.toggle('active', n === step);
        btn.classList.toggle('done', n < step);
        const dot = btn.querySelector('.quote-progress-dot');
        if (dot) dot.textContent = n < step ? '✓' : String(n);
      });
      const labels = {1:'Continue to pricing',2:'Continue to review',3:'Submit quotation'};
      el('quoteSidePrimary').innerHTML = `${labels[step]} <span style="margin-left:auto">›</span>`;
      el('secureSubmissionLabel').style.display = step === 3 ? 'flex' : 'none';
      summaryChecksForStep(step);
      if (step === 3) renderReviewValues();
      window.scrollTo({top:0, behavior:'smooth'});
    }

    async function saveQuotationDraft() { await persistQuotation(false); }

    async function submitQuotation() { await persistQuotation(true); }

    el('startQuoteBtn').addEventListener('click', () => {
      if (quotationLoading) return showToast('Loading the saved quotation. Please wait.');
      const readOnly = !['open','draft'].includes(window.currentQuotation?.status);
      document.querySelectorAll('#view-quote-workspace input, #view-quote-workspace select, #view-quote-workspace textarea').forEach(field => {
        if (!field.readOnly) field.disabled = readOnly;
      });
      document.querySelectorAll('#view-quote-workspace [data-save-quote], #view-quote-workspace [data-submit-quote], #view-quote-workspace [data-next-quote], #view-quote-workspace #quoteSidePrimary, #view-quote-workspace #browseQuoteFiles, #view-quote-workspace #browsePriceFiles').forEach(button => button.disabled = readOnly);
      switchView('quote-workspace');
      el('quoteSidePrimary').disabled=readOnly;
      clearStepErrors();
      el('declarationAccuracy').checked=false;el('declarationAuthority').checked=false;
      if(el('quoteDraftBadge'))el('quoteDraftBadge').textContent=readOnly?'Submitted':'Draft';
      goQuoteStep(1);
      updateQuoteTotals();
    });

    document.querySelectorAll('[data-quote-step]').forEach(btn => btn.addEventListener('click', () => advanceQuoteStep(Number(btn.dataset.quoteStep))));
    document.querySelectorAll('[data-next-quote]').forEach(btn => btn.addEventListener('click', () => advanceQuoteStep(Number(btn.dataset.nextQuote))));
    document.querySelectorAll('[data-save-quote]').forEach(btn => btn.addEventListener('click', saveQuotationDraft));
    document.querySelectorAll('[data-submit-quote]').forEach(btn => btn.addEventListener('click', submitQuotation));

    el('quoteSidePrimary').addEventListener('click', () => {
      if (currentQuoteStep < 3) advanceQuoteStep(currentQuoteStep + 1); else submitQuotation();
    });
    el('quoteDeclineBtn').addEventListener('click', () => el('declineModal').classList.remove('hidden'));

    ['qUnitPrice','qTooling','qSample','qDiscount'].forEach(id => el(id)?.addEventListener('input', updateQuoteTotals));
    el('qCurrency')?.addEventListener('change', updateQuoteTotals);

    let uploadingFiles = false;
    const allowedFileExtensions = new Set(['pdf','doc','docx','xls','xlsx','csv','txt','jpg','jpeg','png','webp','gif','zip']);
    let quoteUploadedFiles = [];

    function renderUploadedFiles(documents) {
      quoteUploadedFiles = documents;
      for (const [type, bodyId] of [['product','quoteUploadRows'],['pricing','priceUploadRows']]) {
        const rows = documents.filter(doc => (doc.document_type === 'Price breakdown' ? 'pricing' : 'product') === type);
        el(bodyId).innerHTML = rows.map(doc => {
          const size = (Number(doc.size) / 1024 / 1024).toFixed(2) + ' MB';
          const actions = `<a class="table-action-link" href="${apiBase}/documents/${Number(doc.id)}/download">Download</a>`+
            (['open','draft'].includes(window.currentQuotation?.status) ? ` <button class="table-action-link remove" data-remove-upload="${Number(doc.id)}" type="button">Remove</button>` : '');
          return `<tr><td>${escapeHtml(doc.document_type)}</td><td><div class="file-cell">${escapeHtml(doc.original_name)}</div></td><td>${size}</td><td><span class="upload-status">Ready</span></td><td>${actions}</td></tr>`;
        }).join('') || '<tr><td colspan="5" class="attachment-empty">No files added yet</td></tr>';
      }
      wireUploadRemoveButtons();
      // Empty-state rows should not count as attachments.
      el('uploadedFileCount').textContent = documents.filter(d=>d.document_type !== 'Price breakdown').length;
      el('priceUploadedFileCount').textContent = documents.filter(d=>d.document_type === 'Price breakdown').length;
      if (el('pricingContextDocCount')) el('pricingContextDocCount').textContent = String(documents.filter(d=>d.document_type !== 'Price breakdown').length);
      summaryChecksForStep(currentQuoteStep);
      renderReviewDocuments();
    }

    function wireUploadRemoveButtons() {
      document.querySelectorAll('#quoteUploadRows [data-remove-upload], #priceUploadRows [data-remove-upload]').forEach(button => {
        button.addEventListener('click', async () => {
          if (uploadingFiles) return showToast('Wait until the upload finishes.');
          button.disabled = true;
          try {
            await requestApi('/documents/'+button.dataset.removeUpload,'DELETE');
            await loadQuotation(window.currentQuotation.id, { documentsOnly: true });
            showToast('File removed.');
          } catch (error) { button.disabled = false; showToast(error.message); }
        });
      });
    }

    async function uploadQuotationFiles(files, kind) {
      const q = window.currentQuotation;
      if (!q || !['open','draft'].includes(q.status) || uploadingFiles) return;
      const feedback = el(kind === 'pricing' ? 'priceUploadFeedback' : 'productUploadFeedback');
      if (!files.length) return;
      if (files.length + quoteUploadedFiles.length > 10) {
        showToast('You can attach at most 10 files to each quotation.'); return;
      }
      for (const file of files) {
        const extension = file.name.split('.').pop().toLowerCase();
        if (!file.size || file.size > 20 * 1024 * 1024 || !allowedFileExtensions.has(extension)) {
          showToast(`${file.name}: Unsupported file type or exceeds 20 MB.`); return;
        }
      }
      uploadingFiles = true;
      document.querySelectorAll('#browseQuoteFiles, #browsePriceFiles, #view-quote-workspace [data-next-quote], #quoteSidePrimary, [data-submit-quote]').forEach(b=>b.disabled=true);
      let success = 0;
      try {
        for (const file of files) {
          feedback.textContent = `Uploading ${success+1} of ${files.length}: ${file.name}`;
          const body = new FormData();
          body.append('file',file);
          body.append('document_type',kind === 'pricing' ? 'Price breakdown' : el('quoteDocumentType').value);
          try { await requestApi('/rfqs/'+q.id+'/documents','POST',body); success++; }
          catch (error) { showToast(`${file.name}: ${error.message}`); }
        }
      } finally {
        uploadingFiles = false;
        document.querySelectorAll('#browseQuoteFiles, #browsePriceFiles, #view-quote-workspace [data-next-quote], #quoteSidePrimary, [data-submit-quote]').forEach(b=>b.disabled=!['open','draft'].includes(window.currentQuotation?.status));
        feedback.textContent = `${success} of ${files.length} file${files.length === 1 ? '' : 's'} uploaded successfully.`;
        await loadQuotation(q.id, { documentsOnly: true });
      }
    }

    function setupQuoteDropzone(kind, dropId, inputId, browseId) {
      const zone=el(dropId), input=el(inputId);
      el(browseId).addEventListener('click', () => input.click());
      zone.addEventListener('click', e => { if (!e.target.closest('button,select,input,label')) input.click(); });
      zone.addEventListener('keydown', e => { if ((e.key === 'Enter' || e.key === ' ') && e.target === zone) { e.preventDefault(); input.click(); } });
      zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag-over');});
      zone.addEventListener('dragleave',()=>zone.classList.remove('drag-over'));
      zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('drag-over');void uploadQuotationFiles(Array.from(e.dataTransfer?.files || []),kind);});
      input.addEventListener('change',e=>{const files=Array.from(e.target.files||[]);e.target.value='';void uploadQuotationFiles(files,kind);});
    }
    setupQuoteDropzone('product','productQuoteDropzone','quoteFileInput','browseQuoteFiles');
    setupQuoteDropzone('pricing','priceQuoteDropzone','priceFileInput','browsePriceFiles');
    renderUploadedFiles([]);

    el('successBackToQuotes').addEventListener('click', () => {
      el('quoteSuccessModal').classList.add('hidden');
      switchView('quotations');
      showToast('Quotation saved. Check delivery status in the RFQ details.');
    });
    el('quoteSuccessModal').addEventListener('click', (e) => { if (e.target === el('quoteSuccessModal')) el('quoteSuccessModal').classList.add('hidden'); });

    el('declineBtn').addEventListener('click', () => el('declineModal').classList.remove('hidden'));
    document.querySelectorAll('[data-close-decline]').forEach(b => b.addEventListener('click', () => el('declineModal').classList.add('hidden')));
    el('confirmDeclineBtn').addEventListener('click', async () => { const q=window.currentQuotation; if (!q) return; const modal=el('declineModal'); try { await requestApi('/rfqs/'+q.id+'/decline','POST',{reason:modal.querySelector('select').value,note:modal.querySelector('textarea').value}); q.status='declined'; renderQuotations(); updateCounts(); modal.classList.add('hidden'); switchView('quotations'); showToast('Decline recorded locally.'); } catch(e) { showToast(e.message); } });

    el('saveProfileBtn').addEventListener('click', saveProfile);




    const portalConfig = window.supplierPortalConfig;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    async function requestApi(path, method='GET', data=undefined) {
      const isForm = data instanceof FormData;
      let response;
      try {
        response = await fetch(apiBase+path, {
          method, credentials:'same-origin',
          headers:{'Accept':'application/json', 'X-CSRF-TOKEN':csrf, ...(isForm ? {} : {'Content-Type':'application/json'})},
          body: data===undefined ? undefined : (isForm ? data : JSON.stringify(data)),
        });
      } catch(e) { throw new Error('Network unavailable. Check your connection and try again.'); }
      if (response.status === 401 || response.status === 419) { window.location.assign(supplierLoginUrl); throw new Error('Session expired.'); }
      const result = await response.json().catch(()=>({}));
      if (!response.ok) {
        if (response.status === 429 && path.includes('/supplier-task')) {
          const retry = Number(response.headers.get('Retry-After'));
          const wait = Number.isFinite(retry) && retry > 0 ? ` Try again in ${Math.ceil(retry)} seconds.` : ' Please wait a moment before retrying.';
          throw new Error((result.message && result.message !== 'Too Many Attempts.'
            ? result.message : 'Too many task requests.') + wait);
        }
        const detail = Object.values(result.errors||{}).flat()[0];
        throw new Error(detail || result.message || `Unable to complete this request (${response.status}).`);
      }
      return result;
    }

    let supplierTaskSubmitting = false;
    function updateSupplierTaskControls() {
      const q = window.currentQuotation;
      const task = q?.supplierTask;
      const verified = Array.isArray(task?.uploaded_files) && task.uploaded_files.some(file =>
        file && typeof file === 'object' && Number.isSafeInteger(Number(file.id)) && Number(file.id) > 0);
      const canUpload = !!task && (!task.completed || !verified) && ['open','draft','submitted'].includes(q.status);
      const canComplete = !!task && !task.completed && ['open','draft','submitted'].includes(q.status);
      const selected = el('supplierInquiryTaskFile').files.length > 0;
      const upload = el('supplierInquiryTaskUpload');
      const complete = el('supplierInquiryTaskComplete');
      el('supplierInquiryTaskFile').disabled = !canUpload || supplierTaskSubmitting;
      upload.disabled = !canUpload || supplierTaskSubmitting || !selected;
      complete.disabled = !canComplete || supplierTaskSubmitting;
      upload.textContent = supplierTaskSubmitting ? 'Please wait...' : 'Upload to FlowTracker';
      complete.textContent = supplierTaskSubmitting ? 'Please wait...' : 'Complete task';
      complete.classList.toggle('hidden', !canComplete);
      el('supplierInquiryTaskCard').setAttribute('aria-busy', String(supplierTaskSubmitting));
    }
    function supplierTaskFeedback(message = '', isError = false) {
      const box = el('supplierInquiryTaskFeedback');
      box.textContent = message;
      box.classList.toggle('hidden', !message);
      box.classList.toggle('is-error', isError);
    }
    function renderRfqDetails(q) {
      const items = q.items || [];
      el('rfqDeliveryDate').textContent = q.deliveryDate || 'Not specified';
      el('rfqProductItems').innerHTML = items.map(item =>
        `<div class="spec"><small>${escapeHtml(item.product_code || item.category || 'Product')}</small><strong>${escapeHtml(item.product_name)} — ${escapeHtml(item.quantity)} ${escapeHtml(item.unit || 'units')}</strong>${item.notes ? `<div class="muted">${escapeHtml(plainRfqText(item.notes))}</div>` : ''}</div>`
      ).join('') || '<span>No product detail provided.</span>';
      const task = q.supplierTask;
      const taskCard = el('supplierInquiryTaskCard');
      taskCard.classList.toggle('hidden', !task);
      if (task) {
        const status = el('supplierInquiryTaskStatus');
        status.textContent = task.completed ? 'Completed' : (task.status || 'Not Started');
        status.className = 'badge supplier-task-status ' + (task.completed ? 'badge-teal' : 'badge-gray');
        el('supplierInquiryTaskTitle').textContent = task.title || 'Supplier task';
        el('supplierInquiryTaskDue').textContent = task.due_date ? 'Due ' + task.due_date : 'No due date set';
        el('supplierInquiryTaskInstructions').textContent = plainRfqText(task.instructions || 'Provide costing information for this request.');
        const ownFiles = Array.isArray(task.uploaded_files) ? task.uploaded_files : [];
        const verifiedFiles = ownFiles.filter(file => file && typeof file === 'object'
          && Number.isSafeInteger(Number(file.id)) && Number(file.id) > 0);
        const legacyFiles = ownFiles.filter(file => !verifiedFiles.includes(file));
        const list = el('supplierInquiryTaskFiles');
        list.replaceChildren();
        verifiedFiles.forEach(file => {
          const item = document.createElement('li');
          item.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3h7l5 5v13H7zM14 3v6h5M10 14h6M10 17h6"/></svg>';
          const name = document.createElement('span');
          name.textContent = typeof file === 'object' && file !== null ? String(file.name || 'Submitted file') : String(file);
          item.append(name);
          const links = document.createElement('span');
          links.className = 'supplier-task-file-links';
          const base = apiBase + '/rfqs/' + Number(q.id) + '/supplier-task/documents/' + Number(file.id);
          if (['application/pdf','image/jpeg','image/png','image/webp','image/gif'].includes(String(file.mime_type).toLowerCase())) {
            const view = document.createElement('a');
            view.href = base + '?view=1';
            view.target = '_blank';
            view.rel = 'noopener noreferrer';
            view.textContent = 'View';
            links.append(view);
          }
          const download = document.createElement('a');
          download.href = base;
          download.textContent = 'Download';
          links.append(download);
          item.append(links);
          list.append(item);
        });
        if (!verifiedFiles.length) {
          const item = document.createElement('li');
          item.className = 'supplier-task-empty';
          item.textContent = 'No file confirmed in FlowTracker yet.';
          list.append(item);
        }
        // A filename saved by an older portal is NOT evidence that the file
        // reached FlowTracker. Never make up a download URL for it.
        legacyFiles.forEach(file => {
          const item = document.createElement('li');
          item.className = 'supplier-task-legacy-file';
          item.textContent = (typeof file === 'string' ? file : String(file?.name || 'Older file')) + ' — not verified in FlowTracker';
          list.append(item);
        });
        el('supplierInquiryTaskFileCount').textContent = verifiedFiles.length + (verifiedFiles.length === 1 ? ' verified file' : ' verified files');
        const recovery = !!task.completed && verifiedFiles.length === 0;
        el('supplierInquiryTaskFileHint').textContent = recovery
          ? 'This task is complete, but no supplier file is verified in FlowTracker. Select the original file and upload it here. The task will remain complete.'
          : (!task.completed && task.requires_submission ? 'A supporting file is required before you can complete this task.' : '');
        const canUpload = (!task.completed || recovery) && ['open','draft','submitted'].includes(q.status);
        el('supplierInquiryTaskActions').classList.toggle('hidden', !canUpload);
        el('supplierInquiryTaskCompleted').classList.toggle('hidden', !task.completed);
        el('supplierInquiryTaskCompletionText').textContent = recovery
          ? 'Task completed. A supporting file still needs to be verified in FlowTracker.'
          : 'Task completed in FlowTracker. No further action is needed.';
        if (!task.completed && !canUpload) supplierTaskFeedback('Task actions are unavailable for this RFQ.');
        updateSupplierTaskControls();
      }
      const docs = q.documents || [];
      el('rfqBuyerDocumentCount').textContent = `${docs.length} ${docs.length === 1 ? 'file' : 'files'}`;
      el('rfqBuyerDocuments').innerHTML = docs.length ? docs.map(doc =>
        `<div class="doc-row"><span class="mini-file-icon">DOC</span><div><strong>${escapeHtml(doc.name)}</strong><small>${Math.round(Number(doc.size || 0) / 1024)} KB</small></div><a class="btn btn-outline-teal btn-sm" href="${apiBase}/rfqs/${Number(q.id)}/buyer-documents/${Number(doc.id)}">Download</a></div>`
      ).join('') : '<div class="empty-state"><p>No reference documents have been shared for this RFQ.</p></div>';
    }

    async function submitSupplierInquiryTask(action) {
      const q = window.currentQuotation;
      const task = q?.supplierTask;
      if (!q || !task || (task.completed && action !== 'upload') || supplierTaskSubmitting || !['open','draft','submitted'].includes(q.status)) return;
      const file = el('supplierInquiryTaskFile').files[0];
      if (action === 'upload' && !file) return supplierTaskFeedback('Choose a file before uploading.', true);
      if (file && action === 'upload') {
        const extension = file.name.split('.').pop().toLowerCase();
        if (!file.size || file.size > 20 * 1024 * 1024 || !allowedFileExtensions.has(extension))
          return supplierTaskFeedback('Unsupported file format or size. Select a supported file up to 20 MB.', true);
      }
      supplierTaskSubmitting = true;
      updateSupplierTaskControls();
      supplierTaskFeedback(action === 'upload' ? 'Uploading securely to FlowTracker...' : 'Confirming completion with FlowTracker...');
      try {
        const body = new FormData();
        body.append('action', action);
        body.append('expected_status', task.status);
        if (action === 'upload') body.append('file', file);
        const result = await requestApi('/rfqs/'+q.id+'/supplier-task', 'POST', body);
        q.supplierTask = result.task;
        if (window.currentQuotation === q) {
          el('supplierInquiryTaskFile').value = '';
          renderRfqDetails(q);
          supplierTaskFeedback(result.message);
        }
        showToast(result.message);
        window.refreshSupplierDashboard?.();
      } catch(error) {
        if (window.currentQuotation === q) supplierTaskFeedback(error.message, true);
        showToast(error.message);
      } finally {
        supplierTaskSubmitting = false;
        updateSupplierTaskControls();
      }
    }
    el('supplierInquiryTaskFile').addEventListener('change', () => {
      supplierTaskFeedback();
      updateSupplierTaskControls();
    });
    el('supplierInquiryTaskUpload').addEventListener('click', () => void submitSupplierInquiryTask('upload'));
    el('supplierInquiryTaskComplete').addEventListener('click', () => void submitSupplierInquiryTask('complete'));

    function plainRfqText(value) {
      const html = String(value ?? '').replace(/<!--flowtrack-rich-text-->/g, '');
      return new DOMParser().parseFromString(html, 'text/html').body.textContent.trim().replace(/\s+/g, ' ');
    }
    function updateSelectedRfq(q) {
      document.querySelectorAll('.rfq-context-card').forEach(card => {
        const v = card.querySelectorAll('.q-context-value');
        if (v[0]) v[0].textContent = q.inquiryRef || q.ref;
        if (v[1]) v[1].textContent = q.ref;
        if (v[2]) v[2].textContent = q.buyer;
        if (v[3]) v[3].textContent = q.due;
      });
      document.querySelectorAll('.quote-product-main h3').forEach(e => e.textContent=q.product);
      document.querySelectorAll('.quote-product-main .meta').forEach(e => e.textContent=q.inquiryRef || q.ref);
      document.querySelectorAll('.quote-product-main .qty-box strong').forEach(e => e.textContent=q.qty);
      document.querySelectorAll('.buyer-requirements').forEach(e => e.textContent=[q.requirements, q.message, q.supplierDetails].map(plainRfqText).filter(Boolean).join(' — ') || 'Review the products and reference files in the RFQ details.');
      document.querySelectorAll('.quote-product-card .rfq-item-summary').forEach(node => {
        node.innerHTML = (q.items || []).map(item => `<div class="rfq-summary-line"><strong>${escapeHtml(item.product_name)}${item.product_code ? ' · ' + escapeHtml(item.product_code) : ''}</strong><span>${escapeHtml(item.quantity)} ${escapeHtml(item.unit || 'units')}${item.notes ? ' · ' + escapeHtml(plainRfqText(item.notes)) : ''}</span></div>`).join('');
      });
      if (el('qCurrency')) el('qCurrency').value = q.currency || 'USD';
      document.querySelectorAll('.review-kv-grid .review-kv').forEach(kv => {
        const key=kv.querySelector('small')?.textContent.trim();const out=kv.querySelector('strong');
        if(key==='Inquiry reference' && out)out.textContent=q.inquiryRef || q.ref;
        if(key==='RFQ reference' && out)out.textContent=q.ref;
      });
      const buyerDocs=q.documents || [];
      el('quoteBuyerFilesInline').innerHTML=buyerDocs.length ? `<strong>Buyer reference files (${buyerDocs.length})</strong><div class="quote-buyer-file-list">${buyerDocs.map(d=>`<a href="${apiBase}/rfqs/${Number(q.id)}/buyer-documents/${Number(d.id)}" class="quote-buyer-file" title="Download ${escapeHtml(d.name)}">${escapeHtml(d.name)} <span>↓</span></a>`).join('')}</div>` : '';
      el('rfqRef').textContent=q.ref+' - Requested by '+q.buyer;
      document.querySelectorAll('.summary-product .muted').forEach(e=>e.textContent=q.code);
      document.querySelectorAll('.review-kv-grid .review-kv').forEach(kv=>{
        if(kv.querySelector('small')?.textContent.trim()==='Phone')kv.querySelector('strong').textContent=el('qPhone')?.value || '--';
      });
      document.querySelectorAll('.quote-sidebar-product strong').forEach(e => e.textContent=q.product);
      document.querySelectorAll('.quote-sidebar-lines .quote-sidebar-line').forEach(line => {
        const label=line.querySelector('span')?.textContent.trim();
        const val=line.querySelector('strong'); if (!val) return;
        if (label==='Supplier') val.textContent=portalConfig.companyName;
        if (label==='Requested quantity') val.textContent=q.qty;
        if (label==='Quotation due') val.textContent=q.due;
      });
      document.querySelectorAll('.pricing-grid input[readonly]').forEach(input=>input.value=Number(q.quantity).toLocaleString());
      document.querySelectorAll('.review-product strong').forEach(e=>e.textContent=q.product);
      document.querySelectorAll('.review-product span').forEach(e=>e.textContent=q.code+' - '+q.qty);
      document.querySelectorAll('.review-price-row span:first-child').forEach(e=>e.textContent=Number(q.quantity).toLocaleString());
      for (const [key,field] of Object.entries(quoteMapping)) {
        const input=el(field);
        if (input && !['contact','email','phone'].includes(key) && input.tagName!=='SELECT')
          input.value=['tooling','sample','discount'].includes(key)?'0.00':'';
      }

    }

    const quoteMapping = {
      contact:'qContact',email:'qEmail',phone:'qPhone',currency:'qCurrency',unit_price:'qUnitPrice',moq:'qMoq',
      tooling:'qTooling',sample:'qSample',discount:'qDiscount',tax:'qTax',production_lead:'qProductionLead',
      sample_lead:'qSampleLead',incoterm:'qIncoterm',shipping_port:'qShippingPort',delivery_date:'qDeliveryDate',
      validity:'qValidity',compliance:'qCompliance',deviations:'qDeviations',notes:'qSupplierNotes',document_notes:'qDocumentNotes'
    };
    function quotationPayload() {
      syncCarriedDetails();
      const data={}; Object.entries(quoteMapping).forEach(([key,id])=>data[key]=el(id)?.value ?? '');
      if ((window.currentQuotation?.items || []).length > 1) data.item_prices = linePrices();
      data.price_breaks = []; // Legacy drafts remain readable; price breaks are no longer offered.
      data.supporting_information = Array.from(document.querySelectorAll('#quoteSupportingOptions input:checked')).map(x=>x.value);
      return data;
    }
    async function persistQuotation(submit) {
      const q=window.currentQuotation;
      if (!q) { showToast('Select an RFQ first.'); return; }
      if (!['open','draft'].includes(q.status)) { showToast('This request is already finalized.'); return; }
      if (uploadingFiles) return showToast('Please wait for the file uploads to finish.');
      if (submit) {
        for (let step=1;step<=2;step++) if (!quoteStageValid(step)) {goQuoteStep(step);return;}
        if (!el('declarationAccuracy').checked || !el('declarationAuthority').checked) {
          goQuoteStep(3);stepError(3,null,'Please confirm both declarations before submitting.');return;
        }
      }
      const payload=quotationPayload();
      if (submit) { payload.accuracy=true; payload.authority=true; }
      const actionButtons=Array.from(document.querySelectorAll('#view-quote-workspace [data-submit-quote], #view-quote-workspace [data-save-quote]'));
      actionButtons.forEach(button => button.disabled = true);
      try {
        const result=await requestApi('/rfqs/'+q.id+(submit?'/submit':'/quotation'),submit?'POST':'PUT',payload);
        q.status=submit?'submitted':'draft'; if (submit) {q.syncStatus=result.sync_status;el('quoteSidePrimary').disabled=true;} renderQuotations(); updateCounts();
        el('savedText').textContent='Saved just now';
        if(el('quoteDraftBadge'))el('quoteDraftBadge').textContent=submit?'Submitted':'Draft';
        window.refreshSupplierDashboard?.();
        if (submit) {
          const syncLabel=el('quoteBuyerSyncStatus');
          if (syncLabel) syncLabel.textContent = result.sync_status === 'delivered' ? 'Submitted to FlowTracker' : 'Submitted locally — awaiting FlowTracker delivery';
          el('quoteSuccessModal').querySelector('.submit-success-copy p').textContent=result.message;
          el('quoteSuccessModal').classList.remove('hidden');
        } else showToast(result.message);
      } catch(e) { showToast(e.message); }
      finally { if (!submit || ['open','draft'].includes(q.status)) actionButtons.forEach(button => button.disabled = false); }
    }

    async function loadQuotation(id, { documentsOnly = false } = {}) {
      try {
        const result=await requestApi('/rfqs/'+id+'/quotation');
        if (window.currentQuotation?.id !== id) return;
        // Uploading or deleting attachments must never overwrite unsaved typed data.
        if (documentsOnly) {
          renderUploadedFiles(result.documents || []);
          return true;
        }
        const data=result.quotation?.data;
        const sync=result.quotation?.sync_status;
        if (sync) { window.currentQuotation.syncStatus=sync; window.currentQuotation.syncAttempts=Number(result.quotation?.sync_attempts || 0); renderQuotations(); }
        if (sync) {
          const label=el('quoteBuyerSyncStatus');
          if (label) label.textContent = sync==='delivered' ? 'Received by FlowTracker' : Number(result.quotation?.sync_attempts || 0)>0 ? 'Submitted locally — delivery retry scheduled; FlowTracker has not received it' : 'Submitted locally — awaiting FlowTracker delivery';
        }
        renderItemPrices(window.currentQuotation, data?.item_prices || []);
        if (data && !data.decline) {
          document.querySelectorAll('#quoteSupportingOptions input').forEach(input => input.checked = (data.supporting_information || []).includes(input.value));
          Object.entries(quoteMapping).forEach(([key,field])=>{if(el(field)&&data[key]!==undefined)el(field).value=data[key] ?? '';});

        }
        renderUploadedFiles(result.documents || []);
        syncCarriedDetails();
        el('savedText').textContent = window.currentQuotation.status === 'submitted' ? 'Submitted' : (data && !data.decline ? 'Draft loaded' : 'Not saved yet');
        updateQuoteTotals();
        return true;
      } catch(e) { showToast(e.message); return false; }
    }
    function updateCounts() {
      const counts={all:quotations.length,open:quotations.filter(q=>q.status==='open').length,
        draft:quotations.filter(q=>q.status==='draft').length,submitted:quotations.filter(q=>q.status==='submitted').length};
      document.querySelectorAll('#quoteTabs .tab').forEach(tab=>{
        const counter=tab.querySelector('.mono');if(counter)counter.textContent='('+counts[tab.dataset.status]+')';
      });
      document.querySelectorAll('.nav-item[data-view="quotations"] .nav-count').forEach(e=>e.textContent=Number(window.supplierPortalConfig.rfqActionCount ?? (counts.open+counts.draft))); 
    }
    const profileLabels = {
      'Company name':'name','Legal company name':'legal_name','Country / region':'country','Tax / registration number':'registration_number',
      'Company description':'description','Primary contact':'primary_contact','Primary email':'primary_email','Phone':'phone',
      'Finance contact':'finance_contact','Finance email':'finance_email','Operations contact':'operations_contact',
      'Registered address':'registered_address','Factory / production address':'factory_address',
      'City':'city','Postal code':'postal_code','Beneficiary name':'beneficiary_name','Bank name':'bank_name',
      'Currency':'bank_currency','SWIFT / BIC':'swift','Payment terms':'payment_terms',
    };
    function profileFields() {
      const fields=[];
      document.querySelectorAll('#view-profile .compact-field').forEach(wrapper=>{
        const label=wrapper.querySelector('label')?.textContent.trim();
        const input=wrapper.querySelector('input,textarea,select');
        const key=profileLabels[label]; if(key&&input&&!input.readOnly)fields.push([key,input]);
      });
      return fields;
    }
    function hydrateProfile() {
      const info={name:portalConfig.companyName,...portalConfig.profile,...portalConfig.banking};
      for(const [key,input] of profileFields())if(info[key]!==undefined&&info[key]!==null)input.value=info[key];
      const acct=document.querySelector('#view-profile input[readonly][value="Not provided"]');
      if(acct)acct.value='Hidden';
    }
    async function saveProfile() {
      const data={};for(const [key,input] of profileFields())data[key]=input.value;
      try { const result=await requestApi('/profile','PUT',data);showToast(result.message); }
      catch(e){showToast(e.message);}
    }
    async function sendSupport() {
      const box=document.querySelector('#view-help .card-body');
      const fields=box.querySelectorAll('select,input,textarea');
      const labels={'RFQ / quotation':'rfq','Order / request':'order','Document request':'document','Bill / payment':'bill','Company profile':'profile'};
      try {
        const r=await requestApi('/support','POST',{category:labels[fields[0].value],reference:fields[1].value,message:fields[2].value});
        fields[2].value=''; showToast(r.message);
      } catch(e) {showToast(e.message);}
    }
    // The server independently enforces tenant scope and document permissions.

    document.addEventListener('click', function(event) {
      const target=event.target.closest('[data-open-rfq],[data-portal-rfq-next],[data-portal-back-rfq],[data-portal-coming-soon],[data-portal-empty-notifications],[data-portal-edit-contact],[data-portal-support],[data-open-quotations]');
      if (!target) return;
      if (target.hasAttribute('data-open-rfq')) { closeNotificationDrawer(); openRfq(Number(target.dataset.openRfq)); }
      else if (target.hasAttribute('data-portal-rfq-next')) {const next=quotations.find(q=>['open','draft'].includes(q.status));if(next)openRfq(next.id);else showToast('No RFQ requires action.');}
      else if (target.hasAttribute('data-portal-back-rfq')) switchView('rfq');
      else if (target.hasAttribute('data-portal-support')) void sendSupport();
      else if (target.hasAttribute('data-open-quotations')) switchView('quotations');
      else if (target.hasAttribute('data-portal-edit-contact')) { el('qContact').focus(); }
      else showToast('Available after FlowTracker API integration.');
    });

    async function loadCompanyDocuments() {
      const box=el('companyDocumentCards'); if (!box) return;
      try {
        const results=await requestApi('/company-documents');
        const docs=results.documents || [];
        if(!docs.length) {
          box.innerHTML='<div class="compliance-card"><strong>No company documents uploaded</strong><small>Upload a registration, tax or compliance document securely.</small></div>';
          return;
        }
        box.innerHTML=docs.map(d=>`<div class="compliance-card"><strong>${escapeHtml(d.document_type)}</strong><small>${escapeHtml(d.original_name)}</small><div class="expiry">${d.expires_at?'Expires '+escapeHtml(d.expires_at):'No expiry date'}</div><div style="display:flex;gap:7px;margin-top:9px"><a class="btn btn-ghost btn-sm" href="${apiBase}/documents/${Number(d.id)}/download">Download</a>${portalConfig.role==='admin'?`<button type="button" class="btn btn-danger-ghost btn-sm" data-company-doc-remove="${Number(d.id)}">Remove</button>`:''}</div></div>`).join('');
      } catch(e) {showToast(e.message);}
    }
    const companyDialog=el('companyDocModal');
    if(portalConfig.role !== 'admin') { const upload=el('uploadCompanyDocBtn'); if(upload) upload.hidden=true; }
    el('uploadCompanyDocBtn')?.addEventListener('click',()=>companyDialog.classList.remove('hidden'));
    document.querySelectorAll('[data-close-company-doc]').forEach(x=>x.addEventListener('click',()=>companyDialog.classList.add('hidden')));
    companyDialog?.addEventListener('click',e=>{if(e.target===companyDialog)companyDialog.classList.add('hidden');});
    el('confirmCompanyDoc')?.addEventListener('click',async()=>{
      const file=el('companyDocFile').files[0]; if(!file){showToast('Choose a file.');return;}
      const payload=new FormData();payload.append('file',file);payload.append('document_type',el('companyDocType').value);
      if(el('companyDocExpiry').value)payload.append('expires_at',el('companyDocExpiry').value);
      try {const res=await requestApi('/company-documents','POST',payload);companyDialog.classList.add('hidden');el('companyDocFile').value='';await loadCompanyDocuments();showToast(res.message);}
      catch(e){showToast(e.message);}
    });
    document.addEventListener('click',async e=>{
      const btn=e.target.closest('[data-company-doc-remove]');if(!btn)return;
      if(!confirm('Remove this company document?'))return;
      try {const res=await requestApi('/company-documents/'+btn.dataset.companyDocRemove,'DELETE');await loadCompanyDocuments();showToast(res.message);}
      catch(err){showToast(err.message);}
    });

    // Supplier portal navigation helpers.
    document.querySelectorAll('[data-view-target]').forEach(btn => btn.addEventListener('click', () => switchView(btn.dataset.viewTarget)));

    // Close modals when clicking the backdrop.
    ['forgotModal','accountModal','declineModal'].forEach(id => { const modal = el(id); if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); }); });

    const requestedView = new URLSearchParams(window.location.search).get('view');
    if (new URLSearchParams(window.location.search).has('orders_page')) switchView('orders');
    else if (['dashboard', 'orders', 'quotations', 'profile', 'notifications', 'help'].includes(requestedView)) switchView(requestedView);
    const initialRfq = Number(new URLSearchParams(window.location.search).get('rfq') || 0);
    if (initialRfq) window.openRfq(initialRfq);
    hydrateProfile();
    updateCounts();
    renderQuotations();
  