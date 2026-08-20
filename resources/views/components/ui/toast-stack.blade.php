<div class="pointer-events-none fixed inset-x-0 bottom-0 z-[70] flex flex-col items-center gap-2 p-4 sm:items-end" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="toast">
            <span class="mt-0.5 text-base leading-none" x-text="toast.type === 'success' ? '✓' : (toast.type === 'warning' ? '⚠' : (toast.type === 'error' ? '✕' : 'ℹ'))"></span>
            <p class="flex-1 pt-0.5 text-ink" x-text="toast.message"></p>
            <button type="button" class="pointer-events-auto btn-icon h-7 w-7" @click="removeToast(toast.id)" aria-label="Dismiss notification">✕</button>
        </div>
    </template>
</div>
