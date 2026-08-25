const SW_URL = '/sw.js';
const UPDATE_DEFER_KEY = 'exam-sw-update-deferred';

let registration = null;

function cacheCurrentBuildAssets() {
    const urls = [
        ...document.querySelectorAll('script[src*="/build/"], link[href*="/build/"]'),
    ].map((el) => el.src || el.href).filter(Boolean);

    if (urls.length && navigator.serviceWorker?.controller) {
        navigator.serviceWorker.controller.postMessage({
            type: 'CACHE_BUILD_ASSETS',
            urls,
        });
    }
}

export async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    try {
        registration = await navigator.serviceWorker.register(SW_URL, { scope: '/' });

        registration.addEventListener('updatefound', () => {
            const installing = registration.installing;
            if (!installing) {
                return;
            }
            installing.addEventListener('statechange', async () => {
                if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                    const hasActiveExam = localStorage.getItem('exam-active-session') === '1';
                    if (hasActiveExam) {
                        localStorage.setItem(UPDATE_DEFER_KEY, '1');
                        window.dispatchEvent(new CustomEvent('pwa-update-available', { detail: { deferred: true } }));
                    } else {
                        window.dispatchEvent(new CustomEvent('pwa-update-available', { detail: { deferred: false } }));
                    }
                }
            });
        });

        if (navigator.serviceWorker.controller) {
            cacheCurrentBuildAssets();
        } else {
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                cacheCurrentBuildAssets();
            }, { once: true });
        }

        return registration;
    } catch (error) {
        console.warn('Service worker registration failed:', error);
        return null;
    }
}

export async function applyPendingUpdate() {
    if (!registration?.waiting) {
        return;
    }
    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    localStorage.removeItem(UPDATE_DEFER_KEY);
    window.location.reload();
}

export function getRegistration() {
    return registration;
}

export default { registerServiceWorker, applyPendingUpdate, getRegistration };
