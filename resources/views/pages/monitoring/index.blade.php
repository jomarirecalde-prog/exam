<x-app-layout>
    <div class="ui-page" x-data="examMonitoring(@js([
        'examinations' => $active->map(fn ($exam) => [
            'id' => $exam->id,
            'title' => $exam->title,
            'subject' => $exam->subject?->code,
            'sections' => $exam->sections->pluck('name')->filter()->join(', ') ?: 'Unassigned',
            'dataUrl' => route('monitoring.data', $exam),
        ])->values()->all(),
        'violationsUrl' => route('monitoring.violations', ['attempt' => '__ATTEMPT__']),
        'reactivateUrl' => route('monitoring.reactivate', ['attempt' => '__ATTEMPT__']),
        'maxWarnings' => config('examination.max_violation_warnings', 3),
    ]))">
        <x-ui.page-header title="Examination Monitoring" subtitle="Monitor student attempts, violations, and reactivate locked examinations." />

        @if ($active->isEmpty())
            <x-ui.card>
                <x-ui.empty-state title="No examinations available for monitoring." icon="activity">
                    Published or active examinations assigned to you will appear here.
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="mb-6 flex flex-wrap gap-2">
                <template x-for="exam in examinations" :key="exam.id">
                    <button
                        type="button"
                        class="rounded-btn border px-4 py-2 text-sm"
                        :class="selectedExamId === exam.id ? 'border-brand bg-brand text-white dark:text-navy-950' : 'border-line bg-surface hover:bg-brand-soft'"
                        @click="selectExam(exam)"
                        x-text="exam.title"
                    ></button>
                </template>
            </div>

            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <p class="font-semibold" x-text="selectedExam?.title || 'Select an examination'"></p>
                        <p class="text-sm text-muted" x-show="selectedExam">
                            <span x-text="selectedExam?.subject"></span>
                            ·
                            <span x-text="selectedExam?.sections"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-muted">
                        <span x-show="loading">Updating...</span>
                        <span x-show="!loading && lastUpdated">Updated <span x-text="lastUpdated"></span></span>
                        <button type="button" class="btn-secondary btn-sm" @click="refresh()" :disabled="!selectedExam || loading">Refresh</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-line bg-canvas text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-5 py-3 font-medium">Student</th>
                                <th class="px-5 py-3 font-medium">Student ID</th>
                                <th class="px-5 py-3 font-medium">Warnings</th>
                                <th class="px-5 py-3 font-medium">Latest Violation</th>
                                <th class="px-5 py-3 font-medium">Date & Time</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-if="!selectedExam">
                                <tr>
                                    <td colspan="7" class="px-5 py-8 text-center text-muted">Select an examination to view monitoring data.</td>
                                </tr>
                            </template>
                            <template x-if="selectedExam && attempts.length === 0 && !loading">
                                <tr>
                                    <td colspan="7" class="px-5 py-8 text-center text-muted">No active or recent attempts for this examination.</td>
                                </tr>
                            </template>
                            <template x-for="row in attempts" :key="row.attempt_id">
                                <tr class="border-b border-line last:border-0">
                                    <td class="px-5 py-4 font-medium" x-text="row.student_name || '—'"></td>
                                    <td class="px-5 py-4 tabular-nums text-muted" x-text="row.student_id || '—'"></td>
                                    <td class="px-5 py-4 tabular-nums">
                                        <span
                                            :class="row.warning_count >= row.max_warnings ? 'text-danger-ink font-semibold' : (row.warning_count > 0 ? 'text-warning-ink' : 'text-muted')"
                                            x-text="`${row.warning_count} / ${row.max_warnings}`"
                                        ></span>
                                    </td>
                                    <td class="px-5 py-4 text-muted" x-text="row.latest_violation || '—'"></td>
                                    <td class="px-5 py-4 text-muted" x-text="row.latest_violation_at || '—'"></td>
                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="{
                                                'bg-brand-soft text-brand': row.status === 'IN_PROGRESS',
                                                'bg-danger-soft text-danger-ink': row.status === 'LOCKED_VIOLATION_LIMIT',
                                                'bg-canvas text-muted': row.status !== 'IN_PROGRESS' && row.status !== 'LOCKED_VIOLATION_LIMIT',
                                            }"
                                            x-text="row.status_label"
                                        ></span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="btn-secondary btn-sm" @click="viewViolations(row)">History</button>
                                            <button
                                                type="button"
                                                class="btn-primary btn-sm"
                                                x-show="row.can_reactivate"
                                                @click="openReactivate(row)"
                                            >Reactivate</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif

        {{-- Violation history modal --}}
        <div x-show="historyOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-navy-950/50" @click="historyOpen = false"></div>
            <div class="relative max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-modal border border-line bg-surface p-6 shadow-pop">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Violation History</h2>
                        <p class="mt-1 text-sm text-muted" x-text="historyMeta"></p>
                    </div>
                    <button type="button" class="btn-icon" @click="historyOpen = false" aria-label="Close"><x-icon name="x" :size="18" /></button>
                </div>
                <div class="mt-5 space-y-4">
                    <template x-if="historyLoading">
                        <p class="text-sm text-muted">Loading violation history...</p>
                    </template>
                    <template x-if="!historyLoading && historyItems.length === 0">
                        <p class="text-sm text-muted">No violations recorded.</p>
                    </template>
                    <template x-for="item in historyItems" :key="item.warning_number + item.detected_at_iso">
                        <div class="rounded-card border border-line p-4">
                            <p class="font-medium" x-text="`Warning ${item.warning_number}`"></p>
                            <p class="mt-1 text-sm text-muted">Type: <span x-text="item.type"></span></p>
                            <p class="text-sm text-muted">Time: <span x-text="item.detected_at"></span></p>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Reactivate modal --}}
        <div x-show="reactivateOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-navy-950/50" @click="!reactivateSubmitting && (reactivateOpen = false)"></div>
            <div class="relative w-full max-w-lg rounded-modal border border-line bg-surface p-6 shadow-pop">
                <h2 class="text-lg font-semibold">Reactivate Examination?</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted">Student:</dt>
                        <dd class="font-medium" x-text="reactivateRow?.student_name"></dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted">Examination:</dt>
                        <dd x-text="selectedExam?.title"></dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-28 shrink-0 text-muted">Reason for Lock:</dt>
                        <dd x-text="reactivateRow?.lock_reason || 'Maximum violation warnings reached'"></dd>
                    </div>
                </dl>
                <p class="mt-4 text-sm leading-6 text-muted">
                    The student's previous answers and examination progress will remain available.
                </p>

                <div class="mt-5">
                    <label class="text-sm font-medium">Reactivation Reason *</label>
                    <textarea
                        class="mt-2 w-full rounded-card border border-line bg-canvas px-3 py-2 text-sm"
                        rows="3"
                        x-model="reactivationReason"
                        placeholder="e.g. False detection due to technical issue"
                    ></textarea>
                </div>

                <fieldset class="mt-5 space-y-2">
                    <legend class="text-sm font-medium">After Reactivation:</legend>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="warning_mode" value="reset" x-model="warningMode">
                        Reset warnings to 0
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="warning_mode" value="keep" x-model="warningMode">
                        Keep existing warnings
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="warning_mode" value="manual" x-model="warningMode">
                        Set warning count manually
                    </label>
                    <div x-show="warningMode === 'manual'" class="pt-2">
                        <input
                            type="number"
                            min="0"
                            :max="maxWarnings"
                            class="w-24 rounded-card border border-line bg-canvas px-3 py-2 text-sm"
                            x-model.number="manualWarningCount"
                        >
                    </div>
                </fieldset>

                <p class="mt-3 text-sm text-danger-ink" x-show="reactivateError" x-text="reactivateError"></p>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="reactivateOpen = false" :disabled="reactivateSubmitting">Cancel</button>
                    <button type="button" class="btn-primary" @click="submitReactivate()" :disabled="reactivateSubmitting">Reactivate Examination</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
