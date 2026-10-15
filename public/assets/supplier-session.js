(function () {
    'use strict';
    const script = document.currentScript;
    const activityUrl = script?.dataset.activityUrl;
    const logoutUrl = script?.dataset.logoutUrl;
    const loginUrl = script?.dataset.loginUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!activityUrl || !logoutUrl || !loginUrl || !csrf) return;
    const expiredLoginUrl = loginUrl + (loginUrl.includes('?') ? '&' : '?') + 'session_expired=1';

    const idleMs = 30 * 60 * 1000;
    const heartbeatMs = 5 * 60 * 1000;
    let lastActivity = Date.now();
    let lastHeartbeat = Date.now();
    let lastBroadcast = 0;
    let exiting = false;
    // Keep multiple tabs from logging each other out while the user works in one of them.
    const channel = typeof BroadcastChannel === 'function' ? new BroadcastChannel('supplier-portal-activity') : null;
    if (channel) channel.onmessage = function (event) {
        if (event.data?.type === 'activity' && Number.isFinite(event.data.at)) {
            lastActivity = Math.max(lastActivity, event.data.at);
        }
        if (event.data?.type === 'logout') location.replace(expiredLoginUrl);
    };
    function recordActivity() {
        if (exiting) return;
        const now = Date.now();
        lastActivity = now;
        if (channel && now - lastBroadcast >= 5000) {
            channel.postMessage({ type: 'activity', at: now });
            lastBroadcast = now;
        }
    }
    ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (eventName) {
        document.addEventListener(eventName, recordActivity, { passive: true });
    });

    async function logout() {
        if (exiting) return;
        exiting = true;
        try {
            await fetch(logoutUrl, {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
        } catch (_) { /* Server enforces its own idle limit as well. */ }
        channel?.postMessage({ type: 'logout' });
        location.replace(expiredLoginUrl);
    }
    async function heartbeat() {
        lastHeartbeat = Date.now();
        try {
            const response = await fetch(activityUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (response.status === 401 || response.status === 419) {
                exiting = true;
                location.replace(expiredLoginUrl);
            }
        } catch (_) { /* A network interruption never overrides the server-side timeout. */ }
    }
    function check() {
        if (exiting) return;
        const now = Date.now();
        if (now - lastActivity >= idleMs) { void logout(); return; }
        // Background realtime polling does not count as user activity.
        if (!document.hidden && lastActivity > lastHeartbeat && now - lastHeartbeat >= heartbeatMs) {
            void heartbeat();
        }
    }
    window.setInterval(check, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
}());
