<div
    x-data="deleteRecordModal()"
    @delete-record-open.window="openModal($event.detail)"
    @keydown.escape.window="closeModal()"
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="'delete-record-title'"
    >
        <div class="fixed inset-0 bg-navy-950/50" @click="closeModal()"></div>

        <div class="relative mx-auto mt-16 w-full max-w-md rounded-modal border border-line bg-surface shadow-pop sm:mt-24">
            <div class="px-6 py-5">
                <h2 id="delete-record-title" class="text-lg font-semibold text-ink" x-text="title"></h2>

                <div class="mt-4 rounded-card border border-line bg-canvas px-4 py-3">
                    <p class="text-sm text-muted">You are about to delete:</p>
                    <p class="mt-1 font-medium text-ink" x-text="recordName"></p>
                    <p class="mt-0.5 text-sm text-muted" x-show="recordDetail" x-text="recordDetail"></p>
                </div>

                <template x-if="blocked">
                    <div class="mt-4">
                        <p class="text-sm font-medium text-danger-ink">Cannot delete this record</p>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-sm leading-6 text-muted">
                            <template x-for="blocker in blockers" :key="blocker.label">
                                <li x-text="`${blocker.count} ${blocker.label}`"></li>
                            </template>
                        </ul>
                        <p class="mt-3 text-sm leading-6 text-muted" x-text="blockedMessage || 'Please reassign or remove the related records before deleting it.'"></p>
                    </div>
                </template>

                <template x-if="!blocked">
                    <p class="mt-4 text-sm leading-6 text-muted" x-text="warning"></p>
                </template>
            </div>

            <div class="flex justify-end gap-2 border-t border-line px-6 py-4">
                <x-ui.button variant="secondary" type="button" @click="closeModal()" ::disabled="submitting">
                    Cancel
                </x-ui.button>

                <form
                    x-show="!blocked"
                    method="post"
                    :action="action"
                    @submit="handleSubmit($event)"
                >
                    @csrf
                    <input type="hidden" name="_method" :value="method">
                    <x-ui.button variant="danger" type="submit" ::disabled="submitting">
                        <span x-show="!submitting" x-text="confirmLabel"></span>
                        <span x-show="submitting">Deleting...</span>
                    </x-ui.button>
                </form>
            </div>
        </div>
    </div>
</div>
