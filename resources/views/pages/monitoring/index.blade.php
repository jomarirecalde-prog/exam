<x-app-layout>
    <div class="ui-page" x-data="examMonitoring(@js([
        'examinations' => $active->values()->all(),
        'violationsUrl' => route('monitoring.violations', ['attempt' => '__ATTEMPT__']),
        'reactivateUrl' => route('monitoring.reactivate', ['attempt' => '__ATTEMPT__']),
        'attemptUrl' => route('monitoring.attempts.show', ['attempt' => '__ATTEMPT__']),
        'maxWarnings' => config('examination.max_violation_warnings', 3),
    ]))">
        <x-ui.page-header
            title="Examination Monitoring"
            subtitle="Monitor student progress, violations, and connection status in real time."
        />

        @if ($active->isEmpty())
            <x-ui.card>
                <x-ui.empty-state title="No active examinations" icon="activity">
                    Published or active examinations assigned to you will appear here when students can take them.
                </x-ui.empty-state>
            </x-ui.card>
        @else
            {{-- Active examinations list --}}
            <section class="mb-8">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted">My Active Examinations</h2>
                <x-ui.card class="overflow-hidden" :padding="false">
                    <template x-if="examinations.length === 0">
                        <div class="px-5 py-8 text-center text-sm text-muted">No active examinations found.</div>
                    </template>
                    <div class="divide-y divide-line">
                        <template x-for="exam in examinations" :key="exam.id">
                            <div
                                class="flex flex-col gap-4 px-5 py-5 transition sm:flex-row sm:items-center sm:justify-between"
                                :class="selectedExamId === exam.id ? 'bg-brand-soft/50' : 'hover:bg-brand-soft/30'"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted" x-text="exam.subject_code || exam.subject"></p>
                                    <h3 class="mt-1 text-lg font-semibold" x-text="exam.title"></h3>
                                    <p class="mt-1 text-sm text-muted">
                                        Section: <span x-text="exam.sections"></span>
                                    </p>
                                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                                        <span
                                            class="inline-flex items-center gap-1.5 font-medium"
                                            :class="exam.is_live ? 'text-success-ink' : 'text-muted'"
                                        >
                                            <span class="h-2 w-2 rounded-full" :class="exam.is_live ? 'bg-success-ink animate-pulse' : 'bg-muted'"></span>
                                            <span x-text="exam.is_live ? 'LIVE' : 'Scheduled'"></span>
                                        </span>
                                        <span class="text-muted">
                                            Students taking exam:
                                            <span class="font-semibold text-ink" x-text="examCardStats(exam.id).taking"></span>
                                            /
                                            <span x-text="examCardStats(exam.id).total || '—'"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <button
                                        type="button"
                                        class="btn-primary w-full sm:w-auto"
                                        @click="selectExam(exam)"
                                        x-text="selectedExamId === exam.id ? 'Monitoring' : 'Monitor Examination'"
                                    ></button>
                                </div>
                            </div>
                        </template>
                    </div>
                </x-ui.card>
            </section>

            <template x-if="!selectedExam">
                <x-ui.card>
                    <x-ui.empty-state title="Select an examination to monitor." icon="activity">
                        Choose an active examination from the list above to view student progress, violations, and connection status.
                    </x-ui.empty-state>
                </x-ui.card>
            </template>

            <template x-if="selectedExam">
                <div class="space-y-6">
                    {{-- Summary cards --}}
                    <section>
                        <div class="mb-4">
                            <h2 class="text-xl font-semibold" x-text="examination?.title"></h2>
                            <p class="text-sm text-muted">
                                <span x-text="examination?.subject"></span>
                                ·
                                <span x-text="examination?.sections"></span>
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                            @foreach ([
                                ['key' => 'total', 'label' => 'Total Students'],
                                ['key' => 'taking_exam', 'label' => 'Taking Exam'],
                                ['key' => 'not_started', 'label' => 'Not Started'],
                                ['key' => 'submitted', 'label' => 'Submitted'],
                                ['key' => 'offline', 'label' => 'Offline'],
                                ['key' => 'locked', 'label' => 'Locked / Violated'],
                            ] as $stat)
                                <div class="ui-card ui-card-pad">
                                    <p class="text-sm text-muted">{{ $stat['label'] }}</p>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-ink" x-text="summary.{{ $stat['key'] }} ?? 0"></p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                        {{-- Main table / cards --}}
                        <x-ui.card>
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input
                                        type="search"
                                        class="w-full min-w-[200px] rounded-card border border-line bg-canvas px-3 py-2 text-sm sm:w-56"
                                        placeholder="Search name or ID..."
                                        x-model.debounce.300ms="searchQuery"
                                    />
                                    <select class="rounded-card border border-line bg-canvas px-3 py-2 text-sm" x-model="statusFilter">
                                        <option value="all">All Students</option>
                                        <option value="taking_exam">Taking Exam</option>
                                        <option value="not_started">Not Started</option>
                                        <option value="offline">Offline</option>
                                        <option value="locked">Locked</option>
                                        <option value="submitted">Submitted</option>
                                        <option value="pending_submission">Pending Submission</option>
                                    </select>
                                    <select class="rounded-card border border-line bg-canvas px-3 py-2 text-sm" x-model="sortBy">
                                        <option value="priority">Needs Attention</option>
                                        <option value="progress">Progress</option>
                                        <option value="remaining">Remaining Time</option>
                                        <option value="warnings">Warnings</option>
                                        <option value="activity">Last Activity</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 text-sm text-muted">
                                    <span class="inline-flex items-center gap-1.5" x-show="polling">
                                        <span class="h-2 w-2 animate-pulse rounded-full bg-brand"></span>
                                        Live
                                    </span>
                                    <span x-show="lastUpdated">Updated <span x-text="lastUpdated"></span></span>
                                </div>
                            </div>

                            {{-- Desktop table --}}
                            <div class="hidden overflow-x-auto lg:block">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="border-b border-line bg-canvas text-xs uppercase tracking-wide text-muted">
                                        <tr>
                                            <th class="px-5 py-3 font-medium">Student</th>
                                            <th class="px-5 py-3 font-medium">Status</th>
                                            <th class="px-5 py-3 font-medium">Progress</th>
                                            <th class="px-5 py-3 font-medium">Current Question</th>
                                            <th class="px-5 py-3 font-medium">Time Left</th>
                                            <th class="px-5 py-3 font-medium">Warnings</th>
                                            <th class="px-5 py-3 font-medium">Connection</th>
                                            <th class="px-5 py-3 font-medium">Last Activity</th>
                                            <th class="px-5 py-3 font-medium">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-if="loading && filteredStudents.length === 0">
                                            <tr><td colspan="9" class="px-5 py-10 text-center text-muted">Loading monitoring data...</td></tr>
                                        </template>
                                        <template x-if="!loading && filteredStudents.length === 0">
                                            <tr><td colspan="9" class="px-5 py-10 text-center text-muted">No students match your filters.</td></tr>
                                        </template>
                                        <template x-for="row in filteredStudents" :key="row.student_db_id + '-' + (row.attempt_id || 'none')">
                                            <tr
                                                class="cursor-pointer border-b border-line last:border-0 hover:bg-brand-soft/40"
                                                @click="openStudent(row)"
                                            >
                                                <td class="px-5 py-4">
                                                    <p class="font-medium" x-text="row.student_name"></p>
                                                    <p class="text-xs text-muted" x-text="row.student_id"></p>
                                                </td>
                                                <td class="px-5 py-4">
                                                    <span class="badge badge-dot" :class="statusBadgeClass(row.monitoring_status)" x-text="row.status_label"></span>
                                                </td>
                                                <td class="px-5 py-4 min-w-[140px]">
                                                    <div class="flex items-center gap-2">
                                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-canvas">
                                                            <div class="h-full rounded-full bg-brand transition-all" :style="`width: ${row.progress_percent}%`"></div>
                                                        </div>
                                                        <span class="w-10 text-right tabular-nums text-xs" x-text="`${row.progress_percent}%`"></span>
                                                    </div>
                                                    <p class="mt-1 text-xs text-muted" x-text="`${row.answered_count} / ${row.total_questions} answered`"></p>
                                                </td>
                                                <td class="px-5 py-4 text-muted" x-text="row.current_question_label"></td>
                                                <td class="px-5 py-4 tabular-nums" x-text="row.remaining_time_label"></td>
                                                <td class="px-5 py-4">
                                                    <button
                                                        type="button"
                                                        class="tabular-nums underline-offset-2 hover:underline"
                                                        :class="row.warning_count >= row.max_warnings ? 'text-danger-ink font-semibold' : (row.warning_count > 0 ? 'text-warning-ink' : 'text-muted')"
                                                        @click.stop="viewViolations(row)"
                                                        x-text="`${row.warning_count} / ${row.max_warnings}`"
                                                    ></button>
                                                </td>
                                                <td class="px-5 py-4">
                                                    <span class="inline-flex items-center gap-1.5 text-xs" :class="connectionClass(row.connection_status)">
                                                        <span class="h-1.5 w-1.5 rounded-full" :class="connectionDotClass(row.connection_status)"></span>
                                                        <span x-text="row.connection_label"></span>
                                                    </span>
                                                </td>
                                                <td class="px-5 py-4 text-muted" x-text="row.last_activity_label"></td>
                                                <td class="px-5 py-4" @click.stop>
                                                    <div class="flex gap-2">
                                                        <button type="button" class="btn-secondary btn-sm" @click="viewViolations(row)">Violations</button>
                                                        <button type="button" class="btn-primary btn-sm" x-show="row.can_reactivate" @click="openReactivate(row)">Reactivate</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Mobile cards --}}
                            <div class="space-y-3 p-4 lg:hidden">
                                <template x-if="loading && filteredStudents.length === 0">
                                    <p class="py-8 text-center text-sm text-muted">Loading monitoring data...</p>
                                </template>
                                <template x-for="row in filteredStudents" :key="'m-' + row.student_db_id + '-' + (row.attempt_id || 'none')">
                                    <article class="rounded-card border border-line p-4" @click="openStudent(row)">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-medium" x-text="row.student_name"></p>
                                                <p class="text-xs text-muted" x-text="row.student_id"></p>
                                            </div>
                                            <span class="badge badge-dot" :class="statusBadgeClass(row.monitoring_status)" x-text="row.status_label"></span>
                                        </div>
                                        <div class="mt-3">
                                            <div class="flex items-center justify-between text-xs text-muted">
                                                <span x-text="`${row.answered_count}/${row.total_questions} answered`"></span>
                                                <span x-text="`${row.progress_percent}%`"></span>
                                            </div>
                                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-canvas">
                                                <div class="h-full rounded-full bg-brand" :style="`width: ${row.progress_percent}%`"></div>
                                            </div>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                            <div><dt class="text-muted">Question</dt><dd x-text="row.current_question_label"></dd></div>
                                            <div><dt class="text-muted">Time</dt><dd x-text="row.remaining_time_label"></dd></div>
                                            <div><dt class="text-muted">Warnings</dt><dd x-text="`${row.warning_count}/${row.max_warnings}`"></dd></div>
                                            <div><dt class="text-muted">Connection</dt><dd x-text="row.connection_label"></dd></div>
                                        </dl>
                                    </article>
                                </template>
                            </div>
                        </x-ui.card>

                        {{-- Activity feed --}}
                        <aside class="ui-card ui-card-pad h-fit xl:sticky xl:top-6">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="font-semibold">Live Activity</h3>
                                <button type="button" class="text-xs text-muted hover:text-ink" @click="showAllActivity = !showAllActivity" x-text="showAllActivity ? 'Show recent' : 'Show all'"></button>
                            </div>
                            <div class="mt-4 max-h-[520px] space-y-3 overflow-y-auto">
                                <template x-if="visibleActivities.length === 0">
                                    <p class="text-sm text-muted">Activity will appear here as students progress.</p>
                                </template>
                                <template x-for="item in visibleActivities" :key="item.id">
                                    <div class="border-b border-line pb-3 last:border-0">
                                        <p class="text-xs tabular-nums text-muted" x-text="item.occurred_at_label"></p>
                                        <p class="mt-1 text-sm">
                                            <span class="font-medium" x-text="item.student_name"></span>
                                            <span x-text="item.message"></span>
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </aside>
                    </div>
                </div>
            </template>
        @endif

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

        {{-- Toast notifications for critical events --}}
        <div class="pointer-events-none fixed bottom-4 right-4 z-50 space-y-2" x-show="notifications.length > 0">
            <template x-for="note in notifications" :key="note.id">
                <div
                    class="pointer-events-auto max-w-sm rounded-card border px-4 py-3 text-sm shadow-pop"
                    :class="note.severity === 'critical' ? 'border-danger-ink bg-danger-soft text-danger-ink' : 'border-line bg-surface text-ink'"
                    x-text="note.message"
                ></div>
            </template>
        </div>
    </div>
</x-app-layout>
