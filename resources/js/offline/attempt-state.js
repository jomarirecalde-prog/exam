import examOfflineDb from './db';

export async function saveAttemptState(attemptId, state) {
    await examOfflineDb.put(examOfflineDb.STORES.attemptSessions, {
        attempt_id: attemptId,
        ...state,
        updated_at: new Date().toISOString(),
    });
}

export async function getAttemptState(attemptId) {
    return examOfflineDb.get(examOfflineDb.STORES.attemptSessions, attemptId);
}

export async function persistExamProgress(attemptId, snapshot) {
    const existing = (await getAttemptState(attemptId)) || { attempt_id: attemptId };
    await saveAttemptState(attemptId, {
        ...existing,
        ...snapshot,
    });
}

export default { saveAttemptState, getAttemptState, persistExamProgress };
