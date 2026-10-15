/* Paginated notification center; the bell drawer keeps its separate 20-item preview. */
(function () {
    'use strict';
    const center = document.getElementById('notificationCenterList');
    if (!center) return;
    let loading = null;
    let controller = null;

    window.loadSupplierNotificationCenter = function (options = {}) {
        const visible = !document.getElementById('view-notifications')?.classList.contains('hidden');
        if (!visible) return Promise.resolve();
        if (!options.fresh && center.dataset.loaded === 'true') return Promise.resolve();
        if (loading && !options.fresh) return loading;
        controller?.abort();
        controller = new AbortController();
        const signal = controller.signal;
        const params = new URLSearchParams(location.search);
        // Opening from the dashboard starts with the All updates view.
        if (params.get('view') !== 'notifications' && !params.has('notifications_page')) {
            params.delete('notification_type');
            params.delete('notification_state');
            params.delete('notifications_page');
        }
        const endpoint = center.dataset.endpoint + '?' + params.toString();
        center.setAttribute('aria-busy', 'true');
        loading = fetch(endpoint, {
            credentials: 'same-origin', cache: 'no-store', signal,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(async response => {
            if (!response.ok) throw new Error('Unable to load notifications');
            const data = await response.json();
            if (!signal.aborted) {
                center.innerHTML = data.html;
                center.dataset.loaded = 'true';
            }
        }).catch(error => {
            if (error.name === 'AbortError') return;
            if (center.dataset.loaded !== 'true') {
                center.innerHTML = '<div class="card"><div class="supplier-notification-empty"><h3>Unable to load notifications</h3><p>Please try again.</p><button class="btn btn-outline-teal btn-sm" type="button" data-retry-notifications>Retry</button></div></div>';
            }
        }).finally(() => {
            if (controller?.signal === signal) {
                loading = null;
                center.removeAttribute('aria-busy');
            }
        });
        return loading;
    };
    center.addEventListener('change', event => {
        const select = event.target.closest('#notificationTypeSelect');
        if (select) window.location.assign(select.value);
    });
    center.addEventListener('click', event => {
        if (event.target.closest('[data-retry-notifications]')) {
            void window.loadSupplierNotificationCenter({ fresh: true });
        }
    });
})();
