/* Company-scoped catalogue revision hints; no pricing or credentials in WebSocket events. */
(function () {
    'use strict';
    var root = document.getElementById('portalView');
    if (!root || !root.dataset.catalogSupplier) return;
    var config = root.dataset;
    var revision = String(config.catalogRevision || '0');
    var supplierId = Number(config.catalogSupplier);
    var channel = 'private-flowtrack.supplier.' + supplierId + '.catalog';
    var host = config.catalogHost;
    var key = config.catalogKey;
    var scheme = config.catalogScheme === 'https' ? 'wss' : 'ws';
    var port = Number(config.catalogPort || 443);
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var socket = null;
    var connected = false;
    var checking = false;
    var dirty = false;
    var retry = 0;
    var reconnectTimer = null;
    var closed = false;
    var pendingRefresh = false;

    // Never discard a supplier's in-progress price changes.
    document.addEventListener('input', function (event) {
        if (event.target.closest('#supplierPriceEditor') || event.target.closest('form')?.querySelector('textarea[name="prices"]')) dirty = true;
    });
    function refresh() {
        if (!pendingRefresh || document.hidden || dirty) return;
        window.location.reload();
    }
    async function check() {
        if (checking || document.hidden || !config.catalogRevisionUrl) return;
        checking = true;
        try {
            var response = await fetch(config.catalogRevisionUrl, {
                credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;
            var data = await response.json();
            if (String(data.revision) !== revision) { pendingRefresh = true; refresh(); }
        } catch (_) {
            // Updates remain persistent and the user can still reload manually.
        } finally { checking = false; }
    }
    function send(packet) {
        if (socket?.readyState === WebSocket.OPEN) socket.send(JSON.stringify(packet));
    }
    function connect() {
        if (closed || !host || !key || !supplierId || !config.catalogAuth) return;
        try {
            socket = new WebSocket(scheme + '://' + host + ':' + port + '/app/' +
                encodeURIComponent(key) + '?protocol=7&client=js&version=8.5.0&flash=false');
        } catch (_) { reconnect(); return; }
        socket.onmessage = async function (event) {
            var packet;
            try { packet = JSON.parse(event.data); } catch (_) { return; }
            if (packet.event === 'pusher:ping') { send({ event: 'pusher:pong', data: {} }); return; }
            if (packet.event === 'pusher:connection_established') {
                var value;
                try { value = typeof packet.data === 'string' ? JSON.parse(packet.data) : packet.data; } catch (_) { return; }
                if (!value?.socket_id) return;
                try {
                    var form = new URLSearchParams({ socket_id: value.socket_id, channel_name: channel });
                    var response = await fetch(config.catalogAuth, {
                        method: 'POST', credentials: 'same-origin', cache: 'no-store',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: form.toString(),
                    });
                    if (!response.ok) { socket?.close(); return; }
                    var authorization = await response.json();
                    if (!authorization?.auth) return;
                    send({ event: 'pusher:subscribe', data: { auth: authorization.auth, channel: channel } });
                } catch (_) { socket?.close(); }
            } else if (packet.event === 'pusher_internal:subscription_succeeded' && packet.channel === channel) {
                connected = true; retry = 0; check();
            } else if (packet.event === 'flowtrack.supplier.catalog.updated' && packet.channel === channel) {
                check();
            }
        };
        socket.onclose = function () { connected = false; reconnect(); };
        socket.onerror = function () { connected = false; };
    }
    function reconnect() {
        if (closed || reconnectTimer || !host || !key) return;
        retry = Math.min(retry + 1, 6);
        reconnectTimer = setTimeout(function () {
            reconnectTimer = null; connect();
        }, Math.min(30000, 1000 * Math.pow(2, retry - 1)));
    }
    var interval = setInterval(function () {
        if (!document.hidden) { refresh(); if (!connected) check(); }
    }, 10000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { refresh(); check(); }
    });
    window.addEventListener('focus', function () { refresh(); check(); });
    window.addEventListener('beforeunload', function () {
        closed = true; clearInterval(interval); clearTimeout(reconnectTimer);
        if (socket) { socket.onclose = null; socket.close(); }
    });
    if (host && key) connect();
})();
