<x-app-layout>
    @php
        $sections = $examination->sections->pluck('name')->filter()->join(', ')
            ?: ($examination->subjectOffering?->section?->name ?? 'Unassigned');
    @endphp

    <div class="ui-page" x-data="examMonitoring(@js([
        'exam' => [
            'id' => $examination->id,
            'title' => $examination->title,
            'subject' => $examination->subject?->name ?? $examination->subject?->code,
            'sections' => $sections,
            'dataUrl' => route('monitoring.data', $examination),
            'controlUrl' => route('monitoring.control', $examination),
            'endUrl' => route('monitoring.end', $examination),
            'reactivateExaminationUrl' => route('monitoring.reactivate-examination', $examination),
            'extendDeadlineUrl' => route('monitoring.extend-deadline', $examination),
            'editUrl' => route('examinations.edit', $examination),
        ],
        'backUrl' => route('monitoring.index'),
        'violationsUrl' => route('monitoring.violations', ['attempt' => '__ATTEMPT__']),
        'reactivateUrl' => route('monitoring.reactivate', ['attempt' => '__ATTEMPT__']),
        'attemptUrl' => route('monitoring.attempts.show', ['attempt' => '__ATTEMPT__']),
        'maxWarnings' => config('examination.max_violation_warnings', 3),
    ]))">
        <x-ui.page-header :title="$examination->title" :subtitle="$examination->subject?->name ?? $examination->subject?->code">
            <a href="{{ route('monitoring.index') }}" class="btn-secondary">Back to Examinations</a>
        </x-ui.page-header>

        <p class="mb-6 text-sm text-muted">
            Section: {{ $sections }}
            ·
            <span class="inline-flex items-center gap-1.5 font-medium" :class="examination?.is_live ? 'text-success-ink' : 'text-muted'">
                <span class="h-2 w-2 rounded-full" :class="examination?.is_live ? 'bg-success-ink animate-pulse' : 'bg-muted'"></span>
                <span x-text="examination?.is_live ? 'Monitoring live' : (examination?.status_label || 'Examination ended')"></span>
            </span>
        </p>

        <div class="space-y-6">
            <section class="ui-card ui-card-pad">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted">Examination Status</p>
                        <p class="mt-2 flex items-center gap-2 text-lg font-semibold text-ink">
                            <span class="h-2.5 w-2.5 rounded-full" :class="examination?.is_live ? 'bg-success-ink animate-pulse' : 'bg-muted'"></span>
                            <span x-text="examination?.status_label || '—'"></span>
                        </p>
                        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-muted">Available From</dt><dd class="font-medium" x-text="examination?.available_from_formatted || '—'"></dd></div>
                            <div><dt class="text-muted">Deadline</dt><dd class="font-medium" x-text="examination?.deadline_at_formatted || '—'"></dd></div>
                            <div><dt class="text-muted">Time Remaining Until Deadline</dt><dd class="font-medium tabular-nums" x-text="deadlineCountdownLabel"></dd></div>
                            <div><dt class="text-muted">Duration</dt><dd class="font-medium" x-text="(examination?.duration_minutes || 0) + ' minutes per student'"></dd></div>
                        </dl>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="selectedExam?.editUrl" class="btn-secondary">Edit Examination</a>
                        <button type="button" class="btn-primary" x-show="control?.can_reactivate" @click="openReactivateExamination()" x-cloak>Reactivate Examination</button>
                        <button type="button" class="btn-secondary" x-show="control?.can_extend_deadline" @click="openExtendDeadline()" x-cloak>Extend Deadline</button>
                        <button type="button" class="btn-secondary text-danger-ink" x-show="control?.can_end" @click="openEndExamination()" x-cloak>End Examination</button>
                    </div>
                </div>
            </section>

            <section>
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
                                    <tr class="cursor-pointer border-b border-line last:border-0 hover:bg-brand-soft/40" @click="openStudent(row)">
                                        <td class="px-5 py-4">
                                            <p class="font-medium" x-text="row.student_name"></p>
                                            <p class="text-xs text-muted" x-text="row.student_id"></p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="badge badge-dot" :class="statusBadgeClass(row.monitoring_status)" x-text="row.status_label"></span>
                                        </td>
                                        <td class="min-w-[140px] px-5 py-4">
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
                                            <button type="button" class="tabular-nums underline-offset-2 hover:underline" :class="row.warning_count >= row.max_warnings ? 'text-danger-ink font-semibold' : (row.warning_count > 0 ? 'text-warning-ink' : 'text-muted')" @click.stop="viewViolations(row)" x-text="`${row.warning_count} / ${row.max_warnings}`"></button>
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
        </div>

        @include('pages.monitoring._modals')
    </div>
</x-app-layout>
