const DISMISS_KEY = 'exam-pwa-install-dismissed';
const DISMISS_DAYS = 14;

export function pwaInstallPrompt() {
    return {
        deferredPrompt: null,
        canInstall: false,
        installed: false,
        dismissed: false,
        updateAvailable: false,
        updateDeferred: false,

        init() {
            this.dismissed = this.isDismissed();
            this.installed = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.deferredPrompt = event;
                this.canInstall = !this.installed && !this.dismissed;
            });

            window.addEventListener('appinstalled', () => {
                this.installed = true;
                this.canInstall = false;
                this.deferredPrompt = null;
            });

            window.addEventListener('pwa-update-available', (event) => {
                this.updateAvailable = true;
                this.updateDeferred = event.detail?.deferred ?? false;
            });
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
