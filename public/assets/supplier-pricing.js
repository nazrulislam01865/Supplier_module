/* Interactions for the two prototype pricing screens; no pricing data is fetched client-side. */
(function () {
    'use strict';
    var menu = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('sidebar');
    if (menu && sidebar) menu.addEventListener('click', function () { sidebar.classList.toggle('open'); });

    // List filters use a native GET form. Submit dropdown changes immediately,
    // without inline scripts (the supplier CSP blocks them) or additional APIs.
    document.querySelectorAll('.pricing-filters select').forEach(function (select) {
        select.addEventListener('change', function () { select.form.requestSubmit(); });
    });

    // One native modal for every product; each supplier-only table is already in the page.
    // No fetches, no new storage, and no changes to the price proposal workflow.
    var priceDialog = document.getElementById('supplierPriceTableDialog');
    if (priceDialog) {
        var lastPriceTrigger = null;
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-price-table-trigger]');
            if (!trigger) return;
            var template = document.getElementById(trigger.dataset.priceTableId || '');
            if (!template || typeof priceDialog.showModal !== 'function') return;
            var container = document.getElementById('supplierPriceDialogTable');
            container.replaceChildren(template.content.cloneNode(true));
            document.getElementById('supplierPriceDialogProduct').textContent = trigger.dataset.productName || '';
            document.getElementById('supplierPriceDialogCode').textContent = trigger.dataset.productCode || '';
            var tierCount = Number(trigger.dataset.tierCount || 0);
            document.getElementById('supplierPriceDialogCount').textContent = tierCount + (tierCount === 1 ? ' tier' : ' tiers');
            lastPriceTrigger = trigger;
            priceDialog.showModal();
            document.body.classList.add('pricing-dialog-open');
            priceDialog.querySelector('[data-price-dialog-close]')?.focus();
        });
        priceDialog.querySelectorAll('[data-price-dialog-close]').forEach(function (button) {
            button.addEventListener('click', function () { priceDialog.close(); });
        });
        priceDialog.addEventListener('click', function (event) {
            if (event.target === priceDialog) priceDialog.close();
        });
        priceDialog.addEventListener('close', function () {
            document.body.classList.remove('pricing-dialog-open');
            if (lastPriceTrigger?.isConnected) lastPriceTrigger.focus();
        });
    }

    var form = document.getElementById('supplierPriceEditor');
    if (!form) return;
    var tbody = document.getElementById('priceTierRows');
    var pending = form.dataset.pending === '1';
    var draftStatus = document.getElementById('pricingDraftStatus');
    var key = form.dataset.draftKey;
    var version = form.dataset.baselineVersion || '';
    function markDirty() { form.dispatchEvent(new Event('input', { bubbles: true })); }
    var nextIndex = Array.from(tbody.querySelectorAll('input[name^="tiers["]')).reduce(function (max, input) {
        var match = input.name.match(/^tiers\[(\d+)\]/);
        return match ? Math.max(max, Number(match[1]) + 1) : max;
    }, 0);
    var baselinePrices = new Map();
    Array.from(tbody.querySelectorAll('.pricing-tier-row')).forEach(function (row) {
        var value = row.querySelector('.pricing-quantity-input')?.value;
        if (value && row.dataset.baseline !== '') baselinePrices.set(value, Number(row.dataset.baseline));
    });

    function tierRows() { return Array.from(tbody.querySelectorAll('.pricing-tier-row')); }
    function currentBaseline(quantity) {
        return baselinePrices.has(String(quantity)) ? baselinePrices.get(String(quantity)) : null;
    }
    function addRow(quantity, price) {
        if (tierRows().length >= 100) return null;
        tbody.querySelector('.pricing-tier-empty')?.remove();
        var index = nextIndex++;
        var row = document.createElement('tr');
        row.className = 'pricing-tier-row';
        row.dataset.baseline = '';
        row.innerHTML = '<td class="pricing-tier-quantity"><input class="pricing-quantity-input" type="number" min="1" max="100000000" step="1" required></td>' +
            '<td class="pricing-current-value">—</td>' +
            '<td><input class="pricing-new-price" type="text" inputmode="decimal" maxlength="18" pattern="[0-9]+(\\.[0-9]{1,6})?" required></td>' +
            '<td><span class="pricing-row-status">New</span></td>' +
            '<td><button class="pricing-remove-row" type="button" aria-label="Remove price tier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16m-13 0 1 14h8l1-14M9 7V4h6v3M10 11v7m4-7v7"/></svg></button></td>';
        var quantityInput = row.querySelector('.pricing-quantity-input');
        var priceInput = row.querySelector('.pricing-new-price');
        quantityInput.name = 'tiers[' + index + '][quantity]';
        priceInput.name = 'tiers[' + index + '][price]';
        quantityInput.value = quantity == null ? '' : String(quantity);
        priceInput.value = price == null ? '' : String(price);
        tbody.appendChild(row);
        return row;
    }
    function updateRow(row) {
        var quantity = row.querySelector('.pricing-quantity-input').value;
        var field = row.querySelector('.pricing-new-price');
        var status = row.querySelector('.pricing-row-status');
        var original = currentBaseline(quantity);
        var proposal = Number(field.value);
        var valid = field.value.trim() !== '' && /^\d+(?:\.\d{1,6})?$/.test(field.value.trim()) && Number.isFinite(proposal);
        var modified = valid && (original === null || Math.abs(proposal - original) > 0.0000001);
        row.querySelector('.pricing-current-value').textContent = original === null ? '—' : '$' + original.toFixed(2);
        field.classList.toggle('is-modified', modified);
        status.className = 'pricing-row-status';
        if (!valid) { status.textContent = 'Unchanged'; return; }
        if (original === null) { status.textContent = 'New'; status.classList.add('is-modified'); return; }
        if (!modified) { status.textContent = 'Unchanged'; return; }
        status.textContent = 'Modified';
        status.classList.add('is-modified');
    }
    function updateRows() { tierRows().forEach(updateRow); }
    updateRows();
    if (pending) {
        try { sessionStorage.removeItem(key); } catch (_) { /* Browser may disable storage. */ }
        return;
    }

    // Drafts are tab-local: sensitive supplier costs are not persisted in server tables or long-lived browser storage.
    function loadDraft() {
        if (form.dataset.hasOld === '1') return;
        try {
            var draft = JSON.parse(sessionStorage.getItem(key) || 'null');
            if (!draft || draft.version !== version || !Array.isArray(draft.rows) ||
                draft.rows.length > 100 || Date.now() - draft.savedAt > 24 * 60 * 60 * 1000) return;
            var existing = tierRows();
            existing.forEach(function (row) { row.remove(); });
            draft.rows.forEach(function (item) {
                if (typeof item.quantity !== 'string' || typeof item.price !== 'string') return;
                if (item.quantity.length > 9 || item.price.length > 18) return;
                addRow(item.quantity, item.price);
            });
            updateRows();
            draftStatus.textContent = 'Draft restored from this tab';
            markDirty();
        } catch (_) { /* A private browser window might block storage. */ }
    }
    loadDraft();

    document.getElementById('addPriceTier')?.addEventListener('click', function () {
        var row = addRow('', '');
        if (row) { updateRows(); markDirty(); row.querySelector('.pricing-quantity-input').focus(); draftStatus.textContent = ''; }
        else draftStatus.textContent = 'Maximum 100 price tiers allowed';
    });
    tbody.addEventListener('click', function (event) {
        var button = event.target.closest('.pricing-remove-row');
        if (!button) return;
        button.closest('.pricing-tier-row')?.remove();
        updateRows();
        markDirty();
        draftStatus.textContent = '';
    });
    form.addEventListener('input', function (event) {
        if (!event.target.matches('.pricing-new-price,.pricing-quantity-input')) return;
        updateRows();
        draftStatus.textContent = '';
    });
    document.getElementById('savePriceDraft')?.addEventListener('click', function () {
        try {
            sessionStorage.setItem(key, JSON.stringify({
                version: version,
                savedAt: Date.now(),
                rows: tierRows().map(function (row) {
                    return { quantity: row.querySelector('.pricing-quantity-input').value,
                        price: row.querySelector('.pricing-new-price').value };
                }),
            }));
            draftStatus.textContent = 'Draft saved in this browser tab';
        } catch (_) { draftStatus.textContent = 'Draft storage is unavailable in this browser'; }
    });
    form.addEventListener('submit', function (event) {
        var rows = tierRows();
        if (!rows.length) { event.preventDefault(); alert('Add at least one price tier before requesting approval.'); return; }
        if (rows.length > 100) { event.preventDefault(); alert('Only 100 price tiers are allowed.'); return; }
        var seen = new Set();
        for (var i = 0; i < rows.length; i++) {
            var quantityInput = rows[i].querySelector('.pricing-quantity-input');
            var priceInput = rows[i].querySelector('.pricing-new-price');
            var quantity = quantityInput.value;
            var price = priceInput.value.trim();
            if (!quantityInput.checkValidity() || !Number.isSafeInteger(Number(quantity)) ||
                Number(quantity) < 1 || Number(quantity) > 100000000) {
                event.preventDefault(); quantityInput.reportValidity(); quantityInput.focus(); return;
            }
            if (seen.has(quantity)) {
                event.preventDefault(); quantityInput.setCustomValidity('Duplicate quantity tier.');
                quantityInput.reportValidity(); quantityInput.focus(); return;
            }
            quantityInput.setCustomValidity('');
            seen.add(quantity);
            if (!/^\d+(?:\.\d{1,6})?$/.test(price) || Number(price) > 999999999.99) {
                event.preventDefault(); priceInput.setCustomValidity('Enter a valid price with up to 6 decimals.');
                priceInput.reportValidity(); priceInput.focus(); return;
            }
            priceInput.setCustomValidity('');
        }
    });
    tbody.addEventListener('input', function (event) {
        if (event.target.matches('input')) event.target.setCustomValidity('');
    });
})();
