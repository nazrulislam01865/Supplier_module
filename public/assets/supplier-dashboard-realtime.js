/* Supplier-scoped Reverb dashboard updates with low-frequency revision fallback. */
(function () {
    'use strict';
    const config = window.supplierPortalConfig?.realtime;
    if (!document.getElementById('dashboardContent') || !config) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const channel = 'private-flowtrack.supplier.' + Number(config.supplierId) + '.dashboard';
    let revision = String(window.supplierPortalConfig.dashboardRevision || '0');
    let socket = null;
    let connected = false;
    let pending = false;
    let checking = false;
    let refreshing = false;
    let closed = false;
    let retry = 0;
    let reconnectTimer = null;
    let lastFallback = 0;

    function send(value) {
        if (socket?.readyState === WebSocket.OPEN) socket.send(JSON.stringify(value));
    }
    function updateUnread(count) {
        const dot = document.getElementById('notificationDot');
        if (dot) dot.classList.toggle('hidden', !Number(count));
        document.querySelectorAll('.nav-item[data-view="notifications"] .nav-count')
            .forEach(node => { node.textContent = String(count); node.classList.toggle('hidden', !Number(count)); });
        const label = document.getElementById('notificationDrawerCount');
        if (label) label.textContent = count + ' unread';
        document.querySelectorAll('[data-mark-notifications-read]').forEach(node => { node.disabled = !Number(count); });
    }
    function safeToUpdate() {
        // Never reset an in-progress supplier quotation or open modal.
        return !document.hidden && !['quote-workspace', 'rfq'].some(v =>
            !document.getElementById('view-' + v)?.classList.contains('hidden'));
    }
    async function refresh() {
        if (refreshing || !safeToUpdate()) { pending = true; return; }
        refreshing = true;
        try {
            const response = await fetch(config.url, {
                credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;
            const data = await response.json();
            // Header branding is FlowTracker-owned; only use trusted, signed API values.
            if (data.branding) {
                const { companyName, name, logoUrl, faviconUrl } = data.branding;
                const icon = document.querySelector('link[rel="icon"]');
                if (faviconUrl && icon && icon.getAttribute('href') !== faviconUrl) icon.href = faviconUrl;
                if (!faviconUrl && icon) icon.remove();
                if (faviconUrl && !icon) {
                    const link = document.createElement('link');
                    link.rel = 'icon'; link.href = faviconUrl; document.head.append(link);
                }
                document.querySelectorAll('[data-flowtrack-brand-name]').forEach(node => {
                    node.textContent = String(name || 'FlowTrack');
                    node.classList.toggle('hidden', Boolean(logoUrl));
                });
                document.querySelectorAll('.topbar .brand').forEach(brand => {
                    const oldLogo = brand.querySelector('.flowtrack-synced-logo');
                    const oldMark = brand.querySelector('.brand-mark');
                    if (logoUrl) {
                        const logo = oldLogo || document.createElement('img');
                        logo.className = 'flowtrack-synced-logo';
                        logo.alt = String(name || 'FlowTrack');
                        logo.src = String(logoUrl);
                        if (!oldLogo) brand.prepend(logo);
                        oldMark?.remove();
                    } else if (oldLogo) {
                        oldLogo.remove();
                        if (!oldMark) {
                            const mark = document.createElement('span');
                            mark.className = 'brand-mark';
                            const dot = document.createElement('span');
                            dot.className = 'brand-dot';
                            mark.append(dot);
                            brand.prepend(mark);
                        }
                    }
                });
                if (companyName) {
                    document.querySelectorAll('.account-meta small, .sidebar-bottom .company-mini strong')
                        .forEach(node => { node.textContent = String(companyName); });
                    window.supplierPortalConfig.companyName = String(companyName);
                }
            }
            // A full snapshot is rendered on the server; no guesswork or browser-side totals.
            document.getElementById('dashboardContent').innerHTML = data.dashboard;
            document.getElementById('notificationDrawerList').innerHTML = data.notifications;
            window.updateSupplierDrawerFilters?.();
            // The center is a separate, server-paginated view; never replace it with the 20-item bell preview.
            if (!document.getElementById('view-notifications')?.classList.contains('hidden')) {
                void window.loadSupplierNotificationCenter?.({ fresh: true });
            }
            updateUnread(data.unread);
            window.supplierPortalConfig.rfqActionCount = Number(data.rfqActionCount);
            if (Array.isArray(data.rfqs)) window.updateSupplierRfqList?.(data.rfqs);
            revision = String(data.revision);
            pending = false;
        } catch (_) {
            // Next Reverb event or fallback check retries.
        } finally {
            refreshing = false;
        }
    }
    async function check() {
        if (checking || document.hidden) return;
        checking = true;
        try {
            const response = await fetch(config.revisionUrl, {
                credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return;
            const data = await response.json();
            if (String(data.revision) !== revision) { pending = true; await refresh(); }
        } catch (_) {
            // Temporary connection loss; the next visibility/reconnect check retries.
        } finally { checking = false; }
    }
    async function markAllRead() {
        try {
            const response = await fetch(config.markReadUrl, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('Unable to mark notifications as read.');
            updateUnread(0);
            document.querySelectorAll('.supplier-notice-new').forEach(node => node.classList.remove('supplier-notice-new'));
            document.querySelectorAll('#notificationDrawerList [data-notice-unread]').forEach(node => {
                node.dataset.noticeUnread = '0';
                node.querySelector('.notice-unread-dot')?.remove();
            });
            window.updateSupplierDrawerFilters?.();
            document.querySelectorAll('[data-mark-notifications-read]').forEach(node => { node.disabled = true; });
            if (!document.getElementById('view-notifications')?.classList.contains('hidden')) {
                void window.loadSupplierNotificationCenter?.({ fresh: true });
            }
        } catch (_) {
            // Leave unread count unchanged; show feedback in existing toast.
            window.supplierDashboardToast?.('Unable to mark notifications as read. Please retry.');
        }
    }
    document.addEventListener('click', event => {
        if (event.target.closest('[data-mark-notifications-read]')) void markAllRead();
    });
    window.refreshSupplierDashboard = refresh;

    function reconnect() {
        if (closed || reconnectTimer || !config.key || !config.host) return;
        retry = Math.min(retry + 1, 6);
        reconnectTimer = setTimeout(() => { reconnectTimer = null; connect(); }, Math.min(30000, 1000 * 2 ** (retry - 1)));
    }
    function connect() {
        if (closed || !config.key || !config.host || !config.supplierId) return;
        const wsScheme = config.scheme === 'https' ? 'wss' : 'ws';
        try {
            socket = new WebSocket(wsScheme + '://' + config.host + ':' + Number(config.port || 443) +
                '/app/' + encodeURIComponent(config.key) + '?protocol=7&client=js&version=8.5.0&flash=false');
        } catch (_) { reconnect(); return; }
        socket.onmessage = async event => {
            let packet;
            try { packet = JSON.parse(event.data); } catch (_) { return; }
            if (packet.event === 'pusher:ping') { send({ event: 'pusher:pong', data: {} }); return; }
            if (packet.event === 'pusher:connection_established') {
                let data;
                try { data = typeof packet.data === 'string' ? JSON.parse(packet.data) : packet.data; } catch (_) { return; }
                if (!data?.socket_id) return;
                try {
                    const form = new URLSearchParams({ socket_id: data.socket_id, channel_name: channel });
                    const result = await fetch(config.authUrl, {
                        method: 'POST', credentials: 'same-origin', cache: 'no-store',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body: form.toString(),
                    });
                    if (!result.ok) { socket?.close(); return; }
                    const auth = await result.json();
                    if (auth.auth) send({ event: 'pusher:subscribe', data: { auth: auth.auth, channel } });
                } catch (_) { socket?.close(); }
            } else if (packet.event === 'pusher_internal:subscription_succeeded' && packet.channel === channel) {
                connected = true; retry = 0; void check();
            } else if (packet.event === 'flowtrack.supplier.dashboard.updated' && packet.channel === channel) {
                pending = true; void check();
            }
        };
        socket.onclose = () => { connected = false; reconnect(); };
        socket.onerror = () => { connected = false; };
    }
    // Reverb is primary. If disconnected, check only the lightweight company
    // revision every five seconds; fetch the full snapshot only on a change.
    const timer = setInterval(() => {
        if (document.hidden) return;
        if (pending) void refresh();
        if (!connected || Date.now() - lastFallback >= 60000) {
            lastFallback = Date.now(); void check();
        }
    }, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { if (pending) void refresh(); void check(); } });
    document.addEventListener('click', () => { if (pending) void refresh(); });
    window.addEventListener('beforeunload', () => {
        closed = true; clearInterval(timer); clearTimeout(reconnectTimer);
        if (socket) { socket.onclose = null; socket.close(); }
    });
    if (config.key && config.host) connect();
})();
