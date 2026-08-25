import examOfflineDb from './db';
import { getDeviceIdentifier } from './device';
import { getPendingEvents, markEventSynced, markEventFailed } from './sync-queue';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

export function isOnline() {
    return navigator.onLine;
}

export async function verifyServerReachable(pingUrl = '/dashboard') {
    if (!navigator.onLine) {
        return false;
    }
    try {
        const response = await fetch(pingUrl, {
            method: 'HEAD',
            credentials: 'same-origin',
            cache: 'no-store',
        });
        return response.ok || response.status === 302 || response.status === 401;
    } catch {
        return false;
    }
}

export async function syncAttempt(attemptId, syncUrl) {
    if (!navigator.onLine) {
        return { synced: false, reason: 'offline' };
    }

    const reachable = await verifyServerReachable();
    if (!reachable) {
        return { synced: false, reason: 'server_unreachable' };
    }

    const pending = await getPendingEvents(attemptId);
    if (pending.length === 0) {
        return { synced: true, processed: 0 };
    }

    const response = await fetch(syncUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            device_identifier: getDeviceIdentifier(),
            events: pending.map((event) => ({
                client_event_uuid: event.client_event_uuid,
                event_type: event.event_type,
                payload: event.payload,
            })),
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 409) {
            return { synced: false, conflicts: data.conflicts || [], reason: 'conflict' };
        }
        pending.forEach((event) => markEventFailed(event.id, data.message || 'Sync failed'));
        return { synced: false, reason: data.message || 'sync_failed' };
    }

    for (const result of data.results || []) {
        const event = pending.find((e) => e.client_event_uuid === result.client_event_uuid);
        if (!event) {
            continue;
        }
        const status = result.status || 'processed';
        if (status === 'processed' || status === 'duplicate' || status === 'skipped') {
            await markEventSynced(event.id, result.result);
            if (result.result?.question_id) {
                await examOfflineDb.markAnswerSynced(attemptId, result.result.question_id);
            }
        }
    }

    return {
        synced: true,
        processed: data.processed || 0,
        duplicates: data.duplicates || 0,
        attempt: data.attempt || null,
        conflicts: data.conflicts || [],
    };
}

export async function syncAllPending(syncUrlTemplate) {
    const all = await getPendingEvents();
    const attemptIds = [...new Set(all.map((e) => e.exam_attempt_id))];
    const results = [];

    for (const attemptId of attemptIds) {
        const url = syncUrlTemplate.replace('__ATTEMPT__', attemptId);
        results.push({ attemptId, ...(await syncAttempt(attemptId, url)) });
    }

    return results;
}

export function installSyncListeners(onOnline) {
    window.addEventListener('online', () => {
        onOnline?.();
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && navigator.onLine) {
            onOnline?.();
        }
    });
}

export default { isOnline, verifyServerReachable, syncAttempt, syncAllPending, installSyncListeners };
