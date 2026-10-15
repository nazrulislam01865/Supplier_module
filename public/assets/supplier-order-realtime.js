/* Private Reverb push with a lightweight revision-check fallback. No task data in events. */
(function () {
    'use strict';
    var root = document.getElementById('supplierOrderView');
    if (!root) return;
    var config = root.dataset;
    var revision = String(config.orderRealtimeRevision || '0');
    var revisionUrl = config.orderRealtimeRevisionUrl;
    var authUrl = config.orderRealtimeAuth;
    var key = config.orderRealtimeKey;
    var host = config.orderRealtimeHost;
    var scheme = config.orderRealtimeScheme === 'https' ? 'wss' : 'ws';
    var port = Number(config.orderRealtimePort || 443);
    var supplierId = Number(config.orderRealtimeSupplier || 0);
    var sourceId = Number(config.orderRealtimeSource || 0);
    var channel = 'private-flowtrack.supplier.' + supplierId + '.order.' + sourceId;
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var socket = null;
    var connected = false;
    var retry = 0;
    var closed = false;
    var refreshPending = false;
    var checking = false;
    var queuedCheck = false;
    var pingTimer;
    var lastHeartbeat = 0;
    var reconnectTimer;

    function safeToRefresh() {
        return !document.hidden && !document.querySelector('dialog[open]') &&
            !document.querySelector('[data-supplier-action-form] input[type=file]:focus');
    }
    function refreshIfReady() {
        if (refreshPending && safeToRefresh()) window.location.reload();
    }
    async function checkRevision() {
        if (checking) { queuedCheck = true; return; }
        if (document.hidden || !revisionUrl) return;
        checking = true;
        try {
            var response = await fetch(revisionUrl, {
                credentials: 'same-origin', cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.status === 404) { refreshPending = true; refreshIfReady(); return; }
            if (!response.ok) return;
            var data = await response.json();
            if (String(data.revision) !== revision) {
                refreshPending = true;
                refreshIfReady();
            }
        } catch (_) {
            // HTTP/scheduler retry will restore consistency.
        } finally {
            checking = false;
            if (queuedCheck) { queuedCheck = false; checkRevision(); }
        }
    }
    function send(packet) {
        if (socket?.readyState === WebSocket.OPEN) socket.send(JSON.stringify(packet));
    }
    function disconnect() {
        connected = false;
        if (socket) { socket.onclose = null; socket.close(); socket = null; }
        if (reconnectTimer) clearTimeout(reconnectTimer);
    }
    function connect() {
        if (closed || !key || !host || !supplierId || !sourceId) return;
        try {
            socket = new WebSocket(scheme + '://' + host + ':' + port + '/app/' +
                encodeURIComponent(key) + '?protocol=7&client=js&version=8.5.0&flash=false');
        } catch (_) { scheduleReconnect(); return; }
        socket.onmessage = async function (event) {
            var packet;
            try { packet = JSON.parse(event.data); } catch (_) { return; }
            if (packet.event === 'pusher:ping') { send({ event: 'pusher:pong', data: {} }); return; }
            if (packet.event === 'pusher:connection_established') {
                var data;
                try { data = typeof packet.data === 'string' ? JSON.parse(packet.data) : packet.data; } catch (_) { return; }
                if (!data?.socket_id) return;
                try {
                    var body = new URLSearchParams({ socket_id: data.socket_id, channel_name: channel });
                    var response = await fetch(authUrl, {
                        method: 'POST', credentials: 'same-origin', cache: 'no-store',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: body.toString(),
                    });
                    if (!response.ok) { disconnect(); scheduleReconnect(); return; }
                    var authorization = await response.json();
                    if (!authorization?.auth) return;
                    send({ event: 'pusher:subscribe', data: { auth: authorization.auth, channel: channel } });
                } catch (_) { disconnect(); scheduleReconnect(); }
            } else if (packet.event === 'pusher_internal:subscription_succeeded') {
                connected = true; retry = 0; checkRevision();
            } else if (packet.event === 'flowtrack.supplier.order.updated' && packet.channel === channel) {
                checkRevision();
            }
        };
        socket.onclose = function () { connected = false; scheduleReconnect(); };
        socket.onerror = function () { connected = false; };
    }
    function scheduleReconnect() {
        if (closed || reconnectTimer || !host || !key) return;
        retry = Math.min(retry + 1, 6);
        reconnectTimer = setTimeout(function () {
            reconnectTimer = null; connect();
        }, Math.min(30000, 1000 * Math.pow(2, retry - 1)));
    }
    // Reverb event is primary. A single revision check per minute protects against
    // missed events or an offline socket without repeatedly polling the database.
    pingTimer = setInterval(function () {
        if (refreshPending) { refreshIfReady(); return; }
        if (!document.hidden && (!connected || Date.now() - lastHeartbeat >= 60000)) {
            lastHeartbeat = Date.now();
            checkRevision();
        }
    }, 60000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { refreshIfReady(); checkRevision(); }
    });
    document.addEventListener('close', refreshIfReady, true);
    window.addEventListener('focus', refreshIfReady);
    window.addEventListener('beforeunload', function () {
        closed = true; clearInterval(pingTimer); disconnect();
    });
    if (key && host) connect();
})();
