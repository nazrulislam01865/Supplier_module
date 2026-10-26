'use strict';
// Unobtrusive filters; form submission and pagination still work without JavaScript.
(() => {
  const form = document.getElementById('supplierOrderFilters');
  if (!form) return;
  let debounce;
  const submit = () => { window.clearTimeout(debounce); form.requestSubmit(); };
  form.querySelectorAll('select, input[type="checkbox"], input[type="date"]').forEach(input => {
    input.addEventListener('change', submit);
  });
  const search = form.querySelector('input[name="search"]');
  search?.addEventListener('input', () => {
    window.clearTimeout(debounce);
    debounce = window.setTimeout(submit, 500);
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
