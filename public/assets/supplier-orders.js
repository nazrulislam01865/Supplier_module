'use strict';
// Unobtrusive filters; form submission and pagination still work without JavaScript.
(() => {
  const form = document.getElementById('supplierOrderFilters');
  if (!form) return;
  const search = form.querySelector('input[name="search"]');
  let debounce;
  let activeRequest;
  let generation = 0;

  // Keep the existing automatic search, but refresh only the server-rendered
  // order results. A full form submission used to reload the page and blur the input.
  const updateSearch = async () => {
    const currentGeneration = ++generation;
    activeRequest?.abort();
    const request = new AbortController();
    activeRequest = request;
    const url = new URL(form.action, window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    const searchedText = search.value;

    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'text/html', 'X-Supplier-Order-Fragment': '1' },
        signal: request.signal,
      });
      if (response.redirected) {
        window.location.assign(response.url);
        return;
      }
      if (!response.ok) throw new Error('Order search failed');
      const html = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (currentGeneration !== generation || search.value !== searchedText) return;

      const currentList = document.getElementById('supplierOrdersList');
      const nextList = html.getElementById('supplierOrdersList');
      const currentStages = document.querySelector('#view-orders .supplier-order-stages');
      const nextStages = html.querySelector('.supplier-order-stages');
      const currentControls = form.querySelector('.supplier-orders-filter-controls');
      const nextControls = nextList?.querySelector('.supplier-orders-filter-controls');
      const resultSelectors = ['.supplier-orders-list-summary', '.supplier-orders-table-viewport', '.supplier-orders-pagination'];
      if (!currentList || !nextList || !currentStages || !nextStages || !currentControls || !nextControls ||
          resultSelectors.some(selector => !currentList.querySelector(selector) || !nextList.querySelector(selector))) {
        throw new Error('Invalid order search response');
      }

      // Never replace the form or its focused search input, so typing, selection
      // and the cursor survive every refresh. Other filters keep their server state.
      currentStages.replaceWith(nextStages);
      currentControls.replaceWith(nextControls);
      for (const selector of resultSelectors) {
        currentList.querySelector(selector).replaceWith(nextList.querySelector(selector));
      }
      window.history.replaceState(window.history.state, '', url.toString());
    } catch (error) {
      if (error.name !== 'AbortError' && currentGeneration === generation) {
        const summary = document.querySelector('#supplierOrdersList .supplier-orders-list-summary');
        if (summary) summary.textContent = 'Unable to update search results. Press Enter to retry.';
      }
    } finally {
      if (activeRequest === request) activeRequest = null;
    }
  };

  const submit = () => {
    window.clearTimeout(debounce);
    ++generation;
    activeRequest?.abort();
    form.requestSubmit();
  };
  form.addEventListener('change', event => {
    if (event.target.matches('select, input[type="checkbox"], input[type="date"]')) submit();
  });
  search?.addEventListener('input', () => {
    window.clearTimeout(debounce);
    ++generation;
    activeRequest?.abort();
    debounce = window.setTimeout(updateSearch, 500);
  });
  search?.addEventListener('keydown', event => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    window.clearTimeout(debounce);
    void updateSearch();
  });
  document.getElementById('supplierOrdersList')?.addEventListener('click', event => {
    if (event.target.closest('a, button, input, select')) return;
    const row = event.target.closest('[data-supplier-order-href]');
    if (row) window.location.assign(row.dataset.supplierOrderHref);
  });
  document.getElementById('supplierOrdersList')?.addEventListener('keydown', event => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    const row = event.target.closest('[data-supplier-order-href]');
    if (!row || event.target !== row) return;
    event.preventDefault();
    window.location.assign(row.dataset.supplierOrderHref);
  });
})();
