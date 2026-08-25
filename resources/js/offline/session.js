import examOfflineDb from './db';

const SESSION_KEY = 'offline_session';

export async function saveOfflineSession(session) {
    await examOfflineDb.setMeta(SESSION_KEY, session);
    await examOfflineDb.setMeta('bound_student_id', session.student_id);
}

export async function getOfflineSession() {
    return examOfflineDb.getMeta(SESSION_KEY);
}

export function verifySessionToken(token) {
    if (!token || !token.includes('.')) {
        return null;
    }
    const [encoded] = token.split('.', 2);
    try {
        return JSON.parse(atob(encoded));
    } catch {
        return null;
    }
}

export async function isOfflineSessionValid() {
    const session = await getOfflineSession();
    if (!session?.token || !session.expires_at) {
        return false;
    }
    const payload = verifySessionToken(session.token);
    if (!payload) {
        return false;
    }
    return new Date(session.expires_at).getTime() > Date.now();
}

export async function bootstrapOfflineAccess(bootstrapUrl, deviceIdentifier, deviceName) {
    const response = await fetch(bootstrapUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ device_identifier: deviceIdentifier, device_name: deviceName }),
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(data.message || 'Unable to initialize offline access.');
    }

    await saveOfflineSession(data.session);

    if (navigator.serviceWorker?.controller && data.shell_urls?.length) {
        navigator.serviceWorker.controller.postMessage({
            type: 'CACHE_SHELL_URLS',
            urls: data.shell_urls,
        });
    }

    return data;
}

export default {
    saveOfflineSession,
    getOfflineSession,
    verifySessionToken,
    isOfflineSessionValid,
    bootstrapOfflineAccess,
};
