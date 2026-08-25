import examOfflineDb from './db';

const PIN_HASH_KEY = 'app_pin_hash';
const UNLOCK_KEY = 'app_unlocked_at';

async function hashPin(pin) {
    const data = new TextEncoder().encode(pin);
    const digest = await crypto.subtle.digest('SHA-256', data);
    return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
}

export async function setLocalPin(pin) {
    const hash = await hashPin(pin);
    await examOfflineDb.setMeta(PIN_HASH_KEY, hash);
}

export async function clearLocalPin() {
    await examOfflineDb.setMeta(PIN_HASH_KEY, null);
    await examOfflineDb.setMeta(UNLOCK_KEY, null);
}

export async function isPinConfigured() {
    const hash = await examOfflineDb.getMeta(PIN_HASH_KEY);
    return Boolean(hash);
}

export async function verifyPin(pin) {
    const stored = await examOfflineDb.getMeta(PIN_HASH_KEY);
    if (!stored) {
        return true;
    }
    const hash = await hashPin(pin);
    if (hash === stored) {
        await examOfflineDb.setMeta(UNLOCK_KEY, Date.now());
        return true;
    }
    return false;
}

export async function isUnlocked() {
    const configured = await isPinConfigured();
    if (!configured) {
        return true;
    }
    const unlockedAt = await examOfflineDb.getMeta(UNLOCK_KEY);
    if (!unlockedAt) {
        return false;
    }
    return Date.now() - Number(unlockedAt) < 30 * 60 * 1000;
}

export async function lockApp() {
    await examOfflineDb.setMeta(UNLOCK_KEY, null);
}

export default { setLocalPin, clearLocalPin, isPinConfigured, verifyPin, isUnlocked, lockApp };
