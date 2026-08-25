{{-- Violation history modal --}}
<div x-show="historyOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
    <div class="absolute inset-0 bg-navy-950/50" @click="historyOpen = false"></div>
    <div class="relative max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-modal border border-line bg-surface p-6 shadow-pop">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Student Violation History</h2>
                <p class="mt-1 text-sm text-muted" x-text="historyMeta"></p>
            </div>
            <button type="button" class="btn-icon" @click="historyOpen = false" aria-label="Close"><x-icon name="x" :size="18" /></button>
        </div>
        <div class="mt-5 space-y-4">
            <template x-if="historyLoading"><p class="text-sm text-muted">Loading violation history...</p></template>
            <template x-if="!historyLoading && historyItems.length === 0"><p class="text-sm text-muted">No violations recorded.</p></template>
            <template x-for="item in historyItems" :key="item.warning_number + item.detected_at_iso">
                <div class="rounded-card border border-line p-4">
                    <p class="font-medium" x-text="`Warning ${item.warning_number}`"></p>
                    <p class="mt-1 text-sm text-muted">Reason: <span x-text="item.type"></span></p>
                    <p class="text-sm text-muted">Time: <span x-text="item.detected_at"></span></p>
                </div>
            </template>
            <p class="text-sm font-medium text-danger-ink" x-show="historyLocked">Examination Locked</p>
            <p class="text-xs text-muted" x-show="historyOfflineNote" x-text="historyOfflineNote"></p>
        </div>
    </div>
</div>

{{-- Student drawer --}}
<div x-show="drawerOpen" x-cloak class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-navy-950/50" @click="drawerOpen = false"></div>
    <aside class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-line bg-surface shadow-pop">
        <div class="flex items-start justify-between gap-3 border-b border-line p-6">
            <div>
                <h2 class="text-lg font-semibold uppercase tracking-wide" x-text="drawerRow?.student_name"></h2>
                <p class="mt-1 text-sm text-muted" x-text="drawerRow?.status_label"></p>
            </div>
            <button type="button" class="btn-icon" @click="drawerOpen = false" aria-label="Close"><x-icon name="x" :size="18" /></button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <template x-if="drawerLoading"><p class="text-sm text-muted">Loading student details...</p></template>
            <template x-if="!drawerLoading && drawerRow">
                <div class="space-y-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">Student Information</p>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-muted">Student ID</dt><dd x-text="drawerRow.student_id"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted">Section</dt><dd x-text="drawerRow.section"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted">Program</dt><dd x-text="drawerRow.program"></dd></div>
                        </dl>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted">Examination Progress</p>
                        <div class="mt-3">
                            <div class="h-2 overflow-hidden rounded-full bg-canvas">
                                <div class="h-full rounded-full bg-brand" :style="`width: ${drawerRow.progress_percent}%`"></div>
                            </div>
                            <p class="mt-2 text-sm" x-text="`${drawerRow.answered_count} of ${drawerRow.total_questions} Questions Answered (${drawerRow.progress_percent}%)`"></p>
                        </div>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-muted">Current Question</dt><dd x-text="drawerRow.current_question_label"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted">Remaining Time</dt><dd x-text="drawerRow.remaining_time_label"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted">Warnings</dt><dd x-text="`${drawerRow.warning_count} / ${drawerRow.max_warnings}`"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted">Connection</dt><dd x-text="drawerRow.connection_label"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted">Last Activity</dt><dd x-text="drawerRow.last_activity_label"></dd></div>
                        </dl>
                        <p class="mt-3 rounded-card bg-warning-soft px-3 py-2 text-xs text-warning-ink" x-show="drawerRow.reactivation_pending">
                            Reactivation pending — the student is offline. Access will restore after synchronization.
                        </p>
                        <p class="mt-3 rounded-card bg-brand-soft px-3 py-2 text-xs text-muted" x-show="drawerRow.connection_detail && drawerRow.connection_status === 'offline'" x-text="drawerRow.connection_detail"></p>
                    </div>
                </div>
            </template>
        </div>
        <div class="flex flex-wrap gap-2 border-t border-line p-6" x-show="drawerRow">
            <button type="button" class="btn-secondary" @click="viewViolations(drawerRow)">View Violations</button>
            <button type="button" class="btn-primary" x-show="drawerRow?.can_reactivate" @click="openReactivate(drawerRow)">Reactivate Exam</button>
        </div>
    </aside>
</div>

{{-- Reactivate modal --}}
<div x-show="reactivateOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
    <div class="absolute inset-0 bg-navy-950/50" @click="!reactivateSubmitting && (reactivateOpen = false)"></div>
    <div class="relative w-full max-w-lg rounded-modal border border-line bg-surface p-6 shadow-pop">
        <h2 class="text-lg font-semibold">Reactivate Examination?</h2>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex gap-2"><dt class="w-28 shrink-0 text-muted">Student:</dt><dd class="font-medium" x-text="reactivateRow?.student_name"></dd></div>
            <div class="flex gap-2"><dt class="w-28 shrink-0 text-muted">Current Status:</dt><dd x-text="reactivateRow?.status_label"></dd></div>
            <div class="flex gap-2"><dt class="w-28 shrink-0 text-muted">Warnings:</dt><dd x-text="`${reactivateRow?.warning_count}/${reactivateRow?.max_warnings}`"></dd></div>
        </dl>
        <p class="mt-4 text-sm leading-6 text-muted" x-show="reactivateRow?.connection_status === 'offline'">
            The student is currently offline. The examination will be reactivated on the student's device after reconnection and synchronization.
        </p>
        <p class="mt-4 text-sm leading-6 text-muted" x-show="reactivateRow?.connection_status !== 'offline'">
            Reactivating allows the student to continue. Previous answers and violation history are preserved.
        </p>
        <div class="mt-5">
            <label class="text-sm font-medium">Optional Notes</label>
            <textarea class="mt-2 w-full rounded-card border border-line bg-canvas px-3 py-2 text-sm" rows="3" x-model="reactivationReason" placeholder="Reason for reactivation"></textarea>
        </div>
        <fieldset class="mt-5 space-y-2">
            <legend class="text-sm font-medium">After Reactivation:</legend>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="warning_mode" value="reset" x-model="warningMode"> Reset warnings to 0</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="warning_mode" value="keep" x-model="warningMode"> Keep existing warnings</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="warning_mode" value="manual" x-model="warningMode"> Set warning count manually</label>
            <div x-show="warningMode === 'manual'" class="pt-2">
                <input type="number" min="0" :max="maxWarnings" class="w-24 rounded-card border border-line bg-canvas px-3 py-2 text-sm" x-model.number="manualWarningCount">
            </div>
        </fieldset>
        <p class="mt-3 text-sm text-danger-ink" x-show="reactivateError" x-text="reactivateError"></p>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn-secondary" @click="reactivateOpen = false" :disabled="reactivateSubmitting">Cancel</button>
            <button type="button" class="btn-primary" @click="submitReactivate()" :disabled="reactivateSubmitting">Reactivate Examination</button>
        </div>
    </div>
</div>

<div class="pointer-events-none fixed bottom-4 right-4 z-50 space-y-2" x-show="notifications.length > 0">
    <template x-for="note in notifications" :key="note.id">
        <div
            class="pointer-events-auto max-w-sm rounded-card border px-4 py-3 text-sm shadow-pop"
            :class="note.severity === 'critical' ? 'border-danger-ink bg-danger-soft text-danger-ink' : 'border-line bg-surface text-ink'"
            x-text="note.message"
        ></div>
    </template>
</div>
