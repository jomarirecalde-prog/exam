const DISMISS_KEY = 'exam-pwa-install-dismissed';
const DISMISS_DAYS = 14;

function detectSafari() {
    const ua = navigator.userAgent;
    return /Safari/i.test(ua) && !/Chrome|CriOS|Chromium|Edg|OPR|Opera|FxiOS/i.test(ua);
}

export function pwaInstallPrompt() {
    return {
        deferredPrompt: null,
        canInstall: false,
        installed: false,
        dismissed: false,
        updateAvailable: false,
        updateDeferred: false,
        manifestAvailable: null,
        serviceWorkerAvailable: 'serviceWorker' in navigator,
        isSafari: detectSafari(),
        statusMessage: '',

        init() {
            this.dismissed = this.isDismissed();
            this.installed = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.deferredPrompt = event;
                this.canInstall = !this.installed && !this.dismissed;
                this.statusMessage = '';
            });

            window.addEventListener('appinstalled', () => {
                this.installed = true;
                this.canInstall = false;
                this.deferredPrompt = null;
                this.statusMessage = '';
            });

            window.addEventListener('pwa-update-available', (event) => {
                this.updateAvailable = true;
                this.updateDeferred = event.detail?.deferred ?? false;
            });

            void this.refreshStatus();
        },

        async refreshStatus() {
            if (this.installed) {
                this.statusMessage = '';
                return;
            }

            try {
                const response = await fetch('/manifest.webmanifest', { method: 'GET', cache: 'no-store' });
                this.manifestAvailable = response.ok;
            } catch {
                this.manifestAvailable = false;
            }

            if (!this.serviceWorkerAvailable) {
                this.statusMessage = 'This browser does not support installing web applications.';
                return;
            }

            if (this.manifestAvailable === false) {
                this.statusMessage = 'The install manifest is unavailable. Refresh after the latest deployment finishes, or contact support if this persists.';
                return;
            }

            if (this.isSafari) {
                this.statusMessage = 'On Safari, use Share → Add to Dock (Mac) or Share → Add to Home Screen (iPhone/iPad).';
                return;
            }

            if (this.dismissed) {
                this.statusMessage = 'Install prompt dismissed. Clear site data or wait 14 days to see it again, or use the browser menu → Install app.';
                return;
            }

            if (!this.canInstall) {
                this.statusMessage = 'Install will appear when your browser offers it. Try Chrome or Edge on desktop, or use the browser menu → Install app.';
            }
        },

        isDismissed() {
            const raw = localStorage.getItem(DISMISS_KEY);
            if (!raw) {
                return false;
            }
            const dismissedAt = Number(raw);
            return Date.now() - dismissedAt < DISMISS_DAYS * 86400000;
        },

        async install() {
            if (!this.deferredPrompt) {
                return;
            }
            this.deferredPrompt.prompt();
            await this.deferredPrompt.userChoice;
            this.deferredPrompt = null;
            this.canInstall = false;
        },

        dismiss() {
            localStorage.setItem(DISMISS_KEY, String(Date.now()));
            this.dismissed = true;
            this.canInstall = false;
        },

        async applyUpdate() {
            const { applyPendingUpdate } = await import('./register-sw.js');
            await applyPendingUpdate();
        },
    };
}

window.pwaInstallPrompt = pwaInstallPrompt;

export default pwaInstallPrompt;
