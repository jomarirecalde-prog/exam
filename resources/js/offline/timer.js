import examOfflineDb from './db';

export function createTimerState({ attemptId, remainingSeconds, timingToken, startedAt, expiresAt }) {
    return {
        attempt_id: attemptId,
        remaining_seconds: remainingSeconds,
        timing_token: timingToken,
        server_started_at: startedAt,
        server_expires_at: expiresAt,
        monotonic_start: performance.now(),
        last_tick_at: Date.now(),
        device_time_at_start: Date.now(),
    };
}

export async function persistTimer(state) {
    await examOfflineDb.saveTimerState(state.attempt_id, state);
}

export async function loadTimer(attemptId) {
    return examOfflineDb.getTimerState(attemptId);
}

export function computeRemaining(state) {
    if (!state) {
        return 0;
    }

    const elapsed = Math.floor((performance.now() - (state.monotonic_start || 0)) / 1000);
    const base = state.remaining_seconds ?? 0;
    const atSave = state.elapsed_at_save || 0;
    const remaining = Math.max(0, base - Math.max(0, elapsed - atSave));

    const deviceDrift = Math.abs(Date.now() - (state.device_time_at_start || Date.now()) - elapsed * 1000);
    if (deviceDrift > 120000) {
        state.time_anomaly = true;
    }

    return remaining;
}

export function tickTimer(state) {
    const remaining = computeRemaining(state);
    state.remaining_seconds = remaining;
    state.elapsed_at_save = Math.floor((performance.now() - (state.monotonic_start || 0)) / 1000);
    state.last_tick_at = Date.now();
    return remaining;
}

export default { createTimerState, persistTimer, loadTimer, computeRemaining, tickTimer };
