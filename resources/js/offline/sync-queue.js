import examOfflineDb from './db';

function uuid() {
    return crypto.randomUUID();
}

export async function enqueueSyncEvent({ attemptId, eventType, payload }) {
    const id = uuid();
    const event = {
        id,
        client_event_uuid: id,
        event_type: eventType,
        exam_attempt_id: attemptId,
        payload: payload || {},
        created_at: new Date().toISOString(),
        sync_status: 'pending',
        retry_count: 0,
        last_error: null,
    };

    await examOfflineDb.put(examOfflineDb.STORES.syncQueue, event);
    return event;
}

export async function getPendingEvents(attemptId = null) {
    const all = await examOfflineDb.getAll(examOfflineDb.STORES.syncQueue);
    return all
        .filter((event) => event.sync_status === 'pending' || event.sync_status === 'failed')
        .filter((event) => (attemptId ? event.exam_attempt_id === attemptId : true))
        .sort((a, b) => a.created_at.localeCompare(b.created_at));
}

export async function markEventSynced(eventId, result = null) {
    const event = await examOfflineDb.get(examOfflineDb.STORES.syncQueue, eventId);
    if (!event) {
        return;
    }
    event.sync_status = 'synced';
    event.synced_at = new Date().toISOString();
    event.result = result;
    await examOfflineDb.put(examOfflineDb.STORES.syncQueue, event);
}

export async function markEventFailed(eventId, error) {
    const event = await examOfflineDb.get(examOfflineDb.STORES.syncQueue, eventId);
    if (!event) {
        return;
    }
    event.sync_status = 'failed';
    event.retry_count = (event.retry_count || 0) + 1;
    event.last_error = String(error?.message || error || 'Sync failed');
    await examOfflineDb.put(examOfflineDb.STORES.syncQueue, event);
}

export async function getQueueSummary() {
    const all = await examOfflineDb.getAll(examOfflineDb.STORES.syncQueue);
    const pending = all.filter((e) => e.sync_status === 'pending' || e.sync_status === 'failed');
    const lastSynced = all
        .filter((e) => e.sync_status === 'synced')
        .sort((a, b) => (b.synced_at || '').localeCompare(a.synced_at || ''))[0];

    return {
        pendingCount: pending.length,
        pending,
        lastSyncedAt: lastSynced?.synced_at || null,
    };
}

export default { enqueueSyncEvent, getPendingEvents, markEventSynced, markEventFailed, getQueueSummary };
