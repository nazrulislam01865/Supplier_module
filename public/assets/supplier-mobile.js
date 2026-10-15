/* Shared mobile menu dismiss behavior. Existing page-specific menu toggle stays unchanged. */
(function () {
    'use strict';
    var sidebar = document.getElementById('sidebar');
    var toggle = document.getElementById('mobileMenuBtn');
    var backdrop = document.querySelector('.supplier-mobile-backdrop');
    if (!sidebar || !toggle || !backdrop) return;
    var narrow = window.matchMedia('(max-width: 900px)');
    function syncState() {
        var open = narrow.matches && sidebar.classList.contains('open');
        document.body.classList.toggle('supplier-mobile-nav-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-controls', 'sidebar');
        backdrop.setAttribute('aria-hidden', String(!open));
    }
    function dismiss() {
        sidebar.classList.remove('open');
        syncState();
    }
    backdrop.addEventListener('click', function () { dismiss(); toggle.focus(); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('open')) { dismiss(); toggle.focus(); }
    });
    sidebar.addEventListener('click', function (event) {
        if (event.target.closest('a[href], button.nav-item')) dismiss();
    });
    narrow.addEventListener('change', dismiss);
    new MutationObserver(syncState).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    syncState();
}());
