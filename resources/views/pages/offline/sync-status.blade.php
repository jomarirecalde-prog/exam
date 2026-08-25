<x-app-layout>
    <div class="ui-page" x-data="examSyncStatus(@js([
        'syncUrlTemplate' => route('exam-attempts.sync', ['attempt' => '__ATTEMPT__']),
        'statusUrl' => route('sync.status'),
    ]))">
        <x-ui.page-header
            title="Synchronization Status"
            subtitle="Review pending offline examination changes and retry synchronization."
        />

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="ui-card ui-card-pad">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-muted">Connection</p>
                        <p class="mt-1 flex items-center gap-2 text-lg font-semibold">
                            <span
                                class="inline-block h-2.5 w-2.5 rounded-full"
                                :class="online ? 'bg-success-ink' : 'bg-warning-ink'"
                            ></span>
                            <span x-text="online ? 'Online' : 'Offline'"></span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-muted">Pending Changes</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums" x-text="pendingCount"></p>
                    </div>
                </div>

                <p class="mt-4 text-sm text-muted" x-show="lastSyncedAt">
                    Last successful sync: <span x-text="lastSyncedAt"></span>
                </p>

                <div class="mt-6 space-y-2" x-show="queue.length" x-cloak>
                    <template x-for="item in queue" :key="item.id">
                        <div class="flex items-center justify-between rounded-card border border-line px-4 py-3 text-sm">
                            <span x-text="formatEvent(item)"></span>
                            <span class="text-warning-ink" x-text="item.sync_status === 'failed' ? 'Failed' : 'Pending'"></span>
                        </div>
                    </template>
                </div>

                <x-ui.empty-state
                    x-show="!queue.length && !loading"
                    x-cloak
                    title="No pending changes"
                    icon="check-circle"
                >
                    All offline examination data is synchronized.
                </x-ui.empty-state>

                <div class="mt-6 flex gap-2">
                    <button type="button" class="btn-primary" :disabled="!online || syncing || !pendingCount" @click="retrySync()">
                        <span x-show="!syncing">Retry Synchronization</span>
                        <span x-show="syncing">Synchronizing...</span>
                    </button>
                    <a href="{{ route('examinations.index') }}" class="btn-secondary">Back to Examinations</a>
                </div>

                <p class="mt-4 text-sm text-danger-ink" x-show="error" x-text="error"></p>
            </section>

            <aside class="ui-card ui-card-pad">
                <p class="text-sm font-medium">Offline reminders</p>
                <ul class="mt-3 space-y-2 text-sm leading-6 text-muted">
                    <li>Do not clear browser or application data until synchronization completes.</li>
                    <li>Do not uninstall the application while submissions are pending.</li>
                    <li>Use the same device that prepared the examination.</li>
                </ul>
            </aside>
        </div>
    </div>
</x-app-layout>
